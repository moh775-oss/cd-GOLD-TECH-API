<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class IntegrityService
{
    /**
     * فحص العلاقات — لأي موديل
     * $relations = ['methodName' => 'الاسم العربي']
     */
    public function checkRelations($model, array $relations): ?string
    {
        foreach ($relations as $relation => $label) {
            if (method_exists($model, $relation) && $model->$relation()->exists()) {
                return "لا يمكن الحذف — مرتبط بـ {$label}";
            }
        }
        return null;
    }

    /**
     * فحص شامل لطرف (عميل/مورد)
     */
    public function checkContact(Contact $contact): ?string
    {
        $companyId = $contact->company_id;

        // 1. القيود الافتتاحية (document_id = contact_id)
        $hasOpening = JournalEntry::where('company_id', $companyId)
            ->whereHas('documentType', fn($q) => $q->where('type_code', 'OPENING_BALANCE'))
            ->where('document_id', $contact->id)
            ->exists();

        if ($hasOpening) {
            return 'لا يمكن حذف طرف له قيد افتتاحي';
        }

        // 2. أي قيد على حساب الطرف (عن طريق acc_no)
        $acc = Account::where('company_id', $companyId)
            ->where('acc_no', $contact->acc_no)
            ->first();

        if ($acc) {
            $hasJournals = JournalEntry::where('company_id', $companyId)
                ->whereHas('details', fn($q) => $q->where('account_id', $acc->id))
                ->exists();

            if ($hasJournals) {
                return 'لا يمكن حذف طرف له قيود محاسبية';
            }
        }

        // 3. العلاقات (فواتير/سندات/إلخ)
        return $this->checkRelations($contact, [
            'salesInvoices'    => 'فواتير مبيعات',
            'purchaseInvoices' => 'فواتير مشتريات',
            'salesReturns'     => 'مرتجعات مبيعات',
            'purchaseReturns'  => 'مرتجعات مشتريات',
            'salesClosings'    => 'تقفيلات مبيعات',
            'purchaseClosings' => 'تقفيلات مشتريات',
            'salesWages'       => 'أجور مبيعات',
            'purchaseWages'    => 'أجور مشتريات',
            'receiptVouchers'  => 'سندات قبض',
            'paymentVouchers'  => 'سندات صرف',
        ]);
    }

    /**
     * فحص شامل لحساب
     */
    public function checkAccount(Account $account): ?string
    {
        $companyId = $account->company_id;

        if ($account->is_system == 1) {
            return 'لا يمكن حذف حساب نظامي';
        }

        $hasChildren = Account::where('company_id', $companyId)
            ->where('father_acc_no', $account->acc_no)
            ->exists();

        if ($hasChildren) {
            return 'لا يمكن حذف حساب له حسابات فرعية';
        }

        $hasJournals = JournalEntry::where('company_id', $companyId)
            ->whereHas('details', fn($q) => $q->where('account_id', $account->id))
            ->exists();

        if ($hasJournals) {
            return 'لا يمكن حذف حساب مستخدم في قيود محاسبية';
        }

        $checks = [
            ['table' => 'contacts',           'col' => 'acc_no', 'msg' => 'مرتبط بعملاء/موردين'],
            ['table' => 'safes',              'col' => 'acc_no', 'msg' => 'مرتبط بصناديق'],
            ['table' => 'banks',              'col' => 'acc_no', 'msg' => 'مرتبط ببنوك'],
            ['table' => 'expense_categories', 'col' => 'acc_no', 'msg' => 'مرتبط بتصنيفات مصروفات'],
            ['table' => 'account_mappings',   'col' => 'acc_no', 'msg' => 'مرتبط بربط الحسابات الافتراضية'],
        ];

        foreach ($checks as $c) {
            if (DB::table($c['table'])->where('company_id', $companyId)->where($c['col'], $account->acc_no)->exists()) {
                return "لا يمكن حذف حساب {$c['msg']}";
            }
        }

        return null;
    }

    /**
     * فحص عام متعدد — لأي جداول
     */
    public function checkMultiple(array $checks, int $companyId): ?string
    {
        foreach ($checks as $check) {
            $table  = $check['table'];
            $column = $check['column'];
            $value  = $check['value'];
            $label  = $check['label'] ?? $table;

            if (DB::table($table)->where('company_id', $companyId)->where($column, $value)->exists()) {
                return 'لا يمكن الحذف — مرتبط بـ ' . $label;
            }
        }

        return null;
    }
}
