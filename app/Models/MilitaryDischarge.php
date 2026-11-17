<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MilitaryDischarge extends Model
{
    protected $fillable = [
        'patient_id',
        'military_unit_id',
        'intake_batch',
        'diagnosis_text',
        'process_status',
        'documents_matrix',
        'hospital_coordinator',
        'unit_coordinator',
        'recorded_by',
    ];

    protected $casts = [
        'documents_matrix' => 'array',
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