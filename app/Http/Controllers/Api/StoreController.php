<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        try {
            $q = Store::with(['branch', 'user'])
                ->where('company_id', $request->user()->company_id);

            if ($request->filled('branch_id')) {
                $q->where('branch_id', $request->branch_id);
            }
            if ($request->filled('is_active')) {
                $q->where('is_active', $request->is_active);
            }
            if ($request->filled('is_main')) {
                $q->where('is_main', $request->is_main);
            }
            if ($request->filled('search')) {
                $s = $request->search;
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('code', 'LIKE', "%{$s}%")
                        ->orWhere('address', 'LIKE', "%{$s}%");
                });
            }

            $stores = $q->orderByDesc('is_main')->orderBy('id', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data' => $stores,
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'code' => 500,
            ], 500);
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $store = Store::with(['branch', 'user', 'warehouseStocks', 'stockMovements'])
                ->where('company_id', $request->user()->company_id)
                ->find($id);

            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'الخزينة غير موجود',
                    'data' => null,
                    'code' => 404,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data' => $store,
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'code' => 500,
            ], 500);
        }
    }

    public function create(Request $request)
    {
        try {
            $companyId = $request->user()->company_id;

            return response()->json([
                'success' => true,
                'message' => 'نموذج إنشاء مخزن',
                'data' => [
                    'branches' => Branch::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->orderBy('id', 'asc')
                        ->get(['id', 'name']),
                    'users' => User::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->orderBy('id', 'asc')
                        ->get(['id', 'name']),
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'code' => 500,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|integer',
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:400',
            'manager_id' => 'nullable|integer',
            'is_main' => 'nullable|integer|in:0,1',
            'is_active' => 'nullable|integer|in:0,1',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;

        $data['company_id'] = $companyId;
        $data['user_id'] = $user->id;

        if (!DB::table('branches')->where('company_id', $companyId)->where('id', $data['branch_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'الفرع غير موجود',
                'data' => null,
                'code' => 422,
            ], 422);
        }

        if (!empty($data['code']) && Store::where('company_id', $companyId)->where('code', $data['code'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'كود الخزينة مستخدم مسبقاً',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        if (Store::where('company_id', $companyId)->where('name', $data['name'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم الخزينة مستخدم مسبقاً',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        $store = DB::transaction(function () use ($data, $companyId, $user) {
            $accountResult = $this->master->createSubAccount(
                $companyId,
                $data['branch_id'],
                '127',
                'مخزون ' . $data['name'],
                $user->id
            );

            if (!$accountResult['success']) {
                throw new \RuntimeException('فشل إنشاء حساب الخزينة: ' . $accountResult['message']);
            }

            $accountId = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('acc_no', $accountResult['data']['acc_no'])
                ->value('id');

            if (!$accountId) {
                throw new \RuntimeException('لم يتم العثور على الحساب المحاسبي للمخزن');
            }

            if (!empty($data['is_main']) && $data['is_main'] == 1) {
                Store::where('company_id', $companyId)
                    ->where('branch_id', $data['branch_id'])
                    ->update(['is_main' => 0]);
            }

            $data['account_id'] = $accountId;

            return Store::create($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الخزينة وحسابه المحاسبي بنجاح',
            'data' => $store,
            'code' => 201,
        ], 201);
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;

        $store = Store::where('company_id', $companyId)->find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'الخزينة غير موجود',
                'data' => null,
                'code' => 404,
            ], 404);
        }

        $data = $request->validate([
            'branch_id' => 'sometimes|integer',
            'name' => 'sometimes|string|max:150',
            'code' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:400',
            'manager_id' => 'nullable|integer',
            'is_main' => 'sometimes|integer|in:0,1',
            'is_active' => 'sometimes|integer|in:0,1',
            'notes' => 'nullable|string|max:500',
        ]);

        if (
            isset($data['branch_id']) && !DB::table('branches')
                ->where('company_id', $companyId)
                ->where('id', $data['branch_id'])
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'الفرع غير موجود',
                'data' => null,
                'code' => 422,
            ], 422);
        }

        if (
            !empty($data['code']) && Store::where('company_id', $companyId)
                ->where('code', $data['code'])
                ->where('id', '!=', $id)
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'كود الخزينة مستخدم مسبقاً',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        if (
            isset($data['name']) && Store::where('company_id', $companyId)
                ->where('name', $data['name'])
                ->where('id', '!=', $id)
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'اسم الخزينة مستخدم مسبقاً',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        DB::transaction(function () use ($data, $store, $companyId, $request, $id) {
            $branchId = $data['branch_id'] ?? $store->branch_id;

            if (!empty($data['is_main']) && $data['is_main'] == 1) {
                Store::where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->where('id', '!=', $id)
                    ->update(['is_main' => 0]);
            }

            if (array_key_exists('name', $data)) {
                $account = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->where('id', $store->account_id)
                    ->first();

                if ($account) {
                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->where('company_id', $companyId)
                        ->update([
                            'name' => 'مخزون ' . $data['name'],
                            'updated_at' => now(),
                        ]);
                } elseif (!$store->account_id) {
                    $accountResult = $this->master->createSubAccount(
                        $companyId,
                        $branchId,
                        '127',
                        'مخزون ' . $data['name'],
                        $request->user()->id
                    );

                    if (!$accountResult['success']) {
                        throw new \RuntimeException('فشل إنشاء حساب الخزينة: ' . $accountResult['message']);
                    }

                    $accountId = DB::table('accounts')
                        ->where('company_id', $companyId)
                        ->where('acc_no', $accountResult['data']['acc_no'])
                        ->value('id');

                    if (!$accountId) {
                        throw new \RuntimeException('لم يتم العثور على الحساب المحاسبي للمخزن');
                    }

                    $data['account_id'] = $accountId;
                } else {
                    throw new \RuntimeException('الحساب المحاسبي المرتبط بالخزينة غير موجود');
                }
            }

            $data['user_id'] = $request->user()->id;
            $store->update($data);
        });

        $store->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل الخزينة وحسابه المحاسبي بنجاح',
            'data' => $store,
            'code' => 200,
        ], 200);
    }

    public function destroy(Request $request, int $id)
    {
        $store = Store::where('company_id', $request->user()->company_id)->find($id);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'الخزينة غير موجود',
                'data' => null,
                'code' => 404,
            ], 404);
        }

        if ($store->is_main == 1) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف الخزينة الرئيسي',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        if (DB::table('items')->where('store_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مخزن يحتوي أصنافاً',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        if (DB::table('warehouse_stocks')->where('warehouse_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مخزن له أرصدة',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        if (DB::table('stock_movements')->where('warehouse_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مخزن له حركات',
                'data' => null,
                'code' => 409,
            ], 409);
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الخزينة',
            'data' => null,
            'code' => 200,
        ], 200);
    }

    public function list(Request $request)
    {
        try {
            $stores = Store::where('company_id', $request->user()->company_id)
                ->where('is_active', 1)
                ->orderByDesc('is_main')
                ->orderBy('id', 'asc')
                ->get(['id', 'branch_id', 'name', 'code', 'is_main']);

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data' => $stores,
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'code' => 500,
            ], 500);
        }
    }
}
