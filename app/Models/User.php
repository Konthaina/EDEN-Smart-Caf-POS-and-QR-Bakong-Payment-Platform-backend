<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * Hidden attributes for arrays/JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function table()
    {
        return $this->hasOne(Table::class);
    }
    // OTP-only: remove the reset-link override.
    // public function sendPasswordResetNotification($token) { ... }  ← deleted
}
