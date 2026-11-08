<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GoldFixing extends Model
{
    protected $table = 'gold_fixings';
    protected $guarded = [];
    protected $casts = ['fixing_date' => 'date', 'price' => 'decimal:4'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
