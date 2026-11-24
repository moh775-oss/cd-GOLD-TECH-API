<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReceiptVoucher extends Model
{
    protected $table = 'receipt_vouchers';
    protected $guarded = [];

    protected $casts = [
        'doc_date'         => 'date',
        'exchange_rate'    => 'decimal:6',
        'cash_amount'      => 'decimal:2',
        'gold_weight'      => 'decimal:3',
        'gold_net_weight'  => 'decimal:3',
        'gram_price'       => 'decimal:4',
        'gold_amount'      => 'decimal:2',
        'total_amount'     => 'decimal:2',
    ];

    public function company()  { return $this->belongsTo(Company::class, 'company_id'); }
    public function branch()   { return $this->belongsTo(Branch::class, 'branch_id'); }
    public function contact()
    {
        return $this->belongsTo(Account::class, 'contact_id');
    }
    public function safe()     { return $this->belongsTo(Safe::class, 'safe_id'); }
    public function bank()     { return $this->belongsTo(Bank::class, 'bank_id'); }
    public function store()    { return $this->belongsTo(Store::class, 'store_id'); }
    public function currency() { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function karat()    { return $this->belongsTo(Karat::class, 'karat_id'); }
    public function user()     { return $this->belongsTo(User::class, 'user_id'); }

    public function journalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'document_id')
            ->where('company_id', $this->company_id)
            ->where('document_type_id', function ($q) {
                $q->select('id')->from('document_types')->where('type_code', 'RECEIPT_VOUCHER')->limit(1);
            });
    }
}