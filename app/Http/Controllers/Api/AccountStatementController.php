<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\DocumentType;
use App\Models\JournalEntryDetail;
use App\Models\Karat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountStatementController extends Controller
{
    public function options(Request $request)
    {
        $companyId = (int)$request->user()->company_id;

        try {
            $accounts = Account::where('company_id', $companyId)
                ->where('is_active', 1)
                ->orderBy('acc_no', 'asc')
                ->get(['id', 'acc_no', 'name', 'father_acc_no', 'account_type', 'account_level', 'final_account', 'account_nature']);

            $branches = Branch::where('company_id', $companyId)
                ->where('is_active', 1)
                ->orderBy('id', 'asc')
                ->get(['id', 'name']);

            $documentTypes = DocumentType::where('is_active', 1)
                ->where(function ($q) use ($companyId) {
                    $q->whereNull('company_id')->orWhere('company_id', $companyId);
                })
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get(['id', 'type_code', 'trans_code', 'name', 'name_en']);

            $karats = Karat::where('company_id', $companyId)
                ->where('is_active', 1)
                ->orderBy('id', 'asc')
                ->get(['id', 'name', 'the_value']);

            return response()->json([
                'success' => true,
                'code'    => 200,
                'message' => 'تم الجلب',
                'data'    => [
                    'accounts'       => $accounts,
                    'branches'       => $branches,
                    'document_types' => $documentTypes,
                    'karats'         => $karats,
                ],
            ]);
        } catch (Throwable $e) {
            return $this->error('فشل الجلب: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        $companyId = (int)$request->user()->company_id;

        $accountId = $request->input('account_id');
        if (!$accountId) {
            return $this->error('يجب اختيار الحساب (account_id)', 422);
        }

        $account = Account::where('company_id', $companyId)->find((int)$accountId);
        if (!$account) {
            return $this->error('الحساب غير موجود', 404);
        }

        try {
            $includeOpening = $this->parseBool($request->input('with_opening', $request->input('include_opening', true)));
            $fromDate       = $request->filled('from') ? date('Y-m-d', strtotime((string)$request->input('from'))) : null;
            $toDate         = $request->filled('to') ? date('Y-m-d', strtotime((string)$request->input('to'))) : null;
            $nature         = strtolower((string)($account->account_nature ?? 'debit'));
            $isCreditNature = ($nature === 'credit');

            $openingCash = 0.0;
            $openingGold = 0.0;

            if ($includeOpening) {
                $preQuery = $this->baseDetailsQuery($companyId, (int)$account->id, $request);

                if ($fromDate) {
                    $preQuery->whereDate('je.journal_date', '<', $fromDate);
                }

                $preTotals = $preQuery->selectRaw('
                    COALESCE(SUM(jed.debit_amount), 0)  AS sum_debit,
                    COALESCE(SUM(jed.credit_amount), 0) AS sum_credit,
                    COALESCE(SUM(jed.gold_debit), 0)    AS sum_gold_debit,
                    COALESCE(SUM(jed.gold_credit), 0)   AS sum_gold_credit
                ')->first();

                $preDebit  = (float)($preTotals->sum_debit ?? 0);
                $preCredit = (float)($preTotals->sum_credit ?? 0);
                $preGDebit = (float)($preTotals->sum_gold_debit ?? 0);
                $preGCredit= (float)($preTotals->sum_gold_credit ?? 0);

                $openingCash = $this->signedBalance($preDebit, $preCredit, $isCreditNature);
                $openingGold = $this->signedBalance($preGDebit, $preGCredit, $isCreditNature);
            }

            $periodQuery = $this->baseDetailsQuery($companyId, (int)$account->id, $request);

            if ($fromDate) {
                $periodQuery->whereDate('je.journal_date', '>=', $fromDate);
            }
            if ($toDate) {
                $periodQuery->whereDate('je.journal_date', '<=', $toDate);
            }

            $periodTotals = (clone $periodQuery)->selectRaw('
                COALESCE(SUM(jed.debit_amount), 0)  AS period_debit,
                COALESCE(SUM(jed.credit_amount), 0) AS period_credit,
                COALESCE(SUM(jed.gold_debit), 0)    AS period_gold_debit,
                COALESCE(SUM(jed.gold_credit), 0)   AS period_gold_credit
            ')->first();

            $periodDebit  = round((float)($periodTotals->period_debit ?? 0), 2);
            $periodCredit = round((float)($periodTotals->period_credit ?? 0), 2);
            $periodGDebit = round((float)($periodTotals->period_gold_debit ?? 0), 3);
            $periodGCredit= round((float)($periodTotals->period_gold_credit ?? 0), 3);

            $runningCash = round($openingCash, 2);
            $runningGold = round($openingGold, 3);

            $rows = $periodQuery
                ->leftJoin('document_types AS dt', 'dt.id', '=', 'je.document_type_id')
                ->leftJoin('branches AS b', function ($join) use ($companyId) {
                    $join->on('b.id', '=', 'jed.branch_id')
                        ->where('b.company_id', '=', $companyId);
                })
                ->leftJoin('karats AS k', function ($join) use ($companyId) {
                    $join->on('k.id', '=', 'jed.karat_id')
                        ->where('k.company_id', '=', $companyId);
                })
                ->select([
                    'jed.id',
                    'jed.journal_id',
                    'jed.branch_id',
                    'b.name AS branch_name',
                    'je.doc_no',
                    'je.journal_date',
                    'je.document_type_id',
                    'dt.type_code AS doc_type_code',
                    'dt.name AS doc_type_name',
                    'je.transaction_type',
                    'jed.debit_amount',
                    'jed.credit_amount',
                    'jed.gold_debit',
                    'jed.gold_credit',
                    'jed.karat_id',
                    'k.name AS karat_name',
                    DB::raw('COALESCE(NULLIF(TRIM(jed.description), ""), je.description) AS statement_description'),
                ])
                ->orderBy('je.journal_date', 'asc')
                ->orderBy('jed.journal_id', 'asc')
                ->orderBy('jed.id', 'asc')
                ->get();

            $statementRows = [];
            foreach ($rows as $row) {
                $drCash  = (float)$row->debit_amount;
                $crCash  = (float)$row->credit_amount;
                $drGold  = (float)$row->gold_debit;
                $crGold  = (float)$row->gold_credit;

                $deltaCash = $this->signedBalance($drCash, $crCash, $isCreditNature);
                $deltaGold = $this->signedBalance($drGold, $crGold, $isCreditNature);

                $runningCash = round($runningCash + $deltaCash, 2);
                $runningGold = round($runningGold + $deltaGold, 3);

                $statementRows[] = [
                    'id'                   => (int)$row->id,
                    'journal_id'           => (int)$row->journal_id,
                    'branch_id'            => $row->branch_id ? (int)$row->branch_id : null,
                    'branch_name'          => $row->branch_name,
                    'doc_no'               => $row->doc_no,
                    'journal_date'         => $row->journal_date,
                    'document_type_id'     => $row->document_type_id ? (int)$row->document_type_id : null,
                    'doc_type_code'        => $row->doc_type_code,
                    'doc_type_name'        => $row->doc_type_name,
                    'transaction_type'     => $row->transaction_type,
                    'debit_amount'         => round($drCash, 2),
                    'credit_amount'        => round($crCash, 2),
                    'running_cash_balance' => $runningCash,
                    'gold_debit'           => round($drGold, 3),
                    'gold_credit'          => round($crGold, 3),
                    'running_gold_balance' => $runningGold,
                    'karat_id'             => $row->karat_id ? (int)$row->karat_id : null,
                    'karat_name'           => $row->karat_name,
                    'description'          => $row->statement_description,
                ];
            }

            $closingCash = $runningCash;
            $closingGold = $runningGold;

            $page    = max(1, (int)$request->input('page', 1));
            $perPage = min(500, max(1, (int)$request->input('per_page', 50)));
            $total   = count($statementRows);
            $paged   = array_slice($statementRows, ($page - 1) * $perPage, $perPage);

            return response()->json([
                'success' => true,
                'code'    => 200,
                'message' => 'تم جلب كشف الحساب',
                'account' => $account,
                'filters' => [
                    'account_id'       => (int)$account->id,
                    'branch_id'        => $request->filled('branch_id') ? (int)$request->input('branch_id') : null,
                    'transaction_type' => $request->input('transaction_type', $request->input('type', 'all')),
                    'document_type_id' => $request->input('document_type_id'),
                    'from'             => $fromDate,
                    'to'               => $toDate,
                    'with_opening'     => $includeOpening,
                ],
                'summary' => [
                    'account_nature' => $nature,
                    'opening_balance' => [
                        'cash' => round($openingCash, 2),
                        'gold' => round($openingGold, 3),
                    ],
                    'period_totals' => [
                        'debit_amount'  => $periodDebit,
                        'credit_amount' => $periodCredit,
                        'net_cash'      => round($this->signedBalance($periodDebit, $periodCredit, $isCreditNature), 2),
                        'gold_debit'    => $periodGDebit,
                        'gold_credit'   => $periodGCredit,
                        'net_gold'      => round($this->signedBalance($periodGDebit, $periodGCredit, $isCreditNature), 3),
                    ],
                    'closing_balance' => [
                        'cash' => $closingCash,
                        'gold' => $closingGold,
                    ],
                ],
                'data' => $paged,
                'meta' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => max(1, (int)ceil($total / $perPage)),
                ],
            ]);
        } catch (Throwable $e) {
            \Log::error('account statement index: ' . $e->getMessage());
            return $this->error('فشل جلب كشف الحساب: ' . $e->getMessage(), 500);
        }
    }

    private function baseDetailsQuery(int $companyId, int $accountId, Request $request)
    {
        $q = JournalEntryDetail::query()
            ->from('journal_entry_details AS jed')
            ->join('journal_entries AS je', 'je.id', '=', 'jed.journal_id')
            ->where('jed.company_id', $companyId)
            ->where('je.company_id', $companyId)
            ->where('jed.account_id', $accountId);

        if ($request->filled('branch_id')) {
            $branchId = (int)$request->input('branch_id');
            $q->where(function ($sub) use ($branchId) {
                $sub->where('jed.branch_id', $branchId)
                    ->orWhere('je.branch_id', $branchId);
            });
        }

        if ($request->filled('document_type_id')) {
            $docType = $request->input('document_type_id');
            if (is_array($docType)) {
                $ids = array_values(array_filter(array_map('intval', $docType)));
                if (!empty($ids)) {
                    $q->whereIn('je.document_type_id', $ids);
                }
            } else {
                $q->where('je.document_type_id', (int)$docType);
            }
        }

        $type = strtolower((string)$request->input('transaction_type', $request->input('type', 'all')));
        if ($type === 'gold') {
            $q->where(function ($sub) {
                $sub->where('jed.gold_debit', '>', 0)
                    ->orWhere('jed.gold_credit', '>', 0);
            });
        } elseif ($type === 'cash') {
            $q->where(function ($sub) {
                $sub->where('jed.debit_amount', '>', 0)
                    ->orWhere('jed.credit_amount', '>', 0);
            });
        } elseif ($type === 'both') {
            $q->where(function ($sub) {
                $sub->where('jed.gold_debit', '>', 0)
                    ->orWhere('jed.gold_credit', '>', 0)
                    ->orWhere('jed.debit_amount', '>', 0)
                    ->orWhere('jed.credit_amount', '>', 0);
            });
        }

        if ($request->filled('karat_id')) {
            $q->where('jed.karat_id', (int)$request->input('karat_id'));
        }

        if ($request->filled('q')) {
            $s = trim((string)$request->input('q'));
            $q->where(function ($sub) use ($s) {
                $sub->where('je.doc_no', 'LIKE', "%{$s}%")
                    ->orWhere('jed.description', 'LIKE', "%{$s}%")
                    ->orWhere('je.description', 'LIKE', "%{$s}%");
            });
        }

        return $q;
    }

    private function signedBalance(float $debit, float $credit, bool $isCreditNature): float
    {
        return $isCreditNature ? ($credit - $debit) : ($debit - $credit);
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value)) return $value !== 0;
        $str = strtolower(trim((string)$value));
        return !in_array($str, ['0', 'false', 'no', 'off', ''], true);
    }

    private function error(string $message, int $code)
    {
        return response()->json([
            'success' => false,
            'code'    => $code,
            'message' => $message,
            'data'    => null,
        ], $code);
    }
}