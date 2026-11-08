<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    protected $table = 'cost_centers';
    protected $guarded = [];

    protected $casts = [
        'is_active'       => 'integer',
        'budget_amount'   => 'decimal:2',
        'opening_balance' => 'decimal:2',
    ];

    // ==================== العلاقات الأساسية ====================
    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo     { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function manager(): BelongsTo    { return $this->belongsTo(User::class, 'manager_id'); }

    // ==================== الحساب المرتبط ====================
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // ==================== الهيكل الهرمي ====================
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id')
            ->where('company_id', $this->company_id);
    }

    public function children(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'parent_id')
            ->where('company_id', $this->company_id);
    }

    // ==================== التفاصيل المرتبطة ====================
    public function journalEntryDetails(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class, 'cost_center_id')
            ->where('company_id', $this->company_id);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'cost_center_id')
            ->where('company_id', $this->company_id);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'cost_center_id')
            ->where('company_id', $this->company_id);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'cost_center_id')
            ->where('company_id', $this->company_id);
    }
}
