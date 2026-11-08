<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';
    protected $guarded = [];
    protected $casts = ['rate_date' => 'date', 'rate' => 'decimal:6'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
