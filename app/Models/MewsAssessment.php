<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MewsAssessment extends Model
{
    protected $fillable = [
        'patient_id', 'evaluator_id', 'symptoms', 'temperature', 
        'heart_rate', 'respiratory_rate', 'systolic_bp', 'o2sat',
        'consciousness', 'total_score', 'risk_level', 'status',
        'assessment_type', 'case_id', 'discharge_type', 'discharge_details', 
        'cancel_reason','symptoms_note',
        'returned_to_unit_at', 
        'return_note','mews_template_id',       
    ];

    // 🌟 พระเอกของเรา: บอก Laravel ว่าฟิลด์ symptoms ให้แปลงเป็น JSON ก่อนลง Database 
    // และแปลงกลับเป็น Array อัตโนมัติตอนดึงข้อมูลมาแสดงผล
    protected $casts = [
        'symptoms' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    // 🌟 ตัวช่วยแปลภาษา (Accessor): แปลง Key ภาษาอังกฤษเป็นข้อความภาษาไทย
    public function getGroupedSymptomsAttribute()
    {
        // 1. ตรวจสอบและแปลง JSON เป็น Array
        $rawSyms = is_string($this->symptoms) ? json_decode($this->symptoms, true) : $this->symptoms;
        if (!is_array($rawSyms)) {
            return [];
        }

        // 2. พจนานุกรมแปลคำ (เก็บไว้ที่เดียวศูนย์กลาง!)
        $dict = [
            'mi_chest_pain' => 'เจ็บแน่นหน้าอกเหมือนโดนกดทับ (MI)',
            'mi_dyspnea' => 'เหนื่อย หายใจไม่อิ่ม ใจสั่น (MI)',
            'mi_referred_pain' => 'เจ็บร้าวไปไหล่ซ้าย กราม แขน (MI)',
            'stroke_dizzy' => 'เวียนศีรษะบ้านหมุน เดินเซ (Stroke)',
            'stroke_vision' => 'ตามัว มองเห็นภาพซ้อน (Stroke)',
            'stroke_facial_weakness' => 'หน้าเบี้ยว ปากเบี้ยวครึ่งซีก (Stroke)',
            'stroke_arm_leg_weakness' => 'แขนขาอ่อนแรงครึ่งซีก (Stroke)',
            'stroke_speech' => 'พูดไม่ชัด สื่อสารไม่เข้าใจ (Stroke)',
            'heat_confusion' => 'ซึมสับสน ชัก หมดสติ (Heat Injury)',
            'heat_fatigue' => 'อ่อนเพลียไม่มีแรง เหงื่อออกมาก (Heat Injury)',
            'heat_pale' => 'หน้าซีดหน้ามืด เป็นลม (Heat Injury)',
            'heat_cramp' => 'ตะคริว หายใจหอบเร็วลึก (Heat Injury)',
            'heat_numbness' => 'ชาปลายมือ/เท้า (Heat Injury)',
            'heat_urine' => 'ปัสสาวะสีเข้ม (Heat Injury)',
            'ana_rash' => 'ผื่นแดง ลมพิษ ปากบวม (Anaphylaxis)',
            'ana_resp' => 'หายใจไม่สะดวก หอบเหนื่อย (Anaphylaxis)',
            'ana_sys' => 'กล้ามเนื้ออ่อนแรง เป็นลม (Anaphylaxis)',
            'ana_gi' => 'ปวดท้อง คลื่นไส้ อาเจียน (Anaphylaxis)',
            'ana_chest' => 'แน่นหน้าอก (Anaphylaxis)',
            'appendicitis' => 'ปวดท้อง RLQ (Appendicitis)',
            'sepsis_drowsy' => 'ซึม (Sepsis)',
            'sepsis_tachypnea' => 'หายใจหอบ (Sepsis)',
            'trauma' => 'เกิดอุบัติเหตุภายใน 24 ชม. (Trauma)'
        ];

        // 3. จัดกลุ่ม (Grouping Logic)
        $grouped = [];
        foreach ($rawSyms as $key) {
            $cat = 'อาการอื่นๆ';
            if (str_starts_with($key, 'mi_')) $cat = 'MI (กล้ามเนื้อหัวใจขาดเลือด)';
            elseif (str_starts_with($key, 'stroke_')) $cat = 'Stroke (หลอดเลือดสมอง)';
            elseif (str_starts_with($key, 'heat_')) $cat = 'Heat Injury (โรคลมร้อน)';
            elseif (str_starts_with($key, 'ana_')) $cat = 'Anaphylaxis (แพ้รุนแรง)';
            elseif ($key === 'appendicitis') $cat = 'Appendicitis';
            elseif (str_starts_with($key, 'sepsis_')) $cat = 'Sepsis (ติดเชื้อในเลือด)';
            elseif ($key === 'trauma') $cat = 'Trauma (อุบัติเหตุ)';

            $grouped[$cat][] = $dict[$key] ?? $key;
        }

        return $grouped;
    }

    // 🌟 เชื่อมความสัมพันธ์ไปยังตาราง mews_templates เพื่อดึงชื่อสูตรคำนวณ
    public function template()
    {
        return $this->belongsTo(\App\Models\MewsTemplate::class, 'mews_template_id');
    }
}