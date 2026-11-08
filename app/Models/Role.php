<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model
{
    protected $table = 'roles';
    protected $guarded = [];
    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function permissions() { return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id'); }
    public function rolePermissions() { return $this->hasMany(RolePermission::class, 'role_id'); }
    public function userRoles() { return $this->hasMany(UserRole::class, 'role_id'); }
    public function users() { return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id'); }
}
