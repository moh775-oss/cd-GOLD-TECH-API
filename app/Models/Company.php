<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Company extends Model
{
    protected $table = 'companies';
    protected $guarded = [];
    protected $casts = [
        'subscription_start' => 'date',
        'subscription_end' => 'date',
        'trial_end' => 'date',
        'last_login_at' => 'datetime',
        'subscription_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    public function subscriptions() { return $this->hasMany(CompanySubscription::class, 'company_id'); }
    public function reminders() { return $this->hasMany(SubscriptionReminder::class, 'company_id'); }
    public function branches() { return $this->hasMany(Branch::class, 'company_id'); }
    public function users() { return $this->hasMany(User::class, 'company_id'); }
    public function companyInfo() { return $this->hasOne(CompanyInfo::class, 'company_id'); }
    public function accounts() { return $this->hasMany(Account::class, 'company_id'); }
    public function documentTypes() { return $this->hasMany(DocumentType::class, 'company_id'); }
    public function contacts() { return $this->hasMany(Contact::class, 'company_id'); }
    public function karats() { return $this->hasMany(Karat::class, 'company_id'); }
    public function items() { return $this->hasMany(Item::class, 'company_id'); }
    public function safes() { return $this->hasMany(Safe::class, 'company_id'); }
    public function banks() { return $this->hasMany(Bank::class, 'company_id'); }
    public function documentSequences() { return $this->hasMany(DocumentSequence::class, 'company_id'); }
    public function salesClosings() { return $this->hasMany(SalesClosing::class, 'company_id'); }
    public function purchaseClosings() { return $this->hasMany(PurchaseClosing::class, 'company_id'); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class, 'company_id'); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class, 'company_id'); }
    public function salesReturns() { return $this->hasMany(SalesReturn::class, 'company_id'); }
    public function purchaseReturns() { return $this->hasMany(PurchaseReturn::class, 'company_id'); }
    public function salesWages() { return $this->hasMany(SalesWage::class, 'company_id'); }
    public function purchaseWages() { return $this->hasMany(PurchaseWage::class, 'company_id'); }
    public function receiptVouchers() { return $this->hasMany(ReceiptVoucher::class, 'company_id'); }
    public function paymentVouchers() { return $this->hasMany(PaymentVoucher::class, 'company_id'); }
    public function journalEntries() { return $this->hasMany(JournalEntry::class, 'company_id'); }
    public function taxes() { return $this->hasMany(Tax::class, 'company_id'); }
    public function currencies() { return $this->hasMany(Currency::class, 'company_id'); }
    public function exchangeRates() { return $this->hasMany(ExchangeRate::class, 'company_id'); }
    public function itemCategories() { return $this->hasMany(ItemCategory::class, 'company_id'); }
    public function stores() { return $this->hasMany(Store::class, 'company_id'); }
    public function warehouseStocks() { return $this->hasMany(WarehouseStock::class, 'company_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'company_id'); }
    public function expenseCategories() { return $this->hasMany(ExpenseCategory::class, 'company_id'); }
    public function expenses() { return $this->hasMany(Expense::class, 'company_id'); }
    public function cashTransactions() { return $this->hasMany(CashTransaction::class, 'company_id'); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class, 'company_id'); }
    public function openingBalances() { return $this->hasMany(OpeningBalance::class, 'company_id'); }
    public function fiscalYears() { return $this->hasMany(FiscalYear::class, 'company_id'); }
    public function accountMappings() { return $this->hasMany(AccountMapping::class, 'company_id'); }
    public function roles() { return $this->hasMany(Role::class, 'company_id'); }
    public function permissions() { return $this->hasMany(Permission::class, 'company_id'); }
    public function auditLogs() { return $this->hasMany(AuditLog::class, 'company_id'); }
    public function attachments() { return $this->hasMany(Attachment::class, 'company_id'); }
    public function notifications() { return $this->hasMany(Notification::class, 'company_id'); }
    public function goldPrices() { return $this->hasMany(GoldPrice::class, 'company_id'); }
    public function goldRateHistories() { return $this->hasMany(GoldRateHistory::class, 'company_id'); }
    public function makingCharges() { return $this->hasMany(MakingCharge::class, 'company_id'); }
    public function goldFixings() { return $this->hasMany(GoldFixing::class, 'company_id'); }
}
