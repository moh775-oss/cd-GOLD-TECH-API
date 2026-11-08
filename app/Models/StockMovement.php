<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockMovement extends Model
{
    protected $table = 'stock_movements';
    protected $guarded = [];
    protected $casts = ['movement_date' => 'date', 'quantity' => 'decimal:3', 'weight' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function warehouse() { return $this->belongsTo(Store::class, 'warehouse_id'); }
    public function item() { return $this->belongsTo(Item::class, 'item_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function documentType() { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
