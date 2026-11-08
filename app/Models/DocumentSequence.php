<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentSequence extends Model
{
    protected $table = 'document_sequences';
    protected $guarded = [];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function documentType() { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
}
