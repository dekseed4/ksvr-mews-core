<?php

namespace App\Models;

use App\Traits\RecordsSystemLog;
use Illuminate\Database\Eloquent\Model;

class RecruitAppointment extends Model
{
    use RecordsSystemLog;
    protected $fillable = [
        'patient_id',
        'military_unit_id',
        'intake_batch',
        'hosxp_oapp_id',
        'hospital_type',
        'hospital_name',
        'appointment_date',
        'appointment_time',
        'clinic_name',
        'doctor_name',
        'app_cause',
        'note',
        'status',
        'recorded_by',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function militaryUnit()
    {
        return $this->belongsTo(MilitaryUnit::class, 'military_unit_id');
    }
}