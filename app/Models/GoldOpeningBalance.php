<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldOpeningBalance extends Model
{
    protected $table = 'gold_opening_balances';

    protected $guarded = [];

    protected $casts = [
        'company_id'       => 'integer',
        'branch_id'        => 'integer',
        'store_id'         => 'integer',
        'account_id'       => 'integer',
        'karat_id'         => 'integer',
        'document_type_id' => 'integer',
        'journal_id'       => 'integer',
        'user_id'          => 'integer',
        'weight'           => 'decimal:3',
        'opening_date'     => 'date:Y-m-d',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function karat()
    {
        return $this->belongsTo(Karat::class, 'karat_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}