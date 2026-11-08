<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AccountMapping extends Model
{
    protected $table = 'account_mappings';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function account() { return $this->belongsTo(Account::class, 'acc_no', 'acc_no'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
