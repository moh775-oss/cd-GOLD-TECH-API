<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DocumentType;
use App\Models\JournalEntry;
use App\Models\Karat;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ClosingService
{
    private const GOLD_CLEARING_ACC_NO = '1320001';
    private const CASH_CLEARING_ACC_NO = '1320002';

    public function __construct(
        private MasterService $master
    ) {}

    public function docTypeId(string $code, int $companyId): ?int
    {
        $docType = DocumentType::where('type_code', $code)->first();

        return $docType?->id;
    }

    public function resolveFallbackKaratId(int $companyId, $candidate = null): int
    {
        if (!empty($candidate)) {
            return (int)$candidate;
        }

        $karatId = Karat::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('id', 'asc')
            ->value('id');

        if ($karatId) {
            return (int)$karatId;
        }

        return (int)(Karat::orderBy('id', 'asc')->value('id') ?? 1);
    }

    public function normalizeClosingPayload(array $data, int $companyId): array
    {
        $type = $data['transaction_type'] ?? 'both';
        $hasGold = in_array($type, ['gold', 'both'], true);
        $hasCash = in_array($type, ['cash', 'both'], true);

        if ($hasGold) {
            $netWeight = round((float)($data['net_weight'] ?? 0), 3);
            $data['net_weight']     = $netWeight;
            $data['jewelry_weight'] = round((float)($data['jewelry_weight'] ?? 0), 3);
            $data['karat_id']       = $this->resolveFallbackKaratId($companyId, $data['karat_id'] ?? null);
            $data['gram_price']     = round((float)($data['gram_price'] ?? 0), 2);
            $data['net_amount']     = round($netWeight * $data['gram_price'], 2);
            $data['jewelry_amount'] = round((float)($data['jewelry_amount'] ?? 0), 2);
        } else {
            $data['net_weight']     = 0;
            $data['jewelry_weight'] = 0;
            $data['karat_id']       = $this->resolveFallbackKaratId($companyId, $data['karat_id'] ?? null);
            $data['gram_price']     = 0;
            $data['net_amount']     = 0;
            $data['jewelry_amount'] = 0;
        }

        $cashPart = $hasCash ? round((float)($data['total_amount'] ?? 0), 2) : 0;
        $goldPart = $hasGold ? round(($data['net_amount'] ?? 0) + ($data['jewelry_amount'] ?? 0), 2) : 0;

        // المبلغ النقدي يبقى مستقلاً ولا يُخلط بقيمة الذهب
        if ($type === 'both') {
            $data['total_amount'] = $cashPart;
        } elseif ($hasCash) {
            $data['total_amount'] = $cashPart;
        } else {
            $data['total_amount'] = $goldPart;
        }

        return $data;
    }

public function buildLines(string $kind, array $data, int $companyId): array
    {
        $contactAccNo = trim((string)($data['contact_acc_no'] ?? ''));
        if ($contactAccNo === '') {
            throw new RuntimeException('رقم حساب العميل/المورد غير متوفر');
        }

        $partyAccount = Account::where('company_id', $companyId)
            ->where('acc_no', $contactAccNo)
            ->where('is_active', 1)
            ->first();

        if (!$partyAccount) {
            throw new RuntimeException("حساب الطرف المقابل ({$contactAccNo}) غير موجود أو غير مفعل");
        }

        $goldAccount = Account::where('company_id', $companyId)
            ->where('acc_no', self::GOLD_CLEARING_ACC_NO)
            ->where('is_active', 1)
            ->first();

        $cashAccount = Account::where('company_id', $companyId)
            ->where('acc_no', self::CASH_CLEARING_ACC_NO)
            ->where('is_active', 1)
            ->first();

        $txType       = $data['transaction_type'] ?? 'both';
        $desc         = $data['notes'] ?? ($kind === 'sales' ? 'تسكير مبيعات' : 'تسكير مشتريات');
        $karatId      = $data['karat_id'] ?? null;
        $costCenterId = $data['cost_center_id'] ?? null;

        $hasGold = in_array($txType, ['gold', 'both'], true);
        $hasCash = in_array($txType, ['cash', 'both'], true);

        $weight = 0.0;
        $goldValue = 0.0;

        if ($hasGold) {
            if (!$goldAccount) {
                throw new RuntimeException('حساب متداول بيع وشراء ذهب (1320001) غير موجود أو غير مفعل');
            }

            $weight = round((float)($data['net_weight'] ?? 0), 3);
            if ($weight <= 0) {
                throw new RuntimeException('الوزن المعاير يجب أن يكون أكبر من صفر');
            }

            $netAmount  = round((float)($data['net_amount'] ?? 0), 2);
            $jewelryAmt = round((float)($data['jewelry_amount'] ?? 0), 2);
            $gramPrice  = round((float)($data['gram_price'] ?? 0), 2);
            $goldValue  = round($netAmount + $jewelryAmt, 2);

            if ($goldValue <= 0 && $gramPrice > 0) {
                $goldValue = round($weight * $gramPrice, 2);
            }
        }

        $cashAmount = 0.0;
        if ($hasCash) {
            $cashAmount = round((float)($data['total_amount'] ?? 0), 2);
            if ($txType === 'cash' && $cashAmount <= 0) {
                throw new RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
            }
        }

        $totalMoneyForCounter = round($goldValue + $cashAmount, 2);

        if ($weight <= 0 && $totalMoneyForCounter <= 0) {
            throw new RuntimeException('يجب إدخال وزن ذهب أو مبلغ لتنفيذ التسكير');
        }

        if ($totalMoneyForCounter > 0 && !$cashAccount) {
            throw new RuntimeException('حساب متداول بيع وشراء مبالغ (1320002) غير موجود أو غير مفعل');
        }

        $lines = [];

        if ($kind === 'sales') {
            // 1. العميل: دائن بالذهب / مدين بقيمة الذهب (والمبلغ إن وجد)
            $lines[] = [
                'account_id'     => $partyAccount->id,
                'cost_center_id' => $costCenterId,
                'debit_amount'   => $totalMoneyForCounter,
                'credit_amount'  => 0.0,
                'gold_debit'     => 0.0,
                'gold_credit'    => $weight,
                'karat_id'       => $weight > 0 ? $karatId : null,
                'description'    => $desc,
            ];

            // 2. حساب تداول الذهب: مدين بالذهب
            if ($weight > 0) {
                $lines[] = [
                    'account_id'     => $goldAccount->id,
                    'cost_center_id' => $costCenterId,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => 0.0,
                    'gold_debit'     => $weight,
                    'gold_credit'    => 0.0,
                    'karat_id'       => $karatId,
                    'description'    => $desc,
                ];
            }

            // 3. حساب تداول المبالغ: دائن بقيمة الذهب (والمبلغ إن وجد)
            if ($totalMoneyForCounter > 0) {
                $lines[] = [
                    'account_id'     => $cashAccount->id,
                    'cost_center_id' => $costCenterId,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => $totalMoneyForCounter,
                    'gold_debit'     => 0.0,
                    'gold_credit'    => 0.0,
                    'karat_id'       => null,
                    'description'    => $desc,
                ];
            }
        } else {
            // 1. المورد: مدين بالذهب / دائن بقيمة الذهب (والمبلغ إن وجد)
            $lines[] = [
                'account_id'     => $partyAccount->id,
                'cost_center_id' => $costCenterId,
                'debit_amount'   => 0.0,
                'credit_amount'  => $totalMoneyForCounter,
                'gold_debit'     => $weight,
                'gold_credit'    => 0.0,
                'karat_id'       => $weight > 0 ? $karatId : null,
                'description'    => $desc,
            ];

            // 2. حساب تداول الذهب: دائن بالذهب
            if ($weight > 0) {
                $lines[] = [
                    'account_id'     => $goldAccount->id,
                    'cost_center_id' => $costCenterId,
                    'debit_amount'   => 0.0,
                    'credit_amount'  => 0.0,
                    'gold_debit'     => 0.0,
                    'gold_credit'    => $weight,
                    'karat_id'       => $karatId,
                    'description'    => $desc,
                ];
            }

            // 3. حساب تداول المبالغ: مدين بقيمة الذهب (والمبلغ إن وجد)
            if ($totalMoneyForCounter > 0) {
                $lines[] = [
                    'account_id'     => $cashAccount->id,
                    'cost_center_id' => $costCenterId,
                    'debit_amount'   => $totalMoneyForCounter,
                    'credit_amount'  => 0.0,
                    'gold_debit'     => 0.0,
                    'gold_credit'    => 0.0,
                    'karat_id'       => null,
                    'description'    => $desc,
                ];
            }
        }

        return $lines;
    }

    public function createLinkedJournal(
        string $kind,
        int $companyId,
        int $branchId,
        int $docId,
        int $docTypeId,
        int $docNo,
        array $data,
        string $contactAccNo
    ): JournalEntry {
        $lines = $this->buildLines($kind, array_merge($data, [
            'contact_acc_no' => $contactAccNo,
        ]), $companyId);

        $res = $this->master->createJournalEntry([
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'doc_no'           => $docNo,
            'journal_date'     => $data['doc_date'] ?? now()->toDateString(),
            'document_type_id' => $docTypeId,
            'document_id'      => $docId,
            'currency_id'      => $data['currency_id'] ?? null,
            'exchange_rate'    => $data['exchange_rate'] ?? 1,
            'transaction_type' => $data['transaction_type'] ?? 'both',
            'description'      => $data['notes'] ?? ($kind === 'sales' ? "تسكير مبيعات رقم {$docNo}" : "تسكير مشتريات رقم {$docNo}"),
            'user_id'          => $data['user_id'] ?? Auth::id(),
        ], $lines);

        if (!$res['success']) {
            throw new RuntimeException($res['message'] ?? 'فشل إنشاء القيد المحاسبي');
        }

        return JournalEntry::where('company_id', $companyId)->findOrFail($res['data']['id']);
    }
}
