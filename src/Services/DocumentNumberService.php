<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * "<PREFIX>-<YYYY>-<0001>" numbers (prefixes in config number_prefixes).
 * Call inside the same DB transaction that inserts the row — the latest
 * number of the year is read with a row lock so two saves can't collide.
 */
class DocumentNumberService
{
    /** @param class-string<Model> $model */
    public function next(string $type, string $model, string $column): string
    {
        $prefix = config("merchandising-sfl.number_prefixes.$type", strtoupper($type)) . '-' . now()->format('Y') . '-';

        $query = $model::query();
        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        $last = $query->where($column, 'like', $prefix . '%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
