<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Bank extends Model
{
    protected $table = 'banks';
    protected $guarded = [];
    protected $casts = ['opening_balance' => 'decimal:2', 'current_balance' => 'decimal:2', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function account() { return $this->belongsTo(Account::class, 'acc_no', 'acc_no'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class, 'bank_id'); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class, 'bank_id'); }
    public function salesReturns() { return $this->hasMany(SalesReturn::class, 'bank_id'); }
    public function purchaseReturns() { return $this->hasMany(PurchaseReturn::class, 'bank_id'); }
    public function salesWages() { return $this->hasMany(SalesWage::class, 'bank_id'); }
    public function purchaseWages() { return $this->hasMany(PurchaseWage::class, 'bank_id'); }
    public function receiptVouchers() { return $this->hasMany(ReceiptVoucher::class, 'bank_id'); }
    public function paymentVouchers() { return $this->hasMany(PaymentVoucher::class, 'bank_id'); }
    public function expenses() { return $this->hasMany(Expense::class, 'bank_id'); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class, 'bank_id'); }
}
