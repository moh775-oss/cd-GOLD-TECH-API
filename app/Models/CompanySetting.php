<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    protected $table = 'company_settings';

    protected $fillable = [
        'company_id',

        // الافتراضيات المرتبطة بـ ID
        'default_branch_id',
        'default_user_id',
        'default_store_id',
        'default_pure_store_id',
        'default_scrap_store_id',
        'default_worked_store_id',
        'default_item_id',
        'default_karat_id',
        'default_category_id',
        'default_safe_id',
        'default_bank_id',
        'default_customer_id',
        'default_supplier_id',
        'base_currency_id',
        'default_tax_id',
        'default_fiscal_year_id',
        'default_cost_center_id',

        // الافتراضيات النصية والأنواع والأسماء
        'company_currency',
        'default_gold_type',
        'default_item_type',
        'default_category_name',
        'default_karat_name',
        'default_store_name',
        'default_item_name',
        'gold_price_source',
        'invoice_prefix_sale',
        'invoice_prefix_purchase',
        'voucher_prefix_receipt',
        'voucher_prefix_payment',
        'journal_prefix',

        // الضرائب والحسابات والطباعة
        'tax_enabled',
        'default_tax_rate',
        'auto_assign_store_by_karat',
        'allow_negative_stock',
        'allow_negative_gold',
        'print_show_tax',
        'print_notes',

        // إعدادات الإشعارات وتنبيهات الأجور
        'notifications_enabled',
        'wage_notifications_enabled',
        'wage_notify_after_days',
        'wage_notify_after_hours',
        'wage_notify_at_time',
        'wage_notify_rule_text',
        'wage_notify_message',
        'invoice_notifications_enabled',
        'voucher_notifications_enabled',

        // حقل نصي عام
        'custom_note',
    ];

    protected $casts = [
        'company_id'                    => 'integer',
        'default_branch_id'             => 'integer',
        'default_user_id'               => 'integer',
        'default_store_id'              => 'integer',
        'default_pure_store_id'         => 'integer',
        'default_scrap_store_id'        => 'integer',
        'default_worked_store_id'       => 'integer',
        'default_item_id'               => 'integer',
        'default_karat_id'              => 'integer',
        'default_category_id'           => 'integer',
        'default_safe_id'               => 'integer',
        'default_bank_id'               => 'integer',
        'default_customer_id'           => 'integer',
        'default_supplier_id'           => 'integer',
        'base_currency_id'              => 'integer',
        'default_tax_id'                => 'integer',
        'default_fiscal_year_id'        => 'integer',
        'default_cost_center_id'        => 'integer',

        'tax_enabled'                   => 'boolean',
        'default_tax_rate'              => 'decimal:4',
        'auto_assign_store_by_karat'    => 'boolean',
        'allow_negative_stock'          => 'boolean',
        'allow_negative_gold'           => 'boolean',
        'print_show_tax'                => 'boolean',

        'notifications_enabled'         => 'boolean',
        'wage_notifications_enabled'    => 'boolean',
        'wage_notify_after_days'        => 'integer',
        'wage_notify_after_hours'       => 'integer',
        'invoice_notifications_enabled' => 'boolean',
        'voucher_notifications_enabled' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
