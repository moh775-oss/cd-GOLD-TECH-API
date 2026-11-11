<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\DocumentType;
use App\Models\JournalEntry;
use App\Services\Concerns\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactService
{
    use ApiResponse;

    public function __construct(
        private MasterService $master,
        private IntegrityService $integrity
    ) {}


    public function create(array $data): array
    {
        try {
            $ctx = $this->master->validateContext(
                (int)$data['company_id'],
                (int)$data['branch_id'],
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            foreach (['name','contact_type'] as $f) {
                if (empty($data[$f])) {
                    return $this->fail("الحقل {$f} مطلوب", null, 422);
                }
            }
            if (!in_array($data['contact_type'], ['customer','supplier','both'])) {
                return $this->fail('contact_type يجب أن يكون customer أو supplier أو both', null, 422);
            }

            $companyId = (int)$data['company_id'];
            $branchId  = (int)$data['branch_id'];
            $userId    = $data['user_id'] ?? Auth::id();
            $opening   = (float)($data['opening_balance'] ?? 0);

            $parentAccNo = match($data['contact_type']) {
                'customer' => '123',
                'supplier' => '261',
                'both'     => '126',
            };

            $accountNature = match($data['contact_type']) {
                'customer' => 'debit',
                'supplier' => 'credit',
                'both'     => $opening >= 0 ? 'debit' : 'credit',
            };

            $finalAccount = 'الميزانية العمومية';

            return DB::transaction(function () use ($data, $companyId, $branchId, $userId, $opening, $parentAccNo, $accountNature, $finalAccount) {

                $accResult = $this->master->createSubAccount(
                    $companyId, $branchId, $parentAccNo, $data['name'], $userId,
                    ['account_nature' => $accountNature, 'final_account' => $finalAccount]
                );
                if (!$accResult['success']) {
                    throw new \RuntimeException($accResult['message']);
                }
                $accNo = $accResult['data']['acc_no'];

                $contact = Contact::create([
                    'company_id'          => $companyId,
                    'branch_id'           => $branchId,
                    'name'                => $data['name'],
                    'name_en'             => $data['name_en']             ?? null,
                    'contact_type'        => $data['contact_type'],
                    'acc_no'              => $accNo,
                    'phone'               => $data['phone']               ?? null,
                    'mobile'              => $data['mobile']              ?? null,
                    'whatsapp'            => $data['whatsapp']            ?? null,
                    'email'               => $data['email']               ?? null,
                    'website'             => $data['website']             ?? null,
                    'tax_number'          => $data['tax_number']          ?? null,
                    'commercial_register' => $data['commercial_register'] ?? null,
                    'national_id'         => $data['national_id']         ?? null,
                    'address'             => $data['address']             ?? null,
                    'city'                => $data['city']                ?? null,
                    'district'            => $data['district']            ?? null,
                    'street'              => $data['street']              ?? null,
                    'building_no'         => $data['building_no']         ?? null,
                    'postal_code'         => $data['postal_code']         ?? null,
                    'extra_no'            => $data['extra_no']            ?? null,
                    'country'             => $data['country']             ?? 'السعودية',
                    'credit_limit'        => $data['credit_limit']        ?? 0,
                    'opening_balance'     => $opening,
                    'balance'             => $opening,
                    'notes'               => $data['notes']               ?? null,
                    'is_active'           => $data['is_active']           ?? 1,
                    'user_id'             => $userId,
                ]);

                if ($opening != 0) {
                    $this->createOpeningJournal($contact, $companyId, $branchId, $userId, $opening, $parentAccNo);
                }

                return $this->ok('تم إضافة الطرف بنجاح', [
                    'contact' => $contact,
                    'acc_no'  => $accNo,
                ], 201);
            });
        } catch (Throwable $e) {
            Log::error('ContactService@create: ' . $e->getMessage());
            return $this->fail('فشل إضافة الطرف: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        قيد افتتاحي
    // ============================================================

    private function createOpeningJournal(Contact $contact, int $companyId, int $branchId, ?int $userId, float $amount, string $parentAccNo): int
    {
        $docType = DocumentType::where('type_code', 'OPENING_BALANCE')->first();
        if (!$docType) throw new \RuntimeException('نوع المستند OPENING_BALANCE غير موجود');

        $counterAcc = Account::where('company_id', $companyId)->where('acc_no', '212')->first();
        if (!$counterAcc) throw new \RuntimeException('حساب 212 غير موجود');

        $contactAcc = Account::where('company_id', $companyId)->where('acc_no', $contact->acc_no)->first();
        if (!$contactAcc) throw new \RuntimeException('حساب الطرف غير موجود');

        $abs = abs($amount);
        $desc = 'رصيد افتتاحي - ' . $contact->name;

        if ($contact->contact_type === 'customer') {
            $lines = [
                ['account_id' => $contactAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $counterAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ];
        } elseif ($contact->contact_type === 'supplier') {
            $lines = [
                ['account_id' => $counterAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $contactAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ];
        } else {
            if ($amount >= 0) {
                $lines = [
                    ['account_id' => $contactAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                    ['account_id' => $counterAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
                ];
            } else {
                $lines = [
                    ['account_id' => $counterAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                    ['account_id' => $contactAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
                ];
            }
        }

        $result = $this->master->createJournalEntry([
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'journal_date'     => now()->toDateString(),
            'document_type_id' => $docType->id,
            'document_id'      => $contact->id,
            'transaction_type' => 'cash',
            'description'      => 'قيد افتتاحي - ' . $contact->name,
            'status'           => 'posted',
            'user_id'          => $userId,
        ], $lines);

        if (!$result['success']) {
            throw new \RuntimeException($result['message']);
        }

        return (int)$result['data']['id'];
    }

    // ============================================================
    //                تعديل القيد الافتتاحي (نفس journal_id)
    // ============================================================

    private function updateOpeningJournal(
    Contact $contact,
    int $companyId,
    int $branchId,
    ?int $userId,
    float $newAmount,
    string $parentAccNo,
    string $newType
): void {

    $journal = JournalEntry::where('company_id', $companyId)
        ->where('document_id', $contact->id)
        ->where('document_type_id', function ($q) {
            $q->select('id')->from('document_types')->where('type_code', 'OPENING_BALANCE')->limit(1);
        })
        ->first();

    // لو مش لاقي، جرّب بدون document_type_id
    if (!$journal) {
        $journal = JournalEntry::where('company_id', $companyId)
            ->where('document_id', $contact->id)
            ->first();
    }

    // مفيش قيد قديم → أنشئ واحد جديد (لو الرصيد != 0)
    if (!$journal) {
        if ($newAmount != 0) {
            $this->createOpeningJournal($contact, $companyId, $branchId, $userId, $newAmount, $parentAccNo);
        }
        return;
    }

    // الرصيد الجديد = 0 → امسح القيد
    if ($newAmount == 0) {
        DB::table('journal_entry_details')->where('journal_id', $journal->id)->delete();
        DB::table('journal_entries')->where('id', $journal->id)->delete();
        return;
    }


    $counterAcc = Account::where('company_id', $companyId)->where('acc_no', '212')->first();
    if (!$counterAcc) throw new \RuntimeException('حساب 212 غير موجود');

    $contactAcc = Account::where('company_id', $companyId)->where('acc_no', $contact->acc_no)->first();
    if (!$contactAcc) throw new \RuntimeException('حساب الطرف غير موجود');

    $abs = abs($newAmount);
    $desc = 'رصيد افتتاحي - ' . $contact->name;

    if ($newType === 'customer') {
        $lines = [
            ['account_id' => $contactAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
            ['account_id' => $counterAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
        ];
    } elseif ($newType === 'supplier') {
        $lines = [
            ['account_id' => $counterAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
            ['account_id' => $contactAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
        ];
    } else {
        if ($newAmount >= 0) {
            $lines = [
                ['account_id' => $contactAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $counterAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ];
        } else {
            $lines = [
                ['account_id' => $counterAcc->id, 'debit_amount' => $abs, 'credit_amount' => 0,   'description' => $desc],
                ['account_id' => $contactAcc->id, 'debit_amount' => 0,   'credit_amount' => $abs, 'description' => $desc],
            ];
        }
    }

    // ✅ امسح التفاصيل القديمة فقط، وحدّث القيد نفسه
    DB::table('journal_entry_details')->where('journal_id', $journal->id)->delete();

    DB::table('journal_entry_details')->insert(
        array_map(fn($line) => [
            'company_id'      => $companyId,
            'branch_id'       => $branchId,
            'journal_id'      => $journal->id,
            'account_id'      => $line['account_id'],
            'currency_id'     => null,
            'exchange_rate'   => 1,
            'debit_amount'    => $line['debit_amount'],
            'credit_amount'   => $line['credit_amount'],
            'gold_debit'      => 0,
            'gold_credit'     => 0,
            'silver_debit'    => 0,
            'silver_credit'   => 0,
            'karat_id'        => null,
            'silver_karat_id' => null,
            'description'     => $line['description'],
            'user_id'         => $userId,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $lines)
    );

    // ✅ القيد `journal_entries` بيفضل زي ما هو (نفس الـ id)
    DB::table('journal_entries')->where('id', $journal->id)->update([
        'updated_at' => now(),
        'user_id'    => $userId,
    ]);
}

    // ============================================================
    //                        تعديل
    // ============================================================

    public function update(int $id, int $companyId, array $data): array
    {
        try {
            $contact = Contact::where('id', $id)->where('company_id', $companyId)->first();
            if (!$contact) return $this->fail('الطرف غير موجود', null, 404);

            $ctx = $this->master->validateContext(
                $companyId,
                (int)($data['branch_id'] ?? $contact->branch_id),
                $data['user_id'] ?? Auth::id()
            );
            if (!$ctx['success']) return $ctx;

            if (isset($data['contact_type']) && !in_array($data['contact_type'], ['customer','supplier','both'])) {
                return $this->fail('contact_type غير صحيح', null, 422);
            }

            $branchId = (int)($data['branch_id'] ?? $contact->branch_id);
            $userId   = $data['user_id'] ?? Auth::id();

            $oldOpening = (float)$contact->opening_balance;
            $newOpening = array_key_exists('opening_balance', $data) && $data['opening_balance'] !== null
                ? (float)$data['opening_balance']
                : $oldOpening;

            $newType = $data['contact_type'] ?? $contact->contact_type;

            $parentAccNo = match($newType) {
                'customer' => '123',
                'supplier' => '261',
                'both'     => '126',
            };

            return DB::transaction(function () use ($contact, $data, $companyId, $branchId, $userId, $newOpening, $newType, $parentAccNo) {

                $contact->update([
                    'name'                => $data['name']                ?? $contact->name,
                    'name_en'             => $data['name_en']             ?? $contact->name_en,
                    'contact_type'        => $newType,
                    'phone'               => $data['phone']               ?? $contact->phone,
                    'mobile'              => $data['mobile']              ?? $contact->mobile,
                    'whatsapp'            => $data['whatsapp']            ?? $contact->whatsapp,
                    'email'               => $data['email']               ?? $contact->email,
                    'website'             => $data['website']             ?? $contact->website,
                    'tax_number'          => $data['tax_number']          ?? $contact->tax_number,
                    'commercial_register' => $data['commercial_register'] ?? $contact->commercial_register,
                    'national_id'         => $data['national_id']         ?? $contact->national_id,
                    'address'             => $data['address']             ?? $contact->address,
                    'city'                => $data['city']                ?? $contact->city,
                    'district'            => $data['district']            ?? $contact->district,
                    'street'              => $data['street']              ?? $contact->street,
                    'building_no'         => $data['building_no']         ?? $contact->building_no,
                    'postal_code'         => $data['postal_code']         ?? $contact->postal_code,
                    'extra_no'            => $data['extra_no']            ?? $contact->extra_no,
                    'country'             => $data['country']             ?? $contact->country,
                    'credit_limit'        => $data['credit_limit']        ?? $contact->credit_limit,
                    'opening_balance'     => $newOpening,
                    'balance'             => $newOpening,
                    'notes'               => $data['notes']               ?? $contact->notes,
                    'is_active'           => $data['is_active']           ?? $contact->is_active,
                    'user_id'             => $userId,
                ]);

                $contact->refresh();

                $this->updateOpeningJournal($contact, $companyId, $branchId, $userId, $newOpening, $parentAccNo, $newType);

                return $this->ok('تم تعديل الطرف بنجاح', $contact);
            });
        } catch (Throwable $e) {
            Log::error('ContactService@update: ' . $e->getMessage());
            return $this->fail('فشل تعديل الطرف: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        حذف
    // ============================================================

   public function delete(int $id, int $companyId): array
    {
        try {
            $contact = Contact::where('id', $id)->where('company_id', $companyId)->first();
            if (!$contact) return $this->fail('الطرف غير موجود', null, 404);


            $error = $this->integrity->checkContact($contact);
            if ($error) {
                return $this->fail($error, null, 409);
            }

            // ✅ الحذف — بس لو مفيش أي قيود/حركات
            return DB::transaction(function () use ($contact, $companyId) {
                $acc = Account::where('company_id', $companyId)->where('acc_no', $contact->acc_no)->first();
                if ($acc) {
                    $acc->delete();
                }

                $contact->delete();
                return $this->ok('تم حذف الطرف بنجاح');
            });
        } catch (Throwable $e) {
            Log::error('ContactService@delete: ' . $e->getMessage());
            return $this->fail('فشل حذف الطرف: ' . $e->getMessage(), null, 500);
        }
    }

    // ============================================================
    //                        جلب
    // ============================================================

    public function getById(int $id, int $companyId): array
    {
        try {
            $contact = Contact::with(['account'])->where('company_id', $companyId)->find($id);
            if (!$contact) return $this->fail('الطرف غير موجود', null, 404);
            return $this->ok('تم الجلب', $contact);
        } catch (Throwable $e) {
            Log::error('ContactService@getById: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }

    public function list(int $companyId, array $filters = []): array
    {
        try {
            $q = Contact::where('company_id', $companyId);

            if (!empty($filters['branch_id'])) {
                $q->where(function ($w) use ($filters) {
                    $w->where('branch_id', $filters['branch_id'])->orWhereNull('branch_id');
                });
            }
            if (!empty($filters['contact_type'])) {
                $q->where('contact_type', $filters['contact_type']);
            }
            if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                $q->where('is_active', $filters['is_active']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'LIKE', "%{$s}%")
                      ->orWhere('mobile', 'LIKE', "%{$s}%")
                      ->orWhere('phone', 'LIKE', "%{$s}%")
                      ->orWhere('acc_no', 'LIKE', "%{$s}%")
                      ->orWhere('tax_number', 'LIKE', "%{$s}%");
                });
            }

            return $this->ok('تم الجلب', $q->orderBy('name')->get());
        } catch (Throwable $e) {
            Log::error('ContactService@list: ' . $e->getMessage());
            return $this->fail('فشل الجلب: ' . $e->getMessage(), null, 500);
        }
    }
}
