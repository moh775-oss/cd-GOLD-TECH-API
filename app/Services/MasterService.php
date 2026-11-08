<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\DocumentType;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\User;

use App\Services\Concerns\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;


class MasterService
{
    use ApiResponse;

    public function __construct(
        private DocumentNumberService $numberService
    ) {}

    // ============================================================
    //                        التحقق الأساسي
    // ============================================================

    public function validateContext(int $companyId, int $branchId, ?int $userId = null): array
    {
        try {
            $company = Company::where('id', $companyId)->where('is_active', 1)->first();
            if (!$company) {
                return $this->fail('الشركة غير موجودة أو غير مفعلة', null, 403);
            }
            if ($company->subscription_end && $company->subscription_end < now()->toDateString()) {
                return $this->fail('انتهى اشتراك الشركة', null, 403);
            }

            $branch = Branch::where('id', $branchId)
                ->where('company_id', $companyId)
                ->where('is_active', 1)
                ->first();
            if (!$branch) {
                return $this->fail('الفرع غير موجود أو لا يتبع الشركة', null, 403);
            }

            if ($userId) {
                $user = User::where('id', $userId)
                    ->where('company_id', $companyId)
                    ->where('is_active', 1)
                    ->first();
                if (!$user) {
                    return $this->fail('المستخدم غير موجود أو لا يتبع الشركة', null, 403);
                }
            }

            return $this->ok('السياق سليم', [
                'company' => $company,
                'branch'  => $branch,
            ]);
        } catch (Throwable $e) {
            Log::error('validateContext: ' . $e->getMessage());
            return $this->fail('خطأ في التحقق: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * توليد رقم مستند جديد لكل (شركة + فرع + نوع)
     */
    public function generateDocNo(int $companyId, int $branchId, int $documentTypeId): array
    {
        try {
            $ctx = $this->validateContext($companyId, $branchId);
            if (!$ctx['success']) return $ctx;

            $no = $this->numberService->generate($companyId, $branchId, $documentTypeId);
            return $this->ok('تم توليد الرقم', ['doc_no' => $no]);
        } catch (Throwable $e) {
            Log::error('generateDocNo: ' . $e->getMessage());
            return $this->fail('فشل توليد الرقم: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        إدارة الحسابات
    // ============================================================

    /**
     * إنشاء حساب جديد مع التحقق الكامل
     * - يحسب level/type تلقائياً من الأب
     * - يورّث nature/final_account من الأب
     * - يتعامل مع father_acc_no = '0' كجذر
     */
    public function createAccount(array $data): array
    {
        try {
            // 1. التحقق من السياق
            $ctx = $this->validateContext(
                (int)$data['company_id'],
                (int)$data['branch_id'],
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            // 2. الحقول الإلزامية
            foreach (['acc_no','name'] as $f) {
                if (empty($data[$f])) {
                    return $this->fail("الحقل {$f} مطلوب", null, 422);
                }
            }

            $companyId = (int)$data['company_id'];
            $branchId  = $data['branch_id'] ?? null;
            $father    = trim((string)($data['father_acc_no'] ?? '0'));
            if ($father === '') $father = '0';

            // 3. منع تكرار acc_no داخل نفس الشركة/الفرع
            $dupQuery = Account::where('company_id', $companyId)->where('acc_no', $data['acc_no']);
            if ($branchId === null) {
                $dupQuery->whereNull('branch_id');
            } else {
                $dupQuery->where('branch_id', $branchId);
            }
            if ($dupQuery->exists()) {
                return $this->fail('رقم الحساب مستخدم مسبقاً في هذا الفرع', null, 409);
            }

            // 4. جلب الأب إن وُجد
            $parent = null;
            if ($father !== '0') {
                $parentQuery = Account::where('company_id', $companyId)->where('acc_no', $father);
                if ($branchId === null) {
                    $parentQuery->whereNull('branch_id');
                } else {
                    $parentQuery->where(function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                    });
                }
                $parent = $parentQuery->first();
                if (!$parent) {
                    return $this->fail('الحساب الأب غير موجود في نفس الفرع', null, 422);
                }
            }

            // 5. حساب الحقول تلقائياً
            $level  = $parent ? ((int)$parent->account_level + 1) : 1;
            $type   = $parent ? 'فرعي' : 'رئيسي';
            $nature = $data['account_nature'] ?? $parent?->account_nature ?? 'debit';
            $final  = $data['final_account']  ?? $parent?->final_account  ?? 'الميزانية العمومية';

            // 6. التحقق من القيم المسموحة (لو مُرّرت)
            if (!in_array($nature, ['debit','credit'])) {
                return $this->fail('account_nature يجب أن يكون debit أو credit', null, 422);
            }
            if (!in_array($final, ['الميزانية العمومية','الأرباح والخسائر'])) {
                return $this->fail('final_account غير صحيح', null, 422);
            }

            // 7. الإنشاء
            $account = Account::create([
                'company_id'     => $companyId,
                'branch_id'      => $branchId,
                'acc_no'         => $data['acc_no'],
                'name'           => $data['name'],
                'father_acc_no'  => $father,
                'account_type'   => $type,
                'account_level'  => $level,
                'final_account'  => $final,
                'account_nature' => $nature,
                'is_active'      => $data['is_active'] ?? 1,
                'is_system'      => $data['is_system'] ?? 0,
                'notes'          => $data['notes'] ?? null,
                'user_id'        => $data['user_id'] ?? Auth::id(),
            ]);

            return $this->ok('تم إنشاء الحساب بنجاح', [
                'id'     => $account->id,
                'acc_no' => $account->acc_no,
                'level'  => $account->account_level,
            ], 201);
        } catch (Throwable $e) {
            Log::error('createAccount: ' . $e->getMessage());
            return $this->fail('فشل إنشاء الحساب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * تعديل حساب مع التحقق الكامل (بما فيه إعادة حساب المستوى عند تغيير الأب)
     */
    public function updateAccount(int $id, int $companyId, array $data): array
    {
        try {
            $account = Account::where('id', $id)->where('company_id', $companyId)->first();
            if (!$account) {
                return $this->fail('الحساب غير موجود', null, 404);
            }
            if ($account->is_system == 1) {
                return $this->fail('لا يمكن تعديل حساب نظامي', null, 409);
            }

            $ctx = $this->validateContext(
                $companyId,
                (int)($data['branch_id'] ?? $account->branch_id),
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            $branchId = $account->branch_id;

            // 1. تغيير acc_no: منع التكرار + منع التغيير إن له أبناء
            if (!empty($data['acc_no']) && $data['acc_no'] !== $account->acc_no) {
                $dupQuery = Account::where('company_id', $companyId)->where('acc_no', $data['acc_no'])->where('id', '!=', $id);
                if ($branchId === null) {
                    $dupQuery->whereNull('branch_id');
                } else {
                    $dupQuery->where('branch_id', $branchId);
                }
                if ($dupQuery->exists()) {
                    return $this->fail('رقم الحساب مستخدم مسبقاً', null, 409);
                }

                if (Account::where('company_id', $companyId)->where('father_acc_no', $account->acc_no)->exists()) {
                    return $this->fail('لا يمكن تغيير رقم الحساب لوجود حسابات فرعية تعتمد عليه', null, 409);
                }
            }

            // 2. الأب الجديد
            $newFather = trim((string)($data['father_acc_no'] ?? $account->father_acc_no));
            if ($newFather === '') $newFather = '0';

            $parent = null;
            if ($newFather !== '0') {
                if ($newFather === $account->acc_no) {
                    return $this->fail('لا يمكن أن يكون الحساب أباً لنفسه', null, 422);
                }
                $parentQuery = Account::where('company_id', $companyId)->where('acc_no', $newFather);
                if ($branchId === null) {
                    $parentQuery->whereNull('branch_id');
                } else {
                    $parentQuery->where(function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                    });
                }
                $parent = $parentQuery->first();
                if (!$parent) {
                    return $this->fail('الحساب الأب غير موجود', null, 422);
                }

                // منع الدوران
                if ($this->isDescendant($parent->acc_no, $account->acc_no, $companyId)) {
                    return $this->fail('لا يمكن جعل الحساب أباً لأحد أبنائه (دوران)', null, 422);
                }
            }

            // 3. حساب المستوى والنوع تلقائياً
            $newLevel = $parent ? ((int)$parent->account_level + 1) : 1;
            $newType  = $parent ? 'فرعي' : 'رئيسي';

            // 4. التحقق من القيم المسموحة
            if (isset($data['account_nature']) && !in_array($data['account_nature'], ['debit','credit'])) {
                return $this->fail('account_nature غير صحيح', null, 422);
            }
            if (isset($data['final_account']) && !in_array($data['final_account'], ['الميزانية العمومية','الأرباح والخسائر'])) {
                return $this->fail('final_account غير صحيح', null, 422);
            }

            // 5. التعديل
            $account->update([
                'acc_no'         => $data['acc_no']         ?? $account->acc_no,
                'name'           => $data['name']           ?? $account->name,
                'father_acc_no'  => $newFather,
                'account_type'   => $newType,
                'account_level'  => $newLevel,
                'final_account'  => $data['final_account']  ?? $account->final_account,
                'account_nature' => $data['account_nature'] ?? $account->account_nature,
                'is_active'      => $data['is_active']      ?? $account->is_active,
                'notes'          => $data['notes']          ?? $account->notes,
                'user_id'        => $data['user_id']        ?? Auth::id(),
            ]);

            return $this->ok('تم تعديل الحساب بنجاح');
        } catch (Throwable $e) {
            Log::error('updateAccount: ' . $e->getMessage());
            return $this->fail('فشل تعديل الحساب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * حذف حساب مع كل شروط التحقق
     */
    public function deleteAccount(int $id, int $companyId): array
    {
        try {
            $account = Account::where('id', $id)->where('company_id', $companyId)->first();
            if (!$account) {
                return $this->fail('الحساب غير موجود', null, 404);
            }

            // حساب نظامي
            if ($account->is_system == 1) {
                return $this->fail('لا يمكن حذف حساب نظامي', null, 409);
            }

            // له أبناء
            if (Account::where('company_id', $companyId)->where('father_acc_no', $account->acc_no)->exists()) {
                return $this->fail('لا يمكن حذف حساب له حسابات فرعية', null, 409);
            }

            // مستخدم في قيود
            if (JournalEntryDetail::where('company_id', $companyId)->where('account_id', $account->id)->exists()) {
                return $this->fail('لا يمكن حذف حساب مستخدم في قيود محاسبية', null, 409);
            }

            // ارتباطات أخرى (بدون علاقات — استعلامات مباشرة)
            $checks = [
                ['table' => 'contacts',            'col' => 'acc_no', 'msg' => 'مرتبط بعملاء/موردين'],
                ['table' => 'safes',               'col' => 'acc_no', 'msg' => 'مرتبط بصناديق'],
                ['table' => 'banks',               'col' => 'acc_no', 'msg' => 'مرتبط ببنوك'],
                ['table' => 'expense_categories',  'col' => 'acc_no', 'msg' => 'مرتبط بتصنيفات مصروفات'],
                ['table' => 'account_mappings',    'col' => 'acc_no', 'msg' => 'مرتبط بربط الحسابات الافتراضية'],
            ];
            foreach ($checks as $c) {
                if (DB::table($c['table'])->where('company_id', $companyId)->where($c['col'], $account->acc_no)->exists()) {
                    return $this->fail("لا يمكن حذف حساب {$c['msg']}", null, 409);
                }
            }

            $account->delete();
            return $this->ok('تم حذف الحساب بنجاح');
        } catch (Throwable $e) {
            Log::error('deleteAccount: ' . $e->getMessage());
            return $this->fail('فشل حذف الحساب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب حساب حسب id
     */
    public function getAccountById(int $id, int $companyId): array
    {
        try {
            $account = Account::with(['parent', 'children'])
                ->where('company_id', $companyId)
                ->find($id);

            if (!$account) {
                return $this->fail('الحساب غير موجود', null, 404);
            }
            return $this->ok('تم الجلب', $account);
        } catch (Throwable $e) {
            Log::error('getAccountById: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب حساب حسب acc_no
     */
    public function getAccountByAccNo(string $accNo, int $companyId, ?int $branchId = null): array
    {
        try {
            $q = Account::with(['parent', 'children'])
                ->where('company_id', $companyId)
                ->where('acc_no', $accNo);

            if ($branchId !== null) {
                $q->where(function ($w) use ($branchId) {
                    $w->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            }

            $account = $q->first();
            if (!$account) {
                return $this->fail('الحساب غير موجود', null, 404);
            }
            return $this->ok('تم الجلب', $account);
        } catch (Throwable $e) {
            Log::error('getAccountByAccNo: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب حسابات حسب الاسم (مصفوفة فارغة لو لا نتائج — ليست 404)
     */
    public function getAccountsByName(string $name, int $companyId, ?int $branchId = null): array
    {
        try {
            $q = Account::with(['parent'])
                ->where('company_id', $companyId)
                ->where('name', 'LIKE', "%{$name}%");

            if ($branchId !== null) {
                $q->where(function ($w) use ($branchId) {
                    $w->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            }

            return $this->ok('تم الجلب', $q->orderBy('acc_no')->get());
        } catch (Throwable $e) {
            Log::error('getAccountsByName: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب كل الحسابات مع فلترة وبحث
     */
    public function listAccounts(int $companyId, array $filters = []): array
    {
        try {
            $q = Account::with(['parent'])->where('company_id', $companyId);

            if (!empty($filters['branch_id'])) {
                $q->where(function ($w) use ($filters) {
                    $w->where('branch_id', $filters['branch_id'])->orWhereNull('branch_id');
                });
            }
            foreach (['account_type','account_nature','final_account','is_active'] as $f) {
                if (!empty($filters[$f])) $q->where($f, $filters[$f]);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('acc_no', 'LIKE', "%{$s}%")->orWhere('name', 'LIKE', "%{$s}%");
                });
            }

            return $this->ok('تم الجلب', $q->orderBy('acc_no')->get());
        } catch (Throwable $e) {
            Log::error('listAccounts: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب شجرة الحسابات (هرمية) — O(n) بدل O(n²)
     */
    public function getAccountsTree(int $companyId, ?int $branchId = null): array
    {
        try {
            $q = Account::where('company_id', $companyId);
            if ($branchId !== null) {
                $q->where(function ($w) use ($branchId) {
                    $w->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            }
            $all = $q->orderBy('acc_no')->get();

            // تجميع الأبناء حسب الأب مرة واحدة
            $grouped = $all->groupBy(fn($a) => $a->father_acc_no ?: '0');
            $roots = $grouped->get('0', collect());

            $tree = $roots->map(fn($r) => $this->buildTree($r, $grouped, 0))->values()->all();

            return $this->ok('تم الجلب', $tree);
        } catch (Throwable $e) {
            Log::error('getAccountsTree: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * بناء الشجرة تكراريًا مع حماية من الدوران
     */
    private function buildTree($node, $grouped, int $depth): array
    {
        $children = $depth > 30
            ? []
            : $grouped->get($node->acc_no, collect())
                ->map(fn($c) => $this->buildTree($c, $grouped, $depth + 1))
                ->values()->all();

        return [
            'id'             => $node->id,
            'acc_no'         => $node->acc_no,
            'name'           => $node->name,
            'father_acc_no'  => $node->father_acc_no,
            'account_type'   => $node->account_type,
            'account_level'  => $node->account_level,
            'final_account'  => $node->final_account,
            'account_nature' => $node->account_nature,
            'is_active'      => $node->is_active,
            'children'       => $children,
        ];
    }

    /**
     * توليد رقم حساب فرعي — يجب استدعاؤها داخل DB::transaction
     */
    public function generateSubAccountNo(int $companyId, ?int $branchId, string $fatherAccNo, int $padding = 4): array
    {
        try {
            if (DB::transactionLevel() === 0) {
                return $this->fail('يجب استدعاء الدالة داخل DB::transaction لضمان عدم التكرار', null, 500);
            }

            $fatherAccNo = trim($fatherAccNo);
            if ($fatherAccNo === '' || $fatherAccNo === '0') {
                return $this->fail('لا يمكن توليد حساب فرعي تحت الجذر', null, 422);
            }
            if ($padding < 1 || $padding > 8) {
                return $this->fail('عدد خانات التسلسل يجب أن يكون بين 1 و 8', null, 422);
            }

            $parentQuery = Account::where('company_id', $companyId)->where('acc_no', $fatherAccNo);
            if ($branchId === null) {
                $parentQuery->whereNull('branch_id');
            } else {
                $parentQuery->where(function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            }
            $parent = $parentQuery->first();
            if (!$parent) {
                return $this->fail('الحساب الأب غير موجود في نفس الفرع', null, 422);
            }

            $expectedLength = strlen($fatherAccNo) + $padding;

            $lastQuery = Account::where('company_id', $companyId)
                ->where('father_acc_no', $fatherAccNo)
                ->whereRaw('LENGTH(acc_no) = ?', [$expectedLength])
                ->orderByRaw('CAST(acc_no AS UNSIGNED) DESC')
                ->lockForUpdate();

            if ($branchId === null) {
                $lastQuery->whereNull('branch_id');
            } else {
                $lastQuery->where(function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            }
            $last = $lastQuery->first();

            $nextSeq = 1;
            if ($last) {
                $tail = substr($last->acc_no, strlen($fatherAccNo));
                if (ctype_digit($tail)) $nextSeq = (int)$tail + 1;
            }

            // loop احتياطي
            $guard = 0;
            do {
                $newAccNo = $fatherAccNo . str_pad((string)$nextSeq, $padding, '0', STR_PAD_LEFT);

                $existsQ = Account::where('company_id', $companyId)->where('acc_no', $newAccNo);
                if ($branchId === null) {
                    $existsQ->whereNull('branch_id');
                } else {
                    $existsQ->where(function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                    });
                }
                if (!$existsQ->exists()) break;
                $nextSeq++;
                $guard++;
            } while ($guard < 1000);

            if ($guard >= 1000) {
                return $this->fail('تعذر توليد رقم فريد بعد 1000 محاولة', null, 500);
            }

            return $this->ok('تم توليد رقم الحساب', [
                'acc_no'         => $newAccNo,
                'father_acc_no'  => $fatherAccNo,
                'sequence'       => $nextSeq,
                'level'          => ((int)$parent->account_level) + 1,
                'account_nature' => $parent->account_nature,
                'final_account'  => $parent->final_account,
            ]);
        } catch (Throwable $e) {
            Log::error('generateSubAccountNo: ' . $e->getMessage());
            return $this->fail('فشل توليد الرقم: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * إنشاء حساب فرعي كامل (توليد + إنشاء) داخل Transaction واحدة
     */
    public function createSubAccount(
        int $companyId,
        ?int $branchId,
        string $fatherAccNo,
        string $name,
        ?int $userId = null,
        array $extra = []
    ): array {
        try {
            return DB::transaction(function () use ($companyId, $branchId, $fatherAccNo, $name, $userId, $extra) {

                // 1. توليد رقم الحساب
                $gen = $this->generateSubAccountNo($companyId, $branchId, $fatherAccNo);
                if (!$gen['success']) return $gen;

                // 2. إنشاء الحساب
                $created = $this->createAccount(array_merge([
                    'company_id'    => $companyId,
                    'branch_id'     => $branchId,
                    'acc_no'        => $gen['data']['acc_no'],
                    'name'          => $name,
                    'father_acc_no' => $fatherAccNo,
                    'user_id'       => $userId ?? Auth::id(),
                ], $extra));

                if (!$created['success']) return $created;

                // 3. إرجاع بيانات كاملة + acc_no صريحاً
                return $this->ok('تم إنشاء الحساب الفرعي بنجاح', [
                    'id'             => $created['data']['id'],
                    'acc_no'         => $created['data']['acc_no'],
                    'name'           => $created['data']['name'],
                    'father_acc_no'  => $created['data']['father_acc_no'],
                    'account_type'   => $created['data']['account_type'],
                    'account_level'  => $created['data']['account_level'],
                    'final_account'  => $created['data']['final_account'],
                    'account_nature' => $created['data']['account_nature'],
                    'branch_id'      => $created['data']['branch_id'],
                    'company_id'     => $created['data']['company_id'],
                    'sequence'       => $gen['data']['sequence'],
                ], 201);
            });
        } catch (Throwable $e) {
            Log::error('createSubAccount: ' . $e->getMessage());
            return $this->fail('فشل إنشاء الحساب الفرعي: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        إدارة القيود
    // ============================================================

    public function createJournalEntry(array $header, array $lines): array
    {
        try {
            $ctx = $this->validateContext(
                (int)$header['company_id'],
                (int)$header['branch_id'],
                $header['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            foreach (['journal_date','document_type_id','document_id'] as $f) {
                if (empty($header[$f])) {
                    return $this->fail("الحقل {$f} مطلوب", null, 422);
                }
            }

            if (!DocumentType::where('id', $header['document_type_id'])->exists()) {
                return $this->fail('نوع المستند غير موجود', null, 422);
            }

            $v = $this->validateJournalLines($lines, (int)$header['company_id'], (int)$header['branch_id']);
            if (!$v['success']) return $v;

            $journal = DB::transaction(function () use ($header, $lines) {
                $docNo = $this->numberService->generate(
                    (int)$header['company_id'],
                    (int)$header['branch_id'],
                    (int)$header['document_type_id']
                );

                $journal = JournalEntry::create([
                    'company_id'       => $header['company_id'],
                    'branch_id'        => $header['branch_id'],
                    'doc_no'           => $docNo,
                    'journal_date'     => $header['journal_date'],
                    'document_type_id' => $header['document_type_id'],
                    'document_id'      => $header['document_id'],
                    'currency_id'      => $header['currency_id']    ?? null,
                    'exchange_rate'    => $header['exchange_rate']  ?? 1,
                    'transaction_type' => $header['transaction_type'] ?? 'both',
                    'description'      => $header['description']    ?? null,
                    'status'           => $header['status']         ?? 'draft',
                    'user_id'          => $header['user_id']        ?? Auth::id(),
                ]);

                $journal->details()->createMany($this->mapLinesToDetails($journal, $lines));
                return $journal;
            });

            return $this->ok('تم إنشاء القيد بنجاح', [
                'id'     => $journal->id,
                'doc_no' => $journal->doc_no,
            ], 201);
        } catch (Throwable $e) {
            Log::error('createJournalEntry: ' . $e->getMessage());
            return $this->fail('فشل إنشاء القيد: ' . $e->getMessage(), null, 500);
        }
    }

    public function updateJournalEntry(int $journalId, int $companyId, array $header, array $lines): array
    {
        try {
            $journal = JournalEntry::with('details')->where('company_id', $companyId)->find($journalId);
            if (!$journal) return $this->fail('القيد غير موجود', null, 404);
            if ($journal->status === 'posted') return $this->fail('لا يمكن تعديل قيد مرحّل. استخدم القيد العكسي', null, 409);

            $v = $this->validateJournalLines($lines, $companyId, (int)$journal->branch_id);
            if (!$v['success']) return $v;

            DB::transaction(function () use ($journal, $header, $lines) {
                $journal->update([
                    'journal_date'     => $header['journal_date']     ?? $journal->journal_date,
                    'document_type_id' => $header['document_type_id'] ?? $journal->document_type_id,
                    'document_id'      => $header['document_id']      ?? $journal->document_id,
                    'currency_id'      => $header['currency_id']      ?? $journal->currency_id,
                    'exchange_rate'    => $header['exchange_rate']    ?? $journal->exchange_rate,
                    'transaction_type' => $header['transaction_type'] ?? $journal->transaction_type,
                    'description'      => $header['description']      ?? $journal->description,
                ]);
                $journal->details()->delete();
                $journal->details()->createMany($this->mapLinesToDetails($journal, $lines));
            });

            return $this->ok('تم تعديل القيد بنجاح');
        } catch (Throwable $e) {
            Log::error('updateJournalEntry: ' . $e->getMessage());
            return $this->fail('فشل تعديل القيد: ' . $e->getMessage(), null, 500);
        }
    }

    public function deleteJournalEntry(int $journalId, int $companyId): array
    {
        try {
            $journal = JournalEntry::where('company_id', $companyId)->find($journalId);
            if (!$journal) return $this->fail('القيد غير موجود', null, 404);
            if ($journal->status === 'posted') return $this->fail('لا يمكن حذف قيد مرحّل. استخدم القيد العكسي', null, 409);

            DB::transaction(function () use ($journal) {
                $journal->details()->delete();
                $journal->delete();
            });

            return $this->ok('تم حذف القيد بنجاح');
        } catch (Throwable $e) {
            Log::error('deleteJournalEntry: ' . $e->getMessage());
            return $this->fail('فشل حذف القيد: ' . $e->getMessage(), null, 500);
        }
    }

    public function postJournalEntry(int $journalId, int $companyId): array
    {
        try {
            $journal = JournalEntry::with('details')->where('company_id', $companyId)->find($journalId);
            if (!$journal) return $this->fail('القيد غير موجود', null, 404);
            if ($journal->status === 'posted') return $this->fail('القيد مرحّل مسبقاً', null, 409);

            $v = $this->validateJournalLines($journal->details->toArray(), $companyId, (int)$journal->branch_id);
            if (!$v['success']) return $v;

            $journal->update(['status' => 'posted']);
            return $this->ok('تم ترحيل القيد بنجاح');
        } catch (Throwable $e) {
            Log::error('postJournalEntry: ' . $e->getMessage());
            return $this->fail('فشل ترحيل القيد: ' . $e->getMessage(), null, 500);
        }
    }

    public function reverseJournalEntry(int $journalId, int $companyId, string $reason = ''): array
    {
        try {
            $journal = JournalEntry::with('details')->where('company_id', $companyId)->find($journalId);
            if (!$journal) return $this->fail('القيد غير موجود', null, 404);
            if ($journal->status !== 'posted') return $this->fail('يمكن عكس القيود المرحّلة فقط', null, 409);

            $reversedLines = $journal->details->map(fn($l) => [
                'account_id'      => $l->account_id,
                'cost_center_id'  => $l->cost_center_id,
                'debit_amount'    => $l->credit_amount,
                'credit_amount'   => $l->debit_amount,
                'gold_debit'      => $l->gold_credit,
                'gold_credit'     => $l->gold_debit,
                'silver_debit'    => $l->silver_credit,
                'silver_credit'   => $l->silver_debit,
                'karat_id'        => $l->karat_id,
                'silver_karat_id' => $l->silver_karat_id,
                'description'     => 'عكس: ' . ($l->description ?? ''),
            ])->toArray();

            return $this->createJournalEntry([
                'company_id'       => $journal->company_id,
                'branch_id'        => $journal->branch_id,
                'journal_date'     => now()->toDateString(),
                'document_type_id' => $journal->document_type_id,
                'document_id'      => $journal->document_id,
                'currency_id'      => $journal->currency_id,
                'exchange_rate'    => $journal->exchange_rate,
                'transaction_type' => $journal->transaction_type,
                'description'      => 'قيد عكسي للقيد رقم ' . $journal->doc_no . ' — ' . $reason,
                'status'           => 'posted',
                'user_id'          => Auth::id(),
            ], $reversedLines);
        } catch (Throwable $e) {
            Log::error('reverseJournalEntry: ' . $e->getMessage());
            return $this->fail('فشل عكس القيد: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * التحقق الكامل من سطور القيد (مع فلترة company_id)
     */
    public function validateJournalLines(array $lines, int $companyId, ?int $branchId = null): array
    {
        try {
            if (count($lines) < 2) {
                return $this->fail('القيد يحتاج سطرين على الأقل', null, 422);
            }

            // ===================== الحسابات =====================
            $accountIds = collect($lines)->pluck('account_id')->filter()->unique()->values()->all();

            $existing = Account::where('company_id', $companyId)
                ->whereIn('id', $accountIds)
                ->pluck('id')->all();
            $missing = array_diff($accountIds, $existing);
            if (!empty($missing)) {
                return $this->fail('حسابات غير موجودة أو لا تتبع الشركة: ' . implode(',', $missing), null, 422);
            }

            $inactive = Account::where('company_id', $companyId)
                ->whereIn('id', $accountIds)
                ->where('is_active', 0)
                ->pluck('id')->all();
            if (!empty($inactive)) {
                return $this->fail('حسابات غير مفعلة: ' . implode(',', $inactive), null, 422);
            }

            // ===================== مراكز التكلفة (اختيارية) =====================
            $costCenterIds = collect($lines)->pluck('cost_center_id')->filter()->unique()->values()->all();

            if (!empty($costCenterIds)) {
                $existingCC = CostCenter::where('company_id', $companyId)
                    ->whereIn('id', $costCenterIds)
                    ->pluck('id')->all();
                $missingCC = array_diff($costCenterIds, $existingCC);
                if (!empty($missingCC)) {
                    return $this->fail('مراكز تكلفة غير موجودة أو لا تتبع الشركة: ' . implode(',', $missingCC), null, 422);
                }

                $inactiveCC = CostCenter::where('company_id', $companyId)
                    ->whereIn('id', $costCenterIds)
                    ->where('is_active', 0)
                    ->pluck('id')->all();
                if (!empty($inactiveCC)) {
                    return $this->fail('مراكز تكلفة غير مفعلة: ' . implode(',', $inactiveCC), null, 422);
                }

                if ($branchId !== null) {
                    $mismatch = CostCenter::where('company_id', $companyId)
                        ->whereIn('id', $costCenterIds)
                        ->whereNotNull('branch_id')
                        ->where('branch_id', '!=', $branchId)
                        ->pluck('id')->all();
                    if (!empty($mismatch)) {
                        return $this->fail('مراكز تكلفة لا تتبع فرع القيد: ' . implode(',', $mismatch), null, 422);
                    }
                }
            }

            // ===================== المجاميع والتوازن =====================
            $totalDebit = 0;
            $totalCredit = 0;
            $goldByKarat = [];
            $silverByKarat = [];

            foreach ($lines as $i => $line) {
                $debit   = (float)($line['debit_amount']  ?? 0);
                $credit  = (float)($line['credit_amount'] ?? 0);
                $gDebit  = (float)($line['gold_debit']    ?? 0);
                $gCredit = (float)($line['gold_credit']   ?? 0);
                $sDebit  = (float)($line['silver_debit']  ?? 0);
                $sCredit = (float)($line['silver_credit'] ?? 0);

                if ($debit < 0 || $credit < 0 || $gDebit < 0 || $gCredit < 0 || $sDebit < 0 || $sCredit < 0) {
                    return $this->fail("السطر {$i}: لا يسمح بقيم سالبة", null, 422);
                }

                if ($debit == 0 && $credit == 0 && $gDebit == 0 && $gCredit == 0 && $sDebit == 0 && $sCredit == 0) {
                    return $this->fail("السطر {$i}: يجب إدخال مبلغ أو وزن ذهب أو فضة", null, 422);
                }

                if (($gDebit > 0 || $gCredit > 0) && empty($line['karat_id'])) {
                    return $this->fail("السطر {$i}: karat_id مطلوب عند وجود وزن ذهب", null, 422);
                }

                if (($sDebit > 0 || $sCredit > 0) && empty($line['silver_karat_id']) && empty($line['karat_id'])) {
                    return $this->fail("السطر {$i}: silver_karat_id مطلوب عند وجود وزن فضة", null, 422);
                }

                $totalDebit  += $debit;
                $totalCredit += $credit;

                if ($gDebit > 0 || $gCredit > 0) {
                    $k = $line['karat_id'] ?? 0;
                    $goldByKarat[$k]['debit']  = ($goldByKarat[$k]['debit']  ?? 0) + $gDebit;
                    $goldByKarat[$k]['credit'] = ($goldByKarat[$k]['credit'] ?? 0) + $gCredit;
                }

                if ($sDebit > 0 || $sCredit > 0) {
                    $sk = $line['silver_karat_id'] ?? $line['karat_id'] ?? 0;
                    $silverByKarat[$sk]['debit']  = ($silverByKarat[$sk]['debit']  ?? 0) + $sDebit;
                    $silverByKarat[$sk]['credit'] = ($silverByKarat[$sk]['credit'] ?? 0) + $sCredit;
                }
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                return $this->fail("القيد غير متوازن: مدين={$totalDebit} دائن={$totalCredit}", null, 422);
            }

            foreach ($goldByKarat as $karat => $g) {
                if (round($g['debit'], 3) !== round($g['credit'], 3)) {
                    return $this->fail("الذهب غير متوازن للعيار {$karat}: مدين={$g['debit']} دائن={$g['credit']}", null, 422);
                }
            }

            foreach ($silverByKarat as $karat => $s) {
                if (round($s['debit'], 3) !== round($s['credit'], 3)) {
                    return $this->fail("الفضة غير متوازنة للعيار {$karat}: مدين={$s['debit']} دائن={$s['credit']}", null, 422);
                }
            }

            return $this->ok('السطور سليمة');
        } catch (Throwable $e) {
            Log::error('validateJournalLines: ' . $e->getMessage());
            return $this->fail('فشل التحقق من السطور: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        دوال مساعدة داخلية
    // ============================================================

    private function mapLinesToDetails(JournalEntry $journal, array $lines): array
    {
        return array_map(fn($line) => [
            'company_id'    => $journal->company_id,
            'branch_id'     => $journal->branch_id,
            'account_id'    => $line['account_id'],
            'currency_id'   => $line['currency_id']   ?? $journal->currency_id,
            'exchange_rate' => $line['exchange_rate'] ?? $journal->exchange_rate,
            'debit_amount'  => $line['debit_amount']  ?? 0,
            'credit_amount' => $line['credit_amount'] ?? 0,
            'gold_debit'    => $line['gold_debit']    ?? 0,
            'gold_credit'   => $line['gold_credit']   ?? 0,
            'karat_id'      => $line['karat_id']      ?? null,
            'silver_debit'    => $line['silver_debit']    ?? 0,
            'silver_credit'   => $line['silver_credit']   ?? 0,
            'silver_karat_id' => $line['silver_karat_id'] ?? null,
            'description'   => $line['description']   ?? null,
            'user_id'       => $journal->user_id,
        ], $lines);
    }

    /**
     * هل $childAccNo ابن (مباشر أو غير مباشر) لـ $parentAccNo؟
     */
    private function isDescendant(string $childAccNo, string $parentAccNo, int $companyId): bool
    {
        $current = $childAccNo;
        $guard = 0;
        while ($current && $current !== '0' && $guard < 50) {
            $acc = Account::where('company_id', $companyId)->where('acc_no', $current)->first();
            if (!$acc) break;
            if ($acc->father_acc_no === $parentAccNo) return true;
            $current = $acc->father_acc_no;
            $guard++;
        }
        return false;
    }
}
