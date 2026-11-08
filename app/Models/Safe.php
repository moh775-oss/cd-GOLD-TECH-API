<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Safe extends Model
{
    protected $table = 'safes';
    protected $guarded = [];
    protected $casts = ['opening_cash' => 'decimal:2', 'current_cash' => 'decimal:2', 'opening_gold' => 'decimal:3', 'current_gold' => 'decimal:3', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function account() { return $this->belongsTo(Account::class, 'acc_no', 'acc_no'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class, 'safe_id'); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class, 'safe_id'); }
    public function salesReturns() { return $this->hasMany(SalesReturn::class, 'safe_id'); }
    public function purchaseReturns() { return $this->hasMany(PurchaseReturn::class, 'safe_id'); }
    public function salesWages() { return $this->hasMany(SalesWage::class, 'safe_id'); }
    public function purchaseWages() { return $this->hasMany(PurchaseWage::class, 'safe_id'); }
    public function receiptVouchers() { return $this->hasMany(ReceiptVoucher::class, 'safe_id'); }
    public function paymentVouchers() { return $this->hasMany(PaymentVoucher::class, 'safe_id'); }
    public function expenses() { return $this->hasMany(Expense::class, 'safe_id'); }
    public function cashTransactions() { return $this->hasMany(CashTransaction::class, 'safe_id'); }
}
