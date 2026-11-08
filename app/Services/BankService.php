<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\DocumentType;
use App\Services\Concerns\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BankService
{
    use ApiResponse;

    public function __construct(private MasterService $master) {}

    /**
     * إنشاء بنك + حساب فرعي + قيد افتتاحي
     */
    public function create(array $data): array
    {
        try {
            $ctx = $this->master->validateContext(
                (int)$data['company_id'],
                (int)$data['branch_id'],
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            if (empty($data['name'])) return $this->fail('اسم البنك مطلوب', null, 422);

            $companyId = (int)$data['company_id'];
            $branchId  = (int)$data['branch_id'];
            $userId    = $data['user_id'] ?? Auth::id();
            $opening   = (float)($data['opening_balance'] ?? 0);

            return DB::transaction(function () use ($data, $companyId, $branchId, $userId, $opening) {

                // 1. حساب فرعي تحت 122 (البنوك)
                $accResult = $this->master->createSubAccount($companyId, $branchId, '122', $data['name'], $userId);
                if (!$accResult['success']) throw new \RuntimeException($accResult['message']);
                $accNo = $accResult['data']['acc_no'];

                // 2. البنك
                $bank = Bank::create([
                    'company_id'      => $companyId,
                    'branch_id'       => $branchId,
                    'name'            => $data['name'],
                    'code'            => $data['code']            ?? null,
                    'acc_no'          => $accNo,
                    'bank_name'       => $data['bank_name']       ?? null,
                    'account_number'  => $data['account_number']  ?? null,
                    'iban'            => $data['iban']            ?? null,
                    'swift'           => $data['swift']           ?? null,
                    'branch_name'     => $data['branch_name']     ?? null,
                    'opening_balance' => $opening,
                    'current_balance' => $opening,
                    'currency'        => $data['currency']        ?? 'SAR',
                    'is_active'       => $data['is_active']       ?? 1,
                    'notes'           => $data['notes']           ?? null,
                    'user_id'         => $userId,
                ]);

                // 3. قيد افتتاحي
                $journalId = null;
                if ($opening != 0) {
                    $journalId = $this->createOpeningJournal($bank, $companyId, $branchId, $userId, $opening);
                }

                return $this->ok('تم إنشاء البنك بنجاح', [
                    'bank'       => $bank,
                    'acc_no'     => $accNo,
                    'journal_id' => $journalId,
                ], 201);
            });
        } catch (Throwable $e) {
            Log::error('BankService@create: ' . $e->getMessage());
            return $this->fail('فشل إنشاء البنك: ' . $e->getMessage(), null, 500);
        }
    }

    private function createOpeningJournal(Bank $bank, int $companyId, int $branchId, ?int $userId, float $amount): int
    {
        $counterAcc = Account::where('company_id', $companyId)->where('acc_no', '212')->first();
        if (!$counterAcc) throw new \RuntimeException('حساب 212 غير موجود');

        $bankAcc = Account::where('company_id', $companyId)->where('acc_no', $bank->acc_no)->first();
        if (!$bankAcc) throw new \RuntimeException('حساب البنك غير موجود');

        $docType = DocumentType::where('type_code', 'OPENING_BALANCE')->first();
        if (!$docType) throw new \RuntimeException('نوع المستند OPENING_BALANCE غير موجود');

        $abs  = abs($amount);
        $desc = 'رصيد افتتاحي - ' . $bank->name;

        $lines = $amount >= 0
            ? [
                ['account_id' => $bankAcc->id,    'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $counterAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ]
            : [
                ['account_id' => $counterAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $bankAcc->id,    'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ];

        $result = $this->master->createJournalEntry([
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'journal_date'     => now()->toDateString(),
            'document_type_id' => $docType->id,
            'document_id'      => $bank->id,
            'transaction_type' => 'cash',
            'description'      => 'قيد افتتاحي - ' . $bank->name,
            'status'           => 'posted',
            'user_id'          => $userId,
        ], $lines);

        if (!$result['success']) throw new \RuntimeException($result['message']);
        return (int)$result['data']['id'];
    }

    public function update(int $id, int $companyId, array $data): array
    {
        try {
            $bank = Bank::where('id', $id)->where('company_id', $companyId)->first();
            if (!$bank) return $this->fail('البنك غير موجود', null, 404);

            $ctx = $this->master->validateContext($companyId, (int)($data['branch_id'] ?? $bank->branch_id), $data['user_id'] ?? Auth::id());
            if (!$ctx['success']) return $ctx;

            $bank->update([
                'name'           => $data['name']           ?? $bank->name,
                'code'           => $data['code']           ?? $bank->code,
                'bank_name'      => $data['bank_name']      ?? $bank->bank_name,
                'account_number' => $data['account_number'] ?? $bank->account_number,
                'iban'           => $data['iban']           ?? $bank->iban,
                'swift'          => $data['swift']          ?? $bank->swift,
                'branch_name'    => $data['branch_name']    ?? $bank->branch_name,
                'currency'       => $data['currency']       ?? $bank->currency,
                'is_active'      => $data['is_active']      ?? $bank->is_active,
                'notes'          => $data['notes']          ?? $bank->notes,
                'user_id'        => $data['user_id']        ?? Auth::id(),
            ]);

            return $this->ok('تم تعديل البنك بنجاح', $bank);
        } catch (Throwable $e) {
            Log::error('BankService@update: ' . $e->getMessage());
            return $this->fail('فشل تعديل البنك: ' . $e->getMessage(), null, 500);
        }
    }

    public function delete(int $id, int $companyId): array
    {
        try {
            $bank = Bank::where('id', $id)->where('company_id', $companyId)->first();
            if (!$bank) return $this->fail('البنك غير موجود', null, 404);

            $tables = ['receipt_vouchers','payment_vouchers','expenses','bank_transactions','sales_invoices','purchase_invoices','sales_returns','purchase_returns','sales_wages','purchase_wages'];
            foreach ($tables as $tbl) {
                if (DB::table($tbl)->where('company_id', $companyId)->where('bank_id', $id)->exists()) {
                    return $this->fail("لا يمكن حذف بنك له حركات في {$tbl}", null, 409);
                }
            }

            return DB::transaction(function () use ($bank, $companyId) {
                $acc = Account::where('company_id', $companyId)->where('acc_no', $bank->acc_no)->first();
                if ($acc && !$acc->journalEntryDetails()->exists()) $acc->delete();
                $bank->delete();
                return $this->ok('تم حذف البنك بنجاح');
            });
        } catch (Throwable $e) {
            Log::error('BankService@delete: ' . $e->getMessage());
            return $this->fail('فشل حذف البنك: ' . $e->getMessage(), null, 500);
        }
    }

    public function getById(int $id, int $companyId): array
    {
        try {
            $bank = Bank::with(['account'])->where('company_id', $companyId)->find($id);
            if (!$bank) return $this->fail('البنك غير موجود', null, 404);
            return $this->ok('تم الجلب', $bank);
        } catch (Throwable $e) {
            Log::error('BankService@getById: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    public function list(int $companyId, array $filters = []): array
    {
        try {
            $q = Bank::where('company_id', $companyId);
            if (!empty($filters['branch_id'])) {
                $q->where(function ($w) use ($filters) {
                    $w->where('branch_id', $filters['branch_id'])->orWhereNull('branch_id');
                });
            }
            if (isset($filters['is_active']) && $filters['is_active'] !== '') $q->where('is_active', $filters['is_active']);
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")->orWhere('iban', 'LIKE', "%{$s}%")->orWhere('account_number', 'LIKE', "%{$s}%");
                });
            }
            return $this->ok('تم الجلب', $q->orderBy('name')->get());
        } catch (Throwable $e) {
            Log::error('BankService@list: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }
}
