<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Attachment extends Model
{
    protected $table = 'attachments';
    protected $guarded = [];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function documentType() { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
