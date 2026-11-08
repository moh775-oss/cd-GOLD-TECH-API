<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GoldRateHistory extends Model
{
    protected $table = 'gold_rate_history';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['old_price' => 'decimal:4', 'new_price' => 'decimal:4', 'changed_at' => 'datetime'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
