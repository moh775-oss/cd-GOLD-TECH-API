<?php

namespace App\Services\Concerns;

trait ApiResponse
{
    // استجابة نجاح موحّدة
    protected function ok(string $message = 'تمت العملية بنجاح', $data = null, int $code = 200): array
    {
        return ['success' => true,  'message' => $message, 'data' => $data, 'code' => $code];
    }

    // استجابة فشل موحّدة
    protected function fail(string $message = 'فشلت العملية', $data = null, int $code = 400): array
    {
        return ['success' => false, 'message' => $message, 'data' => $data, 'code' => $code];
    }
}
