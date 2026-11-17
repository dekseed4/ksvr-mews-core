<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'cid', 'hn', 'rank', 'first_name', 'last_name', 'dob', 'blood_group',
        'phone', 'house_no', 'village_no', 'sub_district', 'district', 'province',
        'father_name', 'mother_name', 'height', 'weight', 'chest_size', 'bmi',
        'scar_details', 'religion', 'education', 'military_id', 'military_unit_id',
        'camp_name', 'battalion', 'company', 'intake_batch','volunteer_status',
        'privilege_reduction', 'service_duration','pttype_code', 'pttype_name', 
        'chronic_disease_status', 'chronic_disease_detail', 'duty_status', 'duty_status_note',
    ];

    // 2. เพิ่มฟังก์ชันเชื่อมไปยังตารางคัดกรอง (ถ้ายังไม่มี)
    public function recruitScreening()
    {
        // 1 คน มี 1 ใบประเมินคัดกรองโรคทางเดินหายใจ
        return $this->hasOne(RecruitScreening::class, 'patient_id');
    }

    // 🌟 ดึงประวัติทั้งหมด
    public function mews()
    {
        return $this->hasMany(MewsAssessment::class, 'patient_id');
    }

    public function mewsAssessments()
    {
        return $this->hasMany(MewsAssessment::class, 'patient_id', 'id');
    }

    // 🌟 ดึงเฉพาะประวัติล่าสุด (ใช้งานในหน้าตารางรายชื่อ)
    public function latestMews()
    {
        return $this->hasOne(MewsAssessment::class, 'patient_id')->latestOfMany();
    }

    // 🌟 ฟังก์ชันวิ่งไปถามข้อมูลจาก HosXP
    public function syncFromHosxpIfNeeded()
    {
        // ถ้ามี HN อยู่แล้ว แปลว่าเคยซิงค์แล้ว ไม่ต้องดึงซ้ำให้หนักเครื่อง
        if (!empty($this->hn)) {
            return false; 
        }

        try {
            // ค้นหาคนไข้ในตาราง patient ของ HosXP ด้วย CID
            $hosxpData = \Illuminate\Support\Facades\DB::connection('hosxp')
                ->table('patient')
                ->where('cid', $this->cid)
                ->first();

            if ($hosxpData) {
                // ถ้าเจอข้อมูล ให้อัปเดตลงฐานข้อมูล KSVR ของเรา
                $this->update([
                    'hn' => $hosxpData->hn,
                    'birthday' => $hosxpData->birthday,
                    'blood_group' => $hosxpData->bloodgrp
                ]);
                return true; // ซิงค์สำเร็จ
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('HosXP Sync Error: ' . $e->getMessage());
        }

        return false;
    }

    // ผูกความสัมพันธ์ 1 User/Patient อยู่ได้ 1 หน่วยทหาร
    public function militaryUnit()
    {
        // กำหนด (ชื่อคลาสที่เชื่อมไป, ชื่อคอลัมน์ในตารางนี้, ชื่อคอลัมน์ในตารางนู้น)
        return $this->belongsTo(MilitaryUnit::class, 'military_unit_id', 'id');
    }

    // 🌟 ฟังก์ชันคำนวณสถานะปัจจุบันของผู้ป่วย (Active Workflow Status)
    public function getActiveWorkflowStatusAttribute()
    {
        // 1. หาใบล่าสุดจาก ID
        $latestMews = $this->mewsAssessments()->orderBy('id', 'desc')->first();

        // 2. ถ้าไม่มี หรือใบล่าสุดโดนปิด/ยกเลิกไปแล้ว -> ไม่ต้องโชว์แบนเนอร์
        if (!$latestMews || in_array($latestMews->status, ['completed', 'cancelled'])) {
            return null; 
        }

        // 3. นับจำนวนครั้งที่ประเมิน (ในแฟ้มปัจจุบัน และต้องไม่นับใบที่ถูกยกเลิก)
        $assessCount = $this->mewsAssessments()
            ->where('case_id', $latestMews->case_id)
            ->where('status', '!=', 'cancelled')
            ->count();
        
        return [
            'is_active' => true,
            'text'      => "กำลังรับการรักษา (ประเมินครั้งที่ {$assessCount})", // 🌟 เปลี่ยนคำให้เคลียร์ขึ้น
            'theme'     => 'amber', 
            'icon'      => 'fa-truck-medical',
            'time_ago'  => $latestMews->created_at->diffForHumans(),
            'mews_id'   => $latestMews->id
        ];
    }

    public function getPatientNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    
    /**
     * 🌟 กรองข้อมูลพลทหารตามสิทธิ์ผู้ใช้งานปัจจุบัน
     * ถ้าเป็นเสนารักษ์ (และไม่ใช่ Admin/บุคลากร รพ.ส่วนกลาง) ให้เห็นเฉพาะหน่วยตัวเอง + ผลัดตัวเอง
     */
    public function scopeForUserAccess($query, $user = null)
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $userRoles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);

        // ถ้ามีสิทธิ์ส่วนกลางของ รพ. (Admin, ผู้บริหาร, ER, OPD, ทะเบียน) ให้ดูได้ทุกหน่วยทุกผลัด
        $hospitalStaffRoles = ['admin', 'executive', 'er_nurse', 'opd_nurse', 'registration'];
        if (!empty(array_intersect($hospitalStaffRoles, $userRoles))) {
            return $query;
        }

        // 🎒 ถ้าเป็นเสนารักษ์ (medic) อย่างเดียว ต้องตรงทั้ง "หน่วยฝึก" และ "ผลัด"
        if (in_array('medic', $userRoles)) {
            return $query->where('military_unit_id', $user->military_unit_id)
                         ->where('intake_batch', $user->intake_batch);
        }

        return $query;
    }

    // 🌟 ความสัมพันธ์เชื่อมไปยังตารางเอกสารนำปลดทหาร (MilitaryDischarge)
    public function militaryDischarge()
    {
        return $this->hasOne(\App\Models\MilitaryDischarge::class, 'patient_id')->latestOfMany();
    }

    // 🌟 ฟังก์ชันเช็คว่าพลทหารนายนี้อยู่ในทะเบียนนำปลดหรือปลดประจำการแล้วหรือไม่
    public function isDischarged(): bool
    {
        return $this->militaryDischarge !== null;
    }

    // 🌟 1. ความสัมพันธ์ดึงประวัติการนัดพบแพทย์ทั้งหมดของพลทหารรายนี้
    public function recruitAppointments()
    {
        return $this->hasMany(\App\Models\RecruitAppointment::class, 'patient_id')
                    ->orderBy('appointment_date', 'asc')
                    ->orderBy('appointment_time', 'asc');
    }

    // 🌟 2. ความสัมพันธ์ดึงเฉพาะ "นัดหมายครั้งถัดไปที่กำลังจะมาถึง" (ตั้งแต่เมื่อวาน/วันนี้เป็นต้นไป ที่ยังไม่ได้ยกเลิก)
    public function upcomingAppointment()
    {
        return $this->hasOne(\App\Models\RecruitAppointment::class, 'patient_id')
                    ->where('status', 'scheduled')
                    ->whereDate('appointment_date', '>=', \Carbon\Carbon::today()->format('Y-m-d'))
                    ->orderBy('appointment_date', 'asc')
                    ->orderBy('appointment_time', 'asc');
    }
    
    // =========================================================================
    // 🎖️ ฟังก์ชันตรวจสอบสถานะกำลังพลและการล็อกการประเมิน MEWS
    // =========================================================================
    public function isCannotAssessMews(): bool
    {
        $status = $this->duty_status ?? 'active';
        return $this->militaryDischarge !== null || in_array($status, ['absent', 'imprisoned', 'deceased']);
    }

    public function getDutyStatusLabelAttribute(): string
    {
        if ($this->militaryDischarge) {
            return $this->militaryDischarge->process_status === 'completed'
                ? 'ปลดทหารแล้ว (' . ($this->militaryDischarge->diagnosis_text ?: '-') . ')'
                : 'อยู่ระหว่างนำปลด (' . ($this->militaryDischarge->diagnosis_text ?: '-') . ')';
        }

        $map = [
            'active'     => 'มารายงานตัว (ประจำการปกติ)',
            'absent'     => 'ไม่มารายงานตัว',
            'imprisoned' => 'ต้องโทษ / ดำเนินคดี',
            'deceased'   => 'เสียชีวิต',
        ];

        $label = $map[$this->duty_status ?? 'active'] ?? 'ประจำการปกติ';
        if (!empty($this->duty_status_note) && ($this->duty_status ?? 'active') !== 'active') {
            $label .= " ({$this->duty_status_note})";
        }
        return $label;
    }

    // 🌟 ประวัติการนอนห้องพยาบาลหน่วยฝึก / แยกโรงนอนทั้งหมดของพลทหารรายนี้
    public function infirmaryStays()
    {
        return $this->hasMany(\App\Models\RecruitInfirmaryStay::class, 'patient_id')
                    ->orderBy('admitted_at', 'desc');
    }

    // 🌟 ดึงเฉพาะรายการที่ "กำลังแยกนอนพักอยู่ปัจจุบัน (status = staying)"
    public function activeInfirmaryStay()
    {
        return $this->hasOne(\App\Models\RecruitInfirmaryStay::class, 'patient_id')
                    ->where('status', 'staying')
                    ->latestOfMany('admitted_at');
    }

    public function covidVitalLogs()
    {
        return $this->hasMany(\App\Models\CovidVitalLog::class, 'patient_id')
                    ->orderBy('record_date', 'asc')
                    ->orderBy('round_time', 'asc');
    }

    public function medications()
    {
        return $this->hasMany(\App\Models\PatientMedication::class, 'patient_id')
                    ->orderBy('is_active', 'desc')
                    ->orderBy('id', 'asc');
    }

    public function activeMedications()
    {
        return $this->hasMany(\App\Models\PatientMedication::class, 'patient_id')
                    ->where('is_active', true)
                    ->orderBy('id', 'asc');
    }

    public function medicationLogs()
    {
        return $this->hasMany(\App\Models\PatientMedicationLog::class, 'patient_id');
    }

    // 🌟 ประวัติการเจ็บป่วยทั้งหมดในรายงานประจำวัน (โควิด, ไข้หวัดใหญ่, ความร้อน, นอน รพ.ค่ายฯ/รพ.สาธารณสุข)
    public function morbidityRecords()
    {
        return $this->hasMany(\App\Models\RecruitMorbidity::class, 'patient_id')
                    ->orderBy('onset_date', 'desc');
    }

    // 🌟 ดึงรายการที่กำลังนอนรักษาตัวใน รพ.ค่ายฯ หรือ รพ.สาธารณสุข (นอก รพ.) ณ ปัจจุบัน (status = treating)
    public function activeIpdMorbidity()
    {
        return $this->hasOne(\App\Models\RecruitMorbidity::class, 'patient_id')
                    ->where('category', 'other_ipd')
                    ->where('status', 'treating')
                    ->orderByDesc('onset_date')
                    ->orderByDesc('id');
    }

    // 🌟 ดึงรายการป่วยที่กำลังรักษาอยู่ทุกประเภท (รวมโควิด-19 / ไข้หวัดใหญ่ / ความร้อน / นอน รพ.)
    public function activeMorbidity()
    {
        return $this->hasOne(\App\Models\RecruitMorbidity::class, 'patient_id')
                    ->where('status', 'treating')
                    ->orderByDesc('onset_date')
                    ->orderByDesc('id');
    }
}