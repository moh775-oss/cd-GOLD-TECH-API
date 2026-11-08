<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseStock extends Model
{
    protected $table = 'warehouse_stock';
    protected $guarded = [];
    protected $casts = ['quantity' => 'decimal:3', 'weight' => 'decimal:3', 'avg_cost' => 'decimal:4', 'last_update' => 'datetime'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function warehouse() { return $this->belongsTo(Store::class, 'warehouse_id'); }
    public function item() { return $this->belongsTo(Item::class, 'item_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
