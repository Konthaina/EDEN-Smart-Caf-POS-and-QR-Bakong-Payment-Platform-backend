<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    public $timestamps = true;

    public static function getCached(string $key, $default = null)
    {
        $cacheKey = "setting:{$key}";
        return Cache::rememberForever($cacheKey, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    public static function setValue(string $key, $value): self
    {
        $row = static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
        return $row;
    }
}
