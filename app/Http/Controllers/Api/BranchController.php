<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Services\IntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BranchController extends Controller
{
    public function __construct(private IntegrityService $integrity) {}

    public function index(Request $request)
    {
        $q = Branch::where('company_id', $request->user()->company_id);

        if ($request->filled('is_active')) {
            $q->where('is_active', $request->is_active);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('name', 'LIKE', "%{$s}%")->orWhere('code', 'LIKE', "%{$s}%")->orWhere('phone', 'LIKE', "%{$s}%"));
        }

        return response()->json([
            'success' => true,
            'message' => 'تم الجلب',
            'data'    => $q->orderByDesc('is_main')->orderBy('name')->get(),
            'code'    => 200,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $branch = Branch::where('company_id', $request->user()->company_id)->find($id);
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'الفرع غير موجود', 'data' => null, 'code' => 404], 404);
        }
        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $branch, 'code' => 200]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'code'        => 'nullable|string|max:50',
            'phone'       => 'nullable|string|max:30',
            'mobile'      => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:150',
            'address'     => 'nullable|string|max:400',
            'city'        => 'nullable|string|max:100',
            'district'    => 'nullable|string|max:100',
            'street'      => 'nullable|string|max:200',
            'building_no' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'is_main'     => 'nullable|integer|in:0,1',
            'is_active'   => 'nullable|integer|in:0,1',
            'notes'       => 'nullable|string|max:500',
        ]);

        $companyId = $request->user()->company_id;
        $userId    = $request->user()->id;

        // ✅ فحص الحد الأقصى للفروع
        $company = Company::find($companyId);
        if ($company->current_branches_count >= $company->max_branches) {
            return response()->json(['success' => false, 'message' => 'وصلت للحد الأقصى للفروع', 'data' => null, 'code' => 409], 409);
        }

        // ✅ منع فرعين رئيسيين
        if (!empty($data['is_main']) && Branch::where('company_id', $companyId)->where('is_main', 1)->exists()) {
            return response()->json(['success' => false, 'message' => 'يوجد فرع رئيسي بالفعل', 'data' => null, 'code' => 409], 409);
        }

        $data['company_id'] = $companyId;
        $data['user_id']    = $userId;

        try {
            $branch = DB::transaction(function () use ($data, $company, $companyId, $userId) {
                // 1. إنشاء الفرع
                $branch = Branch::create($data);

                // 2. نسخ شجرة الحسابات للفرع (لو مش أول فرع)
                $hasAccounts = Account::where('company_id', $companyId)->where('branch_id', $branch->id)->exists();
                if (!$hasAccounts) {
                    // نستخدم accounts_template
                    DB::statement("
                        INSERT INTO accounts
                        (company_id, branch_id, acc_no, name, father_acc_no, account_type,
                         account_level, final_account, account_nature, is_active, is_system, user_id, created_at)
                        SELECT ?, ?, acc_no, name, father_acc_no, account_type,
                               account_level, final_account, account_nature, 1, 1, ?, NOW()
                        FROM accounts_template
                        ORDER BY sort_order
                    ", [$companyId, $branch->id, $userId]);
                }

                // 3. زيادة عدد الفروع
                $company->increment('current_branches_count');

                return $branch;
            });

            return response()->json(['success' => true, 'message' => 'تم إنشاء الفرع', 'data' => $branch, 'code' => 201], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'فشل إنشاء الفرع: ' . $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $branch = Branch::where('company_id', $request->user()->company_id)->find($id);
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'الفرع غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'name'        => 'sometimes|string|max:200',
            'code'        => 'nullable|string|max:50',
            'phone'       => 'nullable|string|max:30',
            'mobile'      => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:150',
            'address'     => 'nullable|string|max:400',
            'city'        => 'nullable|string|max:100',
            'district'    => 'nullable|string|max:100',
            'street'      => 'nullable|string|max:200',
            'building_no' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'is_active'   => 'sometimes|integer|in:0,1',
            'notes'       => 'nullable|string|max:500',
        ]);
        $data['user_id'] = $request->user()->id;

        $branch->update($data);

        return response()->json(['success' => true, 'message' => 'تم تعديل الفرع', 'data' => $branch, 'code' => 200]);
    }

    public function destroy(Request $request, int $id)
    {
        $branch = Branch::where('company_id', $request->user()->company_id)->find($id);
        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'الفرع غير موجود', 'data' => null, 'code' => 404], 404);
        }

        // ✅ منع حذف الفرع الرئيسي
        if ($branch->is_main) {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف الفرع الرئيسي', 'data' => null, 'code' => 409], 409);
        }

        // ✅ فحص شامل
        $error = $this->integrity->checkRelations($branch, [
            'users'             => 'مستخدمين',
            'contacts'          => 'عملاء/موردين',
            'items'             => 'أصناف',
            'safes'             => 'صناديق',
            'banks'             => 'بنوك',
            'stores'            => 'مخازن',
            'salesInvoices'     => 'فواتير مبيعات',
            'purchaseInvoices'  => 'فواتير مشتريات',
            'receiptVouchers'   => 'سندات قبض',
            'paymentVouchers'   => 'سندات صرف',
            'journalEntries'    => 'قيود',
            'expenses'          => 'مصروفات',
        ]);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'data' => null, 'code' => 409], 409);
        }

        try {
            DB::transaction(function () use ($branch) {
                $company = Company::find($branch->company_id);
                $branch->delete();
                $company->decrement('current_branches_count');
            });

            return response()->json(['success' => true, 'message' => 'تم حذف الفرع', 'data' => null, 'code' => 200]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'فشل حذف الفرع: ' . $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }
}
