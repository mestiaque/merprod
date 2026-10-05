<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/**
 * Garment size, ordered by sort_order.
 * Read-only here: entered once in Inventory → Sizes.
 */
class Size extends Model
{
    use IsMaster;
    use SoftDeletes;

    protected $table = 'inv_sizes';

    protected $guarded = ['*'];

    protected $casts = ['is_active' => 'boolean'];
}
