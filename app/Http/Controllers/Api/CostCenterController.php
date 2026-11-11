<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CostCenter;
use App\Services\IntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostCenterController extends Controller
{
    public function __construct(private IntegrityService $integrity) {}

    public function index(Request $request)
    {
        $q = CostCenter::where('company_id', $request->user()->company_id);

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
        $cc = CostCenter::where('company_id', $request->user()->company_id)->find($id);
        if (!$cc) {
            return response()->json(['success' => false, 'message' => 'مركز التكلفة غير موجود', 'data' => null, 'code' => 404], 404);
        }
        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $cc, 'code' => 200]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'       => 'required|integer',
            'parent_id'       => 'nullable|integer',
            'code'            => 'nullable|string|max:50',
            'name'            => 'required|string|max:200',
            'name_en'         => 'nullable|string|max:200',
            'center_type'     => 'nullable|string|max:50',
            'account_id'      => 'nullable|integer',
            'manager_id'      => 'nullable|integer',
            'budget_amount'   => 'nullable|numeric|min:0',
            'opening_balance' => 'nullable|numeric',
            'is_active'       => 'nullable|integer|in:0,1',
            'notes'           => 'nullable|string|max:500',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;

        $cc = CostCenter::create($data);

        return response()->json(['success' => true, 'message' => 'تم إنشاء مركز التكلفة', 'data' => $cc, 'code' => 201], 201);
    }

    public function update(Request $request, int $id)
    {
        $cc = CostCenter::where('company_id', $request->user()->company_id)->find($id);
        if (!$cc) {
            return response()->json(['success' => false, 'message' => 'مركز التكلفة غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'parent_id'       => 'nullable|integer',
            'code'            => 'nullable|string|max:50',
            'name'            => 'sometimes|string|max:200',
            'name_en'         => 'nullable|string|max:200',
            'center_type'     => 'nullable|string|max:50',
            'account_id'      => 'nullable|integer',
            'manager_id'      => 'nullable|integer',
            'budget_amount'   => 'nullable|numeric|min:0',
            'is_active'       => 'sometimes|integer|in:0,1',
            'notes'           => 'nullable|string|max:500',
        ]);
        $data['user_id'] = $request->user()->id;

        $cc->update($data);

        return response()->json(['success' => true, 'message' => 'تم تعديل مركز التكلفة', 'data' => $cc, 'code' => 200]);
    }

    public function destroy(Request $request, int $id)
    {
        $cc = CostCenter::where('company_id', $request->user()->company_id)->find($id);
        if (!$cc) {
            return response()->json(['success' => false, 'message' => 'مركز التكلفة غير موجود', 'data' => null, 'code' => 404], 404);
        }

        // ✅ منع حذف مركز له أبناء
        if (CostCenter::where('company_id', $cc->company_id)->where('parent_id', $cc->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف مركز له مراكز فرعية', 'data' => null, 'code' => 409], 409);
        }

        // ✅ فحص العلاقات
        $error = $this->integrity->checkRelations($cc, [
            'journalEntryDetails' => 'قيود محاسبية',
            'expenses'            => 'مصروفات',
            'salesInvoices'       => 'فواتير مبيعات',
            'purchaseInvoices'    => 'فواتير مشتريات',
        ]);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'data' => null, 'code' => 409], 409);
        }

        $cc->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف مركز التكلفة', 'data' => null, 'code' => 200]);
    }
}
