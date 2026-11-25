<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HasVoucherNavigation;
use App\Models\ReceiptVoucher;
use App\Models\Account;
use App\Models\Karat;
use App\Services\MasterService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ReceiptVoucherController extends Controller
{
    use HasVoucherNavigation;

    public function __construct(
        private MasterService $master,
        private VoucherService $vouchers
    ) {}

    public function options(Request $request)
    {
        $companyId = $request->user()->company_id;

        try {
            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data' => [
                    'cost_centers' => \App\Models\CostCenter::where('company_id', $companyId)->where('is_active', 1)->orderBy('id', 'asc')->get(['id', 'code', 'name']),
                    'contacts' => Account::where('company_id', $companyId)->where('is_active', 1)->where('account_level', 4)->orderBy('id', 'asc')->get(['id', 'name', 'acc_no', 'account_type', 'account_nature']),
                    'safes' => \App\Models\Safe::where('company_id', $companyId)->where('is_active', 1)->orderBy('id', 'asc')->get(['id', 'name', 'code', 'acc_no']),
                    'banks' => \App\Models\Bank::where('company_id', $companyId)->where('is_active', 1)->orderBy('id', 'asc')->get(['id', 'name', 'code', 'acc_no']),
                    'stores' => \App\Models\Store::where('company_id', $companyId)->where('is_active', 1)->orderBy('id', 'asc')->get(['id', 'name', 'code', 'account_id']),
                    'currencies' => \App\Models\Currency::where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))->where('is_active', 1)->orderBy('id', 'asc')->get(['id', 'code', 'name', 'symbol']),
                    'karats' => Karat::where('company_id', $companyId)
                            ->where('is_active', 1)
                            ->orderByRaw("CASE WHEN TRIM(name) = '21' THEN 0 ELSE 1 END ASC")
                            ->orderBy('id', 'asc')
                            ->get(['id', 'name', 'the_value']),
                    ],
                'code' => 200,
            ]);
        } catch (Throwable $e) {
            \Log::error('receipt options: '.$e->getMessage());
            return $this->error('فشل الجلب: '.$e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $q = ReceiptVoucher::with(['contact', 'safe', 'bank', 'store', 'currency', 'karat', 'user', 'journalEntry.details'])
            ->where('company_id', $companyId);

        $this->applyFilters($q, $request);

        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(200, max(1, (int)$request->input('per_page', 20)));
        $total = (clone $q)->count();
        $items = $q->orderByDesc('id')->forPage($page, $perPage)->get();

        $nav = [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int)ceil($total / $perPage),
        ];

        if ($items->isNotEmpty()) {
            $nav = array_merge($nav, $this->navigation($q, $items->first()->id, 'id'));
        }

        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $items, 'meta' => $nav, 'code' => 200]);
    }

    public function show(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;
        $voucher = ReceiptVoucher::with([
            'contact', 'safe', 'bank', 'store', 'currency', 'karat', 'user',
            'journalEntry.details.account', 'journalEntry.details.costCenter',
        ])->where('company_id', $companyId)->find($id);

        if (!$voucher) return $this->error('السند غير موجود', 404);

        $q = ReceiptVoucher::where('company_id', $companyId);
        $this->applyFilters($q, $request);
        $nav = $this->navigation($q, $id, 'id');

        return response()->json([
            'success' => true,
            'message' => 'تم الجلب',
            'data' => array_merge($voucher->toArray(), ['nav' => $nav]),
            'code' => 200,
        ]);
    }

    public function create(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'جاهز لإنشاء السند', 'code' => 200]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, false);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        $companyId = (int)$request->user()->company_id;
        $docTypeId = $this->vouchers->docTypeId('RECEIPT_VOUCHER', $companyId);

        if (!$docTypeId) return $this->error('نوع سند القبض غير معرف', 422);

        try {
            $voucher = DB::transaction(function () use ($request, $data, $companyId, $docTypeId) {
                $data = $this->normalizeGoldFields($data, $companyId);
                $docNo = $this->master->generateDocNo($companyId, (int)$data['branch_id'], $docTypeId);

                if (!$docNo['success']) throw new \RuntimeException($docNo['message'] ?? 'فشل توليد رقم السند');

                $voucher = ReceiptVoucher::create(array_merge($data, [
                    'company_id' => $companyId,
                    'doc_no' => $docNo['data']['doc_no'],
                    'status' => 'posted',
                    'user_id' => $request->user()->id,
                ]));

                $voucher->load(['contact', 'safe', 'bank', 'store']);
                $this->createJournal($voucher, $docTypeId);

                return $voucher->fresh()->load([
                    'contact', 'safe', 'bank', 'store', 'currency', 'karat',
                    'journalEntry.details.account',
                ]);
            });

            return response()->json(['success' => true, 'message' => 'تم حفظ سند', 'data' => $voucher, 'code' => 201], 201);
        } catch (Throwable $e) {
            \Log::error('receipt store: '.$e->getMessage());
            return $this->error('فشل إنشاء السند: '.$e->getMessage(), 500);
        }
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validatePayload($request, true);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        $companyId = (int)$request->user()->company_id;

        try {
            $voucher = DB::transaction(function () use ($id, $data, $companyId) {
                $voucher = ReceiptVoucher::where('company_id', $companyId)->lockForUpdate()->find($id);
                if (!$voucher) throw new \RuntimeException('السند غير موجود');

                $journal = $voucher->journalEntry()->lockForUpdate()->first();
                if (!$journal) throw new \RuntimeException('القيد المحاسبي المرتبط بالسند غير موجود');

                $data = $this->normalizeGoldFields(array_merge($voucher->getAttributes(), $data), $companyId);
                unset($data['id'], $data['company_id'], $data['doc_no'], $data['user_id'], $data['created_at'], $data['updated_at']);
                $voucher->update($data);

                $voucher->refresh()->load(['contact', 'safe', 'bank', 'store']);
                $lines = $this->resolveVoucherLines($voucher);

                $journal->details()->delete();
                $journal->update([
                    'journal_date' => $voucher->doc_date,
                    'currency_id' => $voucher->currency_id,
                    'exchange_rate' => $voucher->exchange_rate,
                    'transaction_type' => $voucher->transaction_type,
                    'description' => $voucher->description,
                    'status' => 'posted',
                ]);
                $journal->details()->createMany($lines);

                return $voucher->fresh()->load([
                    'contact', 'safe', 'bank', 'store', 'currency', 'karat',
                    'journalEntry.details.account', 'journalEntry.details.costCenter',
                ]);
            });

            return response()->json(['success' => true, 'message' => 'تم تعديل السند ', 'data' => $voucher, 'code' => 200]);
        } catch (Throwable $e) {
            \Log::error('receipt update: '.$e->getMessage());
            return $this->error('فشل تعديل السند: '.$e->getMessage(), $e->getMessage() === 'السند غير موجود' ? 404 : 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $companyId = (int)$request->user()->company_id;

        try {
            DB::transaction(function () use ($id, $companyId) {
                $voucher = ReceiptVoucher::where('company_id', $companyId)->lockForUpdate()->find($id);
                if (!$voucher) throw new \RuntimeException('السند غير موجود');

                $journal = $voucher->journalEntry()->lockForUpdate()->first();
                if ($journal) {
                    $journal->details()->delete();
                    $journal->delete();
                }
                $voucher->delete();
            });

            return response()->json(['success' => true, 'message' => 'تم حذف السند والقيد المحاسبي', 'data' => null, 'code' => 200]);
        } catch (Throwable $e) {
            \Log::error('receipt destroy: '.$e->getMessage());
            return $this->error('فشل حذف السند: '.$e->getMessage(), $e->getMessage() === 'السند غير موجود' ? 404 : 500);
        }
    }

    public function post(Request $request, int $id)
    {
        $companyId = (int)$request->user()->company_id;
        $docTypeId = $this->vouchers->docTypeId('RECEIPT_VOUCHER', $companyId);
        if (!$docTypeId) return $this->error('نوع سند القبض غير معرف', 422);

        try {
            $voucher = DB::transaction(function () use ($id, $companyId, $docTypeId) {
                $voucher = ReceiptVoucher::where('company_id', $companyId)->lockForUpdate()->find($id);
                if (!$voucher) throw new \RuntimeException('السند غير موجود');
                if ($voucher->journalEntry()->exists()) throw new \RuntimeException('السند له قيد محاسبي مسبق');

                $voucher->load(['contact', 'safe', 'bank', 'store']);
                $this->createJournal($voucher, $docTypeId);
                return $voucher->fresh()->load('journalEntry.details.account');
            });

            return response()->json(['success' => true, 'message' => 'تم إنشاء القيد وترحيل السند', 'data' => $voucher, 'code' => 200]);
        } catch (Throwable $e) {
            return $this->error('فشل ترحيل السند: '.$e->getMessage(), $e->getMessage() === 'السند غير موجود' ? 404 : 500);
        }
    }

    public function print(Request $request, int $id)
    {
        $voucher = ReceiptVoucher::with([
            'contact', 'safe', 'bank', 'store', 'currency', 'karat', 'user',
            'journalEntry.details.account',
        ])->where('company_id', $request->user()->company_id)->find($id);

        if (!$voucher) return $this->error('السند غير موجود', 404);

        return response()->json([
            'success' => true,
            'message' => 'جاهز للطباعة',
            'data' => [
                'voucher' => $voucher,
                'print_at' => now()->toDateTimeString(),
                'printed_by' => $request->user()->name ?? null,
            ],
            'code' => 200,
        ]);
    }

    private function createJournal(ReceiptVoucher $voucher, int $docTypeId): void
    {
        $counterAccNo = $voucher->contact?->acc_no;
        if (!$counterAccNo) throw new \RuntimeException('حساب الطرف المقابل غير موجود');

        $this->vouchers->createLinkedJournal('RECEIPT_VOUCHER', (int)$voucher->company_id, (int)$voucher->branch_id, (int)$voucher->id, $docTypeId, $this->journalPayload($voucher, $counterAccNo));
        $voucher->update(['status' => 'posted']);
    }

    private function resolveVoucherLines(ReceiptVoucher $voucher): array
    {
        $payload = $this->journalPayload($voucher, $voucher->contact?->acc_no);
        return $this->vouchers->resolveLines('RECEIPT_VOUCHER', (int)$voucher->company_id, $payload);
    }

    private function journalPayload(ReceiptVoucher $voucher, ?string $counterAccNo): array
    {
        $storeAccountId = $voucher->store?->account_id;
        $storeAccNo = $storeAccountId
            ? Account::where('company_id', $voucher->company_id)->where('id', $storeAccountId)->where('is_active', 1)->value('acc_no')
            : null;

        return [
            'branch_id'        => (int)$voucher->branch_id,
            'doc_date' => $voucher->doc_date,
            'description' => $voucher->description,
            'currency_id' => $voucher->currency_id,
            'exchange_rate' => $voucher->exchange_rate,
            'transaction_type' => $voucher->transaction_type,
            'cash_amount' => $voucher->cash_amount,
            'gold_weight' => $voucher->gold_weight,
            'gold_net_weight' => $voucher->gold_net_weight,
            'gold_amount' => $voucher->gold_amount,
            'karat_id' => $voucher->karat_id,
            'cost_center_id' => $voucher->cost_center_id,
            'counter_acc_no' => $counterAccNo,
            'safe_acc_no' => $voucher->safe?->acc_no,
            'bank_acc_no' => $voucher->bank?->acc_no,
            'store_acc_no' => $storeAccNo,
            'user_id' => $voucher->user_id,
        ];
    }

    private function applyFilters($q, Request $r): void
    {
        if ($r->filled('branch_id')) $q->where('branch_id', $r->branch_id);
        if ($r->filled('contact_id')) $q->where('contact_id', $r->contact_id);
        if ($r->filled('account_id')) $q->where('contact_id', $r->account_id);
        if ($r->filled('contact_type')) $q->whereHas('contact', fn($c) => $c->where('account_type', $r->contact_type));
        if ($r->filled('store_id')) $q->where('store_id', $r->store_id);
        if ($r->filled('safe_id')) $q->where('safe_id', $r->safe_id);
        if ($r->filled('bank_id')) $q->where('bank_id', $r->bank_id);
        if ($r->filled('payment_method')) $q->where('payment_method', $r->payment_method);
        if ($r->filled('currency_id')) $q->where('currency_id', $r->currency_id);
        if ($r->filled('karat_id')) $q->where('karat_id', $r->karat_id);
        if ($r->filled('status')) $q->where('status', $r->status);
        if ($r->filled('doc_no')) $q->where('doc_no', $r->doc_no);
        if ($r->filled('transaction_type')) $q->where('transaction_type', $r->transaction_type);
        if ($r->filled('from')) $q->where('doc_date', '>=', $r->from);
        if ($r->filled('to')) $q->where('doc_date', '<=', $r->to);

        if ($r->filled('q')) {
            $s = $r->q;
            $q->where(fn($w) => $w->where('description', 'LIKE', "%{$s}%")
                ->orWhere('reference_no', 'LIKE', "%{$s}%")
                ->orWhere('agent_name', 'LIKE', "%{$s}%")
                ->orWhereHas('contact', fn($c) => $c->where('name', 'LIKE', "%{$s}%")));
        }
    }

    private function normalizeGoldFields(array $data, int $companyId): array
    {
        $type = $data['transaction_type'] ?? 'cash';
        $hasCash = in_array($type, ['cash', 'cash_gold'], true);
        $hasGold = in_array($type, ['gold', 'cash_gold'], true);
        $data['cash_amount'] = $hasCash ? (float)($data['cash_amount'] ?? 0) : 0;

        $method = $data['payment_method'] ?? 'cash';
        $data['safe_id'] = ($hasCash && $method === 'cash') ? ($data['safe_id'] ?? null) : null;
        $data['bank_id'] = ($hasCash && $method === 'bank') ? ($data['bank_id'] ?? null) : null;

        if (!$hasGold) {
            $data['gold_weight'] = 0;
            $data['gold_net_weight'] = 0;
            $data['gold_amount'] = 0;
            $data['karat_id'] = null;
            $data['store_id'] = null;
        } else {
            if (empty($data['gold_net_weight']) && !empty($data['gold_weight']) && !empty($data['karat_id'])) {
                $purity = (float)DB::table('karats')->where('company_id', $companyId)->where('id', $data['karat_id'])->value('the_value');
                if ($purity > 0) $data['gold_net_weight'] = round((float)$data['gold_weight'] * $purity, 3);
            }

            if (empty($data['gold_amount']) && !empty($data['gold_net_weight']) && !empty($data['karat_id'])) {
                $price = $this->getKaratGramPrice($companyId, (int)$data['karat_id']);
                $data['gold_amount'] = $price > 0 ? round((float)$data['gold_net_weight'] * $price, 2) : 0;
            }
        }

        $data['total_amount'] = round((float)($data['cash_amount'] ?? 0) + (float)($data['gold_amount'] ?? 0), 2);
        unset($data['tax_id'], $data['tax_rate'], $data['tax_amount'], $data['discount_amount'], $data['status'], $data['post_now']);
        return $data;
    }

    private function getKaratGramPrice(int $companyId, int $karatId): float
    {
        return (float)(DB::table('gold_prices')->where('company_id', $companyId)->where('karat_id', $karatId)
            ->orderByDesc('price_date')->orderByDesc('id')->value('sell_price') ?? 0);
    }

    private function validatePayload(Request $request, bool $isUpdate)
    {
        $req = $isUpdate ? 'sometimes' : 'required';
        $companyId = $request->user()->company_id;

        try {
            return $request->validate([
                'branch_id' => "{$req}|integer",
                'doc_date' => "{$req}|date",
                'contact_id' => [
                    $req, 'integer',
                    Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', 1)->where('account_level', 4),
                ],
                'safe_id' => 'nullable|integer',
                'bank_id' => 'nullable|integer',
                'store_id' => 'nullable|integer',
                'payment_method' => 'nullable|in:cash,bank',
                'currency_id' => 'nullable|integer',
                'exchange_rate' => 'nullable|numeric|min:0.000001',
                'cash_amount' => 'nullable|numeric|min:0',
                'gold_weight' => 'nullable|numeric|min:0',
                'gold_net_weight' => 'nullable|numeric|min:0',
                'gold_amount' => 'nullable|numeric|min:0',
                'cost_center_id' => 'nullable|integer',
                'karat_id' => 'nullable|integer',
                'total_amount' => $isUpdate ? 'sometimes|numeric|min:0' : 'required|numeric|min:0',
                'reference_no' => 'nullable|string|max:100',
                'description' => 'nullable|string|max:500',
                'agent_name' => 'nullable|string|max:150',
                'transaction_type' => 'nullable|in:cash,gold,cash_gold',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'بيانات غير صحيحة', 'data' => $e->errors(), 'code' => 422], 422);
        }
    }

    private function error(string $msg, int $code)
    {
        return response()->json(['success' => false, 'message' => $msg, 'data' => null, 'code' => $code], $code);
    }
}
