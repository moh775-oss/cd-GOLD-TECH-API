<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalEntryDetail extends Model
{
    protected $table = 'journal_entry_details';
    protected $guarded = [];
    protected $casts = ['exchange_rate' => 'decimal:6', 'debit_amount' => 'decimal:2', 'credit_amount' => 'decimal:2', 'gold_debit' => 'decimal:3', 'gold_credit' => 'decimal:3'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function journal() { return $this->belongsTo(JournalEntry::class, 'journal_id'); }
    public function account() { return $this->belongsTo(Account::class, 'account_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
