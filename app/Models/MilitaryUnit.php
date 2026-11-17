<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MilitaryUnit extends Model
{
    protected $fillable = ['name', 'short_name', 'battalion', 'is_active'];

    // 1 หน่วยทหาร มีเสนารักษ์ (Users) ได้หลายคน
    public function users()
    {
        return $this->hasMany(User::class, 'military_unit_id');
    }

    // 1 หน่วยทหาร มีคนไข้ (Patients) ได้หลายคน
    public function patients()
    {
        return $this->hasMany(Patient::class, 'military_unit_id');
    }
}