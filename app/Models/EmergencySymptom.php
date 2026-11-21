<?php

namespace App\Models;

use App\Traits\RecordsSystemLog;
use Illuminate\Database\Eloquent\Model;

class EmergencySymptom extends Model
{
    use RecordsSystemLog;
    // 🌟 1. ประกาศรายชื่อคอลัมน์ที่อนุญาตให้บันทึกข้อมูลผ่านคำสั่ง create() หรือ update()
    protected $fillable = [
        'category',
        'code',
        'name',
        'is_active',
    ];

    // 🌟 2. (เคล็ดลับเพิ่มเติม) ใช้ $casts เพื่อให้ Laravel รู้ว่า is_active คือ true/false เสมอ
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
