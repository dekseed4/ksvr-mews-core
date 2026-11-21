<?php

namespace App\Traits;

use App\Models\Patient;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait RecordsSystemLog
{
    /**
     * สวิตช์ชั่วคราวสำหรับปิดการลง Log รายแถว (เช่น ตอนนำเข้า Excel ทีละ 500 รายการ)
     */
    public static bool $pauseSystemLogging = false;

    public static function bootRecordsSystemLog(): void
    {
        static::created(function ($model) {
            $model->writeAuditSystemLog('created');
        });

        static::updated(function ($model) {
            $model->writeAuditSystemLog('updated');
        });

        static::deleted(function ($model) {
            $model->writeAuditSystemLog('deleted');
        });
    }

    protected function writeAuditSystemLog(string $action): void
    {
        // 1. ป้องกัน Infinite Loop และพักการทำงานหากถูกปิดชั่วคราวหรือรัน Seeder ใน Console
        if (static::$pauseSystemLogging || $this instanceof SystemLog) {
            return;
        }

        if (app()->runningInConsole() && !Auth::check()) {
            return;
        }

        try {
            // ฟิลด์ที่ห้ามบันทึกลง Log เพื่อความปลอดภัยและลดขยะในฐานข้อมูล
            $ignored = array_merge([
                'created_at',
                'updated_at',
                'password',
                'remember_token',
                'last_seen_at',
                'last_login_ip',
            ], property_exists($this, 'auditExclude') ? $this->auditExclude : []);

            $payload = [
                'module'  => $this->resolveAuditModuleLabel(),
                'summary' => $this->resolveAuditSummary($action),
                'ip'      => request()?->ip(),
                'url'     => request()?->path(),
            ];

            if ($action === 'updated') {
                $changes = $this->getChanges();
                $oldValues = [];
                $newValues = [];

                foreach ($changes as $key => $rawNewVal) {
                    if (in_array($key, $ignored, true)) {
                        continue;
                    }

                    $oldVal = $this->getOriginal($key);
                    $newVal = $this->getAttribute($key);

                    // ข้ามหากค่าก่อน-หลังเท่ากันจริงๆ (เช่น "1" กับ 1)
                    if ($oldVal == $newVal) {
                        continue;
                    }

                    $oldValues[$key] = $oldVal;
                    $newValues[$key] = $newVal;
                }

                // ถ้าไม่มีการเปลี่ยนแปลงฟิลด์สำคัญ ไม่ต้องบันทึก Log
                if (empty($newValues)) {
                    return;
                }

                $payload['old'] = $oldValues;
                $payload['new'] = $newValues;
            } elseif ($action === 'created') {
                $payload['new'] = collect($this->getAttributes())
                    ->except($ignored)
                    ->toArray();
            } elseif ($action === 'deleted') {
                $payload['old'] = collect($this->getOriginal())
                    ->except($ignored)
                    ->toArray();
            }

            // แนบ patient_id ไว้ใน payload เสมอหาก Model นี้เกี่ยวข้องกับผู้ป่วย
            if ($this instanceof Patient) {
                $payload['patient_id'] = $this->getKey();
            } elseif (isset($this->patient_id)) {
                $payload['patient_id'] = $this->patient_id;
            }

            SystemLog::create([
                'user_id'       => Auth::id(),
                'action'        => $action,
                'loggable_type' => static::class,
                'loggable_id'   => $this->getKey(),
                'payload'       => $payload,
            ]);
        } catch (\Throwable $e) {
            // ห้ามให้ระบบ Audit Log พังจนกระทบงานคลินิก (ER/OPD/e-MAR)
            Log::warning('RecordsSystemLog failed: ' . $e->getMessage(), [
                'model'  => static::class,
                'id'     => $this->getKey(),
                'action' => $action,
            ]);
        }
    }

    protected function resolveAuditModuleLabel(): string
    {
        return match (class_basename($this)) {
            'Patient'              => 'ทะเบียนประวัติพลทหาร',
            'MewsAssessment'       => 'ประเมินสัญญาณชีพ (MEWS)',
            'CovidVitalLog'        => 'สัญญาณชีพรายวัน (06:00/18:00)',
            'RecruitInfirmaryStay' => 'ห้องพยาบาล / แยกโรงนอน',
            'PatientMedication'    => 'รายการยา (e-MAR)',
            'PatientMedicationLog' => 'บันทึกการกิน/ทายา (e-MAR)',
            'RecruitAppointment'   => 'นัดพบแพทย์ทหารใหม่',
            'RecruitMorbidity'     => 'รายงานเจ็บป่วยประจำวัน',
            'RecruitScreening'     => 'คัดกรองทหารใหม่แรกรับ',
            'MilitaryDischarge'    => 'เอกสารนำปลดทหาร',
            'MilitaryUnit'         => 'ข้อมูลหน่วยฝึกทหาร',
            'User'                 => 'ผู้ใช้งานระบบ',
            'MewsTemplate'         => 'ตั้งค่าเกณฑ์ MEWS',
            'EmergencySymptom'     => 'ตั้งค่าอาการฉุกเฉินเฉพาะโรค',
            default                => class_basename($this),
        };
    }

    protected function resolveAuditSummary(string $action): string
    {
        $verb = match ($action) {
            'created' => 'เพิ่มข้อมูล',
            'updated' => 'แก้ไขข้อมูล',
            'deleted' => 'ลบข้อมูล',
            default   => $action,
        };

        $module = $this->resolveAuditModuleLabel();
        $target = '';

        if ($this instanceof Patient) {
            $target = trim(($this->rank ?? '') . ($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        } elseif ($this->relationLoaded('patient') && $this->patient) {
            $p = $this->patient;
            $target = trim(($p->rank ?? '') . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''));
        } elseif (!empty($this->drug_name)) {
            $target = $this->drug_name;
        } elseif (!empty($this->name)) {
            $target = $this->name;
        } else {
            $target = '#' . $this->getKey();
        }

        return "{$verb}{$module}: {$target}";
    }
}