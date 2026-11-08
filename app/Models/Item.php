<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Item extends Model
{
    protected $table = 'items';
    protected $guarded = [];
    protected $casts = [
        'weight' => 'decimal:3',
        'purity' => 'decimal:6',
        'stone_weight' => 'decimal:3',
        'cost_price' => 'decimal:4',
        'purchase_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'min_sale_price' => 'decimal:4',
        'wholesale_price' => 'decimal:4',
        'making_charge' => 'decimal:4',
        'profit_margin' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'price_with_exchange' => 'decimal:2',
        'min_qty' => 'decimal:3',
        'max_qty' => 'decimal:3',
        'reorder_qty' => 'decimal:3',
        'current_qty' => 'decimal:3',
        'opening_qty' => 'decimal:3',
        'opening_weight' => 'decimal:3',
        'is_active' => 'boolean',
        'is_sellable' => 'boolean',
        'is_purchasable' => 'boolean',
        'is_trackable' => 'boolean',
        'is_featured' => 'boolean',
    ];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function category() { return $this->belongsTo(ItemCategory::class, 'category_id'); }
    public function store() { return $this->belongsTo(Store::class, 'store_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function tax() { return $this->belongsTo(Tax::class, 'tax_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function warehouseStocks() { return $this->hasMany(WarehouseStock::class, 'item_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'item_id'); }
    public function salesInvoiceDetails() { return $this->hasMany(SalesInvoiceDetail::class, 'item_id'); }
    public function purchaseInvoiceDetails() { return $this->hasMany(PurchaseInvoiceDetail::class, 'item_id'); }
    public function salesReturnDetails() { return $this->hasMany(SalesReturnDetail::class, 'item_id'); }
    public function purchaseReturnDetails() { return $this->hasMany(PurchaseReturnDetail::class, 'item_id'); }
    public function salesWageDetails() { return $this->hasMany(SalesWageDetail::class, 'item_id'); }
    public function purchaseWageDetails() { return $this->hasMany(PurchaseWageDetail::class, 'item_id'); }
    public function makingCharges() { return $this->hasMany(MakingCharge::class, 'item_id'); }
}
