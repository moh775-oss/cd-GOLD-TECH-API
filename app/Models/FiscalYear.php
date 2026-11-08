<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FiscalYear extends Model
{
    protected $table = 'fiscal_years';
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_closed' => 'boolean', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function openingBalances() { return $this->hasMany(OpeningBalance::class, 'fiscal_year_id'); }
}
