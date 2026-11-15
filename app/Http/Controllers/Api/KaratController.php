<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karat;
use Illuminate\Http\Request;
use Throwable;

class KaratController extends Controller
{
    public function index(Request $request)
    {
        try {
            $q = Karat::where('company_id', $request->user()->company_id);

            if ($request->filled('is_active')) {
                $q->where('is_active', $request->is_active);
            }
            if ($request->filled('metal_type')) {
                $q->where('metal_type', $request->metal_type);
            }
            if ($request->filled('search')) {
                $q->where('name', 'LIKE', "%{$request->search}%");
            }

            $karats = $q->orderByDesc('the_value')->get();

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => $karats,
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
            $karat = Karat::where('company_id', $request->user()->company_id)->find($id);

            if (!$karat) {
                return response()->json([
                    'success' => false,
                    'message' => 'العيار غير موجود',
                    'data'    => null,
                    'code'    => 404,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => $karat,
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
        return response()->json([
            'success' => true,
            'message' => 'نموذج إنشاء عيار',
            'data'    => [
                'metal_types' => [
                    ['value' => 'gold',   'label' => 'ذهب'],
                    ['value' => 'silver', 'label' => 'فضة'],
                ],
            ],
            'code' => 200,
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'the_value'  => 'required|numeric|min:0|max:1',
            'metal_type' => 'nullable|string|in:gold,silver',
            'is_active'  => 'nullable|integer|in:0,1',
            'notes'      => 'nullable|string|max:500',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['branch_id']  = $request->user()->branch_id;
        $data['user_id']    = $request->user()->id;

        if (Karat::where('company_id', $data['company_id'])
            ->where('name', $data['name'])
            ->where('metal_type', $data['metal_type'] ?? 'gold')
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم العيار مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        $karat = Karat::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء العيار',
            'data'    => $karat,
            'code'    => 201,
        ], 201);
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        $karat = Karat::where('company_id', $request->user()->company_id)->find($id);
        if (!$karat) {
            return response()->json([
                'success' => false,
                'message' => 'العيار غير موجود',
                'data'    => null,
                'code'    => 404,
            ], 404);
        }

        $data = $request->validate([
            'name'       => 'sometimes|string|max:50',
            'the_value'  => 'sometimes|numeric|min:0|max:1',
            'metal_type' => 'nullable|string|in:gold,silver',
            'is_active'  => 'sometimes|integer|in:0,1',
            'notes'      => 'nullable|string|max:500',
        ]);

        if (isset($data['name']) && Karat::where('company_id', $karat->company_id)
            ->where('name', $data['name'])
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم العيار مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        $data['user_id'] = $request->user()->id;
        $karat->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل العيار',
            'data'    => $karat,
            'code'    => 200,
        ], 200);
    }

    public function destroy(Request $request, int $id)
    {
        $karat = Karat::where('company_id', $request->user()->company_id)->find($id);
        if (!$karat) {
            return response()->json([
                'success' => false,
                'message' => 'العيار غير موجود',
                'data'    => null,
                'code'    => 404,
            ], 404);
        }

        if ($karat->items()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف عيار مستخدم في أصناف',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        $karat->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العيار',
            'data'    => null,
            'code'    => 200,
        ], 200);
    }
}
