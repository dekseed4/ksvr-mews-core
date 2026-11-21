<?php

namespace App\Models;

use App\Traits\RecordsSystemLog;
use Illuminate\Database\Eloquent\Model;

class MewsTemplate extends Model
{
    use RecordsSystemLog;
    protected $fillable = [
        'name', 
        'description', 
        'is_default', 
        'is_active', 
        'rules' // <-- เพิ่มคอลัมน์นี้เข้าไปครับ!
    ];

    // 🌟 2. (ของเดิมที่มีอยู่แล้ว) แปลงข้อมูลให้ถูกต้องตอนใช้งาน
    protected $casts = [
        'rules' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
