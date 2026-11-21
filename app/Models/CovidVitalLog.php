<?php

namespace App\Models;

use App\Traits\RecordsSystemLog;

use Illuminate\Database\Eloquent\Model;

class CovidVitalLog extends Model
{
    use RecordsSystemLog;
    
    protected $fillable = [
        'patient_id',
        'morbidity_id',
        'record_date',
        'round_time',
        'temperature',
        'heart_rate',
        'systolic_bp',
        'diastolic_bp',
        'respiratory_rate',
        'o2sat',
        'mews_score',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'record_date' => 'date',
        'temperature' => 'float',
        'mews_score'  => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}