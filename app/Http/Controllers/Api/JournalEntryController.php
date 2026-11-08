<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\MasterService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(private MasterService $master) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $q = JournalEntry::with(['details.account','details.costCenter','details.currency','details.karat','documentType','currency','user'])
            ->where('company_id', $companyId);

        if ($request->filled('branch_id'))           $q->where('branch_id', $request->branch_id);
        if ($request->filled('doc_no'))              $q->where('doc_no', $request->doc_no);
        if ($request->filled('document_type_id'))    $q->where('document_type_id', $request->document_type_id);
        if ($request->filled('status'))              $q->where('status', $request->status);
        if ($request->filled('transaction_type'))    $q->where('transaction_type', $request->transaction_type);
        if ($request->filled('from'))                $q->where('journal_date', '>=', $request->from);
        if ($request->filled('to'))                  $q->where('journal_date', '<=', $request->to);
        if ($request->filled('cost_center_id'))      $q->whereHas('details', fn($d) => $d->where('cost_center_id', $request->cost_center_id));
        if ($request->filled('account_id'))          $q->whereHas('details', fn($d) => $d->where('account_id', $request->account_id));
        if ($request->filled('karat_id'))            $q->whereHas('details', fn($d) => $d->where('karat_id', $request->karat_id));
        if ($request->filled('silver_karat_id'))     $q->whereHas('details', fn($d) => $d->where('silver_karat_id', $request->silver_karat_id));

        return response()->json([
            'success' => true,
            'message' => 'تم الجلب',
            'data'    => $q->orderByDesc('id')->paginate((int)$request->input('per_page', 50)),
            'code'    => 200,
        ], 200);
    }

    public function show(Request $request, int $id)
    {
        $journal = JournalEntry::with([
            'details.account','details.costCenter','details.currency',
            'details.karat','details.silverKarat',
            'documentType','currency','user'
        ])->where('company_id', $request->user()->company_id)->find($id);

        if (!$journal) {
            return response()->json(['success' => false, 'message' => 'القيد غير موجود', 'data' => null, 'code' => 404], 404);
        }
        return response()->json(['success' => true, 'message' => 'تم الجلب', 'data' => $journal, 'code' => 200], 200);
    }

    public function create(Request $request)
    {
        $result = $this->master->generateDocNo(
            $request->user()->company_id,
            (int)$request->branch_id,
            (int)$request->document_type_id
        );
        return response()->json($result, $result['code']);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, false);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        $header = [
            'company_id'       => $request->user()->company_id,
            'branch_id'        => $data['branch_id'],
            'journal_date'     => $data['journal_date'],
            'document_type_id' => $data['document_type_id'],
            'document_id'      => $data['document_id'],
            'currency_id'      => $data['currency_id']      ?? null,
            'exchange_rate'    => $data['exchange_rate']    ?? 1,
            'transaction_type' => $data['transaction_type'] ?? 'all',
            'description'      => $data['description']      ?? null,
            'status'           => $data['status']           ?? 'draft',
            'user_id'          => $request->user()->id,
        ];

        $result = $this->master->createJournalEntry($header, $data['lines']);
        return response()->json($result, $result['code']);
    }

    public function edit(Request $request, int $id)
    {
        return $this->show($request, $id);
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validatePayload($request, true);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        $result = $this->master->updateJournalEntry(
            $id,
            $request->user()->company_id,
            $data,
            $data['lines']
        );
        return response()->json($result, $result['code']);
    }

    public function destroy(Request $request, int $id)
    {
        $result = $this->master->deleteJournalEntry($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function post(Request $request, int $id)
    {
        $result = $this->master->postJournalEntry($id, $request->user()->company_id);
        return response()->json($result, $result['code']);
    }

    public function reverse(Request $request, int $id)
    {
        $result = $this->master->reverseJournalEntry(
            $id,
            $request->user()->company_id,
            (string)$request->input('reason', '')
        );
        return response()->json($result, $result['code']);
    }

    private function validatePayload(Request $request, bool $isUpdate)
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        $rules = [
            'branch_id'                => "{$required}|integer",
            'journal_date'             => "{$required}|date",
            'document_type_id'         => "{$required}|integer",
            'document_id'              => "{$required}|integer",
            'currency_id'              => 'nullable|integer',
            'exchange_rate'            => 'nullable|numeric|min:0.000001',
            'transaction_type'         => 'nullable|in:cash,gold,silver,gold_silver,cash_gold,cash_silver,all',
            'description'              => 'nullable|string|max:500',
            'status'                   => 'nullable|in:draft,posted',
            'lines'                    => 'required|array|min:2',
            'lines.*.account_id'       => 'required|integer',
            'lines.*.cost_center_id'   => 'nullable|integer',
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
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'data'    => $e->errors(),
                'code'    => 422,
            ], 422);
        }
    }
}
