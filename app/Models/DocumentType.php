<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentType extends Model
{
    protected $table = 'document_types';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function documentSequences() { return $this->hasMany(DocumentSequence::class, 'document_type_id'); }
    public function journalEntries() { return $this->hasMany(JournalEntry::class, 'document_type_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'document_type_id'); }
    public function cashTransactions() { return $this->hasMany(CashTransaction::class, 'document_type_id'); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class, 'document_type_id'); }
    public function attachments() { return $this->hasMany(Attachment::class, 'document_type_id'); }
}
