<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Tax extends Model
{
    protected $table = 'taxes';
    protected $guarded = [];
    protected $casts = ['rate' => 'decimal:4', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function items() { return $this->hasMany(Item::class, 'tax_id'); }
}
