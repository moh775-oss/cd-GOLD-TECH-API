<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Safe;
use App\Services\IntegrityService;
use App\Services\MasterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SafeController extends Controller
{
    public function __construct(
        private IntegrityService $integrity,
        private MasterService $master
    ) {}

    public function index(Request $request)
    {
        $q = Safe::where('company_id', $request->user()->company_id);

        if ($request->branch_id) {
            $q->where('branch_id', $request->branch_id);
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', $request->is_active);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('name', 'LIKE', "%{$s}%")->orWhere('code', 'LIKE', "%{$s}%"));
        }

        return response()->json([
            'success' => true,
            'message' => 'تم الجلب',
            'data'    => $q->orderBy('name')->get(),
            'code'    => 200,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $safe = Safe::where('company_id', $request->user()->company_id)->find($id);
        if (!$safe) {
            return response()->json(['success' => false, 'message' => 'الصندوق غير موجود', 'data' => null, 'code' => 404], 404);
        }
        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $safe, 'code' => 200]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'    => 'required|integer',
            'name'         => 'required|string|max:200',
            'name_en'      => 'nullable|string|max:200',
            'code'         => 'nullable|string|max:50',
            'opening_cash' => 'nullable|numeric|min:0',
            'opening_gold' => 'nullable|numeric|min:0',
            'is_active'    => 'nullable|integer|in:0,1',
            'notes'        => 'nullable|string|max:500',
        ]);

        $companyId = $request->user()->company_id;
        $branchId  = $data['branch_id'];
        $userId    = $request->user()->id;

        $data['company_id']   = $companyId;
        $data['user_id']      = $userId;
        $data['current_cash'] = $data['opening_cash'] ?? 0;
        $data['current_gold'] = $data['opening_gold'] ?? 0;

        try {
            $safe = DB::transaction(function () use ($data, $companyId, $branchId, $userId) {
                // ✅ إنشاء حساب فرعي تحت 121 (الصناديق)
                $accResult = $this->master->createSubAccount(
                    $companyId,
                    $branchId,
                    '121',
                    $data['name'],
                    $userId,
                    [
                        'account_nature' => 'debit',
                        'final_account'  => 'الميزانية العمومية',
                    ]
                );
                if (!$accResult['success']) {
                    throw new \RuntimeException($accResult['message']);
                }

                $data['acc_no'] = $accResult['data']['acc_no'];

                return Safe::create($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الصندوق',
                'data'    => $safe,
                'code'    => 201,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء الصندوق: ' . $e->getMessage(),
                'data'    => null,
                'code'    => 500,
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $safe = Safe::where('company_id', $request->user()->company_id)->find($id);
        if (!$safe) {
            return response()->json(['success' => false, 'message' => 'الصندوق غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'name'      => 'sometimes|string|max:200',
            'name_en'   => 'nullable|string|max:200',
            'code'      => 'nullable|string|max:50',
            'is_active' => 'sometimes|integer|in:0,1',
            'notes'     => 'nullable|string|max:500',
        ]);
        $data['user_id'] = $request->user()->id;

        $safe->update($data);

        return response()->json(['success' => true, 'message' => 'تم تعديل الصندوق', 'data' => $safe, 'code' => 200]);
    }

    public function destroy(Request $request, int $id)
    {
        $safe = Safe::where('company_id', $request->user()->company_id)->find($id);
        if (!$safe) {
            return response()->json(['success' => false, 'message' => 'الصندوق غير موجود', 'data' => null, 'code' => 404], 404);
        }

        // ✅ فحص العلاقات
        $error = $this->integrity->checkRelations($safe, [
            'salesInvoices'    => 'فواتير مبيعات',
            'purchaseInvoices' => 'فواتير مشتريات',
            'salesReturns'     => 'مرتجعات مبيعات',
            'purchaseReturns'  => 'مرتجعات مشتريات',
            'salesWages'       => 'أجور مبيعات',
            'purchaseWages'    => 'أجور مشتريات',
            'receiptVouchers'  => 'سندات قبض',
            'paymentVouchers'  => 'سندات صرف',
            'expenses'         => 'مصروفات',
            'cashTransactions' => 'حركات نقدية',
        ]);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'data' => null, 'code' => 409], 409);
        }

        try {
            DB::transaction(function () use ($safe) {
                // امسح الحساب لو مش مستخدم
                $acc = Account::where('company_id', $safe->company_id)
                    ->where('acc_no', $safe->acc_no)
                    ->first();

                if ($acc && !$this->integrity->checkAccount($acc)) {
                    $acc->delete();
                }

                $safe->delete();
            });

            return response()->json(['success' => true, 'message' => 'تم حذف الصندوق', 'data' => null, 'code' => 200]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'فشل حذف الصندوق: ' . $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }
}
