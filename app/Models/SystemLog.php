<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'loggable_type',
        'loggable_id',
        'payload',
    ];

    // แปลง payload ให้กลับมาเป็น Array อัตโนมัติเมื่อดึงไปใช้งาน
    protected $casts = [
        'payload' => 'array',
    ];

    // ความสัมพันธ์: Log นี้เป็นของ User คนไหน
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ความสัมพันธ์แบบ Polymorphic (ชี้ไปหา Model เป้าหมาย)
    public function loggable()
    {
        return $this->morphTo();
    }
}