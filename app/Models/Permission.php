<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Permission extends Model
{
    protected $table = 'permissions';
    protected $guarded = [];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function roles() { return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id'); }
    public function rolePermissions() { return $this->hasMany(RolePermission::class, 'permission_id'); }
}
