<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_code',
        'total_amount',
        'discount_id',
        'discount_amount',
        'status',
        'payment_method',
        'tax_rate',
        'exchange_rate',
        'tax_amount',
        'total_khr',
        'paid_at',

        // NEW cash fields
        'tendered_currency',
        'cash_tendered_usd',
        'cash_tendered_khr',
        'change_usd',
        'change_khr',
    ];

    protected $casts = [
        'paid_at'            => 'datetime',
        'tax_rate'           => 'float',
        'exchange_rate'      => 'float',
        'tax_amount'         => 'float',
        'total_amount'       => 'float',
        'cash_tendered_usd'  => 'float',
        'change_usd'         => 'float',
        'cash_tendered_khr'  => 'integer',
        'change_khr'         => 'integer',
    ];

    /**
     * Order belongs to a User (include soft-deleted).
     */
    public function user()
    {
        return $this->belongsTo(User::class)
            ->withTrashed()
            ->withDefault(['name' => 'N/A']);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
