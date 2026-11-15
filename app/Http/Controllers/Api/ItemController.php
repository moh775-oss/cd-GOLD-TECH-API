<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Karat;
use App\Models\Store;
use App\Models\Tax;
use App\Services\IntegrityService;
use Illuminate\Http\Request;
use Throwable;

class ItemController extends Controller
{
    public function __construct(private IntegrityService $integrity) {}

    public function index(Request $request)
    {
        $q = Item::with(['category', 'karat', 'tax', 'currency'])
            ->where('company_id', $request->user()->company_id);

        if ($request->branch_id) $q->where('branch_id', $request->branch_id);
        if ($request->filled('category_id')) $q->where('category_id', $request->category_id);
        if ($request->filled('karat_id')) $q->where('karat_id', $request->karat_id);
        if ($request->filled('item_type')) $q->where('item_type', $request->item_type);
        if ($request->filled('gold_type')) $q->where('gold_type', $request->gold_type);
        if ($request->filled('is_active')) $q->where('is_active', $request->is_active);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('name', 'LIKE', "%{$s}%")
                ->orWhere('name_en', 'LIKE', "%{$s}%")
                ->orWhere('code', 'LIKE', "%{$s}%")
                ->orWhere('barcode', 'LIKE', "%{$s}%"));
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
        $item = Item::with(['category', 'karat', 'tax', 'currency', 'store'])
            ->where('company_id', $request->user()->company_id)
            ->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'الصنف غير موجود', 'data' => null, 'code' => 404], 404);
        }
        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $item, 'code' => 200]);
    }

    public function create(Request $request)
    {
        $companyId = $request->user()->company_id;

        return response()->json([
            'success' => true,
            'message' => 'بيانات نموذج الصنف',
            'data'    => [
                'categories' => ItemCategory::where('company_id', $companyId)->where('is_active', 1)->orderBy('name')->get(['id', 'parent_id', 'name']),
                'karats'     => Karat::where('company_id', $companyId)->where('is_active', 1)->orderByDesc('the_value')->get(['id', 'name', 'the_value']),
                'taxes'      => Tax::where('company_id', $companyId)->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'rate']),
                'currencies' => Currency::where('company_id', $companyId)->where('is_active', 1)->orderBy('name')->get(['id', 'code', 'name', 'symbol']),
                'stores'     => Store::where('company_id', $companyId)->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
                'units'      => ['جرام', 'كيلو', 'أوقية', 'قيراط', 'حبة'],
                'item_types' => [
                    ['value' => 'scrap',    'label' => 'كسر مصاغ'],
                    ['value' => 'pure',     'label' => 'ذهب صافي'],
                    ['value' => 'worked',   'label' => 'مصاغ'],
                    ['value' => 'silver',   'label' => 'فضة'],
                    ['value' => 'diamond',  'label' => 'الماس'],
                    ['value' => 'for_sale', 'label' => 'للبيع'],
                    ['value' => 'raw',      'label' => 'خام'],
                ],
                'gold_types' => [
                    ['value' => 'scrap',  'label' => 'كسر'],
                    ['value' => 'worked', 'label' => 'مصاغ'],
                    ['value' => 'pure',   'label' => 'صافي'],
                ],
            ],
            'code' => 200,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'                => 'required|integer',
            'category_id'              => 'nullable|integer',
            'store_id'                 => 'nullable|integer',
            'code'                     => 'nullable|string|max:50',
            'name'                     => 'required|string|max:200',
            'name_en'                  => 'nullable|string|max:200',
            'description'              => 'nullable|string|max:1000',
            'item_type'                => 'nullable|string|max:50',
            'gold_type'                => 'nullable|string|max:50',
            'barcode'                  => 'nullable|string|max:100',
            'karat_id'                 => 'nullable|integer',
            'unit'                     => 'nullable|string|max:50',
            'weight'                   => 'nullable|numeric|min:0',
            'purity'                   => 'nullable|numeric|min:0',
            'stone_weight'             => 'nullable|numeric|min:0',
            'stone_type'               => 'nullable|string|max:50',
            'cost_price'               => 'nullable|numeric|min:0',
            'purchase_price'           => 'nullable|numeric|min:0',
            'purchase_price_with_tax'  => 'nullable|numeric|min:0',
            'sale_price'               => 'nullable|numeric|min:0',
            'sale_price_with_tax'      => 'nullable|numeric|min:0',
            'min_sale_price'           => 'nullable|numeric|min:0',
            'wholesale_price'          => 'nullable|numeric|min:0',
            'making_charge_type'       => 'nullable|string|max:50',
            'making_charge_purchase'   => 'nullable|numeric|min:0',
            'making_charge_sale'       => 'nullable|numeric|min:0',
            'profit_margin'            => 'nullable|numeric',
            'tax_id'                   => 'nullable|integer',
            'tax_rate'                 => 'nullable|numeric|min:0',
            'currency_id'              => 'nullable|integer',
            'exchange_rate'            => 'nullable|numeric|min:0',
            'price_with_exchange'      => 'nullable|numeric|min:0',
            'min_qty'                  => 'nullable|numeric|min:0',
            'max_qty'                  => 'nullable|numeric|min:0',
            'reorder_qty'              => 'nullable|numeric|min:0',
            'opening_qty'              => 'nullable|numeric|min:0',
            'opening_weight'           => 'nullable|numeric|min:0',
            'image_path'               => 'nullable|string|max:500',
            'gallery_path'             => 'nullable|string|max:1000',
            'is_active'                => 'nullable|integer|in:0,1',
            'is_sellable'              => 'nullable|integer|in:0,1',
            'is_purchasable'           => 'nullable|integer|in:0,1',
            'is_trackable'             => 'nullable|integer|in:0,1',
            'is_featured'              => 'nullable|integer|in:0,1',
            'sort_order'               => 'nullable|integer',
            'notes'                    => 'nullable|string|max:1000',
            'tags'                     => 'nullable|string|max:500',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['user_id']    = $request->user()->id;

        // ✅ تحقق من تكرار الاسم
        if (Item::where('company_id', $data['company_id'])->where('name', $data['name'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم الصنف مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        // ✅ تحقق من تكرار الباركود
        if (!empty($data['barcode']) && Item::where('company_id', $data['company_id'])->where('barcode', $data['barcode'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'الباركود مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        // ✅ تحقق من تكرار الكود
        if (!empty($data['code']) && Item::where('company_id', $data['company_id'])->where('code', $data['code'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'كود الصنف مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        if (empty($data['code'])) {
            $lastItem = Item::where('company_id', $data['company_id'])->orderByDesc('id')->first();
            $nextNum  = $lastItem ? ((int)substr($lastItem->code, 5) + 1) : 1;
            $data['code'] = 'ITM-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        }

        $item = Item::create($data);

        return response()->json(['success' => true, 'message' => 'تم إنشاء الصنف', 'data' => $item, 'code' => 201], 201);
    }

    public function edit(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;

        $item = Item::where('company_id', $companyId)->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'الصنف غير موجود', 'data' => null, 'code' => 404], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'بيانات تعديل الصنف',
            'data'    => [
                'item'       => $item,
                'categories' => ItemCategory::where('company_id', $companyId)->where('is_active', 1)->orderBy('name')->get(['id', 'parent_id', 'name']),
                'karats'     => Karat::where('company_id', $companyId)->where('is_active', 1)->orderByDesc('the_value')->get(['id', 'name', 'the_value']),
                'taxes'      => Tax::where('company_id', $companyId)->where('is_active', 1)->orderByDesc('id')->get(['id', 'name', 'rate']),
                'currencies' => Currency::where('company_id', $companyId)->where('is_active', 1)->orderByDesc('id')->get(['id', 'code', 'name', 'symbol']),
                'stores'     => Store::where('company_id', $companyId)->where('is_active', 1)->orderByDesc('id')->get(['id', 'name']),
                'units'      => ['جرام', 'كيلو', 'أوقية', 'قيراط', 'حبة'],
                'item_types' => [
                    ['value' => 'scrap',    'label' => 'كسر مصاغ'],
                    ['value' => 'pure',     'label' => 'ذهب صافي'],
                    ['value' => 'worked',   'label' => 'مصاغ'],
                    ['value' => 'silver',   'label' => 'فضة'],
                    ['value' => 'diamond',  'label' => 'الماس'],
                    ['value' => 'for_sale', 'label' => 'للبيع'],
                    ['value' => 'raw',      'label' => 'خام'],
                ],
                'gold_types' => [
                    ['value' => 'scrap',  'label' => 'كسر'],
                    ['value' => 'worked', 'label' => 'مصاغ'],
                    ['value' => 'pure',   'label' => 'صافي'],
                ],
            ],
            'code' => 200,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $item = Item::where('company_id', $request->user()->company_id)->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'الصنف غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'category_id'              => 'nullable|integer',
            'store_id'                 => 'nullable|integer',
            'code'                     => 'sometimes|string|max:50',
            'name'                     => 'sometimes|string|max:200',
            'name_en'                  => 'nullable|string|max:200',
            'description'              => 'nullable|string|max:1000',
            'item_type'                => 'nullable|string|max:50',
            'gold_type'                => 'nullable|string|max:50',
            'barcode'                  => 'nullable|string|max:100',
            'karat_id'                 => 'nullable|integer',
            'unit'                     => 'nullable|string|max:50',
            'weight'                   => 'nullable|numeric|min:0',
            'purity'                   => 'nullable|numeric|min:0',
            'stone_weight'             => 'nullable|numeric|min:0',
            'stone_type'               => 'nullable|string|max:50',
            'cost_price'               => 'nullable|numeric|min:0',
            'purchase_price'           => 'nullable|numeric|min:0',
            'purchase_price_with_tax'  => 'nullable|numeric|min:0',
            'sale_price'               => 'nullable|numeric|min:0',
            'sale_price_with_tax'      => 'nullable|numeric|min:0',
            'min_sale_price'           => 'nullable|numeric|min:0',
            'wholesale_price'          => 'nullable|numeric|min:0',
            'making_charge_type'       => 'nullable|string|max:50',
            'making_charge_purchase'   => 'nullable|numeric|min:0',
            'making_charge_sale'       => 'nullable|numeric|min:0',
            'profit_margin'            => 'nullable|numeric',
            'tax_id'                   => 'nullable|integer',
            'tax_rate'                 => 'nullable|numeric|min:0',
            'currency_id'              => 'nullable|integer',
            'exchange_rate'            => 'nullable|numeric|min:0',
            'price_with_exchange'      => 'nullable|numeric|min:0',
            'min_qty'                  => 'nullable|numeric|min:0',
            'max_qty'                  => 'nullable|numeric|min:0',
            'reorder_qty'              => 'nullable|numeric|min:0',
            'opening_qty'              => 'nullable|numeric|min:0',
            'opening_weight'           => 'nullable|numeric|min:0',
            'image_path'               => 'nullable|string|max:500',
            'gallery_path'             => 'nullable|string|max:1000',
            'is_active'                => 'sometimes|integer|in:0,1',
            'is_sellable'              => 'sometimes|integer|in:0,1',
            'is_purchasable'           => 'sometimes|integer|in:0,1',
            'is_trackable'             => 'sometimes|integer|in:0,1',
            'is_featured'              => 'sometimes|integer|in:0,1',
            'sort_order'               => 'nullable|integer',
            'notes'                    => 'nullable|string|max:1000',
            'tags'                     => 'nullable|string|max:500',
        ]);

        $companyId = $request->user()->company_id;

        // ✅ تحقق من تكرار الاسم
        if (isset($data['name']) && Item::where('company_id', $companyId)
            ->where('name', $data['name'])
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'اسم الصنف مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        // ✅ تحقق من تكرار الباركود
        if (!empty($data['barcode']) && Item::where('company_id', $companyId)
            ->where('barcode', $data['barcode'])
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'الباركود مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        // ✅ تحقق من تكرار الكود
        if (!empty($data['code']) && Item::where('company_id', $companyId)
            ->where('code', $data['code'])
            ->where('id', '!=', $id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'كود الصنف مستخدم مسبقاً',
                'data'    => null,
                'code'    => 409,
            ], 409);
        }

        $data['user_id'] = $request->user()->id;
        $item->update($data);

        return response()->json(['success' => true, 'message' => 'تم تعديل الصنف', 'data' => $item, 'code' => 200]);
    }

    public function destroy(Request $request, int $id)
    {
        $item = Item::where('company_id', $request->user()->company_id)->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'الصنف غير موجود', 'data' => null, 'code' => 404], 404);
        }

        $error = $this->integrity->checkRelations($item, [
            'warehouseStocks'        => 'أرصدة مخزون',
            'stockMovements'         => 'حركات مخزون',
            'salesInvoiceDetails'    => 'فواتير مبيعات',
            'purchaseInvoiceDetails' => 'فواتير مشتريات',
            'salesReturnDetails'     => 'مرتجعات مبيعات',
            'purchaseReturnDetails'  => 'مرتجعات مشتريات',
            'salesWageDetails'       => 'أجور مبيعات',
            'purchaseWageDetails'    => 'أجور مشتريات',
            'makingCharges'          => 'أجور مصنعية',
        ]);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'data' => null, 'code' => 409], 409);
        }

        $item->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف الصنف', 'data' => null, 'code' => 200]);
    }

    public function nextBarcode(Request $request)
    {
        try {
            $companyId = $request->user()->company_id;
            $prefix = '1000';

            $last = Item::where('company_id', $companyId)
                ->where('barcode', 'LIKE', $prefix . '%')
                ->whereRaw('LENGTH(barcode) = 8')
                ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
                ->first();

            $next = $last ? ((int) $last->barcode + 1) : 10000001;
            $barcode = (string) $next;

            while (Item::where('company_id', $companyId)->where('barcode', $barcode)->exists()) {
                $next++;
                $barcode = (string) $next;
            }

            return response()->json([
                'success' => true,
                'data'    => ['barcode' => $barcode],
                'code'    => 200,
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }
}
