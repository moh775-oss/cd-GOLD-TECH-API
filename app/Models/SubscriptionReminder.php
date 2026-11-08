<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionReminder extends Model
{
    protected $table = 'subscription_reminders';
    protected $guarded = [];
    protected $casts = ['reminder_date' => 'date', 'sent_at' => 'datetime', 'is_sent' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
