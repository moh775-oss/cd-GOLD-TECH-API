<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Notification extends Model
{
    protected $table = 'notifications';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['is_read' => 'boolean', 'read_at' => 'datetime'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
