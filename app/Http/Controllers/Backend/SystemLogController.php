<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    public function index(Request $request)
    {
        // รายชื่อโมดูลทั้งหมดสำหรับตัวกรอง (ตรงกับ Trait RecordsSystemLog ทั้ง 14 โมดูล)
        $moduleOptions = [
            'App\Models\Patient'              => 'ทะเบียนประวัติพลทหาร',
            'App\Models\MewsAssessment'       => 'ประเมินสัญญาณชีพ (MEWS)',
            'App\Models\CovidVitalLog'        => 'สัญญาณชีพรายวัน (06:00/18:00)',
            'App\Models\RecruitInfirmaryStay' => 'ห้องพยาบาล / แยกโรงนอน',
            'App\Models\PatientMedication'    => 'รายการยา (e-MAR)',
            'App\Models\PatientMedicationLog' => 'บันทึกการกิน/ทายา (e-MAR)',
            'App\Models\RecruitAppointment'   => 'นัดพบแพทย์ทหารใหม่',
            'App\Models\RecruitMorbidity'     => 'รายงานเจ็บป่วยประจำวัน',
            'App\Models\RecruitScreening'     => 'คัดกรองทหารใหม่แรกรับ',
            'App\Models\MilitaryDischarge'    => 'เอกสารนำปลดทหาร',
            'App\Models\MilitaryUnit'         => 'ข้อมูลหน่วยฝึกทหาร',
            'App\Models\User'                 => 'ผู้ใช้งานระบบ',
            'App\Models\MewsTemplate'         => 'ตั้งค่าเกณฑ์ MEWS',
            'App\Models\EmergencySymptom'     => 'ตั้งค่าอาการฉุกเฉินเฉพาะโรค',
            'system_only'                     => 'เหตุการณ์ทั่วไป / เชื่อมต่อ HosXP',
        ];

        $actionGroupOptions = [
            'created' => 'เพิ่มข้อมูลใหม่ (Create)',
            'updated' => 'แก้ไขข้อมูล (Update)',
            'deleted' => 'ลบข้อมูล (Delete)',
            'hosxp'   => 'การเชื่อมต่อ / ซิงค์ HosXP',
            'auth'    => 'ผู้ใช้งาน / รหัสผ่าน',
        ];

        $perPage = (int)$request->query('per_page', 30);
        if (!in_array($perPage, [20, 30, 50, 100], true)) {$perPage = 30;
        }

        // ดึง Log พร้อม User และ Target Model (ป้องกัน N+1 Query และไม่แตะ HosXP)
        $query = SystemLog::with(['user:id,rank,name,username', 'loggable'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // 1. ค้นหาคำสำคัญ (ครอบวงเล็บป้องกัน orWhere หักล้างตัวกรองอื่น)
        if ($request->filled('search')) {
            $search = trim((string)$request->query('search'));
            $query->where(function ($q) use ($search) {$q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "\%{$search}%")
                       ->orWhere('username', 'like', "%{$search}%");
                })
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('loggable_id', $search)
                ->orWhere('payload', 'like', "%{$search}%")
                ->orWhereRaw(
                    "CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.summary')) ELSE '' END LIKE ?",
                    ["%{$search}%"]
                );
            });
        }

        // 2. กรองตามโมดูล
        if ($request->filled('module')) {
            $module = (string)$request->query('module');
            if ($module === 'system_only') {$query->whereNull('loggable_type');
            } elseif (array_key_exists($module,$moduleOptions)) {
                $query->where('loggable_type',$module);
            }
        }

        // 3. กรองตามกลุ่มการกระทำ (รองรับทั้ง Log อัตโนมัติใหม่ และ Log รุ่นเดิม)
        if ($request->filled('action_group')) {$group = (string) $request->query('action_group');$query->where(function ($q) use ($group) {
                if ($group === 'created') {$q->where('action', 'created')
                      ->orWhere('action', 'like', 'create_%')
                      ->orWhere('action', 'like', 'import_%');
                } elseif ($group === 'updated') {$q->where('action', 'updated')
                      ->orWhere('action', 'like', 'update_%')
                      ->orWhere('action', 'discharge_patient');
                } elseif ($group === 'deleted') {$q->where('action', 'deleted')
                      ->orWhere('action', 'like', '%delete%');
                } elseif ($group === 'hosxp') {$q->where('action', 'like', '%hosxp%');
                } elseif ($group === 'auth') {$q->whereIn('action', ['reset_password', 'login', 'logout', 'create_user', 'update_user', 'delete_user'])
                      ->orWhere('loggable_type', 'App\Models\User');
                }
            });
        }

        // 4. กรองช่วงวันที่
        if ($request->filled('date_from')) {
            try {
                $query->where('created_at', '>=', Carbon::parse($request->query('date_from'))->startOfDay());
            } catch (\Throwable $e) {
            }
        }
        if ($request->filled('date_to')) {
            try {
                $query->where('created_at', '<=', Carbon::parse($request->query('date_to'))->endOfDay());
            } catch (\Throwable $e) {
            }
        }

        $logs =$query->paginate($perPage)->appends($request->query());

        // สรุปสถิติ KPI ประจำวัน (Query เฉพาะฝั่ง KSVR)
        $today = Carbon::today();$summary = [
            'today_total'  => SystemLog::whereDate('created_at', $today)->count(),
            'today_delete' => SystemLog::whereDate('created_at', $today)
                ->where(function ($q) {$q->where('action', 'deleted')->orWhere('action', 'like', '%delete%');
                })->count(),
            'today_users'  => SystemLog::whereDate('created_at', $today)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
            'filtered_total' => $logs->total(),
        ];

        return view('backend.system_logs.index', compact(
            'logs',
            'moduleOptions',
            'actionGroupOptions',
            'perPage',
            'summary'
        ));
    }
}