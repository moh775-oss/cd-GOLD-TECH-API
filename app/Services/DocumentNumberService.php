<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function generate(int $companyId, int $branchId, int $documentTypeId): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $documentTypeId) {
            $seq = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type_id', $documentTypeId)
                ->lockForUpdate()
                ->first();

            if (!$seq) {
                DB::table('document_sequences')->insert([
                    'company_id'       => $companyId,
                    'branch_id'        => $branchId,
                    'document_type_id' => $documentTypeId,
                    'next_number'      => 1,
                    'padding'          => 0,
                    'prefix'           => null,
                    'created_at'       => now(),
                ]);

                $seq = DB::table('document_sequences')
                    ->where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->where('document_type_id', $documentTypeId)
                    ->lockForUpdate()
                    ->first();
            }

            $current = (int) $seq->next_number;

            DB::table('document_sequences')
                ->where('id', $seq->id)
                ->update([
                    'next_number' => $current + 1,
                    'updated_at'  => now(),
                ]);

            return $current;
        });
    }

    public function reset(int $companyId, int $branchId, int $documentTypeId, int $start = 1): void
    {
        DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->update([
                'next_number' => $start,
                'updated_at'  => now(),
            ]);
    }
}
