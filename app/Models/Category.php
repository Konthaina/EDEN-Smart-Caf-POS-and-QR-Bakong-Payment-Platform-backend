<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name','slug','is_active'];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    // scope សម្រាប់ UI ដើម្បីច្រោះតែអ្វីកំពុងប្រើ
    public function scopeVisible($q)
    {
        return $q->where('is_active', true);
    }
}
