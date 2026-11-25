<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SystemSettingController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'general');
        if (!in_array($activeTab, ['general', 'notifications', 'hosxp', 'clinical'], true)) {
            $activeTab = 'general';
        }

        $settings = SettingService::all();

        // ดึงรายการผลัดที่มีอยู่แล้วในฐานข้อมูล KSVR มาเป็นตัวเลือกแนะนำ
        $existingBatches = Patient::whereNotNull('intake_batch')
            ->where('intake_batch', '!=', '')
            ->distinct()
            ->orderByDesc('intake_batch')
            ->pluck('intake_batch')
            ->values();

        $hosxpInfo = [
            'host'     => config('database.connections.hosxp.host') ?: env('HOSXP_DB_HOST', 'ยังไม่ได้ตั้งค่า'),
            'database' => config('database.connections.hosxp.database') ?: env('HOSXP_DB_DATABASE', '-'),
            'port'     => config('database.connections.hosxp.port') ?: env('HOSXP_DB_PORT', '3306'),
        ];

        $roleLabels = [
            'admin'         => 'ผู้ดูแลระบบ (Admin)',
            'executive'     => 'ผู้บริหาร (Executive)',
            'er_nurse'      => 'พยาบาล ER',
            'opd_nurse'     => 'พยาบาล OPD',
            'screening'     => 'จุดคัดกรอง (Triage)',
            'medical_staff' => 'เจ้าหน้าที่เวชกรรม',
            'registration'  => 'เจ้าหน้าที่ทะเบียน',
            'medic'         => 'เสนารักษ์หน่วยฝึก',
        ];

        return view('backend.settings.general', compact(
            'activeTab',
            'settings',
            'existingBatches',
            'hosxpInfo',
            'roleLabels'
        ));
    }

    public function update(Request $request)
    {
        $tab = $request->input('tab', 'general');
        $userId = Auth::id();

        if ($tab === 'general') {
            $validated = $request->validate([
                'hospital_name'        => 'required|string|max:180',
                'active_intake_batch'  => 'required|string|max:20',
                'announcement_type'    => 'required|in:info,warning,danger',
                'announcement_message' => 'nullable|string|max:500',
            ]);

            SettingService::setMany([
                'hospital_name'        => $validated['hospital_name'],
                'active_intake_batch'  => trim($validated['active_intake_batch']),
                'announcement_enabled' => $request->boolean('announcement_enabled'),
                'announcement_type'    => $validated['announcement_type'],
                'announcement_message' => trim((string) ($validated['announcement_message'] ?? '')),
            ], $userId);
        } elseif ($tab === 'notifications') {
            $validated = $request->validate([
                'discord_webhook_er'      => 'nullable|url|max:500',
                'discord_webhook_exec'    => 'nullable|url|max:500',
                'outbreak_temp_threshold' => 'required|numeric|min:36.5|max:41.0',
                'outbreak_min_cases'      => 'required|integer|min:1|max:50',
                'outbreak_window_hours'   => 'required|integer|min:6|max:168',
            ]);

            SettingService::setMany([
                'discord_enabled'         => $request->boolean('discord_enabled'),
                'discord_webhook_er'      => trim((string) ($validated['discord_webhook_er'] ?? '')),
                'discord_webhook_exec'    => trim((string) ($validated['discord_webhook_exec'] ?? '')),
                'outbreak_temp_threshold' => (float) $validated['outbreak_temp_threshold'],
                'outbreak_min_cases'      => (int) $validated['outbreak_min_cases'],
                'outbreak_window_hours'   => (int) $validated['outbreak_window_hours'],
            ], $userId);
        } elseif ($tab === 'hosxp') {
            $validated = $request->validate([
                'hosxp_timeout_seconds' => 'required|integer|min:2|max:30',
                'hosxp_max_batch_size'  => 'required|integer|min:10|max:200',
            ]);

            SettingService::setMany([
                'hosxp_sync_paused'     => $request->boolean('hosxp_sync_paused'),
                'hosxp_timeout_seconds' => (int) $validated['hosxp_timeout_seconds'],
                'hosxp_max_batch_size'  => (int) $validated['hosxp_max_batch_size'],
            ], $userId);
        } elseif ($tab === 'clinical') {
            $validated = $request->validate([
                'er_sla_red_minutes'          => 'required|integer|min:5|max:120',
                'er_sla_yellow_minutes'       => 'required|integer|min:10|max:240',
                'appointment_alert_days'      => 'required|integer|min:1|max:7',
                'polling_interval_seconds'    => 'required|integer|min:15|max:300',
                'pdpa_chronic_roles'          => 'nullable|array',
                'pdpa_chronic_roles.*'        => 'string|in:admin,executive,er_nurse,opd_nurse,screening,medical_staff,registration,medic',
                // ⏱️ เพิ่ม Validation สำหรับตั้งเวลาล็อกเอาต์อัตโนมัติ
                'idle_timeout_pc'             => 'required|integer|min:0|max:1440',
                'idle_timeout_mobile'         => 'required|integer|min:0|max:1440',
                'idle_timeout_exempt_roles'   => 'nullable|array',
                'idle_timeout_exempt_roles.*' => 'string|in:admin,executive,er_nurse,opd_nurse,screening,medical_staff,registration,medic',
            ]);

            $roles = $validated['pdpa_chronic_roles'] ?? [];
            if (!in_array('admin', $roles, true)) {
                $roles[] = 'admin'; // ล็อกให้ Admin เข้าถึงได้เสมอ
            }

            $exemptRoles = array_values(array_unique($validated['idle_timeout_exempt_roles'] ?? []));

            SettingService::setMany([
                'er_sla_red_minutes'        => (int) $validated['er_sla_red_minutes'],
                'er_sla_yellow_minutes'     => (int) $validated['er_sla_yellow_minutes'],
                'appointment_alert_days'    => (int) $validated['appointment_alert_days'],
                'polling_interval_seconds'  => (int) $validated['polling_interval_seconds'],
                'pdpa_chronic_roles'        => array_values(array_unique($roles)),
                // ⏱️ บันทึกค่าเวลาล็อกเอาต์ลงฐานข้อมูลและล้างแคชอัตโนมัติ
                'idle_timeout_pc'           => (int) $validated['idle_timeout_pc'],
                'idle_timeout_mobile'       => (int) $validated['idle_timeout_mobile'],
                'idle_timeout_exempt_roles' => $exemptRoles,
            ], $userId);
        }

        return redirect()
            ->route('backend.settings.general.index', ['tab' => $tab])
            ->with('success', 'บันทึกการตั้งค่าระบบและอัปเดตแคชเรียบร้อยแล้ว!');
    }

    /**
     * 🏥 ทดสอบการเชื่อมต่อฐานข้อมูล HosXP แบบ Read-Only (ยิงแค่ SELECT 1 เบาที่สุด ไม่สแกนตารางใดๆ)
     */
    public function testHosxp()
    {
        $start = microtime(true);

        try {
            $row = DB::connection('hosxp')->selectOne('SELECT 1 AS ok, VERSION() AS db_version');
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return response()->json([
                'status'     => 'success',
                'latency_ms' => $latencyMs,
                'db_version' => $row->db_version ?? '-',
                'message'    => "เชื่อมต่อ HosXP (Read-Only) สำเร็จภายใน {$latencyMs} ms",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'ไม่สามารถเชื่อมต่อ HosXP ได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔔 ทดสอบส่งข้อความเข้า Discord Webhook (ตรวจโดเมน Discord ป้องกัน SSRF)
     */
    public function testDiscord(Request $request)
    {
        $webhookUrl = trim((string) $request->input('webhook_url', ''));
        $channelName = trim((string) $request->input('channel_name', 'KSVR System'));

        if (!preg_match('#^https://(discord\.com|discordapp\.com)/api/webhooks/#i', $webhookUrl)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'กรุณาระบุลิงก์ Discord Webhook ที่ขึ้นต้นด้วย https://discord.com/api/webhooks/ เท่านั้นครับ',
            ], 422);
        }

        try {
            $response = Http::timeout(5)->post($webhookUrl, [
                'embeds' => [[
                    'title'       => '✅ ทดสอบการเชื่อมต่อแจ้งเตือน KSVR Hospital System',
                    'description' => "ส่งทดสอบจากหน้า **ตั้งค่าระบบทั่วไป** ({$channelName}) สำเร็จเรียบร้อยแล้วครับ",
                    'color'       => 1096065, // สีเขียว Emerald
                    'footer'      => ['text' => 'ผู้ทดสอบ: ' . (Auth::user()->name ?? 'Admin') . ' • ' . now()->addYears(543)->format('d/m/Y H:i น.')],
                ]],
            ]);

            if ($response->successful()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'ส่งข้อความทดสอบเข้า Discord สำเร็จ! กรุณาตรวจสอบในห้องแชทได้เลยครับ',
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Discord ปฏิเสธการส่ง (HTTP ' . $response->status() . ') กรุณาตรวจสอบลิงก์ Webhook อีกครั้ง',
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'เชื่อมต่อ Discord ไม่สำเร็จ: ' . $e->getMessage(),
            ], 500);
        }
    }
}