<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesInvoiceDetail extends Model
{
    protected $table = 'sales_invoice_details';
    protected $guarded = [];
    protected $casts = [
        'weight' => 'decimal:3',
        'gram_price' => 'decimal:4',
        'making_charge' => 'decimal:4',
        'quantity' => 'decimal:3',
        'tax_rate' => 'decimal:4',
        'price_before_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'price_after_discount' => 'decimal:2',
        'price_with_tax' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'price_with_exchange' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'invoice_id'); }
    public function item() { return $this->belongsTo(Item::class, 'item_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function tax() { return $this->belongsTo(Tax::class, 'tax_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
