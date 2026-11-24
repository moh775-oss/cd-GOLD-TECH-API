<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasVoucherNavigation
{
    /**
     * يحسب prev_id/next_id على نفس الفلتر وبنفس الترتيب
     */
    protected function navigation(Builder $q, int $currentId, string $orderCol = 'id'): array
    {
        $prev = (clone $q)
            ->where($orderCol, '<', $currentId)
            ->orderByDesc($orderCol)
            ->value('id');

        $next = (clone $q)
            ->where($orderCol, '>', $currentId)
            ->orderBy($orderCol)
            ->value('id');

        return ['prev_id' => $prev, 'next_id' => $next];
    }
}