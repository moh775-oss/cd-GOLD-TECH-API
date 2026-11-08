<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RolePermission extends Model
{
    protected $table = 'role_permissions';
    protected $guarded = [];
    public $timestamps = false;
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function role() { return $this->belongsTo(Role::class, 'role_id'); }
    public function permission() { return $this->belongsTo(Permission::class, 'permission_id'); }
}
