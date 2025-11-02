<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'price',
        'image',
        'description',
        'is_active',
        'discount_type',
        'discount_value',
        'discount_starts_at',
        'discount_ends_at',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'discount_value'      => 'decimal:2',
        'discount_starts_at'  => 'datetime',
        'discount_ends_at'    => 'datetime',
        'is_active'           => 'boolean',
    ];

    protected $appends = [
        'has_active_discount',
        'discount_amount',
        'final_price',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function variants()
    {
        return $this->hasMany(MenuItemVariant::class);
    }

    /** Scope for items visible on POS / E-menu */
    public function scopeVisible($q)
    {
        return $q->where('is_active', true)->whereNull('deleted_at');
    }

    // ---------- Discount helpers ----------
    public function getHasActiveDiscountAttribute(): bool
    {
        if (!$this->discount_type || !$this->discount_value) return false;
        $now = Carbon::now();
        if ($this->discount_starts_at && $now->lt($this->discount_starts_at)) return false;
        if ($this->discount_ends_at && $now->gt($this->discount_ends_at)) return false;
        return true;
    }

    public function getDiscountAmountAttribute(): float
    {
        if (!$this->has_active_discount) return 0.0;
        if ($this->discount_type === 'percent') {
            return round((float)$this->price * ((float)$this->discount_value / 100), 2);
        }
        return (float) min($this->discount_value, $this->price);
    }

    public function getFinalPriceAttribute(): float
    {
        return (float) max(0.0, round((float)$this->price - $this->discount_amount, 2));
    }
}
