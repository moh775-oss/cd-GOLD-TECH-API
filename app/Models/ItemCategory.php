<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ItemCategory extends Model
{
    protected $table = 'item_categories';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function parent() { return $this->belongsTo(ItemCategory::class, 'parent_id'); }
    public function children() { return $this->hasMany(ItemCategory::class, 'parent_id'); }
    public function items() { return $this->hasMany(Item::class, 'category_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
