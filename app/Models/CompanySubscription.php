<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompanySubscription extends Model
{
    protected $table = 'company_subscriptions';
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'paid_at' => 'datetime', 'amount' => 'decimal:2'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
