<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RecruitAppointment;
use App\Services\WebPushService;
use Carbon\Carbon;

class SendAppointmentWebPushCommand extends Command
{
    protected $signature = 'ksvr:send-appointment-pushes';
    protected $description = 'ส่งแจ้งเตือน Web Push คิวนัดพบแพทย์ (วันนี้และพรุ่งนี้) ไปยังมือถือเสนารักษ์และพยาบาลอัตโนมัติ';

    public function handle(): int
    {
        $today    = Carbon::today()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $appointments = RecruitAppointment::with(['patient', 'militaryUnit'])
            ->where('status', 'scheduled')
            ->whereIn(\Illuminate\Support\Facades\DB::raw('DATE(appointment_date)'), [$today, $tomorrow])
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('ไม่มีรายการนัดพบแพทย์สำหรับวันนี้และพรุ่งนี้');
            return self::SUCCESS;
        }

        // 1. แจ้งเตือนรวมไปยังพยาบาลและเจ้าหน้าที่เวชกรรม
        $totalCount = $appointments->count();
        WebPushService::sendToHospitalRoles(
            ['admin', 'er_nurse', 'opd_nurse', 'medical_staff'],
            "📅 แจ้งเตือนคิวนัดพบแพทย์ ({$totalCount} ราย)",
            "มีพลทหารต้องไปพบแพทย์ (วันนี้/พรุ่งนี้) จำนวน {$totalCount} ราย แตะเพื่อดูรายชื่อ",
            route('backend.recruit_appointments.index'),
            'daily-hosp-app-alert',
            false
        );

        // 2. แยกกลุ่มตามหน่วยฝึก แล้วยิงตรงเข้ามือถือของเสนารักษ์แต่ละหน่วยฝึก
        $byUnit = $appointments->groupBy('military_unit_id');
        foreach ($byUnit as $unitId => $unitApps) {
            if (!$unitId) continue;

            $firstApp = $unitApps->first();
            $names = $unitApps->map(function ($a) use ($today) {
                $p = $a->patient;
                $when = $a->appointment_date->format('Y-m-d') === $today ? 'นัดวันนี้' : 'นัดพรุ่งนี้';
                return ($p->rank ?? 'พลฯ') . ($p->first_name ?? '') . " ({$when})";
            })->take(3)->implode(', ');

            $cnt = $unitApps->count();
            WebPushService::sendToUnitMedics(
                (int) $unitId,
                $firstApp->intake_batch,
                "📅 แจ้งเตือนคิวนัดพบแพทย์ ({$cnt} ราย)",
                "เตรียมส่งตัว: {$names}" . ($cnt > 3 ? " และอื่นๆ" : ""),
                route('backend.recruit_appointments.index'),
                "daily-unit-app-{$unitId}"
            );
        }

        $this->info("ส่ง Web Push แจ้งเตือนนัดพบแพทย์สำเร็จ {$totalCount} รายการ!");
        return self::SUCCESS;
    }
}