<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CashTransaction extends Model
{
    protected $table = 'cash_transactions';
    protected $guarded = [];
    protected $casts = ['trans_date' => 'date', 'amount' => 'decimal:2', 'exchange_rate' => 'decimal:6'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function safe() { return $this->belongsTo(Safe::class, 'safe_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function documentType() { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
