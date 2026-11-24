<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyInfo extends Model
{
    protected $table = 'company_info';
    protected $guarded = [];
    protected $appends = ['logo_url'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function getLogoUrlAttribute()
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
    
}
