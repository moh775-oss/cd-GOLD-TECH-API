<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompanyInfo extends Model
{
    protected $table = 'company_info';
    protected $guarded = [];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
