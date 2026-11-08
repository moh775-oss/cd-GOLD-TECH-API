<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AccountsTemplate extends Model
{
    protected $table = 'accounts_template';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function father() { return $this->belongsTo(AccountsTemplate::class, 'father_acc_no', 'acc_no'); }
    public function children() { return $this->hasMany(AccountsTemplate::class, 'father_acc_no', 'acc_no'); }
}
