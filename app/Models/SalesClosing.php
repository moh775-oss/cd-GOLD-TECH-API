<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesClosing extends Model
{
    protected $table = 'sales_closing';
    protected $guarded = [];

    protected $casts = [
        'doc_date'       => 'date',
        'net_weight'     => 'decimal:3',
        'jewelry_weight' => 'decimal:3',
        'gram_price'     => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'jewelry_amount' => 'decimal:2',
        'total_amount'   => 'decimal:2',
    ];

    public function contact() { return $this->belongsTo(Contact::class, 'contact_id'); }   // ✅
    public function karat()   { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function user()    { return $this->belongsTo(User::class, 'user_id'); }

    public function journalEntry()
    {
        return $this->hasOne(JournalEntry::class, 'document_id')
            ->whereHas('documentType', fn($q) => $q->where('type_code', 'SALES_CLOSING'));
    }
}
