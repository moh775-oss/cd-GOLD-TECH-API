<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\IntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    public function __construct(private IntegrityService $integrity) {}

    public function index(Request $request)
    {
        $q = Currency::where('company_id', $request->user()->company_id);

        if ($request->filled('is_active')) {
            $q->where('is_active', $request->is_active);
        }
        if ($request->filled('is_base')) {
            $q->where('is_base', $request->is_base);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('name', 'LIKE', "%{$s}%")->orWhere('code', 'LIKE', "%{$s}%"));
        }

        $currencies = $q->orderByDesc('is_base')->orderBy('name')->get();

        // ✅ جيب سعر الصرف الحالي لكل عملة
        $currencies->each(function ($currency) {
            $latest = ExchangeRate::where('currency_id', $currency->id)
                ->orderByDesc('rate_date')
                ->first();
            $currency->rate = $latest?->rate ?? null;
        });

        return response()->json([
            'success' => true,
            'message' => 'تم الجلب',
            'data'    => $currencies,
            'code'    => 200,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $currency = Currency::where('company_id', $request->user()->company_id)->find($id);
        if (!$currency) {
            return response()->json(['success' => false, 'message' => 'العملة غير موجودة', 'data' => null, 'code' => 404], 404);
        }

        $latest = ExchangeRate::where('currency_id', $currency->id)
            ->orderByDesc('rate_date')
            ->first();
        $currency->rate = $latest?->rate ?? null;

        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $currency, 'code' => 200]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:10',
            'symbol'    => 'nullable|string|max:10',
            'rate'      => 'nullable|numeric|min:0',
            'is_base'   => 'nullable|integer|in:0,1',
            'is_active' => 'nullable|integer|in:0,1',
        ]);

        $companyId = $request->user()->company_id;
        $userId    = $request->user()->id;
        $rate      = $data['rate'] ?? null;

        // ✅ شيل rate من بيانات currencies
        unset($data['rate']);

        $data['company_id'] = $companyId;
        $data['user_id']    = $userId;

        try {
            $currency = DB::transaction(function () use ($data, $rate, $companyId, $userId) {
                // 1. إنشاء العملة
                $currency = Currency::create($data);

                // 2. لو rate موجود → أضف سعر الصرف في exchange_rates
                if ($rate !== null) {
                    ExchangeRate::create([
                        'company_id'  => $companyId,
                        'currency_id' => $currency->id,
                        'rate'        => $rate,
                        'rate_date'   => now()->toDateString(),
                        'user_id'     => $userId,
                    ]);
                }

                return $currency;
            });

            $currency->rate = $rate;

            return response()->json(['success' => true, 'message' => 'تم إنشاء العملة', 'data' => $currency, 'code' => 201], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'فشل إنشاء العملة: ' . $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $currency = Currency::where('company_id', $request->user()->company_id)->find($id);
        if (!$currency) {
            return response()->json(['success' => false, 'message' => 'العملة غير موجودة', 'data' => null, 'code' => 404], 404);
        }

        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'code'      => 'sometimes|string|max:10',
            'symbol'    => 'nullable|string|max:10',
            'rate'      => 'nullable|numeric|min:0',
            'is_base'   => 'nullable|integer|in:0,1',
            'is_active' => 'sometimes|integer|in:0,1',
        ]);
        $data['user_id'] = $request->user()->id;

        $rate = $data['rate'] ?? null;
        unset($data['rate']);

        try {
            DB::transaction(function () use ($currency, $data, $rate) {
                // 1. تحديث العملة
                $currency->update($data);

                // 2. لو rate موجود → حدّث/أضف سعر الصرف
                if ($rate !== null) {
                    $today = now()->toDateString();
                    $exchange = ExchangeRate::where('currency_id', $currency->id)
                        ->where('rate_date', $today)
                        ->first();

                    if ($exchange) {
                        $exchange->update(['rate' => $rate, 'user_id' => $data['user_id']]);
                    } else {
                        ExchangeRate::create([
                            'company_id'  => $currency->company_id,
                            'currency_id' => $currency->id,
                            'rate'        => $rate,
                            'rate_date'   => $today,
                            'user_id'     => $data['user_id'],
                        ]);
                    }
                }
            });

            $latest = ExchangeRate::where('currency_id', $currency->id)
                ->orderByDesc('rate_date')
                ->first();
            $currency->rate = $latest?->rate ?? null;

            return response()->json(['success' => true, 'message' => 'تم تعديل العملة', 'data' => $currency, 'code' => 200]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'فشل تعديل العملة: ' . $e->getMessage(), 'data' => null, 'code' => 500], 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $currency = Currency::where('company_id', $request->user()->company_id)->find($id);
        if (!$currency) {
            return response()->json(['success' => false, 'message' => 'العملة غير موجودة', 'data' => null, 'code' => 404], 404);
        }

        // ✅ فحص العلاقات
        $error = $this->integrity->checkRelations($currency, [
            'exchangeRates' => 'أسعار صرف',
            'items'         => 'أصناف',
        ]);

        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'data' => null, 'code' => 409], 409);
        }

        $currency->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف العملة', 'data' => null, 'code' => 200]);
    }
}
