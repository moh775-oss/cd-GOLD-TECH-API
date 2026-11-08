<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReceiptVoucher extends Model
{
    protected $table = 'receipt_vouchers';
    protected $guarded = [];
    protected $casts = [
        'doc_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'price_with_exchange' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'price_before_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'price_after_discount' => 'decimal:2',
        'price_with_tax' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'gold_weight' => 'decimal:3',
        'gram_price' => 'decimal:4',
        'gold_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function contact() { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function safe() { return $this->belongsTo(Safe::class, 'safe_id'); }
    public function bank() { return $this->belongsTo(Bank::class, 'bank_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function tax() { return $this->belongsTo(Tax::class, 'tax_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
