<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class MenuItemVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'menu_item_id',
        'name',
        'price',
        'is_active',
        'sku',
        'position',

        // New: per-variant discount fields
        'discount_type',
        'discount_value',
        'discount_starts_at',
        'discount_ends_at',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'is_active'           => 'boolean',
        'discount_value'      => 'decimal:2',
        'discount_starts_at'  => 'datetime',
        'discount_ends_at'    => 'datetime',
    ];

    // Expose computed pricing in API responses
    protected $appends = [
        'has_active_discount',
        'discount_amount',
        'final_price',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'menu_item_variant_id');
    }

    public function scopeVisible($q)
    {
        return $q->where('is_active', true)->whereNull('deleted_at');
    }

    // ---------- Variant discount helpers (with fallback to parent item) ----------
    public function getHasActiveDiscountAttribute(): bool
    {
        if (!$this->discount_type || !$this->discount_value) {
            return false;
        }

        $now = Carbon::now();
        if ($this->discount_starts_at && $now->lt($this->discount_starts_at)) {
            return false;
        }
        if ($this->discount_ends_at && $now->gt($this->discount_ends_at)) {
            return false;
        }

        return true;
    }

    public function getDiscountAmountAttribute(): float
    {
        $price = (float) $this->price;

        // 1) Prefer variant's own discount if active
        if ($this->has_active_discount) {
            if ($this->discount_type === 'percent') {
                return round($price * ((float) $this->discount_value / 100), 2);
            }
            return (float) min((float) $this->discount_value, $price);
        }

        // 2) Fallback to parent menu item's discount rules if active
        $item = $this->relationLoaded('menuItem') ? $this->menuItem : $this->menuItem()->first();
        if (!$item || !$item->has_active_discount) {
            return 0.0;
        }

        if ($item->discount_type === 'percent') {
            return round($price * ((float) $item->discount_value / 100), 2);
        }
        return (float) min((float) $item->discount_value, $price);
    }

    public function getFinalPriceAttribute(): float
    {
        $price = (float) $this->price;
        return (float) max(0.0, round($price - (float) $this->discount_amount, 2));
    }
}
