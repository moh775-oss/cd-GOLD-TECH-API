<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserRole extends Model
{
    protected $table = 'user_roles';
    protected $guarded = [];
    public $timestamps = false;
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function role() { return $this->belongsTo(Role::class, 'role_id'); }
}
