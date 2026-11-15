<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyInfo;
use App\Models\Contact;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\DocumentType;
use App\Models\FiscalYear;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Karat;
use App\Models\Safe;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tax;
use App\Models\User;
use App\Services\Concerns\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompanyService
{
    use ApiResponse;

    public function __construct(private MasterService $master) {}

    // ============================================================
    //                        إنشاء شركة كاملة
    // ============================================================

    /**
     * إنشاء شركة + فرع + مخزن + صندوق + شجرة حسابات + سنة مالية + مستخدم admin
     * كل شيء داخل Transaction واحدة (إما الكل ينجح أو الكل يفشل)
     */
    public function create(array $data): array
    {
        try {
            // 1. التحقق من الحقول الإلزامية
            $required = ['name', 'mobile', 'password', 'admin_name'];
            foreach ($required as $f) {
                if (empty($data[$f])) {
                    return $this->fail("الحقل {$f} مطلوب", null, 422);
                }
            }

            // 2. التحقق من صحة الهاتف
            if (!preg_match('/^05\d{8}$/', $data['mobile']) && !preg_match('/^\d{9,15}$/', $data['mobile'])) {
                return $this->fail('رقم الهاتف غير صحيح', null, 422);
            }

            // 3. التحقق من تكرار الهاتف
            if (User::where('mobile', $data['mobile'])->exists()) {
                return $this->fail('رقم الهاتف مستخدم مسبقاً', null, 409);
            }

            // 4. التحقق من كلمة المرور
            if (strlen($data['password']) < 6) {
                return $this->fail('كلمة المرور يجب أن تكون 6 أحرف على الأقل', null, 422);
            }

            $userId = $data['user_id'] ?? Auth::id();

            return DB::transaction(function () use ($data, $userId) {

                // ============ 1. الشركة ============
                $company = Company::create([
                    'name'                    => $data['name'],
                    'name_en'                 => $data['name_en']                 ?? null,
                    'commercial_register'     => $data['commercial_register']     ?? null,
                    'tax_number'              => $data['tax_number']              ?? null,
                    'phone'                   => $data['phone']                   ?? null,
                    'mobile'                  => $data['mobile'],
                    'email'                   => $data['email']                   ?? null,
                    'website'                 => $data['website']                 ?? null,
                    'logo_path'               => $data['logo_path']  ?? null,
                    'cover_path'              => $data['cover_path'] ?? null,
                    'country'                 => $data['country']                 ?? 'السعودية',
                    'city'                    => $data['city']                    ?? null,
                    'district'                => $data['district']                ?? null,
                    'street'                  => $data['street']                  ?? null,
                    'building_no'             => $data['building_no']             ?? null,
                    'postal_code'             => $data['postal_code']             ?? null,
                    'extra_no'                => $data['extra_no']                ?? null,
                    'address'                 => $data['address']                 ?? null,
                    'subscription_plan'       => $data['subscription_plan']       ?? 'trial',
                    'subscription_start'      => $data['subscription_start']      ?? now()->toDateString(),
                    'subscription_end'        => $data['subscription_end']        ?? now()->addDays(14)->toDateString(),
                    'subscription_amount'     => $data['subscription_amount']     ?? 0,
                    'subscription_cycle'      => $data['subscription_cycle']      ?? 'monthly',
                    'subscription_status'     => 'active',
                    'max_users'               => $data['max_users']               ?? 5,
                    'max_branches'            => $data['max_branches']            ?? 3,
                    'max_contacts'            => $data['max_contacts']            ?? 100,
                    'current_users_count'     => 1,
                    'current_branches_count'  => 1,
                    'is_active'               => 1,
                    'user_id'                 => $userId,
                ]);

                // ============ 2. الفرع الرئيسي ============
                $branch = Branch::create([
                    'company_id'  => $company->id,
                    'name'        => $data['branch_name'] ?? 'الفرع الرئيسي',
                    'code'        => 'BR-001',
                    'phone'       => $data['mobile'],
                    'address'     => $data['address'] ?? null,
                    'city'        => $data['city']    ?? null,
                    'is_main'     => 1,
                    'is_active'   => 1,
                    'user_id'     => $userId,
                ]);

                // ============ 3. المخزن الرئيسي ============
                Store::create([
                    'company_id' => $company->id,
                    'branch_id'  => $branch->id,
                    'name'       => $data['store_name'] ?? ' خزينة الذهب',
                    'code'       => 'WH-001',
                    'is_main'    => 1,
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                // ============ 4. نسخ شجرة الحسابات ============
                DB::statement("
                    INSERT INTO accounts
                    (company_id, branch_id, acc_no, name, father_acc_no, account_type,
                    account_level, final_account, account_nature, is_active, is_system, user_id, created_at)
                    SELECT ?, ?, acc_no, name, father_acc_no, account_type,
                        account_level, final_account, account_nature, 1, 1, ?, NOW()
                    FROM accounts_template
                    ORDER BY sort_order
                ", [$company->id, $branch->id, $userId]);

                // ============ 5. الصندوق الرئيسي ============
                $safeAccResult = $this->master->createSubAccount(
                    $company->id, $branch->id, '121', 'الصندوق الرئيسي', $userId
                );
                if (!$safeAccResult['success']) {
                    throw new \RuntimeException('فشل إنشاء حساب الصندوق: ' . $safeAccResult['message']);
                }

                Safe::create([
                    'company_id'   => $company->id,
                    'branch_id'    => $branch->id,
                    'name'         => $data['safe_name'] ?? 'الصندوق الرئيسي',
                    'code'         => 'SF-001',
                    'acc_no'       => $safeAccResult['data']['acc_no'],
                    'opening_cash' => 0,
                    'current_cash' => 0,
                    'opening_gold' => 0,
                    'current_gold' => 0,
                    'currency'     => 'SAR',
                    'is_active'    => 1,
                    'user_id'      => $userId,
                ]);

                // ============ 5.1. البنك الرئيسي ============
                $bankAccResult = $this->master->createSubAccount(
                    $company->id, $branch->id, '122', 'حساب الشبكة', $userId
                );
                if (!$bankAccResult['success']) {
                    throw new \RuntimeException('فشل إنشاء حساب البنك: ' . $bankAccResult['message']);
                }

                Bank::create([
                    'company_id'      => $company->id,
                    'branch_id'       => $branch->id,
                    'name'            => $data['bank_name'] ?? 'حساب الشبكة',
                    'code'            => 'BK-001',
                    'acc_no'          => $bankAccResult['data']['acc_no'],
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active'       => 1,
                    'user_id'         => $userId,
                ]);

                // ============ 5.2. عميل عام ============
                $customerAccResult = $this->master->createSubAccount(
                    $company->id, $branch->id, '123', 'عميل عام', $userId
                );
                if (!$customerAccResult['success']) {
                    throw new \RuntimeException('فشل إنشاء حساب العميل العام: ' . $customerAccResult['message']);
                }

                Contact::create([
                    'company_id'      => $company->id,
                    'branch_id'       => $branch->id,
                    'name'            => 'عميل عام',
                    'contact_type'    => 'customer',
                    'acc_no'          => $customerAccResult['data']['acc_no'],
                    'is_active'       => 1,
                    'opening_balance' => 0,
                    'balance'         => 0,
                    'credit_limit'    => 0,
                    'country'         => 'السعودية',
                    'user_id'         => $userId,
                ]);

                // ============ 5.3. مورد عام ============
                $supplierAccResult = $this->master->createSubAccount(
                    $company->id, $branch->id, '261', 'مورد عام', $userId
                );
                if (!$supplierAccResult['success']) {
                    throw new \RuntimeException('فشل إنشاء حساب المورد العام: ' . $supplierAccResult['message']);
                }

                Contact::create([
                    'company_id'      => $company->id,
                    'branch_id'       => $branch->id,
                    'name'            => 'مورد عام',
                    'contact_type'    => 'supplier',
                    'acc_no'          => $supplierAccResult['data']['acc_no'],
                    'is_active'       => 1,
                    'opening_balance' => 0,
                    'balance'         => 0,
                    'credit_limit'    => 0,
                    'country'         => 'السعودية',
                    'user_id'         => $userId,
                ]);

                // ============ 6. السنة المالية ============
                FiscalYear::create([
                    'company_id' => $company->id,
                    'name'       => $data['fiscal_year_name'] ?? ('السنة المالية ' . now()->year),
                    'start_date' => $data['fiscal_year_start'] ?? now()->startOfYear()->toDateString(),
                    'end_date'   => $data['fiscal_year_end']   ?? now()->endOfYear()->toDateString(),
                    'is_closed'  => 0,
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                // ============ 7. المستخدم admin ============
                $admin = User::create([
                    'company_id'          => $company->id,
                    'branch_id'           => $branch->id,
                    'name'                => $data['admin_name'],
                    'mobile'              => $data['mobile'],
                    'password_hash'       => Hash::make($data['password']),
                    'email'               => $data['email'] ?? null,
                    'role'                => 'admin',
                    'allow_branch_switch' => 1,
                    'is_active'           => 1,
                    'user_id'             => $userId,
                ]);

                // ============ 7.5. العملات ============
                $sar = Currency::create([
                    'company_id' => $company->id,
                    'code'       => 'SAR',
                    'name'       => 'ريال سعودي',
                    'symbol'     => 'ر.س',
                    'is_base'    => 1,
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                Currency::create([
                    'company_id' => $company->id,
                    'code'       => 'USD',
                    'name'       => 'دولار أمريكي',
                    'symbol'     => '$',
                    'is_base'    => 0,
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                // ============ 7.6. الضرائب ============
                Tax::create([
                    'company_id' => $company->id,
                    'name'       => 'ضريبة القيمة المضافة 15%',
                    'rate'       => 15.0000,
                    'is_default' => 1,
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                Tax::create([
                    'company_id' => $company->id,
                    'name'       => 'ضريبة صفرية 0%',
                    'rate'       => 0.0000,
                    'is_default' => 0,
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                // ============ 7.5. مركز التكلفة الرئيسي ============
                CostCenter::create([
                    'company_id'      => $company->id,
                    'branch_id'       => $branch->id,
                    'parent_id'       => null,
                    'code'            => 'CC-001',
                    'name'            =>  'مركز التكلفة الرئيسي',
                    'name_en'         => null,
                    'center_type'     => 'cost',
                    'account_id'      => null,
                    'manager_id'      => $userId,
                    'budget_amount'   => 0,
                    'opening_balance' => 0,
                    'is_active'       => 1,
                    'notes'           => null,
                    'user_id'         => $userId,
                ]);

                // ============ 7.6. العيارات (لكل شركة) ============
                $karats = [
                    ['name' => '24', 'the_value' => 0.999000],
                    ['name' => '22', 'the_value' => 0.916000],
                    ['name' => '21', 'the_value' => 0.875000],
                    ['name' => '18', 'the_value' => 0.750000],
                    ['name' => '14', 'the_value' => 0.585000],
                ];

                foreach ($karats as $k) {
                    Karat::create([
                        'company_id' => $company->id,
                        'branch_id'  => $branch->id,
                        'name'       => $k['name'],
                        'the_value'  => $k['the_value'],
                        'is_active'  => 1,
                        'user_id'    => $userId,
                    ]);
                }

                // ============ 7.7. مجموعات الأصناف (لكل شركة) ============
                $goldCat = ItemCategory::create([
                    'company_id' => $company->id,
                    'parent_id'  => null,
                    'name'       => 'الذهب',
                    'name_en'    => 'Gold',
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                foreach ([
                    ['name' => 'كسر مصاغ', 'name_en' => 'Scrap Gold'],
                    ['name' => 'مصاغ',     'name_en' => 'Worked Gold'],
                    ['name' => 'ذهب صافي', 'name_en' => 'Pure Gold'],
                ] as $c) {
                    ItemCategory::create([
                        'company_id' => $company->id,
                        'parent_id'  => $goldCat->id,
                        'name'       => $c['name'],
                        'name_en'    => $c['name_en'],
                        'is_active'  => 1,
                        'user_id'    => $userId,
                    ]);
                }

                ItemCategory::create([
                    'company_id' => $company->id,
                    'parent_id'  => null,
                    'name'       => 'الفضة',
                    'name_en'    => 'Silver',
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);

                ItemCategory::create([
                    'company_id' => $company->id,
                    'parent_id'  => null,
                    'name'       => 'الماس',
                    'name_en'    => 'Diamond',
                    'is_active'  => 1,
                    'user_id'    => $userId,
                ]);


                $karat24 = Karat::where('company_id', $company->id)->where('name', '24')->first();

                // خد مجموعة الذهب الرئيسية
                $goldCategory = ItemCategory::where('company_id', $company->id)
                    ->where('name', 'الذهب')
                    ->whereNull('parent_id')
                    ->first();

                // خد المخزن الرئيسي (اللي اتعمل في خطوة 3)
                $mainStore = Store::where('company_id', $company->id)->where('is_main', 1)->first();

                // خد العملة الأساسية (SAR)
                $sarCurrency = Currency::where('company_id', $company->id)->where('code', 'SAR')->first();

                // خد الضريبة الافتراضية (15%)
                $defaultTax = Tax::where('company_id', $company->id)->where('is_default', 1)->first();

                // ✅ إنشاء الصنف "ذهب"
                Item::create([
                    'company_id'               => $company->id,
                    'branch_id'                => $branch->id,
                    'category_id'              => $goldCategory?->id,
                    'store_id'                 => $mainStore?->id,
                    'code'                     => 'GOLD-001',
                    'name'                     => 'ذهب',
                    'name_en'                  => 'Gold',
                    'description'              => ' ذهب صافي',
                    'item_type'                => 'pure',
                    'gold_type'                => 'pure',
                    'karat_id'                 => $karat24?->id,
                    'unit'                     => 'جرام',
                    'weight'                   => 0,
                    'purity'                   => 1.000000,
                    'cost_price'               => 0,
                    'purchase_price'           => 0,
                    'sale_price'               => 0,
                    'min_sale_price'           => 0,
                    'wholesale_price'          => 0,
                    'tax_id'                   => $defaultTax?->id,
                    'tax_rate'                 => 15.0000,
                    'currency_id'              => $sarCurrency?->id,
                    'exchange_rate'            => 1.000000,
                    'min_qty'                  => 0,
                    'max_qty'                  => 0,
                    'reorder_qty'              => 0,
                    'current_qty'              => 0,
                    'opening_qty'              => 0,
                    'opening_weight'           => 0,
                    'is_active'                => 1,
                    'is_sellable'              => 1,
                    'is_purchasable'           => 1,
                    'is_trackable'             => 1,
                    'is_featured'              => 0,
                    'sort_order'               => 0,
                    'user_id'                  => $userId,
                ]);

                // ============ 8. CompanyInfo ============
                CompanyInfo::create([
                    'company_id' => $company->id,
                    'branch_id'  => $branch->id,
                    'name'       => $company->name,
                    'name_en'    => $company->name_en,
                    'phone'      => $company->phone,
                    'mobile'     => $company->mobile,
                    'email'      => $company->email,
                    'website'    => $company->website,
                    'address'    => $company->address,
                    'city'       => $company->city,
                    'tax_number' => $company->tax_number,
                    'logo_path'  => $company->logo_path,
                    'user_id'    => $userId,
                ]);

                // ============ 9. الإعدادات ============
                $settings = [
                    ['company_currency',       'SAR',     'string',  'العملة الافتراضية'],
                    ['base_currency_id',       $sar->id,  'int',     'معرف العملة الأساسية'],
                    ['tax_enabled',            '1',       'bool',    'تفعيل الضريبة'],
                    ['default_tax_rate',       '15',      'decimal', 'نسبة الضريبة الافتراضية'],
                    ['invoice_prefix_sale',    'INV',     'string',  'بادئة فواتير المبيعات'],
                    ['invoice_prefix_purchase','PUR',     'string',  'بادئة فواتير المشتريات'],
                    ['voucher_prefix_receipt', 'RCV',     'string',  'بادئة سندات القبض'],
                    ['voucher_prefix_payment', 'PAY',     'string',  'بادئة سندات الصرف'],
                    ['journal_prefix',         'JV',      'string',  'بادئة القيود'],
                    ['gold_price_source',      'manual',  'string',  'مصدر أسعار الذهب'],
                ];

                foreach ($settings as $s) {
                    Setting::create([
                        'company_id'  => $company->id,
                        'branch_id'   => $branch->id,
                        'key_name'    => $s[0],
                        'value'       => $s[1],
                        'value_type'  => $s[2],
                        'description' => $s[3],
                    ]);
                }

                // ============ 10. document_sequences ============
                $docTypes = DocumentType::whereIn('type_code', [
                    'PURCHASE','SALE','PURCHASE_RETURN','SALE_RETURN',
                    'RECEIPT_VOUCHER','PAYMENT_VOUCHER','RECEIPT_MULTI','PAYMENT_MULTI',
                    'JOURNAL_ENTRY','OPENING_BALANCE','FIRST_STOCK',
                    'STOCK_SUPPLY','STOCK_ISSUE','STOCK_TRANSFER','STOCK_RECEIVE','STOCK_ADJUST',
                    'CASHIER_SALE','CASHIER_SALE_RET','OFFER_PRICE','SERVICE_SALE','SERVICE_SALE_RET','DEBIT_NOTE',
                ])->get();

                foreach ($docTypes as $dt) {
                    DB::table('document_sequences')->insert([
                        'company_id'       => $company->id,
                        'branch_id'        => $branch->id,
                        'document_type_id' => $dt->id,
                        'next_number'      => 1,
                        'padding'          => 0,
                        'prefix'           => null,
                        'created_at'       => now(),
                    ]);
                }

                return $this->ok('تم إنشاء الشركة بنجاح', [
                    'company_id'    => $company->id,
                    'branch_id'     => $branch->id,
                    'user_id'       => $admin->id,
                    'admin_mobile'  => $admin->mobile,
                ], 201);
            });
        } catch (Throwable $e) {
            Log::error('CompanyService@create: ' . $e->getMessage());
            return $this->fail('فشل إنشاء الشركة: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        تعديل شركة
    // ============================================================

    public function update(int $companyId, array $data): array
    {
        try {
            $company = Company::find($companyId);
            if (!$company) return $this->fail('الشركة غير موجودة', null, 404);

            $company->update([
                'name'                => $data['name']                ?? $company->name,
                'name_en'             => $data['name_en']             ?? $company->name_en,
                'commercial_register' => $data['commercial_register'] ?? $company->commercial_register,
                'tax_number'          => $data['tax_number']          ?? $company->tax_number,
                'phone'               => $data['phone']               ?? $company->phone,
                'email'               => $data['email']               ?? $company->email,
                'logo_path'           => $data['logo_path']  ?? $company->logo_path,
                'cover_path'          => $data['cover_path'] ?? $company->cover_path,
                'website'             => $data['website']             ?? $company->website,
                'city'                => $data['city']                ?? $company->city,
                'district'            => $data['district']            ?? $company->district,
                'street'              => $data['street']              ?? $company->street,
                'building_no'         => $data['building_no']         ?? $company->building_no,
                'postal_code'         => $data['postal_code']         ?? $company->postal_code,
                'address'             => $data['address']             ?? $company->address,
                'subscription_plan'   => $data['subscription_plan']   ?? $company->subscription_plan,
                'subscription_end'    => $data['subscription_end']    ?? $company->subscription_end,
                'subscription_amount' => $data['subscription_amount'] ?? $company->subscription_amount,
                'subscription_status' => $data['subscription_status'] ?? $company->subscription_status,
                'max_users'           => $data['max_users']           ?? $company->max_users,
                'max_branches'        => $data['max_branches']        ?? $company->max_branches,
                'is_active'           => $data['is_active']           ?? $company->is_active,
                'notes'               => $data['notes']               ?? $company->notes,
            ]);

            return $this->ok('تم تعديل الشركة بنجاح', $company);
        } catch (Throwable $e) {
            Log::error('CompanyService@update: ' . $e->getMessage());
            return $this->fail('فشل تعديل الشركة: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        حذف / تعطيل
    // ============================================================

    /**
     * حذف شركة (Soft — تعطيل فقط)
     */
    public function delete(int $companyId): array
    {
        try {
            $company = Company::find($companyId);
            if (!$company) return $this->fail('الشركة غير موجودة', null, 404);

             if ($companyId === 1) {
                return $this->fail('لا يمكن تعطيل الشركة الرئيسية للنظام', null, 409);
            }
            // منع حذف شركة لها حركات
            if (DB::table('journal_entries')->where('company_id', $companyId)->exists()) {
                return $this->fail('لا يمكن حذف شركة لها قيود محاسبية', null, 409);
            }

            $company->update(['is_active' => 0]);
            return $this->ok('تم تعطيل الشركة بنجاح');
        } catch (Throwable $e) {
            Log::error('CompanyService@delete: ' . $e->getMessage());
            return $this->fail('فشل حذف الشركة: ' . $e->getMessage(), null, 500);
        }
    }

    /**
 * حذف شركة بالكامل — كل بياناتها
 */
public function forceDelete(int $companyId): array
{
    try {
        $company = Company::find($companyId);
        if (!$company) return $this->fail('الشركة غير موجودة', null, 404);
        if ($companyId === 1) {
            return $this->fail('لا يمكن حذف الشركة الرئيسية للنظام', null, 409);
        }
        return DB::transaction(function () use ($companyId) {

            // 1. حذف التفاصيل المرتبطة بالمستندات (لأنها بلا FK)
            DB::table('journal_entry_details')->where('company_id', $companyId)->delete();
            DB::table('journal_entries')->where('company_id', $companyId)->delete();

            DB::table('sales_invoice_details')->where('company_id', $companyId)->delete();
            DB::table('sales_invoices')->where('company_id', $companyId)->delete();

            DB::table('purchase_invoice_details')->where('company_id', $companyId)->delete();
            DB::table('purchase_invoices')->where('company_id', $companyId)->delete();

            DB::table('sales_return_details')->where('company_id', $companyId)->delete();
            DB::table('sales_returns')->where('company_id', $companyId)->delete();

            DB::table('purchase_return_details')->where('company_id', $companyId)->delete();
            DB::table('purchase_returns')->where('company_id', $companyId)->delete();

            DB::table('sales_wage_details')->where('company_id', $companyId)->delete();
            DB::table('sales_wages')->where('company_id', $companyId)->delete();

            DB::table('purchase_wage_details')->where('company_id', $companyId)->delete();
            DB::table('purchase_wages')->where('company_id', $companyId)->delete();

            DB::table('sales_closing')->where('company_id', $companyId)->delete();
            DB::table('purchase_closing')->where('company_id', $companyId)->delete();

            DB::table('receipt_vouchers')->where('company_id', $companyId)->delete();
            DB::table('payment_vouchers')->where('company_id', $companyId)->delete();

            DB::table('expenses')->where('company_id', $companyId)->delete();
            DB::table('cash_transactions')->where('company_id', $companyId)->delete();
            DB::table('bank_transactions')->where('company_id', $companyId)->delete();

            DB::table('stock_movements')->where('company_id', $companyId)->delete();
            DB::table('warehouse_stock')->where('company_id', $companyId)->delete();

            // 2. حذف البيانات الأساسية
            DB::table('item_categories')->where('company_id', $companyId)->delete();
            DB::table('items')->where('company_id', $companyId)->delete();
            DB::table('karats')->where('company_id', $companyId)->delete();
            DB::table('taxes')->where('company_id', $companyId)->delete();
            DB::table('currencies')->where('company_id', $companyId)->delete();
            DB::table('exchange_rates')->where('company_id', $companyId)->delete();
            DB::table('contacts')->where('company_id', $companyId)->delete();
            DB::table('safes')->where('company_id', $companyId)->delete();
            DB::table('banks')->where('company_id', $companyId)->delete();
            DB::table('cost_centers')->where('company_id', $companyId)->delete();
            DB::table('stores')->where('company_id', $companyId)->delete();
            DB::table('accounts')->where('company_id', $companyId)->delete();
            DB::table('fiscal_years')->where('company_id', $companyId)->delete();
            DB::table('settings')->where('company_id', $companyId)->delete();
            DB::table('document_sequences')->where('company_id', $companyId)->delete();
            DB::table('company_info')->where('company_id', $companyId)->delete();

            // 3. حذف المستخدمين وربطاتهم
            DB::table('user_branches')->where('company_id', $companyId)->delete();
            DB::table('user_roles')->where('company_id', $companyId)->delete();

            $userIds = User::where('company_id', $companyId)->pluck('id')->all();
            if (!empty($userIds)) {
                DB::table('personal_access_tokens')
                    ->whereIn('tokenable_id', $userIds)
                    ->where('tokenable_type', User::class)
                    ->delete();
            }
            User::where('company_id', $companyId)->delete();

            // 4. حذف الفروع
            Branch::where('company_id', $companyId)->delete();

            // 5. حذف الشركة
            Company::where('id', $companyId)->delete();

            return $this->ok('تم حذف الشركة وكل بياناتها بنجاح');
        });
    } catch (Throwable $e) {
        Log::error('CompanyService@forceDelete: ' . $e->getMessage());
        return $this->fail('فشل حذف الشركة: ' . $e->getMessage(), null, 500);
    }
}

    // ============================================================
    //                        جلب
    // ============================================================

    public function getById(int $companyId): array
    {
        try {
            $company = Company::with(['branches', 'users'])
                ->withCount(['branches', 'users'])
                ->find($companyId);
            if (!$company) return $this->fail('الشركة غير موجودة', null, 404);
            return $this->ok('تم الجلب', $company);
        } catch (Throwable $e) {
            Log::error('CompanyService@getById: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    public function list(array $filters = []): array
    {
        try {
            $q = Company::withCount(['branches', 'users']);

            if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                $q->where('is_active', $filters['is_active']);
            }
            if (!empty($filters['subscription_status'])) {
                $q->where('subscription_status', $filters['subscription_status']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")
                      ->orWhere('mobile', 'LIKE', "%{$s}%")
                      ->orWhere('tax_number', 'LIKE', "%{$s}%");
                });
            }

            return $this->ok('تم الجلب', $q->orderByDesc('id')->get());
        } catch (Throwable $e) {
            Log::error('CompanyService@list: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }
}
