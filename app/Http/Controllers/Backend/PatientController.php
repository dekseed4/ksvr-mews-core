<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\HosxpPatient;
use App\Models\MilitaryDischarge;
use App\Models\MilitaryUnit;
use App\Models\Patient;
use App\Services\AuditLog;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PatientController extends Controller
{
    private bool $hosxpTimeoutApplied = false;

    // 🌟 แปลงสิทธิ์ผู้ใช้เป็น Array มาตรฐาน
    private function getUserRoles($user): array
    {
        if (!$user) {
            return [];
        }

        $raw = $user->roles ?? $user->role ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [$raw];
        }

        return is_array($raw) ? $raw : (array) $raw;
    }

    // 🌟 1. ตรวจสอบว่า User เป็นบุคลากรระดับโรงพยาบาลที่มีสิทธิ์ดูคนไข้ทั้งหมดหรือไม่
    private function canViewAllPatients($user): bool
    {
        $roles = $this->getUserRoles($user);

        return !empty(array_intersect(
            ['admin', 'er_nurse', 'opd_nurse', 'executive', 'registration', 'medical_staff', 'screening'],
            $roles
        ));
    }

    // 🛡️ ตรวจสอบสิทธิ์การดูข้อมูลโรคประจำตัวตามนโยบาย PDPA จาก SettingService
    private function canViewChronicData($user): bool
    {
        $roles = $this->getUserRoles($user);
        if (in_array('admin', $roles, true)) {
            return true;
        }

        $allowedRoles = class_exists(SettingService::class)
            ? (array) SettingService::get('pdpa_chronic_roles', ['admin'])
            : ['admin'];

        return !empty(array_intersect($roles, $allowedRoles));
    }

    // 🌟 2. ฟังก์ชันตัวช่วยกรอง Query สำหรับ "เสนารักษ์" (ผูกทั้งหน่วยฝึก + ผลัดที่)
    private function scopeForMedic($query, $user)
    {
        if ($this->canViewAllPatients($user)) {
            return $query;
        }

        $query->where('military_unit_id', $user->military_unit_id);

        if (!empty($user->intake_batch)) {
            $query->where('intake_batch', $user->intake_batch);
        }

        return $query;
    }

    // 🌟 3. เช็กสิทธิ์การเข้าถึงคนไข้รายบุคคล
    private function checkPatientAccess($patient): bool
    {
        $user = auth()->user();

        if ($this->canViewAllPatients($user)) {
            return true;
        }

        if ($user->military_unit_id !== $patient->military_unit_id) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลผู้ป่วยของหน่วยทหารอื่นครับ');
        }

        if (!empty($user->intake_batch) && $user->intake_batch !== $patient->intake_batch) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลทหารใหม่ต่างผลัดครับ');
        }

        return true;
    }

    // =========================================================================
    // 🛡️ HosXP Safety Helpers (อ่านจาก Cache 0 DB Query เพื่อปกป้องเซิร์ฟเวอร์ HIS)
    // =========================================================================
    private function isHosxpSyncPaused(): bool
    {
        return class_exists(SettingService::class)
            ? (bool) SettingService::get('hosxp_sync_paused', false)
            : false;
    }

    private function getHosxpMaxBatchSize(): int
    {
        $size = class_exists(SettingService::class)
            ? (int) SettingService::get('hosxp_max_batch_size', 50)
            : 50;

        return max(10, min(200, $size));
    }

    private function applyHosxpTimeout(): void
    {
        if ($this->hosxpTimeoutApplied) {
            return;
        }

        $timeout = class_exists(SettingService::class)
            ? (int) SettingService::get('hosxp_timeout_seconds', 5)
            : 5;
        $timeout = max(2, min(30, $timeout));

        $options = config('database.connections.hosxp.options', []);
        if (!is_array($options)) {
            $options = [];
        }
        $options[\PDO::ATTR_TIMEOUT] = $timeout;

        config(['database.connections.hosxp.options' => $options]);
        DB::purge('hosxp');

        $this->hosxpTimeoutApplied = true;
    }

    private function hosxpPausedJsonResponse()
    {
        return response()->json([
            'status'  => 'error',
            'paused'  => true,
            'message' => 'ขณะนี้ผู้ดูแลระบบพักการเชื่อมต่อ/ซิงค์ข้อมูล HosXP ชั่วคราว (อยู่ในช่วงดูแลรักษาฐานข้อมูล HIS) กรุณาลองใหม่ภายหลังครับ',
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function cleanHosxpUtf8($string): string
    {
        if (empty($string)) {
            return '';
        }
        $utf8 = @iconv('TIS-620', 'UTF-8//IGNORE', (string) $string);
        return trim(mb_convert_encoding($utf8 ?: (string) $string, 'UTF-8', 'UTF-8'));
    }

    private function normalizeHosxpBloodGroup($rawBlood): ?string
    {
        $bg = strtoupper(str_replace(' ', '', $this->cleanHosxpUtf8($rawBlood)));
        if (empty($bg) || str_contains($bg, 'ไม่') || $bg === '-') {
            return null;
        }
        if (preg_match('/^(AB|A|B|O)[+-]?$/', $bg, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function parseHosxpBirthday($hosxpRecord): ?string
    {
        try {
            $rawBday = method_exists($hosxpRecord, 'getRawOriginal')
                ? $hosxpRecord->getRawOriginal('birthday')
                : ($hosxpRecord->birthday ?? null);
            $bdayStr = trim((string) $rawBday);

            if ($bdayStr !== '' && $bdayStr !== '0000-00-00' && !str_starts_with($bdayStr, '0000') && !str_contains($bdayStr, '-00')) {
                $dt = Carbon::parse($bdayStr);
                $yr = $dt->year > (int) date('Y') ? ($dt->year - 543) : $dt->year;
                if ($yr >= 1900 && $yr <= (int) date('Y') && checkdate($dt->month, $dt->day, $yr)) {
                    return sprintf('%04d-%02d-%02d', $yr, $dt->month, $dt->day);
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function buildBatchOptionsForUser($user): array
    {
        if (!$this->canViewAllPatients($user) && !empty($user->intake_batch)) {
            return [$user->intake_batch];
        }

        $currentYearBE = (int) date('Y') + 544;
        $batches = [];
        for ($i = 0; $i < 5; $i++) {
            $beYear = $currentYearBE - $i;
            $shortYear = substr((string) $beYear, -2);
            $batches[] = "1/{$shortYear}";
            $batches[] = "2/{$shortYear}";
        }

        $activeBatch = class_exists(SettingService::class)
            ? trim((string) SettingService::get('active_intake_batch', ''))
            : '';

        if ($activeBatch !== '' && !in_array($activeBatch, $batches, true)) {
            array_unshift($batches, $activeBatch);
        }

        return $batches;
    }

    // =========================================================================
    // 📋 หน้าทะเบียนรายชื่อและภาพรวมข้อมูลพลทหาร (Index & Overview Dashboard)
    // =========================================================================
    public function index(Request $request)
    {
        $user = auth()->user();
        $canViewChronic = $this->canViewChronicData($user);

        $viewMode = $request->input('view', 'list');

        // 🌟 Eager Load ความสัมพันธ์ทั้งหมดที่ต้องแสดงในตารางเพื่อป้องกัน N+1 Query
        $query = Patient::with([
            'latestMews',
            'militaryUnit',
            'recruitScreening',
            'militaryDischarge',
            'upcomingAppointment',
            'activeInfirmaryStay',
            'activeIpdMorbidity',
        ])->withCount('mews');

        $this->scopeForMedic($query, $user);

        // 1. ตัวกรองค้นหาจากข้อความ
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $searchNoSpace = str_replace(' ', '', $search);

            $query->where(function ($q) use ($search, $searchNoSpace, $canViewChronic) {
                $q->where('cid', 'like', "%{$search}%")
                  ->orWhere('hn', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('pttype_name', 'like', "%{$search}%")
                  ->orWhere('scar_details', 'like', "%{$search}%")
                  ->orWhere('duty_status_note', 'like', "%{$search}%");

                if ($canViewChronic) {
                    $q->orWhere('chronic_disease_detail', 'like', "%{$search}%");
                }

                $q->orWhereRaw("CONCAT(IFNULL(first_name,''), ' ', IFNULL(last_name,'')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CONCAT(IFNULL(first_name,''), IFNULL(last_name,'')) LIKE ?", ["%{$searchNoSpace}%"])
                  ->orWhereRaw("CONCAT(IFNULL(rank,''), IFNULL(first_name,''), IFNULL(last_name,'')) LIKE ?", ["%{$searchNoSpace}%"]);
            });
        }

        // 2. ตัวกรองผลัดที่
        if ($request->filled('batch')) {
            $query->where('intake_batch', $request->batch);
        }

        // 3. ตัวกรองรหัสหน่วยฝึก
        if ($request->filled('unit')) {
            $query->where('military_unit_id', $request->unit);
        }

        // 3.1 ตัวกรองสถานะรหัส HN
        if ($request->filled('hn_status')) {
            if ($request->hn_status === 'unsynced') {
                $query->where(function ($q) {
                    $q->whereNull('hn')->orWhere('hn', '')->orWhere('hn', '-');
                });
            } elseif ($request->hn_status === 'synced') {
                $query->whereNotNull('hn')->where('hn', '!=', '')->where('hn', '!=', '-');
            }
        }

        // 3.2 ตัวกรอง "สิทธิการรักษา (pttype)"
        if ($request->filled('pttype')) {
            if ($request->pttype === 'unsynced') {
                $query->where(function ($q) {
                    $q->whereNull('pttype_name')->orWhere('pttype_name', '');
                });
            } else {
                $query->where('pttype_name', $request->pttype);
            }
        }

        // 3.3 ตัวกรอง "สถานะการคัดกรองแรกรับ (screening_status)"
        if ($request->filled('screening_status')) {
            $scStatus = $request->screening_status;
            if ($scStatus === 'unscreened') {
                $query->doesntHave('recruitScreening')
                      ->where(function ($q) {
                          $q->where('duty_status', 'active')->orWhereNull('duty_status');
                      });
            } elseif ($scStatus === 'screened') {
                $query->whereHas('recruitScreening');
            } elseif (in_array($scStatus, ['green', 'yellow', 'red'], true)) {
                $query->whereHas('recruitScreening', function ($q) use ($scStatus) {
                    $q->where('color_group', $scStatus);
                });
            }
        }

        // 3.4 ตัวกรอง "โรคประจำตัว (chronic_status)" ตามสิทธิ์ PDPA
        if ($canViewChronic && $request->filled('chronic_status')) {
            if ($request->chronic_status === 'has_chronic') {
                $query->whereIn('chronic_disease_status', ['yes', 'other']);
            } elseif (in_array($request->chronic_status, ['none', 'yes', 'other'], true)) {
                $query->where('chronic_disease_status', $request->chronic_status);
            }
        }

        // 🌟 3.5 ตัวกรอง "สถานะกำลังพล / การรายงานตัว / การปลดทหาร (discharge_status)"
        if ($request->filled('discharge_status')) {
            $dStat = $request->discharge_status;
            if ($dStat === 'discharged') {
                $query->whereHas('militaryDischarge');
            } elseif ($dStat === 'active_duty') {
                $query->doesntHave('militaryDischarge')
                      ->where(function ($q) {
                          $q->where('duty_status', 'active')->orWhereNull('duty_status');
                      });
            } elseif (in_array($dStat, ['absent', 'imprisoned', 'deceased'], true)) {
                $query->where('duty_status', $dStat);
            } elseif ($dStat === 'inactive_all') {
                $query->where(function ($q) {
                    $q->whereIn('duty_status', ['absent', 'imprisoned', 'deceased'])
                      ->orWhereHas('militaryDischarge');
                });
            }
        }

        // 🌟 3.6 ตัวกรอง "สถานะการนัดพบแพทย์ (appointment_filter)"
        if ($request->filled('appointment_filter')) {
            $todayDate = Carbon::today()->format('Y-m-d');
            $tomorrowDate = Carbon::tomorrow()->format('Y-m-d');

            if ($request->appointment_filter === 'has_upcoming') {
                $query->whereHas('upcomingAppointment');
            } elseif ($request->appointment_filter === 'today') {
                $query->whereHas('recruitAppointments', function ($q) use ($todayDate) {
                    $q->where('status', 'scheduled')->whereDate('appointment_date', $todayDate);
                });
            } elseif ($request->appointment_filter === 'tomorrow') {
                $query->whereHas('recruitAppointments', function ($q) use ($tomorrowDate) {
                    $q->where('status', 'scheduled')->whereDate('appointment_date', $tomorrowDate);
                });
            }
        }

        // 🌟 3.7 ตัวกรอง "สถานะรักษา MEWS & การนอน รพ. (mews_status)"
        if ($request->filled('mews_status')) {
            $mStatus = $request->mews_status;

            if ($mStatus === 'pending_er') {
                $query->whereHas('latestMews', function ($q) {
                    $q->where('status', 'pending')
                      ->where(function ($sub) {
                          $sub->where('assessment_type', 'er')
                              ->orWhereIn('risk_level', ['observe', 'emergency'])
                              ->orWhere(function ($symQ) {
                                  $symQ->whereNotNull('symptoms')
                                       ->where('symptoms', '!=', '[]')
                                       ->where('symptoms', '!=', '')
                                       ->where('symptoms', '!=', 'null');
                              });
                      });
                });
            } elseif ($mStatus === 'pending_opd') {
                $query->whereHas('latestMews', function ($q) {
                    $q->where('status', 'pending')
                      ->where('assessment_type', 'field')
                      ->where('risk_level', 'normal')
                      ->where(function ($symQ) {
                          $symQ->whereNull('symptoms')
                               ->orWhere('symptoms', '[]')
                               ->orWhere('symptoms', '')
                               ->orWhere('symptoms', 'null');
                      });
                });
            } elseif ($mStatus === 'hospitalized') {
                $query->where(function ($mainQ) {
                    $mainQ->whereHas('latestMews', function ($q) {
                        $q->where('status', 'completed')
                          ->whereIn('discharge_type', ['admitted', 'refer'])
                          ->whereNull('returned_to_unit_at');
                    })->orWhereIn('id', function ($sub) {
                        $sub->select('patient_id')
                            ->from('recruit_morbidities')
                            ->where('category', 'other_ipd')
                            ->where('status', 'treating');
                    });
                });
            } elseif ($mStatus === 'admitted_only') {
                $query->where(function ($mainQ) {
                    $mainQ->whereHas('latestMews', function ($q) {
                        $q->where('status', 'completed')
                          ->where('discharge_type', 'admitted')
                          ->whereNull('returned_to_unit_at');
                    })->orWhereIn('id', function ($sub) {
                        $sub->select('patient_id')
                            ->from('recruit_morbidities')
                            ->where('category', 'other_ipd')
                            ->where('sub_type', 'camp_hospital')
                            ->where('status', 'treating');
                    });
                });
            } elseif ($mStatus === 'refer_only') {
                $query->where(function ($mainQ) {
                    $mainQ->whereHas('latestMews', function ($q) {
                        $q->where('status', 'completed')
                          ->where('discharge_type', 'refer')
                          ->whereNull('returned_to_unit_at');
                    })->orWhereIn('id', function ($sub) {
                        $sub->select('patient_id')
                            ->from('recruit_morbidities')
                            ->where('category', 'other_ipd')
                            ->where('sub_type', 'public_hospital')
                            ->where('status', 'treating');
                    });
                });
            } elseif ($mStatus === 'admit_cumulative') {
                $query->where(function ($mainQ) {
                    $mainQ->whereHas('mews', function ($q) {
                        $q->where('status', 'completed')
                          ->whereIn('discharge_type', ['admitted', 'refer']);
                    })->orWhereIn('id', function ($sub) {
                        $sub->select('patient_id')
                            ->from('recruit_morbidities')
                            ->where('category', 'other_ipd');
                    });
                });
            } elseif ($mStatus === 'returned_unit') {
                $query->whereHas('latestMews', function ($q) {
                    $q->where('status', 'completed')
                      ->whereIn('discharge_type', ['admitted', 'refer'])
                      ->whereNotNull('returned_to_unit_at');
                })->whereDoesntHave('activeIpdMorbidity');
            } elseif ($mStatus === 'recovered') {
                $query->whereHas('latestMews', function ($q) {
                    $q->where('status', 'completed')
                      ->where(function ($sub) {
                          $sub->where('discharge_type', 'recovered')
                              ->orWhereNotNull('returned_to_unit_at');
                      });
                });
            } elseif ($mStatus === 'has_history') {
                $query->whereHas('mews');
            } elseif ($mStatus === 'no_history') {
                $query->doesntHave('mews');
            }
        }

        // =========================================================================
        // 📥 โหมดส่งออกข้อมูลแบบเลือกคอลัมน์เอง (Custom Column Export API)
        // =========================================================================
        if ($request->boolean('export_custom')) {
            $exportQuery = clone $query;

            if ($request->filled('selected_ids')) {
                $selectedIds = array_filter(explode(',', (string) $request->selected_ids));
                if (!empty($selectedIds)) {
                    $exportQuery->whereIn('id', $selectedIds);
                }
            }

            $exportRecords = $exportQuery->orderBy('intake_batch', 'desc')
                ->orderBy('military_unit_id', 'asc')
                ->orderBy('battalion', 'asc')
                ->orderBy('first_name', 'asc')
                ->get();

            $formattedRows = $exportRecords->map(function ($p) use ($canViewChronic) {
                $sc = $p->recruitScreening;
                $mews = $p->latestMews;
                $dc = $p->militaryDischarge;
                $dutyStat = $p->duty_status ?? 'active';

                $dutyLabels = [
                    'active'     => 'มารายงานตัว (ประจำการปกติ)',
                    'absent'     => 'ไม่มารายงานตัว',
                    'imprisoned' => 'ต้องโทษ / ดำเนินคดี',
                    'deceased'   => 'เสียชีวิต',
                ];

                $dobText = '-';
                $ageText = '-';
                if (!empty($p->dob)) {
                    try {
                        $dt = Carbon::parse($p->dob);
                        $dobText = $dt->copy()->addYears(543)->format('d/m/Y');
                        $ageText = $dt->age . ' ปี';
                    } catch (\Throwable $e) {
                    }
                }

                $screeningColorText = 'รอคัดกรอง';
                if (in_array($dutyStat, ['absent', 'imprisoned', 'deceased'], true)) {
                    $screeningColorText = 'ไม่ได้คัดกรอง (' . ($dutyLabels[$dutyStat] ?? '-') . ')';
                } elseif ($sc) {
                    $screeningColorText = $sc->color_group === 'red'
                        ? 'พบเชื้อ (สีแดง)'
                        : ($sc->color_group === 'yellow' ? 'กลุ่มเสี่ยง (สีเหลือง)' : 'ปกติ (สีเขียว)');
                }

                $activeIpd = $p->activeIpdMorbidity;
                $infStay   = $p->activeInfirmaryStay;

                $mewsStatusText = 'ไม่มีประวัติ';
                if ($mews && $mews->status === 'pending') {
                    $rawExportSyms = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : ($mews->symptoms ?? []);
                    $isErExport = ($mews->assessment_type === 'er') || in_array($mews->risk_level, ['observe', 'emergency'], true) || (!empty($rawExportSyms));
                    $mewsStatusText = $isErExport ? 'กำลังรักษา (ER)' : 'รอตรวจแผนกผู้ป่วยนอก (OPD)';
                } elseif (($mews && $mews->status === 'completed' && in_array($mews->discharge_type, ['admitted', 'refer'], true) && empty($mews->returned_to_unit_at)) || $activeIpd) {
                    $isPub = $activeIpd ? ($activeIpd->sub_type === 'public_hospital') : ($mews && $mews->discharge_type === 'refer');
                    $dxStr = ($activeIpd && $activeIpd->diagnosis_text) ? " [Dx: {$activeIpd->diagnosis_text}]" : '';
                    $mewsStatusText = ($isPub ? 'นอน รพ.สาธารณสุข (ยังไม่กลับหน่วยฝึก)' : 'Admit นอน รพ.ค่ายฯ (ยังไม่กลับหน่วยฝึก)') . $dxStr;
                } elseif ($dc) {
                    $mewsStatusText = 'ปลดทหารแล้ว (งดประเมิน MEWS)';
                } elseif (in_array($dutyStat, ['absent', 'imprisoned', 'deceased'], true)) {
                    $mewsStatusText = ($dutyLabels[$dutyStat] ?? '-') . ' (งดประเมิน MEWS)';
                } elseif ($mews) {
                    if ($mews->status === 'cancelled') {
                        $mewsStatusText = 'ยกเลิกเคส';
                    } elseif (in_array($mews->discharge_type, ['admitted', 'refer'], true) && !empty($mews->returned_to_unit_at)) {
                        $mewsStatusText = 'กลับหน่วยฝึกแล้ว (เมื่อ ' . Carbon::parse($mews->returned_to_unit_at)->addYears(543)->format('d/m/Y') . ')';
                    } else {
                        $mewsStatusText = 'จำหน่ายกลับหน่วย';
                    }
                }

                $infirmaryStatusText = 'อยู่ในโรงนอนปกติ';
                if ($infStay) {
                    $locName = $infStay->location_type === 'unit_infirmary' ? 'นอนห้องพยาบาลหน่วยฝึก' : 'ฝากนอนแยกกัก รพ.ค่ายฯ';
                    $infirmaryStatusText = "{$locName} ({$infStay->diagnosis_text})";
                }

                $addressParts = [];
                if (!empty($p->house_no)) $addressParts[] = 'บ้านเลขที่ ' . $p->house_no;
                if (!empty($p->village_no)) $addressParts[] = 'ม.' . $p->village_no;
                if (!empty($p->sub_district)) $addressParts[] = 'ต.' . $p->sub_district;
                if (!empty($p->district)) $addressParts[] = 'อ.' . $p->district;
                if (!empty($p->province)) $addressParts[] = 'จ.' . $p->province;

                $upApp = $p->upcomingAppointment;
                $nextAppText = '-';
                if ($upApp && $upApp->appointment_date && !$dc && $dutyStat === 'active') {
                    $appDateTh = $upApp->appointment_date->copy()->addYears(543)->format('d/m/Y');
                    $hospLabel = $upApp->hospital_name ?: ($upApp->hospital_type === 'public_hospital' ? 'รพ.สธ.' : 'รพ.ค่ายฯ');
                    $nextAppText = "{$appDateTh} ({$hospLabel} - " . ($upApp->clinic_name ?: ($upApp->app_cause ?: 'นัดตรวจ')) . ')';
                }

                $resolvedDischargeStatus = $dc
                    ? ($dc->process_status === 'completed' ? 'ปลดทหารแล้ว (ส่ง ศปน. ครบ)' : 'ขึ้นทะเบียนนำปลด (' . $dc->diagnosis_text . ')')
                    : (($dutyLabels[$dutyStat] ?? 'ประจำการปกติ') . (!empty($p->duty_status_note) ? " ({$p->duty_status_note})" : ''));

                $row = [
                    'full_name'           => ($p->rank ?? '') . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''),
                    'rank'                => $p->rank ?: '-',
                    'first_name'          => $p->first_name ?: '-',
                    'last_name'           => $p->last_name ?: '-',
                    'cid'                 => $p->cid ?: '-',
                    'hn'                  => $p->hn ?: 'ยังไม่มี HN',
                    'pttype_name'         => $p->pttype_name ?: 'รอซิงค์สิทธิ HosXP',
                    'military_id'         => $p->military_id ?: '-',
                    'intake_batch'        => $p->intake_batch ?: '-',
                    'training_unit'       => $p->militaryUnit->name ?? $p->company ?? '-',
                    'battalion'           => $p->battalion ?: '-',
                    'camp_name'           => $p->camp_name ?: 'ค่ายกฤษณ์สีวะรา',
                    'dob'                 => $dobText,
                    'age'                 => $ageText,
                    'blood_group'         => $p->blood_group ?: '-',
                    'height'              => $p->height ?: '-',
                    'weight'              => $p->weight ?: '-',
                    'chest_size'          => $p->chest_size ?: '-',
                    'bmi'                 => $p->bmi ?: '-',
                    'scar_details'        => $p->scar_details ?: '-',
                    'screening_status'    => $screeningColorText,
                    'screening_note'      => $sc ? ($sc->symptoms_note ?: 'ปกติ') : '-',
                    'mews_status'         => $mewsStatusText,
                    'mews_score'          => $mews ? (string) $mews->total_score : '-',
                    'discharge_status'    => $resolvedDischargeStatus,
                    'phone'               => $p->phone ?: '-',
                    'father_name'         => $p->father_name ?: '-',
                    'mother_name'         => $p->mother_name ?: '-',
                    'full_address'        => !empty($addressParts) ? implode(' ', $addressParts) : '-',
                    'district'            => $p->district ?: '-',
                    'province'            => $p->province ?: '-',
                    'religion'            => $p->religion ?: '-',
                    'education'           => $p->education ?: '-',
                    'volunteer_status'    => $p->volunteer_status ?: '-',
                    'privilege_reduction' => $p->privilege_reduction ?: '-',
                    'service_duration'    => $p->service_duration ?: '-',
                    'next_appointment'    => $nextAppText,
                    'infirmary_status'    => $infirmaryStatusText,
                ];

                if ($canViewChronic) {
                    $cStatus = $p->chronic_disease_status ?? 'none';
                    $row['chronic_status'] = $cStatus === 'yes' ? 'มีโรคประจำตัว' : ($cStatus === 'other' ? 'อื่นๆ (ระบุ)' : 'ไม่มีโรคประจำตัว');
                    $row['chronic_detail'] = $p->chronic_disease_detail ?: '-';
                }

                return $row;
            });

            return response()->json([
                'status' => 'success',
                'count'  => $formattedRows->count(),
                'data'   => $formattedRows,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        // 4. ดึงเฉพาะคนที่ใบประเมินล่าสุดมีสถานะ pending (และยังประจำการปกติ)
        $activePatientsQuery = clone $query;
        $activePatients = $activePatientsQuery->doesntHave('militaryDischarge')
            ->where(function ($q) {
                $q->where('duty_status', 'active')->orWhereNull('duty_status');
            })
            ->whereHas('latestMews', function ($q) {
                $q->where('status', 'pending');
            })
            ->get()
            ->sortByDesc(function ($patient) {
                return $patient->latestMews->updated_at ?? $patient->created_at;
            });

        // 5. ดึงรายชื่อผลัดไปทำ Dropdown
        $availableBatchesQuery = Patient::whereNotNull('intake_batch')
            ->where('intake_batch', '!=', '');
        $this->scopeForMedic($availableBatchesQuery, $user);

        $availableBatches = $availableBatchesQuery->distinct()
            ->pluck('intake_batch')
            ->sortDesc();

        $patients = $query->orderBy('intake_batch', 'desc')
            ->orderBy('military_unit_id', 'asc')
            ->orderBy('battalion', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->appends($request->query());

        // 6. สถิติ Dashboard ยอดรวม
        $pttypeStatsQuery = Patient::query();
        $this->scopeForMedic($pttypeStatsQuery, $user);
        if ($request->filled('batch')) {
            $pttypeStatsQuery->where('intake_batch', $request->batch);
        }
        if ($request->filled('unit')) {
            $pttypeStatsQuery->where('military_unit_id', $request->unit);
        }

        $totalPatientsCount = (clone $pttypeStatsQuery)->count();

        $emergencyCount = $activePatients->filter(fn ($p) => $p->latestMews && $p->latestMews->risk_level === 'emergency')->count();
        $observeCount = $activePatients->filter(fn ($p) => $p->latestMews && $p->latestMews->risk_level === 'observe')->count();

        // 6.1 สถิติแยกตาม "สิทธิการรักษา"
        $pttypeCounts = (clone $pttypeStatsQuery)
            ->select(
                DB::raw("CASE WHEN pttype_name IS NULL OR pttype_name = '' THEN 'unsynced' ELSE pttype_name END as right_key"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('right_key')
            ->orderByDesc('total')
            ->get();

        $availablePttypesQuery = Patient::whereNotNull('pttype_name')->where('pttype_name', '!=', '');
        $this->scopeForMedic($availablePttypesQuery, $user);
        $availablePttypes = $availablePttypesQuery->distinct()->orderBy('pttype_name')->pluck('pttype_name');

        // 6.2 สถิติแยกตาม "สถานะการคัดกรองแรกรับ" (นับเฉพาะผู้ที่มารายงานตัวจริง)
        $screeningBaseQuery = (clone $pttypeStatsQuery)->where(function ($q) {
            $q->where('duty_status', 'active')->orWhereNull('duty_status');
        });
        $totalScopedForScreening = (clone $screeningBaseQuery)->count();
        $greenScreeningCount  = (clone $screeningBaseQuery)->whereHas('recruitScreening', fn ($q) => $q->where('color_group', 'green'))->count();
        $yellowScreeningCount = (clone $screeningBaseQuery)->whereHas('recruitScreening', fn ($q) => $q->where('color_group', 'yellow'))->count();
        $redScreeningCount    = (clone $screeningBaseQuery)->whereHas('recruitScreening', fn ($q) => $q->where('color_group', 'red'))->count();
        $screenedTotalCount   = $greenScreeningCount + $yellowScreeningCount + $redScreeningCount;
        $unscreenedCount      = max(0, $totalScopedForScreening - $screenedTotalCount);

        $screeningStats = [
            'total'      => $totalScopedForScreening,
            'screened'   => $screenedTotalCount,
            'unscreened' => $unscreenedCount,
            'green'      => $greenScreeningCount,
            'yellow'     => $yellowScreeningCount,
            'red'        => $redScreeningCount,
            'percent'    => $totalScopedForScreening > 0 ? round(($screenedTotalCount / $totalScopedForScreening) * 100, 1) : 0,
        ];

        // 6.3 สถิติเสริมสำหรับหน้า Overview Dashboard (รวบไว้ใน 1 Query เดียว)
        $aggStats = (clone $pttypeStatsQuery)->selectRaw("
            SUM(CASE WHEN hn IS NOT NULL AND hn != '' AND hn != '-' THEN 1 ELSE 0 END) as synced_hn,
            SUM(CASE WHEN chronic_disease_status = 'yes' THEN 1 ELSE 0 END) as chronic_yes,
            SUM(CASE WHEN chronic_disease_status = 'other' THEN 1 ELSE 0 END) as chronic_other,
            SUM(CASE WHEN duty_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN duty_status = 'imprisoned' THEN 1 ELSE 0 END) as imprisoned_count,
            SUM(CASE WHEN duty_status = 'deceased' THEN 1 ELSE 0 END) as deceased_count
        ")->first();

        $syncedHnCount     = (int) ($aggStats->synced_hn ?? 0);
        $unsyncedHnCount   = max(0, $totalPatientsCount - $syncedHnCount);
        $chronicYesCount   = $canViewChronic ? (int) ($aggStats->chronic_yes ?? 0) : 0;
        $chronicOtherCount = $canViewChronic ? (int) ($aggStats->chronic_other ?? 0) : 0;
        $absentCount       = (int) ($aggStats->absent_count ?? 0);
        $imprisonedCount   = (int) ($aggStats->imprisoned_count ?? 0);
        $deceasedCount     = (int) ($aggStats->deceased_count ?? 0);

        $dischargeTotalCount = 0;
        if (class_exists(MilitaryDischarge::class) && Schema::hasTable('military_discharges')) {
            $dcQuery = MilitaryDischarge::query();
            $this->scopeForMedic($dcQuery, $user);
            if ($request->filled('batch')) {
                $dcQuery->where('intake_batch', $request->batch);
            }
            if ($request->filled('unit')) {
                $dcQuery->where('military_unit_id', $request->unit);
            }
            $dischargeTotalCount = $dcQuery->count();
        }

        $overviewExtraStats = [
            'synced_hn'        => $syncedHnCount,
            'unsynced_hn'      => $unsyncedHnCount,
            'hn_percent'       => $totalPatientsCount > 0 ? round(($syncedHnCount / $totalPatientsCount) * 100, 1) : 0,
            'chronic_yes'      => $chronicYesCount,
            'chronic_other'    => $chronicOtherCount,
            'chronic_total'    => $chronicYesCount + $chronicOtherCount,
            'discharge_total'  => $dischargeTotalCount,
            'absent_count'     => $absentCount,
            'imprisoned_count' => $imprisonedCount,
            'deceased_count'   => $deceasedCount,
            'inactive_total'   => $absentCount + $imprisonedCount + $deceasedCount,
        ];

        // 7. ดึงชื่อ 4 ผลัดล่าสุดตามสิทธิ์
        $batchQuery = Patient::select('intake_batch')
            ->whereNotNull('intake_batch')
            ->where('intake_batch', '!=', '');
        $this->scopeForMedic($batchQuery, $user);

        $recentBatches = $batchQuery->distinct()
            ->orderBy('intake_batch', 'desc')
            ->limit(4)
            ->pluck('intake_batch');

        // 8. สถิติแยกตามหน่วยและผลัด (4 ผลัดล่าสุด)
        $unitStatsQuery = Patient::whereIn('intake_batch', $recentBatches)
            ->select('intake_batch', 'military_unit_id', DB::raw('count(*) as total'))
            ->with('militaryUnit:id,name,short_name');
        $this->scopeForMedic($unitStatsQuery, $user);

        $batchUnitStats = $unitStatsQuery->groupBy('intake_batch', 'military_unit_id')
            ->orderBy('intake_batch', 'desc')
            ->get()
            ->groupBy('intake_batch');

        // 8.1 สถิติของทุกผลัดทั้งหมด
        $allUnitStatsQuery = Patient::whereNotNull('intake_batch')
            ->where('intake_batch', '!=', '')
            ->select('intake_batch', 'military_unit_id', DB::raw('count(*) as total'))
            ->with('militaryUnit:id,name,short_name');
        $this->scopeForMedic($allUnitStatsQuery, $user);

        $allBatchUnitStats = $allUnitStatsQuery->groupBy('intake_batch', 'military_unit_id')
            ->orderBy('intake_batch', 'desc')
            ->get()
            ->groupBy('intake_batch');

        // 9. รายชื่อหน่วยฝึกสำหรับ Dropdown
        $unitsDropdownQuery = MilitaryUnit::where('is_active', true)->orderBy('name');
        if (!$this->canViewAllPatients($user)) {
            $unitsDropdownQuery->where('id', $user->military_unit_id);
        }
        $allUnits = $unitsDropdownQuery->get();

        $hosxpSyncPaused   = $this->isHosxpSyncPaused();
        $hosxpMaxBatchSize = $this->getHosxpMaxBatchSize();

        return view('backend.patients.index', compact(
            'patients', 'activePatients', 'availableBatches',
            'totalPatientsCount', 'emergencyCount', 'observeCount', 'batchUnitStats', 'recentBatches', 'allBatchUnitStats',
            'allUnits', 'availablePttypes', 'pttypeCounts', 'screeningStats',
            'viewMode', 'overviewExtraStats', 'hosxpSyncPaused', 'hosxpMaxBatchSize'
        ));
    }

    public function showSearchForm()
    {
        return view('backend.patients.search');
    }

    public function processSearch(Request $request)
    {
        $request->validate([
            'search_term'  => 'required|string|min:2',
            'scanned_name' => 'nullable|string|max:255',
            'is_scanned'   => 'nullable|boolean',
        ], [
            'search_term.required' => 'กรุณากรอกข้อมูลที่ต้องการค้นหา',
            'search_term.min'      => 'กรุณากรอกข้อมูลอย่างน้อย 2 ตัวอักษร',
        ]);

        $term        = trim((string) $request->search_term);
        $termNoSpace = str_replace(' ', '', $term);
        $cleanDigits = preg_replace('/[^0-9]/', '', $term);
        $scannedName = trim((string) $request->input('scanned_name', ''));
        $isScanned   = $request->boolean('is_scanned');
        $user        = auth()->user();

        $userRoles = $this->getUserRoles($user);
        $isClinical = !empty(array_intersect(
            ['admin', 'er_nurse', 'opd_nurse', 'medic', 'medical_staff', 'screening'],
            $userRoles
        ));

        $query = Patient::with(['militaryUnit', 'militaryDischarge'])->where(function ($q) use ($term, $termNoSpace) {
            $q->where('cid', 'LIKE', "%{$term}%")
              ->orWhere('hn', 'LIKE', "%{$term}%")
              ->orWhereRaw("CONCAT(IFNULL(first_name,''), IFNULL(last_name,'')) LIKE ?", ["%{$termNoSpace}%"])
              ->orWhereRaw("CONCAT(IFNULL(rank,''), IFNULL(first_name,''), IFNULL(last_name,'')) LIKE ?", ["%{$termNoSpace}%"])
              ->orWhere('first_name', 'LIKE', "%{$term}%")
              ->orWhere('last_name', 'LIKE', "%{$term}%");
        });

        $this->scopeForMedic($query, $user);

        $ksvrResults = $query->get();

        if ($ksvrResults->count() === 1) {
            $patient = $ksvrResults->first();

            // 🚫 บล็อกการส่งไปหน้าประเมิน MEWS หากปลดทหาร หรือ ไม่มารายงานตัว/ต้องโทษ/เสียชีวิต
            if ($patient->militaryDischarge || in_array($patient->duty_status ?? 'active', ['absent', 'imprisoned', 'deceased'], true)) {
                return redirect()->route('backend.patients.show', $patient->id)
                    ->with('error', "พลทหาร {$patient->first_name} อยู่ในสถานะ \"{$patient->duty_status_label}\" ไม่สามารถแจ้งหรือประเมิน MEWS ได้ครับ");
            }

            if ($isClinical) {
                return redirect()->route('backend.mews.create', ['patient_id' => $patient->id])
                    ->with('success', 'พบข้อมูลประวัติในระบบ KSVR เรียบร้อยแล้ว');
            }

            return redirect()->route('backend.patients.show', $patient->id)
                ->with('success', 'พบข้อมูลประวัติผู้ป่วยแล้ว');
        }

        if ($ksvrResults->count() > 1) {
            return redirect()->route('backend.patients.index', ['view' => 'list', 'search' => $term])
                ->with('info', 'พบข้อมูลมากกว่า 1 รายการ กรุณาเลือกจากตารางด้านล่างครับ');
        }

        // =========================================================================
        // 🔍 กรณีไม่พบในสิทธิ์ที่ดูแล: รวบรวม "เลขบัตร ปชช. + ชื่อ-สกุล" เพื่อยืนยัน
        // =========================================================================
        $resolvedCid  = strlen($cleanDigits) === 13 ? $cleanDigits : null;
        $resolvedName = $scannedName !== '' ? $scannedName : null;
        $nameSource   = $resolvedName ? 'ocr' : null;
        $extraNote    = null;
        $hosxpHn      = null;
        $existsInKsvr = false;

        if ($resolvedCid) {
            // 1. ตรวจสอบว่ามีเลขบัตรนี้ในระบบ KSVR แต่อยู่ต่างหน่วย/ต่างผลัดหรือไม่ (Query ผ่าน Index cid)
            $otherScopePatient = Patient::with('militaryUnit')
                ->where('cid', $resolvedCid)
                ->first(['id', 'cid', 'hn', 'rank', 'first_name', 'last_name', 'military_unit_id', 'intake_batch']);

            if ($otherScopePatient) {
                $existsInKsvr = true;
                $resolvedName = trim(($otherScopePatient->rank ?? '') . ($otherScopePatient->first_name ?? '') . ' ' . ($otherScopePatient->last_name ?? ''));
                $nameSource   = 'ksvr_other_scope';
                $unitLabel    = $otherScopePatient->militaryUnit->short_name ?? $otherScopePatient->militaryUnit->name ?? 'ไม่ระบุหน่วย';
                $batchLabel   = $otherScopePatient->intake_batch ?: 'ไม่ระบุผลัด';
                $extraNote    = "พบประวัติในระบบ KSVR แล้ว แต่อยู่ต่างสังกัด/ผลัด ({$unitLabel} | ผลัด {$batchLabel})";
            } elseif ($this->isHosxpSyncPaused()) {
                // 🛡️ เคารพสวิตช์พักการเชื่อมต่อ HosXP ชั่วคราวจากหน้าตั้งค่าระบบ
                $extraNote = 'ขณะนี้ระบบพักการเชื่อมต่อ HosXP ชั่วคราว จึงแสดงข้อมูลเฉพาะจากฝั่ง KSVR และบัตรที่สแกนครับ';
            } else {
                // 2. ตรวจสอบแบบ Read-Only ความเร็วสูงจาก HosXP ด้วยเลขบัตร 13 หลัก (Exact Match ผ่าน Index cid)
                try {
                    $this->applyHosxpTimeout();
                    $hosxpPatient = HosxpPatient::select('hn', 'cid', 'pname', 'fname', 'lname')
                        ->where('cid', $resolvedCid)
                        ->first();

                    if ($hosxpPatient) {
                        $pname = $this->cleanHosxpUtf8($hosxpPatient->pname ?? '');
                        $fname = $this->cleanHosxpUtf8($hosxpPatient->fname ?? '');
                        $lname = $this->cleanHosxpUtf8($hosxpPatient->lname ?? '');
                        $fullHosxpName = trim($pname . $fname . ' ' . $lname);

                        if ($fullHosxpName !== '') {
                            $resolvedName = $fullHosxpName;
                            $nameSource   = 'hosxp';
                            $hosxpHn      = $this->cleanHosxpUtf8($hosxpPatient->hn ?? '');
                            $extraNote    = 'ยังไม่ได้ลงทะเบียนในระบบ KSVR แต่พบชื่อ-สกุลตรงกันในฐานข้อมูล HosXP ของโรงพยาบาล';
                        }
                    }
                } catch (\Throwable $e) {
                    // หากเชื่อมต่อ HosXP ไม่ได้ชั่วคราว ให้ใช้ชื่อที่อ่านจากหน้าบัตร (OCR) แทนโดยไม่ให้หน้าเว็บค้าง
                }
            }
        }

        // ตรวจสอบสูตร Mod-11 ของเลขบัตร 13 หลัก
        $isCidValid = false;
        if ($resolvedCid && preg_match('/^[1-8]\d{12}$/', $resolvedCid)) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += ((int) $resolvedCid[$i]) * (13 - $i);
            }
            $isCidValid = ((11 - ($sum % 11)) % 10) === (int) $resolvedCid[12];
        }

        return back()->withInput()->with('not_found_detail', [
            'searched_term'  => $term,
            'cid'            => $resolvedCid,
            'formatted_cid'  => $resolvedCid
                ? preg_replace('/^(\d)(\d{4})(\d{5})(\d{2})(\d)$/', '$1-$2-$3-$4-$5', $resolvedCid)
                : null,
            'is_cid_valid'   => $isCidValid,
            'full_name'      => $resolvedName,
            'name_source'    => $nameSource,
            'hosxp_hn'       => $hosxpHn,
            'exists_in_ksvr' => $existsInKsvr,
            'is_scanned'     => $isScanned,
            'extra_note'     => $extraNote,
        ]);
    }

    public function autocomplete(Request $request)
    {
        $term = trim((string) $request->get('term'));
        $termNoSpace = str_replace(' ', '', $term);
        $user = auth()->user();

        if ($term === '') {
            return response()->json([]);
        }

        $query = Patient::with(['militaryUnit', 'militaryDischarge']);
        $this->scopeForMedic($query, $user);

        $patients = $query->where(function ($q) use ($term, $termNoSpace) {
            $q->where('cid', 'LIKE', "%{$term}%")
              ->orWhere('hn', 'LIKE', "%{$term}%")
              ->orWhere('first_name', 'LIKE', "%{$term}%")
              ->orWhere('last_name', 'LIKE', "%{$term}%")
              ->orWhereRaw("CONCAT(IFNULL(first_name,''), IFNULL(last_name,'')) LIKE ?", ["%{$termNoSpace}%"]);
        })
            ->take(10)
            ->get(['id', 'cid', 'hn', 'rank', 'first_name', 'last_name', 'military_unit_id', 'battalion', 'duty_status', 'duty_status_note']);

        $mappedPatients = $patients->map(function ($patient) {
            $isLocked = $patient->militaryDischarge !== null || in_array($patient->duty_status ?? 'active', ['absent', 'imprisoned', 'deceased'], true);
            return [
                'id'           => $patient->id,
                'cid'          => $patient->cid,
                'hn'           => $patient->hn,
                'rank'         => $patient->rank,
                'first_name'   => $patient->first_name,
                'last_name'    => $patient->last_name,
                'battalion'    => $patient->battalion,
                'company'      => $patient->militaryUnit->short_name ?? $patient->militaryUnit->name ?? '-',
                'is_locked'    => $isLocked,
                'status_label' => $isLocked ? $patient->duty_status_label : null,
            ];
        });

        return response()->json($mappedPatients);
    }

    public function create()
    {
        $user = auth()->user();

        $unitsQuery = MilitaryUnit::where('is_active', true)->orderBy('name');
        if (!$this->canViewAllPatients($user)) {
            $unitsQuery->where('id', $user->military_unit_id);
        }
        $units = $unitsQuery->get();
        $batches = $this->buildBatchOptionsForUser($user);

        return view('backend.patients.create', compact('units', 'batches'));
    }

    public function show($id)
    {
        $patient = Patient::with([
            'mewsAssessments' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'recruitScreening',
            'militaryDischarge',
            'upcomingAppointment',
            'recruitAppointments',
            'activeInfirmaryStay',
            'infirmaryStays' => function ($query) {
                $query->orderBy('admitted_at', 'desc');
            },
            'activeIpdMorbidity',
            'morbidityRecords',
            'medications',
            'covidVitalLogs',
        ])->findOrFail($id);

        $this->checkPatientAccess($patient);

        return view('backend.patients.show', compact('patient'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$this->canViewAllPatients($user)) {
            $request->merge([
                'military_unit_id' => $user->military_unit_id,
                'intake_batch'     => $user->intake_batch ?? $request->intake_batch,
            ]);
        }

        $validatedData = $request->validate([
            'cid'                    => 'required|string|size:13|unique:patients,cid',
            'hn'                     => 'nullable|string|max:20',
            'pttype_code'            => 'nullable|string|max:10',
            'pttype_name'            => 'nullable|string|max:150',
            'rank'                   => 'required|string|max:50',
            'first_name'             => 'required|string|max:255',
            'last_name'              => 'required|string|max:255',
            'dob'                    => 'nullable|date',
            'blood_group'            => 'nullable|string|max:10',
            'phone'                  => 'nullable|string|max:20',
            'house_no'               => 'nullable|string|max:50',
            'village_no'             => 'nullable|string|max:10',
            'sub_district'           => 'nullable|string|max:100',
            'district'               => 'nullable|string|max:100',
            'province'               => 'nullable|string|max:100',
            'father_name'            => 'nullable|string|max:255',
            'mother_name'            => 'nullable|string|max:255',
            'height'                 => 'nullable|string|max:10',
            'weight'                 => 'nullable|string|max:10',
            'chest_size'             => 'nullable|string|max:20',
            'bmi'                    => 'nullable|string|max:10',
            'scar_details'           => 'nullable|string|max:255',
            'religion'               => 'nullable|string|max:50',
            'education'              => 'nullable|string|max:100',
            'military_id'            => 'nullable|string|max:50',
            'military_unit_id'       => 'required|exists:military_units,id',
            'camp_name'              => 'nullable|string|max:255',
            'battalion'              => 'nullable|string|max:255',
            'intake_batch'           => 'nullable|string|max:20',
            'volunteer_status'       => 'nullable|string|max:100',
            'privilege_reduction'    => 'nullable|string|max:100',
            'service_duration'       => 'nullable|string|max:50',
            'duty_status'            => 'nullable|in:active,absent,imprisoned,deceased',
            'duty_status_note'       => 'nullable|string|max:255',
            'chronic_disease_status' => 'nullable|in:none,yes,other',
            'chronic_disease_detail' => 'nullable|string|max:255',
        ], [
            'cid.unique'                => 'เลขประจำตัวประชาชนนี้ ถูกลงทะเบียนในระบบ KSVR เรียบร้อยแล้ว',
            'cid.size'                  => 'เลขประจำตัวประชาชนต้องมี 13 หลักถ้วน',
            'military_unit_id.required' => 'กรุณาเลือกสังกัด/กองร้อย',
        ]);

        if (($validatedData['chronic_disease_status'] ?? 'none') === 'none') {
            $validatedData['chronic_disease_detail'] = null;
        }
        if (($validatedData['duty_status'] ?? 'active') === 'active') {
            $validatedData['duty_status_note'] = null;
        }

        try {
            $patient = Patient::create($validatedData);

            AuditLog::record('create_patient', $patient, [
                'cid'     => $patient->cid,
                'unit_id' => $patient->military_unit_id,
            ]);

            return redirect()->route('backend.patients.index', ['view' => 'list'])
                ->with('success', 'เพิ่มข้อมูลประวัติทหารใหม่เรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            Log::error('Patient Create Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง');
        }
    }

    public function edit($id)
    {
        $patient = Patient::findOrFail($id);
        $this->checkPatientAccess($patient);

        $user = auth()->user();
        $unitsQuery = MilitaryUnit::where('is_active', true)->orderBy('name');
        if (!$this->canViewAllPatients($user)) {
            $unitsQuery->where('id', $user->military_unit_id);
        }
        $units = $unitsQuery->get();
        $batches = $this->buildBatchOptionsForUser($user);

        return view('backend.patients.edit', compact('patient', 'units', 'batches'));
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if (!$this->canViewAllPatients($user)) {
            $request->merge([
                'military_unit_id' => $user->military_unit_id,
                'intake_batch'     => $user->intake_batch ?? $request->intake_batch,
            ]);
        }

        $patient = Patient::findOrFail($id);
        $this->checkPatientAccess($patient);

        $validatedData = $request->validate([
            'cid'                    => 'required|string|size:13|unique:patients,cid,' . $patient->id,
            'hn'                     => 'nullable|string|max:20',
            'pttype_code'            => 'nullable|string|max:10',
            'pttype_name'            => 'nullable|string|max:150',
            'rank'                   => 'required|string|max:50',
            'first_name'             => 'required|string|max:255',
            'last_name'              => 'required|string|max:255',
            'dob'                    => 'nullable|date',
            'religion'               => 'nullable|string|max:50',
            'education'              => 'nullable|string|max:100',
            'blood_group'            => 'nullable|string|max:10',
            'height'                 => 'nullable|numeric',
            'weight'                 => 'nullable|numeric',
            'bmi'                    => 'nullable|string|max:10',
            'chest_size'             => 'nullable|string|max:20',
            'scar_details'           => 'nullable|string|max:255',
            'phone'                  => 'nullable|string|max:20',
            'father_name'            => 'nullable|string|max:255',
            'mother_name'            => 'nullable|string|max:255',
            'house_no'               => 'nullable|string|max:50',
            'village_no'             => 'nullable|string|max:10',
            'sub_district'           => 'nullable|string|max:100',
            'district'               => 'nullable|string|max:100',
            'province'               => 'nullable|string|max:100',
            'military_id'            => 'nullable|string|max:50',
            'battalion'              => 'nullable|string|max:255',
            'military_unit_id'       => 'required|exists:military_units,id',
            'intake_batch'           => 'nullable|string|max:20',
            'camp_name'              => 'nullable|string|max:255',
            'volunteer_status'       => 'nullable|string|max:100',
            'privilege_reduction'    => 'nullable|string|max:100',
            'service_duration'       => 'nullable|string|max:50',
            'duty_status'            => 'nullable|in:active,absent,imprisoned,deceased',
            'duty_status_note'       => 'nullable|string|max:255',
            'chronic_disease_status' => 'nullable|in:none,yes,other',
            'chronic_disease_detail' => 'nullable|string|max:255',
        ], [
            'cid.unique'                => 'เลขประจำตัวประชาชนนี้ มีในระบบแล้ว ไม่สามารถใช้ซ้ำได้',
            'cid.size'                  => 'เลขประจำตัวประชาชนต้องมี 13 หลักถ้วน',
            'military_unit_id.required' => 'กรุณาเลือกสังกัด/กองร้อย',
        ]);

        if (($validatedData['chronic_disease_status'] ?? 'none') === 'none') {
            $validatedData['chronic_disease_detail'] = null;
        }
        if (($validatedData['duty_status'] ?? 'active') === 'active') {
            $validatedData['duty_status_note'] = null;
        }

        try {
            $patient->update($validatedData);

            AuditLog::record('update_patient', $patient, [
                'updated_fields' => array_keys($validatedData),
            ]);

            return redirect()->route('backend.patients.show', $patient->id)
                ->with('success', 'อัปเดตข้อมูลประวัติเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            Log::error('Patient Update Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล กรุณาลองใหม่อีกครั้ง');
        }
    }

    // 🌟 อัปเดตสถานะการรายงานตัว / สถานะกำลังพลแบบด่วน (1-Click Status Update)
    public function updateDutyStatus(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $this->checkPatientAccess($patient);

        $data = $request->validate([
            'duty_status'      => 'required|in:active,absent,imprisoned,deceased',
            'duty_status_note' => 'nullable|string|max:255',
        ]);

        if ($data['duty_status'] === 'active') {
            $data['duty_status_note'] = null;
        }

        $oldStatus = $patient->duty_status ?? 'active';
        $patient->update([
            'duty_status'      => $data['duty_status'],
            'duty_status_note' => $data['duty_status_note'] ?? null,
        ]);

        AuditLog::record('update_duty_status', $patient, [
            'old_status' => $oldStatus,
            'new_status' => $data['duty_status'],
            'note'       => $data['duty_status_note'] ?? null,
        ]);

        $msg = "อัปเดตสถานะของ {$patient->rank}{$patient->first_name} เป็น \"{$patient->duty_status_label}\" เรียบร้อยแล้ว";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status'  => 'success',
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function destroy($id)
    {
        try {
            $patient = Patient::findOrFail($id);
            $this->checkPatientAccess($patient);

            $deletedName = $patient->rank . $patient->first_name . ' ' . $patient->last_name;
            $deletedCid = $patient->cid;

            $patient->delete();

            AuditLog::record('delete_patient', null, [
                'deleted_name' => $deletedName,
                'deleted_cid'  => $deletedCid,
            ]);

            return back()->with('success', 'ลบข้อมูลประวัติผู้ป่วยออกจากระบบเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            return back()->with('error', 'ไม่สามารถลบข้อมูลได้: ' . $e->getMessage());
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'patient_ids' => 'required|string',
        ]);

        try {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $request->patient_ids)))));
            if (empty($ids)) {
                return back()->with('error', 'ไม่พบรายการที่ต้องการลบ');
            }

            $roles = $this->getUserRoles(auth()->user());
            if (!in_array('admin', $roles, true)) {
                abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบข้อมูลจำนวนมากได้');
            }

            Patient::whereIn('id', $ids)->delete();

            AuditLog::record('bulk_delete_patients', null, [
                'deleted_count' => count($ids),
                'deleted_ids'   => $ids,
            ]);

            return back()->with('success', 'ลบข้อมูลพลทหารจำนวน ' . count($ids) . ' รายการเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            return back()->with('error', 'ไม่สามารถลบข้อมูลได้: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 🔍 ค้นหาข้อมูลจาก HosXP แบบ Read-Only (หน้า Create / Edit) — คุมด้วย HIS Safety
    // =========================================================================
    public function searchHosxp(Request $request)
    {
        // 🛡️ เช็กสวิตช์พักการซิงค์ HosXP ชั่วคราวก่อนยิง Query
        if ($this->isHosxpSyncPaused()) {
            return $this->hosxpPausedJsonResponse();
        }

        $rawTerm = (string) $request->get('term', '');
        $term = preg_replace('/[^0-9]/', '', $rawTerm);

        if (empty($term)) {
            return response()->json(['status' => 'error', 'message' => 'กรุณากรอกเฉพาะตัวเลข (CID หรือ HN) ครับ']);
        }

        try {
            $this->applyHosxpTimeout();

            try {
                $query = HosxpPatient::leftJoin('pttype', 'patient.pttype', '=', 'pttype.pttype')
                    ->select(
                        'patient.hn', 'patient.cid', 'patient.pname', 'patient.fname', 'patient.lname',
                        'patient.birthday', 'patient.bloodgrp',
                        'patient.pttype as pttype_code', 'pttype.name as pttype_name'
                    );
                if (strlen($term) === 13) {
                    $query->where('patient.cid', $term);
                } else {
                    $query->where('patient.hn', $term);
                }
                $hosxpPatient = $query->first();
            } catch (\Throwable $joinErr) {
                $query = HosxpPatient::select('hn', 'cid', 'pname', 'fname', 'lname', 'birthday', 'bloodgrp', 'pttype as pttype_code');
                if (strlen($term) === 13) {
                    $query->where('cid', $term);
                } else {
                    $query->where('hn', $term);
                }
                $hosxpPatient = $query->first();
            }

            if (!$hosxpPatient) {
                AuditLog::record('search_hosxp_not_found', null, ['searched_term' => $term]);
                return response()->json(['status' => 'not_found', 'message' => 'ไม่พบข้อมูลใน HosXP กรุณาตรวจสอบเลขอีกครั้งครับ']);
            }

            $ptCode = mb_substr($this->cleanHosxpUtf8($hosxpPatient->pttype_code ?? ''), 0, 10);
            $ptName = mb_substr($this->cleanHosxpUtf8($hosxpPatient->pttype_name ?? ''), 0, 150);
            if (empty($ptName) && !empty($ptCode)) {
                $ptName = 'รหัสสิทธิ: ' . $ptCode;
            }

            $foundHn = $this->cleanHosxpUtf8($hosxpPatient->hn);

            AuditLog::record('search_hosxp_success', null, [
                'searched_term' => $term,
                'found_hn'      => $foundHn,
            ]);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'hn'          => $foundHn,
                    'cid'         => $this->cleanHosxpUtf8($hosxpPatient->cid),
                    'rank'        => $this->cleanHosxpUtf8($hosxpPatient->pname),
                    'first_name'  => $this->cleanHosxpUtf8($hosxpPatient->fname),
                    'last_name'   => $this->cleanHosxpUtf8($hosxpPatient->lname),
                    'dob'         => $this->parseHosxpBirthday($hosxpPatient) ?? $hosxpPatient->birthday,
                    'blood_group' => $this->normalizeHosxpBloodGroup($hosxpPatient->bloodgrp ?? '') ?? $this->cleanHosxpUtf8($hosxpPatient->bloodgrp),
                    'pttype_code' => $ptCode ?: null,
                    'pttype_name' => $ptName ?: null,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        } catch (\Throwable $e) {
            Log::error('HosXP Connection Failed: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล HosXP ได้ (Connection Timeout)',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    // =========================================================================
    // 🔄 ซิงค์ข้อมูล HN, วันเกิด, กรุ๊ปเลือด และสิทธิการรักษา กับ HosXP (รายบุคคล)
    // =========================================================================
    public function syncHosxp(Request $request, $id)
    {
        try {
            // 🛡️ เช็กสวิตช์พักการซิงค์ HosXP ชั่วคราวก่อนเริ่มงาน
            if ($this->isHosxpSyncPaused()) {
                return $this->hosxpPausedJsonResponse();
            }

            $patient = Patient::findOrFail($id);
            $user = auth()->user();
            $userRoles = $this->getUserRoles($user);

            $allowedRoles = ['admin', 'registration', 'er_nurse', 'opd_nurse', 'medical_staff'];
            if (empty(array_intersect($allowedRoles, $userRoles))) {
                return response()->json(['status' => 'ignored', 'message' => 'สิทธิ์ของคุณไม่จำเป็นต้องซิงค์ข้อมูล HosXP']);
            }

            $isRegistrarOrAdmin = in_array('admin', $userRoles, true) || in_array('registration', $userRoles, true);

            $cleanCid = preg_replace('/[^0-9]/', '', $patient->cid ?? '');
            if (strlen($cleanCid) !== 13) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'เลขบัตรประชาชนในระบบไม่ครบ 13 หลัก ไม่สามารถค้นหาใน HosXP ได้',
                ]);
            }

            $this->applyHosxpTimeout();

            try {
                $hosxpPatient = HosxpPatient::leftJoin('pttype', 'patient.pttype', '=', 'pttype.pttype')
                    ->select(
                        'patient.hn', 'patient.cid', 'patient.pname', 'patient.fname', 'patient.lname',
                        'patient.birthday', 'patient.bloodgrp',
                        'patient.pttype as pttype_code', 'pttype.name as pttype_name'
                    )
                    ->where('patient.cid', $cleanCid)
                    ->first();
            } catch (\Throwable $joinErr) {
                $hosxpPatient = HosxpPatient::select('hn', 'cid', 'pname', 'fname', 'lname', 'birthday', 'bloodgrp', 'pttype as pttype_code')
                    ->where('cid', $cleanCid)
                    ->first();
            }

            if (!$hosxpPatient || empty($hosxpPatient->hn)) {
                return response()->json([
                    'status'  => 'not_found',
                    'message' => 'ยังไม่พบประวัติเลขบัตรประชาชนนี้ในระบบ HosXP ของโรงพยาบาล',
                ]);
            }

            $hosxpHn     = $this->cleanHosxpUtf8($hosxpPatient->hn);
            $hosxpFname  = $this->cleanHosxpUtf8($hosxpPatient->fname ?? '');
            $hosxpLname  = $this->cleanHosxpUtf8($hosxpPatient->lname ?? '');
            $hosxpBlood  = $this->normalizeHosxpBloodGroup($hosxpPatient->bloodgrp ?? '');
            $hosxpPtCode = mb_substr($this->cleanHosxpUtf8($hosxpPatient->pttype_code ?? ''), 0, 10);
            $hosxpPtName = mb_substr($this->cleanHosxpUtf8($hosxpPatient->pttype_name ?? ''), 0, 150);
            if (empty($hosxpPtName) && !empty($hosxpPtCode)) {
                $hosxpPtName = 'รหัสสิทธิ: ' . $hosxpPtCode;
            }
            $hosxpDob = $this->parseHosxpBirthday($hosxpPatient);

            $mewsFname = trim($patient->first_name ?? '');
            $mewsLname = trim($patient->last_name ?? '');

            $syncPayload = ['hn' => $hosxpHn];
            if (!empty($hosxpPtCode)) {
                $syncPayload['pttype_code'] = $hosxpPtCode;
            }
            if (!empty($hosxpPtName)) {
                $syncPayload['pttype_name'] = $hosxpPtName;
            }
            if ($hosxpDob) {
                $syncPayload['dob'] = $hosxpDob;
            }
            if (!empty($hosxpBlood) && empty($patient->blood_group)) {
                $syncPayload['blood_group'] = $hosxpBlood;
            }

            if ($request->boolean('force_sync') && $isRegistrarOrAdmin) {
                if ($request->boolean('update_name')) {
                    $syncPayload['first_name'] = $hosxpFname;
                    $syncPayload['last_name']  = $hosxpLname;
                }
                try {
                    Patient::where('id', $patient->id)->update($syncPayload);
                } catch (\Throwable $saveErr) {
                    Patient::where('id', $patient->id)->update(['hn' => $hosxpHn]);
                }

                AuditLog::record('sync_hosxp', $patient, [
                    'hn'          => $hosxpHn,
                    'pttype_name' => $hosxpPtName ?: null,
                    'force_sync'  => true,
                ]);

                return response()->json([
                    'status'       => 'synced',
                    'hn'           => $hosxpHn,
                    'pttype_code'  => $hosxpPtCode ?: null,
                    'pttype_name'  => $hosxpPtName ?: null,
                    'dob'          => $hosxpDob ?? $patient->dob,
                    'updated_name' => $request->boolean('update_name') ? ($patient->rank . $hosxpFname . ' ' . $hosxpLname) : null,
                    'message'      => 'ยืนยันและซิงค์รหัส HN: ' . $hosxpHn . ($hosxpPtName ? " ({$hosxpPtName})" : '') . ' เรียบร้อยแล้ว!',
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }

            $isNameMatch = ($mewsFname === $hosxpFname) && ($mewsLname === $hosxpLname);

            if ($isNameMatch) {
                try {
                    Patient::where('id', $patient->id)->update($syncPayload);
                } catch (\Throwable $saveErr) {
                    Patient::where('id', $patient->id)->update(['hn' => $hosxpHn]);
                }

                AuditLog::record('sync_hosxp', $patient, [
                    'hn'          => $hosxpHn,
                    'pttype_name' => $hosxpPtName ?: null,
                    'force_sync'  => false,
                ]);

                return response()->json([
                    'status'      => 'synced',
                    'hn'          => $hosxpHn,
                    'pttype_code' => $hosxpPtCode ?: null,
                    'pttype_name' => $hosxpPtName ?: null,
                    'dob'         => $hosxpDob ?? $patient->dob,
                    'message'     => 'ซิงค์ HN: ' . $hosxpHn . ($hosxpPtName ? " | สิทธิ: {$hosxpPtName}" : '') . ' สำเร็จ!',
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }

            if ($isRegistrarOrAdmin) {
                return response()->json([
                    'status'      => 'mismatch_registrar',
                    'hn'          => $hosxpHn,
                    'pttype_name' => $hosxpPtName ?: 'ไม่ระบุสิทธิ',
                    'cid'         => $cleanCid,
                    'mews_name'   => $mewsFname . ' ' . $mewsLname,
                    'hosxp_name'  => $hosxpFname . ' ' . $hosxpLname,
                    'message'     => 'พบเลข ปชช. ตรงกัน แต่ชื่อ-นามสกุลใน MEWS กับ HosXP ไม่ตรงกัน กรุณาตรวจสอบ',
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }

            return response()->json([
                'status'  => 'mismatch_pass',
                'message' => 'พบ HN ใน HosXP แต่ชื่อ-นามสกุลไม่ตรงกัน ระบบส่งเรื่องให้ห้องทะเบียนตรวจสอบแล้ว (ดำเนินการรักษาต่อได้เลยครับ)',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        } catch (\Throwable $e) {
            Log::warning('HosXP Sync Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล HosXP ได้ในขณะนี้ กรุณาลองใหม่อีกครั้งครับ',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    // =========================================================================
    // 🔄 ซิงค์ข้อมูล HN + วันเกิด + สิทธิการรักษา แบบกลุ่ม (Bulk Sync HosXP)
    // คุมความปลอดภัยด้วย hosxp_sync_paused + hosxp_max_batch_size + hosxp_timeout_seconds
    // =========================================================================
    public function bulkSyncHosxp(Request $request)
    {
        try {
            // 🛡️ 1. เช็กสวิตช์พักการซิงค์ HosXP ชั่วคราว
            if ($this->isHosxpSyncPaused()) {
                return $this->hosxpPausedJsonResponse();
            }

            $request->validate([
                'patient_ids' => 'required|array|min:1',
            ]);

            $user = auth()->user();
            $userRoles = $this->getUserRoles($user);

            $allowedRoles = ['admin', 'registration', 'er_nurse', 'opd_nurse', 'medical_staff'];
            if (empty(array_intersect($allowedRoles, $userRoles))) {
                return response()->json(['status' => 'error', 'message' => 'สิทธิ์ของคุณไม่จำเป็นต้องซิงค์ข้อมูล HosXP']);
            }

            // 🛡️ 2. ตรวจสอบเพดานจำนวนสูงสุดต่อรอบ (hosxp_max_batch_size) ป้องกันโหลดหนักบนเซิร์ฟเวอร์ HIS
            $patientIds = array_values(array_unique(array_filter(array_map('intval', $request->input('patient_ids', [])))));
            $maxBatchSize = $this->getHosxpMaxBatchSize();

            if (count($patientIds) > $maxBatchSize) {
                return response()->json([
                    'status'         => 'error',
                    'limit_exceeded' => true,
                    'max_batch_size' => $maxBatchSize,
                    'selected_count' => count($patientIds),
                    'message'        => "ระบบจำกัดการซิงค์แบบกลุ่มไม่เกิน {$maxBatchSize} รายต่อครั้ง เพื่อป้องกันโหลดหนักต่อเซิร์ฟเวอร์ HosXP (คุณเลือกไว้ " . count($patientIds) . " ราย กรุณาแบ่งซิงค์ทีละไม่เกิน {$maxBatchSize} รายครับ)",
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }

            $isRegistrarOrAdmin = in_array('admin', $userRoles, true) || in_array('registration', $userRoles, true);
            $forceSync  = $request->boolean('force_sync') && $isRegistrarOrAdmin;
            $updateName = $request->boolean('update_name') && $isRegistrarOrAdmin;

            $patientsQuery = Patient::whereIn('id', $patientIds);
            $this->scopeForMedic($patientsQuery, $user);
            $patients = $patientsQuery->get();

            if ($patients->isEmpty()) {
                return response()->json(['status' => 'error', 'message' => 'ไม่พบรายการผู้ป่วยที่เลือก']);
            }

            $cidMap = [];
            foreach ($patients as $p) {
                $cleanCid = preg_replace('/[^0-9]/', '', $p->cid ?? '');
                if (strlen($cleanCid) === 13) {
                    $cidMap[$cleanCid][] = $p;
                }
            }

            if (empty($cidMap)) {
                return response()->json(['status' => 'error', 'message' => 'รายการที่เลือกไม่มีเลขบัตรประชาชนครบ 13 หลัก']);
            }

            // 🛡️ 3. ตั้ง Connection Timeout จาก SettingService แล้วยิง Query Read-Only แบบ Batch 1 ครั้งผ่าน Index cid
            $this->applyHosxpTimeout();
            $targetCids = array_keys($cidMap);

            try {
                $hosxpRecords = HosxpPatient::leftJoin('pttype', 'patient.pttype', '=', 'pttype.pttype')
                    ->select(
                        'patient.hn', 'patient.cid', 'patient.pname', 'patient.fname', 'patient.lname',
                        'patient.birthday', 'patient.bloodgrp',
                        'patient.pttype as pttype_code', 'pttype.name as pttype_name'
                    )
                    ->whereIn('patient.cid', $targetCids)
                    ->get();
            } catch (\Throwable $joinErr) {
                $hosxpRecords = HosxpPatient::select('hn', 'cid', 'pname', 'fname', 'lname', 'birthday', 'bloodgrp', 'pttype as pttype_code')
                    ->whereIn('cid', $targetCids)
                    ->get();
            }

            $hosxpByCid = [];
            foreach ($hosxpRecords as $hp) {
                $c = $this->cleanHosxpUtf8($hp->cid);
                if (!empty($hp->hn)) {
                    $ptCode = mb_substr($this->cleanHosxpUtf8($hp->pttype_code ?? ''), 0, 10);
                    $ptName = mb_substr($this->cleanHosxpUtf8($hp->pttype_name ?? ''), 0, 150);
                    if (empty($ptName) && !empty($ptCode)) {
                        $ptName = 'รหัสสิทธิ: ' . $ptCode;
                    }

                    $hosxpByCid[$c] = [
                        'hn'          => $this->cleanHosxpUtf8($hp->hn),
                        'fname'       => $this->cleanHosxpUtf8($hp->fname ?? ''),
                        'lname'       => $this->cleanHosxpUtf8($hp->lname ?? ''),
                        'dob'         => $this->parseHosxpBirthday($hp),
                        'blood_group' => $this->normalizeHosxpBloodGroup($hp->bloodgrp ?? ''),
                        'pttype_code' => $ptCode ?: null,
                        'pttype_name' => $ptName ?: null,
                    ];
                }
            }

            $syncedCount   = 0;
            $notFoundCount = 0;
            $mismatched    = [];

            foreach ($patients as $patient) {
                $cleanCid = preg_replace('/[^0-9]/', '', $patient->cid ?? '');
                if (!isset($hosxpByCid[$cleanCid])) {
                    $notFoundCount++;
                    continue;
                }

                $hData     = $hosxpByCid[$cleanCid];
                $mewsFname = trim($patient->first_name ?? '');
                $mewsLname = trim($patient->last_name ?? '');
                $isMatch   = ($mewsFname === $hData['fname']) && ($mewsLname === $hData['lname']);

                if ($isMatch || $forceSync) {
                    $updateFields = ['hn' => $hData['hn']];
                    if (!empty($hData['pttype_code'])) {
                        $updateFields['pttype_code'] = $hData['pttype_code'];
                    }
                    if (!empty($hData['pttype_name'])) {
                        $updateFields['pttype_name'] = $hData['pttype_name'];
                    }
                    if (!empty($hData['dob'])) {
                        $updateFields['dob'] = $hData['dob'];
                    }
                    if (!empty($hData['blood_group']) && empty($patient->blood_group)) {
                        $updateFields['blood_group'] = $hData['blood_group'];
                    }
                    if (!$isMatch && $forceSync && $updateName) {
                        $updateFields['first_name'] = $hData['fname'];
                        $updateFields['last_name']  = $hData['lname'];
                    }

                    try {
                        Patient::where('id', $patient->id)->update($updateFields);
                    } catch (\Throwable $saveErr) {
                        Patient::where('id', $patient->id)->update(['hn' => $hData['hn']]);
                    }
                    $syncedCount++;
                } else {
                    $mismatched[] = [
                        'id'          => $patient->id,
                        'cid'         => $cleanCid,
                        'hn'          => $hData['hn'],
                        'pttype_name' => $hData['pttype_name'] ?? 'ไม่ระบุสิทธิ',
                        'mews_name'   => ($patient->rank ?? '') . $mewsFname . ' ' . $mewsLname,
                        'hosxp_name'  => $hData['fname'] . ' ' . $hData['lname'],
                    ];
                }
            }

            AuditLog::record('bulk_sync_hosxp', null, [
                'selected_count' => count($patientIds),
                'max_batch_size' => $maxBatchSize,
                'synced_count'   => $syncedCount,
                'mismatch_count' => count($mismatched),
                'not_found'      => $notFoundCount,
            ]);

            return response()->json([
                'status'             => 'success',
                'synced_count'       => $syncedCount,
                'not_found_count'    => $notFoundCount,
                'mismatched'         => $mismatched,
                'is_registrar_admin' => $isRegistrarOrAdmin,
                'max_batch_size'     => $maxBatchSize,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        } catch (\Throwable $e) {
            Log::warning('HosXP Bulk Sync Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล HosXP ได้ในขณะนี้ กรุณาลองใหม่อีกครั้งครับ',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }
}