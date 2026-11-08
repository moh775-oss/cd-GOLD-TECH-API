<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
