<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MakingCharge extends Model
{
    protected $table = 'making_charges';
    protected $guarded = [];
    protected $casts = ['value' => 'decimal:4', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class, 'company_id'); }
    public function karat() { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function item() { return $this->belongsTo(Item::class, 'item_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
