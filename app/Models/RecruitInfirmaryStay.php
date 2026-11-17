<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitInfirmaryStay extends Model
{
    protected $fillable = [
        'patient_id',
        'military_unit_id',
        'intake_batch',
        'mews_case_id',
        'location_type',
        'bed_or_zone',
        'reason_category',
        'diagnosis_text',
        'care_instruction',
        'admitted_at',
        'discharged_at',
        'discharge_note',
        'status',
        'recorded_by',
    ];

    protected $casts = [
        'admitted_at'   => 'datetime',
        'discharged_at' => 'datetime',
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