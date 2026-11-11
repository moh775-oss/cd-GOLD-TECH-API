<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Services\IntegrityService;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(private IntegrityService $integrity) {}

    public function index(Request $request)
    {
        $q = Tax::where('company_id', $request->user()->company_id);

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
            'data'    => $q->orderByDesc('is_default')->orderBy('name')->get(),
            'code'    => 200,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $tax = Tax::where('company_id', $request->user()->company_id)->find($id);
        if (!$tax) {
            return response()->json(['success' => false, 'message' => 'الضريبة غير موجودة', 'data' => null, 'code' => 404], 404);
        }
        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $tax, 'code' => 200]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'name_en'    => 'nullable|string|max:100',
            'code'       => 'nullable|string|max:50',
            'rate'       => 'required|numeric|min:0',
            'is_default' => 'nullable|integer|in:0,1',
            'is_active'  => 'nullable|integer|in:0,1',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;

        $tax = Tax::create($data);

        return response()->json(['success' => true, 'message' => 'تم إنشاء الضريبة', 'data' => $tax, 'code' => 201], 201);
    }

    public function update(Request $request, int $id)
    {
        $tax = Tax::where('company_id', $request->user()->company_id)->find($id);
        if (!$tax) {
            return response()->json(['success' => false, 'message' => 'الضريبة غير موجودة', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'name_en'    => 'nullable|string|max:100',
            'code'       => 'nullable|string|max:50',
            'rate'       => 'sometimes|numeric|min:0',
            'is_default' => 'nullable|integer|in:0,1',
            'is_active'  => 'sometimes|integer|in:0,1',
        ]);
        $data['user_id'] = $request->user()->id;

        $tax->update($data);

        return response()->json(['success' => true, 'message' => 'تم تعديل الضريبة', 'data' => $tax, 'code' => 200]);
    }

    public function destroy(Request $request, int $id)
    {
        $tax = Tax::where('company_id', $request->user()->company_id)->find($id);
        if (!$tax) {
            return response()->json(['success' => false, 'message' => 'الضريبة غير موجودة', 'data' => null, 'code' => 404], 404);
        }

        // ✅ فحص العلاقات
        $error = $this->integrity->checkRelations($tax, [
            'items' => 'أصناف',
        ]);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'data' => null, 'code' => 409], 409);
        }

        $tax->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الضريبة', 'data' => null, 'code' => 200]);
    }
}
