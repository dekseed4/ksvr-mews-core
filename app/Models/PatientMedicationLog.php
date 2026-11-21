<?php

namespace App\Models;

use App\Traits\RecordsSystemLog;
use Illuminate\Database\Eloquent\Model;

class PatientMedicationLog extends Model
{
    use RecordsSystemLog;
    
    protected $fillable = [
        'patient_medication_id',
        'patient_id',
        'log_date',
        'time_slot',
        'status',
        'given_by',
        'remark',
    ];

    protected $casts = [
        'log_date' => 'date',
    ];

    public function medication()
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function giver()
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}