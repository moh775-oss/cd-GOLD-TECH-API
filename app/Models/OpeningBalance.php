<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OpeningBalance extends Model
{
    protected $table = 'opening_balances';
    protected $guarded = [];
    protected $casts = ['debit_amount' => 'decimal:2', 'credit_amount' => 'decimal:2', 'gold_debit' => 'decimal:3', 'gold_credit' => 'decimal:3', 'exchange_rate' => 'decimal:6'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function fiscalYear() { return $this->belongsTo(FiscalYear::class, 'fiscal_year_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
