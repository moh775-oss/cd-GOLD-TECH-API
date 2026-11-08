<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $table = 'accounts';
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'integer',
        'account_level' => 'integer',
    ];

    // ==================== العلاقات الأساسية ====================
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo  { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    // ==================== العلاقة الذاتية ====================
    // الأب: father_acc_no → acc_no (مع نفس الشركة والفرع)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'father_acc_no', 'acc_no')
            ->where('company_id', $this->company_id)
            ->where(function ($q) {
                $q->where('branch_id', $this->branch_id)
                  ->orWhereNull('branch_id');
            });
    }

    // الأبناء: acc_no ← father_acc_no (مع نفس الشركة والفرع)
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'father_acc_no', 'acc_no')
            ->where('company_id', $this->company_id)
            ->where(function ($q) {
                $q->where('branch_id', $this->branch_id)
                  ->orWhereNull('branch_id');
            });
    }

    // ==================== علاقات الحذف ====================
    public function journalEntryDetails(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class, 'account_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'acc_no', 'acc_no')
            ->where('company_id', $this->company_id);
    }

    public function safes(): HasMany
    {
        return $this->hasMany(Safe::class, 'acc_no', 'acc_no')
            ->where('company_id', $this->company_id);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(Bank::class, 'acc_no', 'acc_no')
            ->where('company_id', $this->company_id);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'acc_no', 'acc_no')
            ->where('company_id', $this->company_id);
    }

    public function accountMappings(): HasMany
    {
        return $this->hasMany(AccountMapping::class, 'acc_no', 'acc_no')
            ->where('company_id', $this->company_id);
    }
    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'account_id')
            ->where('company_id', $this->company_id);
    }
}
