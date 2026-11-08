<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Branch extends Model
{
    protected $table = 'branches';
    protected $guarded = [];
    protected $casts = ['is_main' => 'boolean', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function users() { return $this->hasMany(User::class, 'branch_id'); }
    public function userBranches() { return $this->hasMany(UserBranch::class, 'branch_id'); }
    public function companyInfo() { return $this->hasOne(CompanyInfo::class, 'branch_id'); }
    public function accounts() { return $this->hasMany(Account::class, 'branch_id'); }
    public function contacts() { return $this->hasMany(Contact::class, 'branch_id'); }
    public function karats() { return $this->hasMany(Karat::class, 'branch_id'); }
    public function items() { return $this->hasMany(Item::class, 'branch_id'); }
    public function safes() { return $this->hasMany(Safe::class, 'branch_id'); }
    public function banks() { return $this->hasMany(Bank::class, 'branch_id'); }
    public function documentSequences() { return $this->hasMany(DocumentSequence::class, 'branch_id'); }
    public function salesClosings() { return $this->hasMany(SalesClosing::class, 'branch_id'); }
    public function purchaseClosings() { return $this->hasMany(PurchaseClosing::class, 'branch_id'); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class, 'branch_id'); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class, 'branch_id'); }
    public function salesReturns() { return $this->hasMany(SalesReturn::class, 'branch_id'); }
    public function purchaseReturns() { return $this->hasMany(PurchaseReturn::class, 'branch_id'); }
    public function salesWages() { return $this->hasMany(SalesWage::class, 'branch_id'); }
    public function purchaseWages() { return $this->hasMany(PurchaseWage::class, 'branch_id'); }
    public function receiptVouchers() { return $this->hasMany(ReceiptVoucher::class, 'branch_id'); }
    public function paymentVouchers() { return $this->hasMany(PaymentVoucher::class, 'branch_id'); }
    public function journalEntries() { return $this->hasMany(JournalEntry::class, 'branch_id'); }
    public function stores() { return $this->hasMany(Store::class, 'branch_id'); }
    public function warehouseStocks() { return $this->hasMany(WarehouseStock::class, 'branch_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'branch_id'); }
    public function expenses() { return $this->hasMany(Expense::class, 'branch_id'); }
    public function cashTransactions() { return $this->hasMany(CashTransaction::class, 'branch_id'); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class, 'branch_id'); }
    public function openingBalances() { return $this->hasMany(OpeningBalance::class, 'branch_id'); }
    public function attachments() { return $this->hasMany(Attachment::class, 'branch_id'); }
    public function notifications() { return $this->hasMany(Notification::class, 'branch_id'); }
    public function goldPrices() { return $this->hasMany(GoldPrice::class, 'branch_id'); }
    public function settings() { return $this->hasMany(Setting::class, 'branch_id'); }
    public function auditLogs() { return $this->hasMany(AuditLog::class, 'branch_id'); }
}
