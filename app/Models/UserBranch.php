<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserBranch extends Model
{
    protected $table = 'user_branches';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['is_default' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
}
