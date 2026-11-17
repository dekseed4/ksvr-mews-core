<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientMedication extends Model
{
    protected $fillable = [
        'patient_id',
        'care_group',
        'drug_name',
        'dosage',
        'meal_timing',
        'take_morning',
        'take_noon',
        'take_evening',
        'take_bedtime',
        'take_prn',
        'custom_slot_text',
        'start_date',
        'end_date',
        'is_active',
        'instruction_note',
        'note',
        'created_by',
    ];

    protected $casts = [
        'take_morning' => 'boolean',
        'take_noon'    => 'boolean',
        'take_evening' => 'boolean',
        'take_bedtime' => 'boolean',
        'take_prn'     => 'boolean',
        'is_active'    => 'boolean',
        'start_date'   => 'date',
        'end_date'     => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function logs()
    {
        return $this->hasMany(PatientMedicationLog::class, 'patient_medication_id');
    }

    public function getMealTimingLabelAttribute(): string
    {
        return match ($this->meal_timing) {
            'before_meal' => 'ก่อนอาหาร',
            'after_meal'  => 'หลังอาหาร',
            'with_meal'   => 'พร้อมอาหาร',
            'prn'         => 'เวลามีอาการ (PRN)',
            'external'    => 'ยาทา / ยานวดภายนอก',
            'bedtime'     => 'ก่อนนอน',
            default       => 'ตามแพทย์สั่ง / ไม่ระบุ',
        };
    }

    public function getCareGroupLabelAttribute(): string
    {
        return match ($this->care_group) {
            'psychiatric'  => '🧠 กลุ่มโรคจิตเวช (เฝ้าระวังพิเศษ)',
            'chronic'      => '❤️ กลุ่มโรคประจำตัว / โรคเรื้อรัง',
            default        => '🏕️ ผู้ป่วยแยกกัก CI / ห้องพยาบาลหน่วยฝึก',
        };
    }
}