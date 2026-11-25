<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HasVoucherNavigation;
use App\Models\PurchaseClosing;
use App\Models\Contact;
use App\Models\Karat;
use App\Models\JournalEntry;
use App\Services\MasterService;
use App\Services\ClosingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class PurchaseClosingController extends Controller
{
    use HasVoucherNavigation;

    private const TYPE_CODE    = 'PURCHASE_CLOSING';
    private const KIND         = 'purchase';
    private const CONTACT_TYPE = 'supplier';

    public function __construct(
        private MasterService $master,
        private ClosingService $closings
    ) {}

    public function options(Request $request)
    {
        $companyId = $request->user()->company_id;

        try {
            return response()->json([
                'success' => true, 'code' => 200, 'message' => 'تم الجلب',
                'data' => [
                    'contacts' => Contact::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->where('contact_type', self::CONTACT_TYPE)
                        ->orderBy('id', 'asc')
                        ->get(['id', 'name', 'acc_no', 'contact_type', 'balance']),

                    'karats' => Karat::where('company_id', $companyId)
                            ->where('is_active', 1)
                            ->orderByRaw("CASE WHEN TRIM(name) = '21' THEN 0 ELSE 1 END ASC")
                            ->orderBy('id', 'asc')
                            ->get(['id', 'name', 'the_value']),
                    'users' => \App\Models\User::where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->orderBy('id', 'asc')
                        ->get(['id', 'name', 'role']),
                ],
            ]);
        } catch (Throwable $e) {
            return $this->error('فشل الجلب: '.$e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $q = PurchaseClosing::with(['contact', 'karat', 'user', 'journalEntry.details'])
            ->where('company_id', $companyId);

        $this->applyFilters($q, $request);

        $page    = max(1, (int)$request->input('page', 1));
        $perPage = min(200, max(1, (int)$request->input('per_page', 20)));
        $total   = (clone $q)->count();
        $items   = $q->orderByDesc('id')->forPage($page, $perPage)->get();

        $totals = (clone $q)->reorder()->selectRaw('
            COALESCE(SUM(net_weight), 0)     AS total_net_weight,
            COALESCE(SUM(jewelry_weight), 0) AS total_jewelry_weight,
            COALESCE(SUM(net_amount), 0)     AS total_net_amount,
            COALESCE(SUM(jewelry_amount), 0) AS total_jewelry_amount,
            COALESCE(SUM(total_amount), 0)   AS grand_total_amount
        ')->first();

        $nav = [
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => (int)ceil($total / $perPage),
        ];
        if ($items->isNotEmpty()) {
            $nav = array_merge($nav, $this->navigation($q, $items->first()->id, 'id'));
        }

        return response()->json([
            'success' => true, 'message' => 'تم الجلب',
            'data'    => $items,
            'meta'    => $nav,
            'totals'  => $totals,
            'code'    => 200,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;

        $row = PurchaseClosing::with([
            'contact', 'karat', 'user',
            'journalEntry.details.account',
            'journalEntry.details.karat',
            'journalEntry.details.costCenter',
        ])->where('company_id', $companyId)->find($id);

        if (!$row) return $this->error('التسكير غير موجود', 404);

        $q = PurchaseClosing::where('company_id', $companyId);
        $this->applyFilters($q, $request);
        $nav = $this->navigation($q, $id, 'id');

        return response()->json([
            'success' => true, 'message' => 'تم الجلب',
            'data' => array_merge($row->toArray(), ['nav' => $nav]),
            'code' => 200,
        ]);
    }

    public function create(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'جاهز لإنشاء تسكير مشتريات',
            'data'    => null,
            'code'    => 200,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, false);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        $companyId = (int)$request->user()->company_id;
        $docTypeId = $this->closings->docTypeId(self::TYPE_CODE, $companyId);
        if (!$docTypeId) return $this->error('نوع المستند PURCHASE_CLOSING غير معرف', 422);

        try {
            $row = DB::transaction(function () use ($request, $data, $companyId, $docTypeId) {
                $data = $this->normalize($data, $companyId);

                $docNoRes = $this->master->generateDocNo($companyId, (int)$data['branch_id'], $docTypeId);
                if (!$docNoRes['success']) throw new \RuntimeException($docNoRes['message']);
                $docNo = (int)$docNoRes['data']['doc_no'];

                $row = PurchaseClosing::create(array_merge($data, [
                    'company_id' => $companyId,
                    'doc_no'     => $docNo,
                    'user_id' => $data['user_id'] ?? $request->user()->id,
                ]));

                $row->load('contact');
                $this->createJournal($row, $docTypeId, $docNo);

                return $row->fresh()->load([
                    'contact', 'karat', 'user',
                    'journalEntry.details.account',
                ]);
            });

            return response()->json([
                'success' => true, 'message' => 'تم إنشاء تسكير المشتريات',
                'data'    => $row, 'code' => 201,
            ], 201);
        } catch (Throwable $e) {
            \Log::error('purchase closing store: '.$e->getMessage());
            return $this->error('فشل الحفظ: '.$e->getMessage(), 500);
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
        $docTypeId = $this->closings->docTypeId(self::TYPE_CODE, $companyId);
        if (!$docTypeId) return $this->error('نوع المستند PURCHASE_CLOSING غير معرف', 422);

        try {
            $row = DB::transaction(function () use ($id, $data, $companyId, $docTypeId) {
                $row = PurchaseClosing::where('company_id', $companyId)
                    ->lockForUpdate()->find($id);
                if (!$row) throw new \RuntimeException('التسكير غير موجود');

                $journal = JournalEntry::where('company_id', $companyId)
                    ->where('document_type_id', $docTypeId)
                    ->where('document_id', $row->id)
                    ->lockForUpdate()
                    ->first();
                if (!$journal) throw new \RuntimeException('القيد المرتبط غير موجود');

                $data = $this->normalize(array_merge($row->getAttributes(), $data), $companyId);
                unset(
                    $data['id'], $data['company_id'], $data['doc_no'],
                    $data['created_at'], $data['updated_at']
                );

                $row->update($data);
                $row->refresh()->load('contact');

                $journal->details()->delete();
                $journal->update([
                    'journal_date'     => $row->doc_date,
                    'transaction_type' => $row->transaction_type,
                    'description'      => $row->notes,
                ]);

                $lines = $this->closings->buildLines(self::KIND, array_merge($row->toArray(), [
                    'contact_acc_no' => $row->contact?->acc_no,
                    'doc_date'       => $row->doc_date->format('Y-m-d'),
                ]), $companyId);

                $journal->details()->createMany($this->mapLinesToDetails($journal, $lines));

                return $row->fresh()->load([
                    'contact', 'karat', 'user',
                    'journalEntry.details.account',
                ]);
            });

            return response()->json([
                'success' => true, 'message' => 'تم تعديل التسكير',
                'data'    => $row, 'code' => 200,
            ]);
        } catch (Throwable $e) {
            \Log::error('purchase closing update: '.$e->getMessage());
            return $this->error('فشل التعديل: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $companyId = (int)$request->user()->company_id;
        $docTypeId = $this->closings->docTypeId(self::TYPE_CODE, $companyId);
        if (!$docTypeId) return $this->error('نوع المستند PURCHASE_CLOSING غير معرف', 422);

        try {
            DB::transaction(function () use ($id, $companyId, $docTypeId) {
                $row = PurchaseClosing::where('company_id', $companyId)
                    ->lockForUpdate()->find($id);
                if (!$row) throw new \RuntimeException('التسكير غير موجود');

                $journal = JournalEntry::where('company_id', $companyId)
                    ->where('document_type_id', $docTypeId)
                    ->where('document_id', $row->id)
                    ->lockForUpdate()
                    ->first();

                if ($journal) {
                    $journal->details()->delete();
                    $journal->delete();
                }
                $row->delete();
            });

            return response()->json([
                'success' => true, 'message' => 'تم حذف التسكير',
                'data' => null, 'code' => 200,
            ]);
        } catch (Throwable $e) {
            \Log::error('purchase closing destroy: '.$e->getMessage());
            return $this->error('فشل الحذف: '.$e->getMessage(), 500);
        }
    }

    public function print(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;

        $row = PurchaseClosing::with(['contact', 'karat', 'user'])->where('company_id', $companyId)->find($id);

        if (!$row) return $this->error('التسكير غير موجود', 404);

        return response()->json([
            'success' => true, 'message' => 'جاهز للطباعة',
            'data' => [
                'closing'    => $row,
                'print_at'   => now()->toDateTimeString(),
                'printed_by' => $request->user()->name,
            ],
            'code' => 200,
        ]);
    }

    private function createJournal(PurchaseClosing $row, int $docTypeId, int $docNo): void
    {
        $accNo = $row->contact?->acc_no;
        if (!$accNo) throw new \RuntimeException('حساب الطرف المقابل غير موجود');

        $this->closings->createLinkedJournal(
            self::KIND,
            (int)$row->company_id,
            (int)$row->branch_id,
            (int)$row->id,
            $docTypeId,
            $docNo,
            array_merge($row->toArray(), [
                'doc_date' => $row->doc_date->format('Y-m-d'),
            ]),
            $accNo
        );
    }

    private function mapLinesToDetails(JournalEntry $journal, array $lines): array
    {
        return array_map(fn($l) => [
            'company_id'      => $journal->company_id,
            'branch_id'       => $journal->branch_id,
            'account_id'      => $l['account_id'],
            'cost_center_id'  => $l['cost_center_id'] ?? null,
            'currency_id'     => $journal->currency_id,
            'exchange_rate'   => $journal->exchange_rate,
            'debit_amount'    => $l['debit_amount']    ?? 0,
            'credit_amount'   => $l['credit_amount']   ?? 0,
            'gold_debit'      => $l['gold_debit']      ?? 0,
            'gold_credit'     => $l['gold_credit']     ?? 0,
            'karat_id'        => $l['karat_id']        ?? null,
            'silver_debit'    => 0,
            'silver_credit'   => 0,
            'silver_karat_id' => null,
            'description'     => $l['description']     ?? null,
            'user_id'         => $journal->user_id,
        ], $lines);
    }

    private function normalize(array $data, int $companyId): array
    {
        return $this->closings->normalizeClosingPayload($data, $companyId);
    }

    private function applyFilters($q, Request $r): void
    {
        if ($r->filled('branch_id'))        $q->where('branch_id', $r->branch_id);
        if ($r->filled('contact_id'))       $q->where('contact_id', $r->contact_id);
        if ($r->filled('karat_id'))         $q->where('karat_id', $r->karat_id);
        if ($r->filled('transaction_type')) $q->where('transaction_type', $r->transaction_type);
        if ($r->filled('doc_no'))           $q->where('doc_no', $r->doc_no);
        if ($r->filled('user_id'))          $q->where('user_id', $r->user_id);
        if ($r->filled('from'))             $q->where('doc_date', '>=', $r->from);
        if ($r->filled('to'))               $q->where('doc_date', '<=', $r->to);

        if ($r->filled('min_amount'))       $q->where('total_amount', '>=', (float)$r->min_amount);
        if ($r->filled('max_amount'))       $q->where('total_amount', '<=', (float)$r->max_amount);

        if ($r->filled('min_weight'))       $q->where('net_weight', '>=', (float)$r->min_weight);
        if ($r->filled('max_weight'))       $q->where('net_weight', '<=', (float)$r->max_weight);

        if ($r->filled('contact_type')) {
            $q->whereHas('contact', fn($c) => $c->where('contact_type', $r->contact_type));
        }

        if ($r->filled('q')) {
            $s = $r->q;
            $q->where(function ($w) use ($s) {
                $w->where('doc_no', 'LIKE', "%{$s}%")
                  ->orWhere('notes', 'LIKE', "%{$s}%")
                  ->orWhereHas('contact', fn($c) => $c
                        ->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('acc_no', 'LIKE', "%{$s}%")
                        ->orWhere('mobile', 'LIKE', "%{$s}%"));
            });
        }
    }

    private function validatePayload(Request $request, bool $isUpdate)
    {
        $req = $isUpdate ? 'sometimes' : 'required';
        $companyId = $request->user()->company_id;

        try {
            return $request->validate([
                'branch_id' => "{$req}|integer",
                'doc_date'  => "{$req}|date",
                'contact_id' => [
                    $req, 'integer',
                    Rule::exists('contacts', 'id')
                        ->where('company_id', $companyId)
                        ->where('is_active', 1)
                        ->where('contact_type', self::CONTACT_TYPE),
                ],
                'user_id' => [
                        'nullable',
                        'integer',
                        Rule::exists('users', 'id')
                            ->where('company_id', $companyId)
                            ->where('is_active', 1),
                ],
                'transaction_type' => "{$req}|in:gold,cash,both",
                'net_weight'       => 'nullable|numeric|min:0',
                'jewelry_weight'   => 'nullable|numeric|min:0',
                'karat_id'         => 'nullable|integer',
                'gram_price'       => 'nullable|numeric|min:0',
                'jewelry_amount'   => 'nullable|numeric|min:0',
                'total_amount'     => 'nullable|numeric|min:0',
                'notes'            => 'nullable|string|max:5000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false, 'message' => 'بيانات غير صحيحة',
                'data' => $e->errors(), 'code' => 422,
            ], 422);
        }
    }

    private function error(string $m, int $c)
    {
        return response()->json([
            'success' => false, 'message' => $m, 'data' => null, 'code' => $c,
        ], $c);
    }
}
