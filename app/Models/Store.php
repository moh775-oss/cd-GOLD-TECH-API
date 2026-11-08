<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Store extends Model
{
    protected $table = 'store';
    protected $guarded = [];
    protected $casts = ['is_main' => 'boolean', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function items() { return $this->hasMany(Item::class, 'store_id'); }
    public function warehouseStocks() { return $this->hasMany(WarehouseStock::class, 'warehouse_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'warehouse_id'); }
}
