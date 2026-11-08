<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseClosing extends Model
{
    protected $table = 'purchase_closing';
    protected $guarded = [];
    protected $casts = ['doc_date' => 'date', 'net_weight' => 'decimal:3', 'jewelry_weight' => 'decimal:3', 'gram_price' => 'decimal:4', 'net_amount' => 'decimal:2', 'jewelry_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch() { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function contact() { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
