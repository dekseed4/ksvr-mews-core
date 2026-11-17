<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HosxpPatient extends Model
{
    // 🌟 1. ระบุ "เข็มทิศ" ให้วิ่งไปหา Connection ของ HosXP แทนฐานข้อมูลหลัก
    protected $connection = 'hosxp';
    
    // 🌟 2. ระบุชื่อตารางให้เป๊ะ! (ตารางประวัติใน HosXP ชื่อ patient)
    protected $table = 'patient';
    
    // 🌟 3. ระบุ Primary Key ของตารางนี้
    protected $primaryKey = 'hn';
    
    // เราแค่ดึงข้อมูลมาดู ปิดการใช้งาน Timestamps (created_at, updated_at) ไปเลยครับ
    public $timestamps = false;
    
    // ป้องกันไม่ให้เผลอไปสั่ง Create หรือ Update ข้อมูลใน HosXP โดยเด็ดขาด (Read-only)
    protected $guarded = ['*']; 
}