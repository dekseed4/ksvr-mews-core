<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitScreening extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'military_unit_id',
        'intake_batch',
        'color_group',
        'symptoms_note',
        'screener_id'
    ];

    // เชื่อมกลับไปหาประวัติพลทหาร
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    // เชื่อมกลับไปหาหน่วยฝึก
    public function militaryUnit()
    {
        return $this->belongsTo(MilitaryUnit::class);
    }
    
    // เชื่อมกลับไปหาเจ้าหน้าที่ผู้ประเมิน
    public function screener()
    {
        return $this->belongsTo(User::class, 'screener_id');
    }
}
