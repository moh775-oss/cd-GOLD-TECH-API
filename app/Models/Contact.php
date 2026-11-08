<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model
{
    protected $table = 'contacts';
    protected $guarded = [];
    protected $casts = ['credit_limit' => 'decimal:2', 'opening_balance' => 'decimal:2', 'balance' => 'decimal:2', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function account() { return $this->belongsTo(Account::class, 'acc_no', 'acc_no'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class, 'contact_id'); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class, 'contact_id'); }
    public function salesReturns() { return $this->hasMany(SalesReturn::class, 'contact_id'); }
    public function purchaseReturns() { return $this->hasMany(PurchaseReturn::class, 'contact_id'); }
    public function salesClosings() { return $this->hasMany(SalesClosing::class, 'contact_id'); }
    public function purchaseClosings() { return $this->hasMany(PurchaseClosing::class, 'contact_id'); }
    public function salesWages() { return $this->hasMany(SalesWage::class, 'contact_id'); }
    public function purchaseWages() { return $this->hasMany(PurchaseWage::class, 'contact_id'); }
    public function receiptVouchers() { return $this->hasMany(ReceiptVoucher::class, 'contact_id'); }
    public function paymentVouchers() { return $this->hasMany(PaymentVoucher::class, 'contact_id'); }
}
