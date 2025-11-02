<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_variant_id', // ✅ allow mass-assignment
        'quantity',
        'price',
        'subtotal',
        'customizations',
        'note',
    ];

    protected $casts = [
        'customizations' => 'array', // ✅ store as JSON/array safely
        'price'          => 'float',
        'subtotal'       => 'float',
        'quantity'       => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function menuItemVariant()
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }
}
