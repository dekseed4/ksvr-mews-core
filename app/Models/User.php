<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\RecordsSystemLog;

#[Fillable(['rank', 'name', 'username', 'email', 'password', 'phone', 'roles', 'must_change_password', 'military_unit_id', 'intake_batch'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, RecordsSystemLog;

    /**
     * 🌟 กำหนด $fillable แบบ Property มาตรฐานควบคู่กัน เพื่อป้องกันปัญหา Attribute Cache บน PHP-FPM
     */
    protected $fillable = [
        'rank',
        'name',
        'username',
        'email',
        'password',
        'phone',
        'roles',
        'must_change_password',
        'military_unit_id',
        'intake_batch',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'last_seen_at'         => 'datetime',
            'password'             => 'hashed',
            'must_change_password' => 'boolean',
            'roles'                => 'array',
        ];
    }

    public function hasRole(string $role): bool
    {
        $userRoles = $this->roles;

        if (empty($userRoles)) {
            return false;
        }

        if (is_string($userRoles)) {
            $userRoles = json_decode($userRoles, true);
        }

        if (is_array($userRoles)) {
            return in_array($role, $userRoles);
        }

        return false;
    }

    public function militaryUnit()
    {
        return $this->belongsTo(\App\Models\MilitaryUnit::class, 'military_unit_id');
    }

    public function isOnline(): bool
    {
        if (\Illuminate\Support\Facades\Cache::has("user-is-online-{$this->id}")) {
            return true;
        }

        if (empty($this->last_seen_at)) {
            return false;
        }

        return \Carbon\Carbon::parse($this->last_seen_at)->gt(now()->subMinutes(5));
    }

    public function getLastSeenLabelAttribute(): string
    {
        if ($this->isOnline()) {
            return 'กำลังออนไลน์';
        }

        return !empty($this->last_seen_at)
            ? 'ใช้งานเมื่อ ' . \Carbon\Carbon::parse($this->last_seen_at)->locale('th')->diffForHumans()
            : 'ยังไม่เคยเข้าใช้งาน';
    }

    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes(5));
    }
    
}