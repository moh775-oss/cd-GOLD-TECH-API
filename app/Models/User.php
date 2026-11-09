<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';
    protected $guarded = [];
    protected $hidden = ['password_hash'];

    protected $casts = [
        'allow_branch_switch' => 'boolean',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function creator() { return $this->belongsTo(User::class, 'user_id'); }
    public function userRoles() { return $this->hasMany(UserRole::class, 'user_id'); }
    public function userBranches() { return $this->hasMany(UserBranch::class, 'user_id'); }
    public function roles() { return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id'); }
    public function branches() { return $this->belongsToMany(Branch::class, 'user_branches', 'user_id', 'branch_id'); }
    public function auditLogs() { return $this->hasMany(AuditLog::class, 'user_id'); }
    public function notifications() { return $this->hasMany(Notification::class, 'user_id'); }
    public function attachments() { return $this->hasMany(Attachment::class, 'user_id'); }
}
