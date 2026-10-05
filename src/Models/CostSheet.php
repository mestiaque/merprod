<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

/**
 * Pre-order costing. Every cost column is per dozen; per-piece figures
 * (offer / target / final price) are per piece. recalculate() is the only
 * place the totals are derived.
 */
class CostSheet extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'approved' => ['Approved', 'success'],
    ];

    public const GROUPS = [
        'fabric' => 'Fabric',
        'trims' => 'Trims & Accessories',
        'process' => 'Wash / Print / Embroidery',
    ];

    protected $table = 'msfl_cost_sheets';

    protected $fillable = [
        'cost_sheet_no', 'buyer_id', 'style_id', 'inquiry_id', 'style_ref', 'garment_description', 'size_range',
        'currency_id', 'costing_date', 'order_qty', 'smv', 'cm_cost', 'commercial_percent', 'other_cost',
        'profit_percent', 'buyer_target_price', 'final_price', 'remarks', 'created_by',
    ];

    protected $casts = [
        'costing_date' => 'date',
        'approved_at' => 'datetime',
        'smv' => 'decimal:2',
        'fabric_cost' => 'decimal:4',
        'trims_cost' => 'decimal:4',
        'process_cost' => 'decimal:4',
        'cm_cost' => 'decimal:4',
        'commercial_percent' => 'decimal:2',
        'commercial_cost' => 'decimal:4',
        'other_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'profit_percent' => 'decimal:2',
        'profit_amount' => 'decimal:4',
        'offer_price' => 'decimal:4',
        'buyer_target_price' => 'decimal:4',
        'final_price' => 'decimal:4',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CostSheetItem::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function materialCost(): float
    {
        return (float) $this->fabric_cost + (float) $this->trims_cost + (float) $this->process_cost;
    }

    /**
     * Per dozen:
     *   material   = fabric + trims + process (sum of line amounts)
     *   commercial = material x commercial %
     *   total      = material + CM + commercial + other
     *   profit     = total x profit %
     * Per piece:
     *   offer      = (total + profit) / 12
     */
    public function recalculate(): void
    {
        $this->loadMissing('items');

        foreach (array_keys(self::GROUPS) as $group) {
            $this->{$group . '_cost'} = round($this->items->where('group', $group)->sum('amount'), 4);
        }

        $material = $this->materialCost();
        $this->commercial_cost = round($material * (float) $this->commercial_percent / 100, 4);
        $this->total_cost = round($material + (float) $this->cm_cost + (float) $this->commercial_cost + (float) $this->other_cost, 4);
        $this->profit_amount = round((float) $this->total_cost * (float) $this->profit_percent / 100, 4);
        $this->offer_price = round(((float) $this->total_cost + (float) $this->profit_amount) / 12, 4);

        $this->save();
    }
}
