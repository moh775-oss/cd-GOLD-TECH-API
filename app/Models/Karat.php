<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Karat extends Model
{
    protected $table = 'karats';
    protected $guarded = [];
    protected $casts = ['the_value' => 'decimal:6', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function items() { return $this->hasMany(Item::class, 'karat_id'); }
    public function goldPrices() { return $this->hasMany(GoldPrice::class, 'karat_id'); }
    public function goldRateHistories() { return $this->hasMany(GoldRateHistory::class, 'karat_id'); }
    public function makingCharges() { return $this->hasMany(MakingCharge::class, 'karat_id'); }
    public function goldFixings() { return $this->hasMany(GoldFixing::class, 'karat_id'); }
    public function salesInvoiceDetails() { return $this->hasMany(SalesInvoiceDetail::class, 'karat_id'); }
    public function purchaseInvoiceDetails() { return $this->hasMany(PurchaseInvoiceDetail::class, 'karat_id'); }
    public function salesReturnDetails() { return $this->hasMany(SalesReturnDetail::class, 'karat_id'); }
    public function purchaseReturnDetails() { return $this->hasMany(PurchaseReturnDetail::class, 'karat_id'); }
    public function salesWageDetails() { return $this->hasMany(SalesWageDetail::class, 'karat_id'); }
    public function purchaseWageDetails() { return $this->hasMany(PurchaseWageDetail::class, 'karat_id'); }
    public function warehouseStocks() { return $this->hasMany(WarehouseStock::class, 'karat_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'karat_id'); }
    public function openingBalances() { return $this->hasMany(OpeningBalance::class, 'karat_id'); }
    public function journalEntryDetails() { return $this->hasMany(JournalEntryDetail::class, 'karat_id'); }
}
