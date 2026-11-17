<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitMorbidity extends Model
{
    protected $fillable = [
        'patient_id',
        'military_unit_id',
        'intake_batch',
        'category',
        'sub_type',
        'severity',
        'treatment_location',
        'diagnosis_text',
        'onset_date',
        'status',
        'recovered_date',
        'recorded_by',
    ];

    protected $casts = [
        'onset_date'     => 'date',
        'recovered_date' => 'date',
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