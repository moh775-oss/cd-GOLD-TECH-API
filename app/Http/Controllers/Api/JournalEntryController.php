<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HasVoucherNavigation;
use App\Models\JournalEntry;
use App\Models\DocumentType;
use App\Services\MasterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;


class JournalEntryController extends Controller
{
    use HasVoucherNavigation;

    private const TYPE_CODE = 'JOURNAL_ENTRY';

    public function __construct(private MasterService $master) {}

    private function journalDocTypeId(int $companyId): ?int
    {
        return DocumentType::where('type_code', self::TYPE_CODE)
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->orderByRaw('company_id IS NULL')
            ->value('id');
    }

    // =========================================================
    // OPTIONS
    // =========================================================
    public function options(Request $request)
    {
        $companyId = $request->user()->company_id;

        try {
            return response()->json([
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => [
                    'accounts' => \App\Models\Account::where('company_id', $companyId)
                        ->where('is_active', 1)->orderBy('acc_no')
                        ->get(['id','acc_no','name','account_level','account_nature']),
                    'cost_centers' => \App\Models\CostCenter::where('company_id', $companyId)
                        ->where('is_active', 1)->orderBy('id', 'asc')
                        ->get(['id','code','name']),
                    'currencies' => \App\Models\Currency::where(function ($q) use ($companyId) {
                            $q->whereNull('company_id')->orWhere('company_id', $companyId);
                        })->where('is_active', 1)->orderBy('id', 'asc')
                        ->get(['id','code','name','symbol']),
                    'karats' => \App\Models\Karat::where('company_id', $companyId)
                        ->where('is_active', 1)->orderBy('id', 'asc')
                        ->get(['id','name','the_value']),
                ],
                'code' => 200,
            ], 200);
        } catch (Throwable $e) {
            \Log::error('journal options: '.$e->getMessage());
            return $this->error('فشل الجلب: '.$e->getMessage(), 500);
        }
    }

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        $q = JournalEntry::with([
            'details.account','details.costCenter','details.currency','details.karat',
            'documentType','currency','user',
        ])
        ->where('company_id', $companyId)
        ->where('document_type_id', $docTypeId);

        $this->applyFilters($q, $request);

        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(200, max(1, (int)$request->input('per_page', 20)));

        $total = (clone $q)->count();
        $items = $q->orderByDesc('id')->forPage($page, $perPage)->get();

        $nav = ['current_page' => $page, 'per_page' => $perPage, 'total' => $total,
                'last_page' => (int)ceil($total / $perPage)];
        if ($items->isNotEmpty()) {
            $nav = array_merge($nav, $this->navigation($q, $items->first()->id, 'id'));
        }

        return response()->json([
            'success' => true, 'message' => 'تم الجلب',
            'data' => $items, 'meta' => $nav, 'code' => 200,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        $journal = JournalEntry::with([
            'details.account','details.costCenter','details.currency',
            'details.karat','details.silverKarat',
            'documentType','currency','user',
        ])
        ->where('company_id', $companyId)
        ->where('document_type_id', $docTypeId)
        ->find($id);

        if (!$journal) return $this->error('القيد غير موجود', 404);

        $q = JournalEntry::where('company_id', $companyId)
            ->where('document_type_id', $docTypeId);
        $this->applyFilters($q, $request);
        $nav = $this->navigation($q, $id, 'id');

        return response()->json([
            'success' => true, 'message' => 'تم الجلب',
            'data' => array_merge($journal->toArray(), ['nav' => $nav]),
            'code' => 200,
        ]);
    }

    // =========================================================
    // CREATE — لا يولّد رقم (MasterService يتولى ذلك في store)
    // =========================================================
    public function create(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'سيُولَّد رقم المستند عند الحفظ',
            'data'    => null,
            'code'    => 200,
        ]);
    }

    // =========================================================
    // STORE — الرقم يُولَّد في MasterService (مرة واحدة فقط)
    // =========================================================
    public function store(Request $request)
    {
        $data = $this->validatePayload($request, false);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        try {
            $journal = DB::transaction(function () use ($request, $data, $companyId, $docTypeId) {
                $type = $data['transaction_type'] ?? 'cash';
                if (!in_array($type, ['cash', 'gold'], true)) $type = 'cash';

                // ✅ لا نستدعي generateDocNo — MasterService يولّد الرقم
                // نمرّر document_id مؤقتاً (سيُستبدل بـ doc_no في نفس الترانزاكشن)
                $result = $this->master->createJournalEntry([
                    'company_id'       => $companyId,
                    'branch_id'        => $data['branch_id'],
                    // 'doc_no' محذوف — MasterService يولّده
                    'journal_date'     => $data['journal_date'],
                    'document_type_id' => $docTypeId,
                    'document_id'      => 1, // مؤقت — يُحدَّث أدناه
                    'currency_id'      => $data['currency_id']      ?? null,
                    'exchange_rate'    => $data['exchange_rate']    ?? 1,
                    'transaction_type' => $type,
                    'description'      => $data['description']      ?? null,
                    'status'           => 'posted',
                    'user_id'          => $request->user()->id,
                ], $data['lines']);

                if (!$result['success']) {
                    throw new \RuntimeException($result['message']);
                }

                // ✅ تحديث document_id = doc_no داخل نفس الترانزاكشن
                $journal = JournalEntry::find($result['data']['id']);
                $journal->update(['document_id' => $journal->doc_no]);

                return $journal->load([
                    'details.account','details.costCenter','details.currency',
                    'details.karat','documentType','currency','user',
                ]);
            });

            return response()->json([
                'success' => true, 'message' => 'تم إنشاء القيد',
                'data' => $journal, 'code' => 201,
            ], 201);
        } catch (Throwable $e) {
            \Log::error('journal store: '.$e->getMessage());
            return $this->error('فشل إنشاء القيد: '.$e->getMessage(), 500);
        }
    }

    // =========================================================
    // EDIT
    // =========================================================
    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    // =========================================================
    // UPDATE
    // =========================================================
    public function update(Request $request, int $id)
    {
        $data = $this->validatePayload($request, true);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        $journal = JournalEntry::where('company_id', $companyId)
            ->where('document_type_id', $docTypeId)
            ->find($id);
        if (!$journal) return $this->error('القيد غير موجود', 404);

        $type = $data['transaction_type'] ?? $journal->transaction_type ?? 'cash';
        if (!in_array($type, ['cash', 'gold'], true)) $type = 'cash';

        try {
            DB::transaction(function () use ($journal, $data, $companyId, $type, $docTypeId) {
                $result = $this->master->updateJournalEntry(
                    $journal->id,
                    $companyId,
                    [
                        'journal_date'     => $data['journal_date']     ?? $journal->journal_date,
                        'document_type_id' => $docTypeId,
                        'document_id'      => $journal->document_id,
                        'currency_id'      => $data['currency_id']      ?? $journal->currency_id,
                        'exchange_rate'    => $data['exchange_rate']    ?? $journal->exchange_rate,
                        'transaction_type' => $type,
                        'description'      => $data['description']      ?? $journal->description,
                    ],
                    $data['lines']
                );

                if (!$result['success']) {
                    throw new \RuntimeException($result['message']);
                }
            });

            return response()->json([
                'success' => true, 'message' => 'تم التعديل',
                'data'    => $journal->fresh()->load([
                    'details.account','details.costCenter','details.currency',
                    'details.karat','documentType','currency','user',
                ]),
                'code'    => 200,
            ]);
        } catch (Throwable $e) {
            \Log::error('journal update: '.$e->getMessage());
            return $this->error('فشل التعديل: '.$e->getMessage(), 500);
        }
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        $journal = JournalEntry::where('company_id', $companyId)
            ->where('document_type_id', $docTypeId)
            ->find($id);
        if (!$journal) return $this->error('القيد غير موجود', 404);

        $result = $this->master->deleteJournalEntry($id, $companyId);
        return response()->json($result, $result['code']);
    }

    // =========================================================
    // POST (القيد دايماً مرحّل)
    // =========================================================
    public function post(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        $journal = JournalEntry::where('company_id', $companyId)
            ->where('document_type_id', $docTypeId)
            ->find($id);
        if (!$journal) return $this->error('القيد غير موجود', 404);

        return response()->json([
            'success' => true, 'message' => 'القيد مرحّل مسبقاً',
            'data' => null, 'code' => 200,
        ]);
    }

    // =========================================================
    // REVERSE
    // =========================================================
    public function reverse(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        $journal = JournalEntry::where('company_id', $companyId)
            ->where('document_type_id', $docTypeId)
            ->find($id);
        if (!$journal) return $this->error('القيد غير موجود', 404);

        $result = $this->master->reverseJournalEntry(
            $id,
            $companyId,
            (string)$request->input('reason', '')
        );
        return response()->json($result, $result['code']);
    }

    // =========================================================
    // PRINT
    // =========================================================
    public function print(Request $request, int $id)
    {
        $companyId = $request->user()->company_id;
        $docTypeId = $this->journalDocTypeId($companyId);
        if (!$docTypeId) return $this->error('نوع المستند JOURNAL_ENTRY غير مهيأ', 500);

        $journal = JournalEntry::with([
            'details.account','details.costCenter','details.currency',
            'details.karat','documentType','currency','user',
        ])
        ->where('company_id', $companyId)
        ->where('document_type_id', $docTypeId)
        ->find($id);

        if (!$journal) return $this->error('القيد غير موجود', 404);

        return response()->json([
            'success' => true, 'message' => 'جاهز للطباعة',
            'data'    => [
                'journal'    => $journal,
                'print_at'   => now()->toDateTimeString(),
                'printed_by' => $request->user()->name ?? null,
            ],
            'code'    => 200,
        ]);
    }

    // =========================================================
    // HELPERS
    // =========================================================
    private function applyFilters($q, Request $r): void
    {
        if ($r->filled('branch_id'))        $q->where('branch_id', $r->branch_id);
        if ($r->filled('doc_no'))           $q->where('doc_no', $r->doc_no);
        if ($r->filled('transaction_type')) $q->where('transaction_type', $r->transaction_type);
        if ($r->filled('currency_id'))      $q->where('currency_id', $r->currency_id);
        if ($r->filled('from'))             $q->where('journal_date', '>=', $r->from);
        if ($r->filled('to'))               $q->where('journal_date', '<=', $r->to);

        if ($r->filled('account_id')) {
            $q->whereHas('details', fn($d) => $d->where('account_id', $r->account_id));
        }
        if ($r->filled('cost_center_id')) {
            $q->whereHas('details', fn($d) => $d->where('cost_center_id', $r->cost_center_id));
        }
        if ($r->filled('karat_id')) {
            $q->whereHas('details', fn($d) => $d->where('karat_id', $r->karat_id));
        }
        if ($r->filled('q')) {
            $s = $r->q;
            $q->where(function ($w) use ($s) {
                $w->where('description', 'LIKE', "%{$s}%")
                  ->orWhere('doc_no', 'LIKE', "%{$s}%");
            });
        }
    }

    // =========================================================
    // VALIDATION
    // =========================================================
    private function validatePayload(Request $request, bool $isUpdate)
    {
        $req = $isUpdate ? 'sometimes' : 'required';

        $rules = [
            'branch_id'                => "{$req}|integer",
            'journal_date'             => "{$req}|date",
            'currency_id'              => 'nullable|integer',
            'exchange_rate'            => 'nullable|numeric|min:0.000001',
            'transaction_type'         => 'nullable|in:cash,gold',
            'description'              => 'nullable|string|max:500',
            'lines'                    => 'required|array|min:2',
            'lines.*.account_id'       => 'required|integer',
            'lines.*.cost_center_id'   => 'nullable|integer',
            'lines.*.currency_id'      => 'nullable|integer',
            'lines.*.exchange_rate'    => 'nullable|numeric|min:0.000001',
            'lines.*.debit_amount'     => 'nullable|numeric|min:0',
            'lines.*.credit_amount'    => 'nullable|numeric|min:0',
            'lines.*.gold_debit'       => 'nullable|numeric|min:0',
            'lines.*.gold_credit'      => 'nullable|numeric|min:0',
            'lines.*.silver_debit'     => 'nullable|numeric|min:0',
            'lines.*.silver_credit'    => 'nullable|numeric|min:0',
            'lines.*.karat_id'         => 'nullable|integer',
            'lines.*.silver_karat_id'  => 'nullable|integer',
            'lines.*.description'      => 'nullable|string|max:500',
        ];

        try {
            return $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false, 'message' => 'بيانات غير صحيحة',
                'data' => $e->errors(), 'code' => 422,
            ], 422);
        }
    }

    private function error(string $msg, int $code)
    {
        return response()->json([
            'success' => false, 'message' => $msg, 'data' => null, 'code' => $code,
        ], $code);
    }
}
