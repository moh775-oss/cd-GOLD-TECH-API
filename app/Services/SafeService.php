<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DocumentType;
use App\Models\Safe;
use App\Services\Concerns\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SafeService
{
    use ApiResponse;

    public function __construct(private MasterService $master) {}

    /**
     * إنشاء صندوق + حساب فرعي + قيد افتتاحي (إن وُجد رصيد)
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

            if (empty($data['name'])) return $this->fail('اسم الصندوق مطلوب', null, 422);

            $companyId = (int)$data['company_id'];
            $branchId  = (int)$data['branch_id'];
            $userId    = $data['user_id'] ?? Auth::id();
            $openCash  = (float)($data['opening_cash'] ?? 0);

            return DB::transaction(function () use ($data, $companyId, $branchId, $userId, $openCash) {

                // 1. حساب فرعي تحت 121 (الصناديق)
                $accResult = $this->master->createSubAccount($companyId, $branchId, '121', $data['name'], $userId);
                if (!$accResult['success']) throw new \RuntimeException($accResult['message']);
                $accNo = $accResult['data']['acc_no'];

                // 2. الصندوق
                $safe = Safe::create([
                    'company_id'     => $companyId,
                    'branch_id'      => $branchId,
                    'name'           => $data['name'],
                    'code'           => $data['code']         ?? null,
                    'acc_no'         => $accNo,
                    'opening_cash'   => $openCash,
                    'current_cash'   => $openCash,
                    'opening_gold'   => $data['opening_gold'] ?? 0,
                    'current_gold'   => $data['opening_gold'] ?? 0,
                    'currency'       => $data['currency']     ?? 'SAR',
                    'is_active'      => $data['is_active']    ?? 1,
                    'notes'          => $data['notes']        ?? null,
                    'user_id'        => $userId,
                ]);

                // 3. قيد افتتاحي
                $journalId = null;
                if ($openCash != 0) {
                    $journalId = $this->createOpeningJournal($safe, $companyId, $branchId, $userId, $openCash);
                }

                return $this->ok('تم إنشاء الصندوق بنجاح', [
                    'safe'       => $safe,
                    'acc_no'     => $accNo,
                    'journal_id' => $journalId,
                ], 201);
            });
        } catch (Throwable $e) {
            Log::error('SafeService@create: ' . $e->getMessage());
            return $this->fail('فشل إنشاء الصندوق: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * قيد افتتاحي للصندوق: الصندوق مدين / 212 دائن
     */
    private function createOpeningJournal(Safe $safe, int $companyId, int $branchId, ?int $userId, float $amount): int
    {
        $counterAcc = Account::where('company_id', $companyId)->where('acc_no', '212')->first();
        if (!$counterAcc) throw new \RuntimeException('حساب 212 غير موجود');

        $safeAcc = Account::where('company_id', $companyId)->where('acc_no', $safe->acc_no)->first();
        if (!$safeAcc) throw new \RuntimeException('حساب الصندوق غير موجود');

        $docType = DocumentType::where('type_code', 'OPENING_BALANCE')->first();
        if (!$docType) throw new \RuntimeException('نوع المستند OPENING_BALANCE غير موجود');

        $abs  = abs($amount);
        $desc = 'رصيد افتتاحي - ' . $safe->name;

        $lines = $amount >= 0
            ? [
                ['account_id' => $safeAcc->id,    'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $counterAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ]
            : [
                ['account_id' => $counterAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $safeAcc->id,    'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ];

        $result = $this->master->createJournalEntry([
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'journal_date'     => now()->toDateString(),
            'document_type_id' => $docType->id,
            'document_id'      => $safe->id,
            'transaction_type' => 'cash',
            'description'      => 'قيد افتتاحي - ' . $safe->name,
            'status'           => 'posted',
            'user_id'          => $userId,
        ], $lines);

        if (!$result['success']) throw new \RuntimeException($result['message']);
        return (int)$result['data']['id'];
    }

    /**
     * تعديل صندوق
     */
    public function update(int $id, int $companyId, array $data): array
    {
        try {
            $safe = Safe::where('id', $id)->where('company_id', $companyId)->first();
            if (!$safe) return $this->fail('الصندوق غير موجود', null, 404);

            $ctx = $this->master->validateContext(
                $companyId,
                (int)($data['branch_id'] ?? $safe->branch_id),
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            $safe->update([
                'name'         => $data['name']         ?? $safe->name,
                'code'         => $data['code']         ?? $safe->code,
                'currency'     => $data['currency']     ?? $safe->currency,
                'is_active'    => $data['is_active']    ?? $safe->is_active,
                'notes'        => $data['notes']        ?? $safe->notes,
                'user_id'      => $data['user_id']      ?? Auth::id(),
            ]);

            return $this->ok('تم تعديل الصندوق بنجاح', $safe);
        } catch (Throwable $e) {
            Log::error('SafeService@update: ' . $e->getMessage());
            return $this->fail('فشل تعديل الصندوق: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * حذف صندوق
     */
    public function delete(int $id, int $companyId): array
    {
        try {
            $safe = Safe::where('id', $id)->where('company_id', $companyId)->first();
            if (!$safe) return $this->fail('الصندوق غير موجود', null, 404);

            // منع الحذف إن كان له حركات
            $tables = ['receipt_vouchers','payment_vouchers','expenses','cash_transactions','sales_invoices','purchase_invoices','sales_returns','purchase_returns','sales_wages','purchase_wages'];
            foreach ($tables as $tbl) {
                if (DB::table($tbl)->where('company_id', $companyId)->where('safe_id', $id)->exists()) {
                    return $this->fail("لا يمكن حذف صندوق له حركات في {$tbl}", null, 409);
                }
            }

            return DB::transaction(function () use ($safe, $companyId) {
                $acc = Account::where('company_id', $companyId)->where('acc_no', $safe->acc_no)->first();
                if ($acc && !$acc->journalEntryDetails()->exists()) $acc->delete();
                $safe->delete();
                return $this->ok('تم حذف الصندوق بنجاح');
            });
        } catch (Throwable $e) {
            Log::error('SafeService@delete: ' . $e->getMessage());
            return $this->fail('فشل حذف الصندوق: ' . $e->getMessage(), null, 500);
        }
    }

    public function getById(int $id, int $companyId): array
    {
        try {
            $safe = Safe::with(['account'])->where('company_id', $companyId)->find($id);
            if (!$safe) return $this->fail('الصندوق غير موجود', null, 404);
            return $this->ok('تم الجلب', $safe);
        } catch (Throwable $e) {
            Log::error('SafeService@getById: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    public function list(int $companyId, array $filters = []): array
    {
        try {
            $q = Safe::where('company_id', $companyId);
            if (!empty($filters['branch_id'])) {
                $q->where(function ($w) use ($filters) {
                    $w->where('branch_id', $filters['branch_id'])->orWhereNull('branch_id');
                });
            }
            if (isset($filters['is_active']) && $filters['is_active'] !== '') $q->where('is_active', $filters['is_active']);
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")->orWhere('code', 'LIKE', "%{$s}%");
                });
            }
            return $this->ok('تم الجلب', $q->orderBy('name')->get());
        } catch (Throwable $e) {
            Log::error('SafeService@list: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }
}
