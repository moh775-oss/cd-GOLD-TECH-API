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

            $stores = $q->orderByDesc('is_main')->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => $stores,
                'code'    => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
                'code'    => 500,
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
                    'message' => 'المخزن غير موجود',
                    'data'    => null,
                    'code'    => 404,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => $store,
                'code'    => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
                'code'    => 500,
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
                'data'    => [
                    'branches' => Branch::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'users' => User::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
                'code'    => 500,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|integer',
            'name'      => 'required|string|max:150',
            'code'      => 'nullable|string|max:30',
            'address'   => 'nullable|string|max:400',
            'manager_id'=> 'nullable|integer',
            'is_main'   => 'nullable|integer|in:0,1',
            'is_active' => 'nullable|integer|in:0,1',
            'notes'     => 'nullable|string|max:500',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;

        if (!empty($data['code']) && Store::where('company_id', $data['company_id'])
            ->where('code', $data['code'])
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'كود المخزن مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (Store::where('company_id', $data['company_id'])
            ->where('name', $data['name'])
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم المخزن مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (!empty($data['is_main']) && $data['is_main'] == 1) {
            Store::where('company_id', $data['company_id'])
                ->where('branch_id', $data['branch_id'])
                ->update(['is_main' => 0]);
        }

        $store = Store::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المخزن',
            'data'    => $store,
            'code'    => 201,
        ], 201);
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        $store = Store::where('company_id', $request->user()->company_id)->find($id);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'المخزن غير موجود',
                'data'    => null,
                'code'    => 404,
            ], 404);
        }

        $data = $request->validate([
            'branch_id' => 'sometimes|integer',
            'name'      => 'sometimes|string|max:150',
            'code'      => 'nullable|string|max:30',
            'address'   => 'nullable|string|max:400',
            'manager_id'=> 'nullable|integer',
            'is_main'   => 'sometimes|integer|in:0,1',
            'is_active' => 'sometimes|integer|in:0,1',
            'notes'     => 'nullable|string|max:500',
        ]);

        if (isset($data['code']) && !empty($data['code']) && Store::where('company_id', $store->company_id)
            ->where('code', $data['code'])
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'كود المخزن مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (isset($data['name']) && Store::where('company_id', $store->company_id)
            ->where('name', $data['name'])
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم المخزن مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (!empty($data['is_main']) && $data['is_main'] == 1) {
            Store::where('company_id', $store->company_id)
                ->where('branch_id', $data['branch_id'] ?? $store->branch_id)
                ->where('id', '!=', $id)
                ->update(['is_main' => 0]);
        }

        $data['user_id'] = $request->user()->id;
        $store->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل المخزن',
            'data'    => $store,
            'code'    => 200,
        ], 200);
    }

    public function destroy(Request $request, int $id)
    {
        $store = Store::where('company_id', $request->user()->company_id)->find($id);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'المخزن غير موجود',
                'data'    => null,
                'code'    => 404,
            ], 404);
        }

        if ($store->is_main == 1) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف المخزن الرئيسي',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (DB::table('items')->where('store_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مخزن يحتوي أصنافاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (DB::table('warehouse_stocks')->where('warehouse_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مخزن له أرصدة',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (DB::table('stock_movements')->where('warehouse_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مخزن له حركات',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المخزن',
            'data'    => null,
            'code'    => 200,
        ], 200);
    }

    public function list(Request $request)
    {
        try {
            $stores = Store::where('company_id', $request->user()->company_id)
                ->where('is_active', 1)
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'code', 'is_main']);

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => $stores,
                'code'    => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
                'code'    => 500,
            ], 500);
        }
    }
}
