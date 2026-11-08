<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Currency extends Model
{
    protected $table = 'currencies';
    protected $guarded = [];
    protected $casts = ['is_base' => 'boolean', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function exchangeRates() { return $this->hasMany(ExchangeRate::class, 'currency_id'); }
    public function items() { return $this->hasMany(Item::class, 'currency_id'); }
}
