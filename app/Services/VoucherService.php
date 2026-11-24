<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\DocumentType;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function __construct(private MasterService $master) {}

    public function docTypeId(string $typeCode, int $companyId): ?int
    {
        return DocumentType::where('type_code', $typeCode)
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->orderByRaw('company_id IS NULL')
            ->value('id');
    }

    public function buildVoucherLines(string $typeCode, array $voucher): array
    {
        $isReceipt = $typeCode === 'RECEIPT_VOUCHER';
        if (!$isReceipt && $typeCode !== 'PAYMENT_VOUCHER') {
            throw new \RuntimeException('نوع السند غير صحيح');
        }

        $counterAccNo = $voucher['counter_acc_no'] ?? null;
        if (!$counterAccNo) throw new \RuntimeException('حساب الطرف المقابل مطلوب');

        $cash = round((float)($voucher['cash_amount'] ?? 0), 2);
        $goldAmount = round((float)($voucher['gold_amount'] ?? 0), 2);
        $goldWeight = round((float)($voucher['gold_weight'] ?? 0), 3);
        $karatId = $voucher['karat_id'] ?? null;
        $costCenterId = $voucher['cost_center_id'] ?? null;
        $total = round($cash + $goldAmount, 2);

        $treasuryAccNo = $voucher['safe_acc_no'] ?? $voucher['bank_acc_no'] ?? null;
        $storeAccNo = $voucher['store_acc_no'] ?? null;

        if ($cash > 0 && !$treasuryAccNo) {
            throw new \RuntimeException('حساب الصندوق أو البنك مطلوب');
        }

        if ($goldWeight > 0 && !$storeAccNo) {
            throw new \RuntimeException('الحساب المحاسبي للمخزن غير مربوط');
        }

        if ($goldWeight > 0 && !$karatId) {
            throw new \RuntimeException('عيار الذهب مطلوب');
        }

        if ($cash <= 0 && $goldWeight <= 0) {
            throw new \RuntimeException('يجب إدخال مبلغ نقدي أو وزن ذهب');
        }

        $lines = [];

        if ($cash > 0) {
            $lines[] = $this->line(
                $treasuryAccNo,
                $isReceipt ? $cash : 0,
                $isReceipt ? 0 : $cash,
                0,
                0,
                null,
                $costCenterId,
                'الصندوق/البنك'
            );
        }

        if ($goldWeight > 0) {
            $lines[] = $this->line(
                $storeAccNo,
                $isReceipt ? $goldAmount : 0,
                $isReceipt ? 0 : $goldAmount,
                $isReceipt ? $goldWeight : 0,
                $isReceipt ? 0 : $goldWeight,
                $karatId,
                $costCenterId,
                'المخزون الذهبي'
            );
        }

        $lines[] = $this->line(
            $counterAccNo,
            $isReceipt ? 0 : $total,
            $isReceipt ? $total : 0,
            $isReceipt ? 0 : $goldWeight,
            $isReceipt ? $goldWeight : 0,
            $goldWeight > 0 ? $karatId : null,
            $costCenterId,
            'الطرف المقابل'
        );

        $debit = round(collect($lines)->sum('debit_amount'), 2);
        $credit = round(collect($lines)->sum('credit_amount'), 2);

        if ($debit !== $credit) {
            throw new \RuntimeException("قيد السند غير متوازن: المدين {$debit} والدائن {$credit}");
        }

        $goldDebit = round(collect($lines)->sum('gold_debit'), 3);
        $goldCredit = round(collect($lines)->sum('gold_credit'), 3);

        if ($goldDebit !== $goldCredit) {
            throw new \RuntimeException('وزن الذهب في القيد غير متوازن');
        }

        return $lines;
    }

    private function line(
        string $accNo,
        float $debit = 0,
        float $credit = 0,
        float $goldDebit = 0,
        float $goldCredit = 0,
        ?int $karatId = null,
        ?int $costCenterId = null,
        ?string $desc = null
    ): array {
        return [
            'account_acc_no' => $accNo,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'gold_debit' => $goldDebit,
            'gold_credit' => $goldCredit,
            'karat_id' => $karatId,
            'cost_center_id' => $costCenterId,
            'description' => $desc,
        ];
    }

   public function resolveLines(string $typeCode, int $companyId, array $voucher): array
    {
        $branchId = (int)($voucher['branch_id'] ?? 1);
        $userId   = (int)($voucher['user_id'] ?? auth()->id() ?? 1);

        return array_map(function ($line) use ($companyId, $branchId, $userId) {
            $accountId = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('acc_no', $line['account_acc_no'])
                ->where('is_active', 1)
                ->value('id');

            if (!$accountId) {
                throw new \RuntimeException('الحساب المحاسبي غير موجود أو غير مفعل: '.$line['account_acc_no']);
            }

            unset($line['account_acc_no']);

            return array_merge($line, [
                'company_id' => $companyId,
                'branch_id'  => $branchId,
                'account_id' => $accountId,
                'user_id'    => $userId,
            ]);
        }, $this->buildVoucherLines($typeCode, $voucher));
    }

    public function createLinkedJournal(
        string $typeCode,
        int $companyId,
        int $branchId,
        int $documentId,
        int $documentTypeId,
        array $voucher
    ): JournalEntry {
        $lines = $this->resolveLines($typeCode, $companyId, $voucher);

        $res = $this->master->createJournalEntry([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'journal_date' => $voucher['doc_date'],
            'document_type_id' => $documentTypeId,
            'document_id' => $documentId,
            'currency_id' => $voucher['currency_id'] ?? null,
            'exchange_rate' => $voucher['exchange_rate'] ?? 1,
            'transaction_type' => $voucher['transaction_type'] ?? 'cash',
            'description' => $voucher['description'] ?? null,
            'status' => 'posted',
            'user_id' => $voucher['user_id'] ?? null,
        ], $lines);

        if (!$res['success']) {
            throw new \RuntimeException($res['message'] ?? 'فشل إنشاء القيد');
        }

        $journal = JournalEntry::with('details')->where('company_id', $companyId)
            ->find($res['data']['id']);

        if (!$journal) throw new \RuntimeException('تعذر استرجاع القيد المحاسبي');

        return $journal;
    }
}
