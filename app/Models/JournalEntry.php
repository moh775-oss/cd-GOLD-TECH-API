<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalEntry extends Model
{
    protected $table = 'journal_entries';
    protected $guarded = [];
    protected $casts = ['journal_date' => 'date', 'exchange_rate' => 'decimal:6'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function documentType() { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function details() { return $this->hasMany(JournalEntryDetail::class, 'journal_id'); }
}
