<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\DocumentType;
use App\Models\GoldOpeningBalance;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Karat;
use App\Models\Store;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class GoldOpeningBalanceController extends Controller
{
    public function __construct(
        private DocumentNumberService $numberService
    ) {
    }

    public function options(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        try {
            $branches = Branch::where('company_id', $companyId)
                ->where('is_active', 1)
                ->orderBy('id', 'asc')
                ->get(['id', 'name']);

            $stores = Store::where('company_id', $companyId)
                ->where('is_active', 1)
                ->orderBy('id', 'asc')
                ->get(['id', 'name', 'branch_id', 'is_main', 'account_id']);

            $karats = Karat::where('company_id', $companyId)
                            ->where('is_active', 1)
                            ->orderByRaw("CASE WHEN TRIM(name) = '21' THEN 0 ELSE 1 END ASC")
                            ->orderBy('id', 'asc')
                            ->get(['id', 'name', 'the_value']);

            $capitalAccount = Account::where('company_id', $companyId)
                ->where('acc_no', '211')
                ->first(['id', 'acc_no', 'name', 'account_nature']);

            $docType = $this->resolveGoldOpeningDocType($companyId);

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => 'تم الجلب',
                'data' => [
                    'branches' => $branches,
                    'stores' => $stores,
                    'karats' => $karats,
                    'capital_account' => $capitalAccount,
                    'document_type' => $docType,
                ],
            ]);
        } catch (Throwable $e) {
            return $this->error('فشل الجلب: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        try {
            $query = GoldOpeningBalance::with([
                'account:id,acc_no,name,account_nature',
                'branch:id,name',
                'store:id,name,account_id',
                'karat:id,name,the_value',
                'user:id,name',
                'documentType:id,name,type_code',
            ])->where('company_id', $companyId);

            if ($request->filled('branch_id')) {
                $query->where('branch_id', (int) $request->input('branch_id'));
            }
            if ($request->filled('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
            if ($request->filled('karat_id')) {
                $query->where('karat_id', (int) $request->input('karat_id'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->input('user_id'));
            }
            if ($request->filled('from')) {
                $query->whereDate('opening_date', '>=', date('Y-m-d', strtotime((string) $request->input('from'))));
            }
            if ($request->filled('to')) {
                $query->whereDate('opening_date', '<=', date('Y-m-d', strtotime((string) $request->input('to'))));
            }
            if ($request->filled('q')) {
                $s = trim((string) $request->input('q'));
                $query->where(function ($sub) use ($s) {
                    $sub->where('doc_no', 'LIKE', "%{$s}%")
                        ->orWhere('description', 'LIKE', "%{$s}%")
                        ->orWhereHas('store', function ($stQ) use ($s) {
                            $stQ->where('name', 'LIKE', "%{$s}%");
                        })
                        ->orWhereHas('karat', function ($kQ) use ($s) {
                            $kQ->where('name', 'LIKE', "%{$s}%");
                        });
                });
            }

            $totalWeight = (float) (clone $query)->sum('weight');
            $totalAmount = (float) (clone $query)->sum('total_amount');

            $items = $query
                ->orderBy('opening_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $rows = $items->map(function (GoldOpeningBalance $item) {
                return [
                    'id' => $item->id,
                    'company_id' => $item->company_id,
                    'branch_id' => $item->branch_id,
                    'store_id' => $item->store_id,
                    'account_id' => $item->account_id,
                    'karat_id' => $item->karat_id,
                    'document_type_id' => $item->document_type_id,
                    'journal_id' => $item->journal_id,
                    'doc_no' => $item->doc_no,
                    'opening_date' => optional($item->opening_date)->format('Y-m-d'),
                    'weight' => (float) $item->weight,
                    'price' => (float) ($item->price ?? 0),
                    'total_amount' => (float) ($item->total_amount ?? 0),
                    'side' => $item->side,
                    'description' => $item->description,
                    'user_id' => $item->user_id,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                    'acc_no' => $item->account?->acc_no,
                    'account_name' => $item->account?->name,
                    'branch_name' => $item->branch?->name,
                    'store_name' => $item->store?->name,
                    'karat_name' => $item->karat?->name,
                    'karat_value' => $item->karat?->the_value,
                    'user_name' => $item->user?->name,
                    'document_type_name' => $item->documentType?->name,
                    'document_type_code' => $item->documentType?->type_code,
                ];
            });

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => 'تم جلب أرصدة الذهب الافتتاحية',
                'data' => $rows,
                'totals' => [
                    'total_weight' => round($totalWeight, 3),
                    'total_amount' => round($totalAmount, 2),
                    'count' => $rows->count(),
                ],
            ]);
        } catch (Throwable $e) {
            return $this->error('فشل الجلب: ' . $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $userId = (int) $user->id;

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'store_id' => ['required', 'integer'],
            'karat_id' => ['required', 'integer'],
            'weight' => ['required', 'numeric', 'gt:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'opening_date' => ['required', 'date'],
            'doc_no' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $created = DB::transaction(function () use ($companyId, $userId, $validated) {
                $store = Store::where('company_id', $companyId)->find((int) $validated['store_id']);
                if (!$store) {
                    throw new \RuntimeException('الخزينة المحدد غير موجود');
                }

                $karat = Karat::where('company_id', $companyId)->find((int) $validated['karat_id']);
                if (!$karat) {
                    throw new \RuntimeException('العيار المحدد غير موجود');
                }

                $capitalAccount = $this->resolveCapitalAccount($companyId);
                $storeAccount = $this->resolveStoreAccount($companyId, $store);

                $docType = $this->resolveGoldOpeningDocType($companyId);
                if (!$docType) {
                    throw new \RuntimeException('نوع المستند OPENING_GOLD غير معرف في جدول document_types');
                }

                $branchId = !empty($validated['branch_id']) ? (int) $validated['branch_id'] : (int) ($store->branch_id ?? 1);
                $weight = round((float) $validated['weight'], 3);
                $price = round((float) $validated['price'], 2);
                $totalAmount = isset($validated['total_amount'])
                    ? round((float) $validated['total_amount'], 2)
                    : round($weight * $price, 2);
                $openingDate = date('Y-m-d', strtotime((string) $validated['opening_date']));
                $description = trim((string) ($validated['description'] ?? ('رصيد افتتاحي ذهب - ' . $store->name)));

                $docNo = !empty($validated['doc_no'])
                    ? trim((string) $validated['doc_no'])
                    : $this->numberService->generate($companyId, $branchId, (int) $docType->id);

                $journal = JournalEntry::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'doc_no' => $docNo,
                    'journal_date' => $openingDate,
                    'document_type_id' => (int) $docType->id,
                    'transaction_type' => 'gold',
                    'description' => $description,
                    'status' => 'posted',
                    'user_id' => $userId,
                ]);

                // تسجيل وزن الذهب فقط بدون قيمة الذهب في القيد
                JournalEntryDetail::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'journal_id' => (int) $journal->id,
                    'account_id' => (int) $storeAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 0,
                    'gold_debit' => $weight,
                    'gold_credit' => 0,
                    'karat_id' => (int) $karat->id,
                    'description' => $description,
                    'user_id' => $userId,
                ]);

                JournalEntryDetail::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'journal_id' => (int) $journal->id,
                    'account_id' => (int) $capitalAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 0,
                    'gold_debit' => 0,
                    'gold_credit' => $weight,
                    'karat_id' => (int) $karat->id,
                    'description' => $description,
                    'user_id' => $userId,
                ]);

                $opening = GoldOpeningBalance::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'store_id' => (int) $store->id,
                    'account_id' => (int) $capitalAccount->id,
                    'karat_id' => (int) $karat->id,
                    'document_type_id' => (int) $docType->id,
                    'journal_id' => (int) $journal->id,
                    'doc_no' => $docNo,
                    'opening_date' => $openingDate,
                    'weight' => $weight,
                    'price' => $price,
                    'total_amount' => $totalAmount,
                    'side' => 'credit',
                    'description' => $description,
                    'user_id' => $userId,
                ]);

                $journal->update(['document_id' => (int) $opening->id]);

                return $opening;
            });

            return response()->json([
                'success' => true,
                'code' => 201,
                'message' => 'تم حفظ الرصيد الافتتاحي ',
                'data' => $created,
            ], 201);
        } catch (Throwable $e) {
            return $this->error('فشل الحفظ: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $userId = (int) $user->id;

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'store_id' => ['required', 'integer'],
            'karat_id' => ['required', 'integer'],
            'weight' => ['required', 'numeric', 'gt:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'opening_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $updated = DB::transaction(function () use ($companyId, $userId, $id, $validated) {
                $opening = GoldOpeningBalance::where('company_id', $companyId)
                    ->where('id', (int) $id)
                    ->lockForUpdate()
                    ->first();

                if (!$opening) {
                    throw new \RuntimeException('السجل غير موجود');
                }

                $store = Store::where('company_id', $companyId)->find((int) $validated['store_id']);
                if (!$store) {
                    throw new \RuntimeException('الخزينة المحدد غير موجود');
                }

                $karat = Karat::where('company_id', $companyId)->find((int) $validated['karat_id']);
                if (!$karat) {
                    throw new \RuntimeException('العيار المحدد غير موجود');
                }

                $capitalAccount = $this->resolveCapitalAccount($companyId);
                $storeAccount = $this->resolveStoreAccount($companyId, $store);

                $branchId = !empty($validated['branch_id']) ? (int) $validated['branch_id'] : (int) ($store->branch_id ?? $opening->branch_id ?? 1);
                $weight = round((float) $validated['weight'], 3);
                $price = round((float) $validated['price'], 2);
                $totalAmount = isset($validated['total_amount'])
                    ? round((float) $validated['total_amount'], 2)
                    : round($weight * $price, 2);
                $openingDate = date('Y-m-d', strtotime((string) $validated['opening_date']));
                $description = trim((string) ($validated['description'] ?? ('رصيد افتتاحي ذهب - ' . $store->name)));

                $opening->update([
                    'branch_id' => $branchId,
                    'store_id' => (int) $store->id,
                    'account_id' => (int) $capitalAccount->id,
                    'karat_id' => (int) $karat->id,
                    'opening_date' => $openingDate,
                    'weight' => $weight,
                    'price' => $price,
                    'total_amount' => $totalAmount,
                    'side' => 'credit',
                    'description' => $description,
                ]);

                if (!empty($opening->journal_id)) {
                    $journal = JournalEntry::where('company_id', $companyId)->find((int) $opening->journal_id);
                    if ($journal) {
                        $journal->update([
                            'branch_id' => $branchId,
                            'journal_date' => $openingDate,
                            'transaction_type' => 'gold',
                            'description' => $description,
                        ]);

                        JournalEntryDetail::where('company_id', $companyId)
                            ->where('journal_id', (int) $journal->id)
                            ->delete();

                        // تسجيل وزن الذهب فقط بدون قيمة الذهب في القيد
                        JournalEntryDetail::create([
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'journal_id' => (int) $journal->id,
                            'account_id' => (int) $storeAccount->id,
                            'debit_amount' => 0,
                            'credit_amount' => 0,
                            'gold_debit' => $weight,
                            'gold_credit' => 0,
                            'karat_id' => (int) $karat->id,
                            'description' => $description,
                            'user_id' => $userId,
                        ]);

                        JournalEntryDetail::create([
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'journal_id' => (int) $journal->id,
                            'account_id' => (int) $capitalAccount->id,
                            'debit_amount' => 0,
                            'credit_amount' => 0,
                            'gold_debit' => 0,
                            'gold_credit' => $weight,
                            'karat_id' => (int) $karat->id,
                            'description' => $description,
                            'user_id' => $userId,
                        ]);
                    }
                }

                return $opening;
            });

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => 'تم تحديث الرصيد الافتتاحي ',
                'data' => $updated,
            ]);
        } catch (Throwable $e) {
            return $this->error('فشل التحديث: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        try {
            DB::transaction(function () use ($companyId, $id) {
                $opening = GoldOpeningBalance::where('company_id', $companyId)
                    ->where('id', (int) $id)
                    ->lockForUpdate()
                    ->first();

                if (!$opening) {
                    throw new \RuntimeException('السجل غير موجود');
                }

                if (!empty($opening->journal_id)) {
                    JournalEntryDetail::where('company_id', $companyId)
                        ->where('journal_id', (int) $opening->journal_id)
                        ->delete();

                    JournalEntry::where('company_id', $companyId)
                        ->where('id', (int) $opening->journal_id)
                        ->delete();
                }

                $opening->delete();
            });

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => 'تم حذف الرصيد الافتتاحي للذهب ',
            ]);
        } catch (Throwable $e) {
            return $this->error('فشل الحذف: ' . $e->getMessage(), 500);
        }
    }

    private function resolveCapitalAccount(int $companyId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('acc_no', '211')
            ->first();

        if (!$account) {
            throw new \RuntimeException('حساب رأس المال برقم (211) غير موجود في دليل الحسابات');
        }

        return $account;
    }

    private function resolveStoreAccount(int $companyId, Store $store): Account
    {
        if (!empty($store->account_id)) {
            $acc = Account::where('company_id', $companyId)->find((int) $store->account_id);
            if ($acc)
                return $acc;
        }

        $acc = Account::where('company_id', $companyId)
            ->where('is_active', 1)
            ->where(function ($q) use ($store) {
                $q->where('name', 'LIKE', '%' . $store->name . '%')
                    ->orWhere('name', 'LIKE', '%مخزن%')
                    ->orWhere('name', 'LIKE', '%بضاعة%')
                    ->orWhere('name', 'LIKE', '%ذهب%');
            })
            ->where('acc_no', '!=', '211')
            ->orderBy('id', 'asc')
            ->first();

        if (!$acc) {
            throw new \RuntimeException('لم يتم العثور على حساب مرتبط بالخزينة: ' . $store->name);
        }

        return $acc;
    }

    private function resolveGoldOpeningDocType(int $companyId)
    {
        return DocumentType::where('is_active', 1)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->where('type_code', 'OPENING_GOLD')
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id', 'asc')
            ->first();
    }

    private function error(string $message, int $code)
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], $code);
    }
}
