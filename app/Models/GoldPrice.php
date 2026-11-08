<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GoldPrice extends Model
{
    protected $table = 'gold_prices';
    protected $guarded = [];
    protected $casts = ['price_date' => 'date', 'buy_price' => 'decimal:4', 'sell_price' => 'decimal:4', 'market_price' => 'decimal:4'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
