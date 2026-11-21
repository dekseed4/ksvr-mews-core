<?php

namespace App\Models;

use App\Traits\RecordsSystemLog;
use Illuminate\Database\Eloquent\Model;

class PatientMedication extends Model
{
    use RecordsSystemLog;
    protected $table = 'patient_medications';

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

    // สำรองให้เรียก $med->note หรือ $med->instruction_note ได้ทั้งคู่
    public function getNoteAttribute(): ?string
    {
        return $this->instruction_note;
    }

    public function getMealTimingLabelAttribute(): string
    {
        return match ($this->meal_timing) {
            'before_meal'  => 'ก่อนอาหาร',
            'after_meal'   => 'หลังอาหาร',
            'with_meal'    => 'พร้อมอาหาร',
            'bedtime_only' => 'ก่อนนอนอย่างเดียว',
            'bedtime'      => 'ก่อนนอน',
            'prn'          => 'เวลามีอาการ (PRN)',
            'external'     => 'ยาทา / ยานวดภายนอก',
            default        => 'ตามแพทย์สั่ง / ไม่ระบุ',
        };
    }

    public function getCareGroupLabelAttribute(): string
    {
        return match ($this->care_group) {
            'psychiatric' => '🧠 กลุ่มโรคจิตเวช (เฝ้าระวังพิเศษ)',
            'chronic'     => '❤️ กลุ่มโรคประจำตัว / โรคเรื้อรัง',
            default       => '🏕️ ผู้ป่วยแยกกัก CI / ห้องพยาบาลหน่วยฝึก',
        };
    }
}