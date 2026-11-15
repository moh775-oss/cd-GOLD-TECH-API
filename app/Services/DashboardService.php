<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\PurchaseWage;
use App\Models\ReceiptVoucher;
use App\Models\PaymentVoucher;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SalesWage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardService
{
    public function getStats(int $companyId, ?int $branchId = null): array
    {
        try {
            $branchFilter = function ($q) use ($branchId) {
                if ($branchId) $q->where('branch_id', $branchId);
            };

            // ============ العدادات الأساسية ============
            $branchesCount = Branch::where('company_id', $companyId)->where('is_active', 1)->count();
            $usersCount    = User::where('company_id', $companyId)->where('is_active', 1)->count();
            $contactsCount = Contact::where('company_id', $companyId)->where('is_active', 1)->count();
            $itemsCount    = Item::where('company_id', $companyId)->where('is_active', 1)->count();

            // ============ المستندات ============
            $salesInvoices    = SalesInvoice::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $purchaseInvoices = PurchaseInvoice::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $salesReturns     = SalesReturn::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $purchaseReturns  = PurchaseReturn::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $salesWages       = SalesWage::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $purchaseWages    = PurchaseWage::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $receipts         = ReceiptVoucher::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $payments         = PaymentVoucher::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $expenses         = Expense::where('company_id', $companyId)->when($branchId, $branchFilter)->count();
            $journalEntries   = JournalEntry::where('company_id', $companyId)->when($branchId, $branchFilter)->count();

            // ============ الإجماليات المالية ============
            $totalSales = DB::table('sales_invoices')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('total_amount');

            $totalPurchases = DB::table('purchase_invoices')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('total_amount');

            $totalExpenses = DB::table('expenses')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('total_amount');

            $totalReceipts = DB::table('receipt_vouchers')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('total_amount');

            $totalPayments = DB::table('payment_vouchers')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('total_amount');

            // ============ أرصدة الصناديق والبنوك ============
            $totalCash = DB::table('safes')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('current_cash');

            $totalBankBalance = DB::table('banks')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('current_balance');

            $totalGoldWeight = DB::table('safes')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('current_gold');

            $totalContactsBalance = DB::table('contacts')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum('balance');

            // ============ الفروع (تفاصيل) ============
            $branchesDetails = DB::table('branches')
                ->where('company_id', $companyId)
                ->where('is_active', 1)
                ->select('id', 'name', 'code', 'is_main')
                ->get()
                ->map(function ($b) {
                    return [
                        'id'         => $b->id,
                        'name'       => $b->name,
                        'code'       => $b->code,
                        'is_main'    => $b->is_main,
                        'users'      => DB::table('users')->where('branch_id', $b->id)->where('is_active', 1)->count(),
                        'sales'      => DB::table('sales_invoices')->where('branch_id', $b->id)->count(),
                        'purchases'  => DB::table('purchase_invoices')->where('branch_id', $b->id)->count(),
                        'receipts'   => DB::table('receipt_vouchers')->where('branch_id', $b->id)->count(),
                        'payments'   => DB::table('payment_vouchers')->where('branch_id', $b->id)->count(),
                    ];
                });

            // ============ آخر المستندات ============
            $latestSales = DB::table('sales_invoices')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'doc_no', 'doc_date', 'total_amount']);

            $latestReceipts = DB::table('receipt_vouchers')
                ->where('company_id', $companyId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'doc_no', 'doc_date', 'total_amount']);

            // ============ المبيعات آخر 7 أيام ============
            $last7Days = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $sum = DB::table('sales_invoices')
                    ->where('company_id', $companyId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('doc_date', $date)
                    ->sum('total_amount');

                $last7Days[] = [
                    'date'  => $date,
                    'label' => now()->subDays($i)->format('d/m'),
                    'value' => (float) $sum,
                ];
            }

            // ============ المبيعات آخر 12 شهر ============
            $last12Months = [];
            for ($i = 11; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth()->toDateString();
                $end   = now()->subMonths($i)->endOfMonth()->toDateString();

                $sum = DB::table('sales_invoices')
                    ->where('company_id', $companyId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('doc_date', [$start, $end])
                    ->sum('total_amount');

                $last12Months[] = [
                    'label' => now()->subMonths($i)->format('m/Y'),
                    'value' => (float) $sum,
                ];
            }

            return [
                'success' => true,
                'message' => 'تم الجلب',
                'data'    => [
                    'counters' => [
                        'branches'        => $branchesCount,
                        'users'           => $usersCount,
                        'contacts'        => $contactsCount,
                        'items'           => $itemsCount,
                        'sales_invoices'  => $salesInvoices,
                        'purchase_invoices' => $purchaseInvoices,
                        'sales_returns'   => $salesReturns,
                        'purchase_returns'=> $purchaseReturns,
                        'sales_wages'     => $salesWages,
                        'purchase_wages'  => $purchaseWages,
                        'receipts'        => $receipts,
                        'payments'        => $payments,
                        'expenses'        => $expenses,
                        'journal_entries' => $journalEntries,
                    ],
                    'totals' => [
                        'sales'           => (float) $totalSales,
                        'purchases'       => (float) $totalPurchases,
                        'expenses'        => (float) $totalExpenses,
                        'receipts'        => (float) $totalReceipts,
                        'payments'        => (float) $totalPayments,
                        'cash'            => (float) $totalCash,
                        'bank_balance'    => (float) $totalBankBalance,
                        'gold_weight'     => (float) $totalGoldWeight,
                        'contacts_balance'=> (float) $totalContactsBalance,
                    ],
                    'branches_details' => $branchesDetails,
                    'latest_sales'     => $latestSales,
                    'latest_receipts'  => $latestReceipts,
                    'chart_7days'      => $last7Days,
                    'chart_12months'   => $last12Months,
                ],
                'code' => 200,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
                'code'    => 500,
            ];
        }
    }
}