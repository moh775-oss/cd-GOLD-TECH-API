<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Services\IntegrityService;
use Illuminate\Http\Request;

class ItemCategoryController extends Controller
{
    public function __construct(private IntegrityService $integrity) {}

    public function index(Request $request)
    {
        $q = ItemCategory::with(['parent', 'children'])
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('parent_id')) {
            $q->where('parent_id', $request->parent_id);
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', $request->is_active);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('name', 'LIKE', "%{$s}%")->orWhere('name_en', 'LIKE', "%{$s}%"));
        }

        return response()->json([
            'success' => true,
            'message' => 'تم الجلب',
            'data'    => $q->orderBy('name')->get(),
            'code'    => 200,
        ]);
    }

    public function tree(Request $request)
    {
        $companyId = $request->user()->company_id;

        $all = ItemCategory::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $grouped = $all->groupBy('parent_id');
        $roots = $grouped->get('', collect())->merge($grouped->get(null, collect()));

        $tree = $roots->map(fn($r) => $this->buildTree($r, $grouped))->values()->all();

        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $tree, 'code' => 200]);
    }

    private function buildTree($node, $grouped): array
    {
        $children = $grouped->get($node->id, collect())
            ->map(fn($c) => $this->buildTree($c, $grouped))
            ->values()->all();

        return [
            'id'        => $node->id,
            'parent_id' => $node->parent_id,
            'name'      => $node->name,
            'name_en'   => $node->name_en,
            'is_active' => $node->is_active,
            'children'  => $children,
        ];
    }

    public function show(Request $request, int $id)
    {
        $category = ItemCategory::with(['parent', 'children'])
            ->where('company_id', $request->user()->company_id)
            ->find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'المجموعة غير موجودة', 'data' => null, 'code' => 404], 404);
        }
        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $category, 'code' => 200]);
    }

    public function create(Request $request)
    {
        $companyId = $request->user()->company_id;

        return response()->json([
            'success' => true,
            'message' => 'بيانات نموذج المجموعة',
            'data'    => [
                'parents' => ItemCategory::where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->whereNull('parent_id')
                    ->orderBy('name')
                    ->get(['id', 'name', 'name_en']),
            ],
            'code' => 200,
        ]);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_id' => 'nullable|integer',
            'name'      => 'required|string|max:200',
            'name_en'   => 'nullable|string|max:200',
            'is_active' => 'nullable|integer|in:0,1',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;


        if (ItemCategory::where('company_id', $data['company_id'])
            ->where('parent_id', $data['parent_id'] ?? null)
            ->where('name', $data['name'])
            ->exists()) {
            return response()->json(['success' => false, 'message' => 'المجموعة موجودة مسبقاً', 'data' => null, 'code' => 409], 409);
        }

        $category = ItemCategory::create($data);

        return response()->json(['success' => true, 'message' => 'تم إنشاء المجموعة', 'data' => $category, 'code' => 201], 201);
    }

    public function edit(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;

        $category = ItemCategory::where('company_id', $companyId)->find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'المجموعة غير موجودة', 'data' => null, 'code' => 404], 404);
    }

        return response()->json([
            'success' => true,
            'message' => 'بيانات تعديل المجموعة',
            'data'    => [
                'category' => $category,
                'parents'  => ItemCategory::where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->whereNull('parent_id')
                    ->where('id', '!=', $id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'name_en']),
            ],
            'code' => 200,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $category = ItemCategory::where('company_id', $request->user()->company_id)->find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'المجموعة غير موجودة', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'parent_id' => 'nullable|integer',
            'name'      => 'sometimes|string|max:200',
            'name_en'   => 'nullable|string|max:200',
            'is_active' => 'sometimes|integer|in:0,1',
        ]);
        $data['user_id'] = $request->user()->id;

        // ✅ منع جعل المجموعة أباً لنفسها
        if (isset($data['parent_id']) && $data['parent_id'] == $id) {
            return response()->json(['success' => false, 'message' => 'لا يمكن أن تكون المجموعة أباً لنفسها', 'data' => null, 'code' => 422], 422);
        }

        $category->update($data);

        return response()->json(['success' => true, 'message' => 'تم تعديل المجموعة', 'data' => $category, 'code' => 200]);
    }

    public function destroy(Request $request, int $id)
    {
        $category = ItemCategory::where('company_id', $request->user()->company_id)->find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'المجموعة غير موجودة', 'data' => null, 'code' => 404], 404);
        }

        // ✅ منع حذف مجموعة لها أبناء
        if (ItemCategory::where('company_id', $category->company_id)->where('parent_id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف مجموعة لها مجموعات فرعية', 'data' => null, 'code' => 409], 409);
        }

        // ✅ فحص العلاقات
        $error = $this->integrity->checkRelations($category, [
            'items' => 'أصناف',
        ]);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'data' => null, 'code' => 409], 409);
        }

        $category->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف المجموعة', 'data' => null, 'code' => 200]);
    }
}
