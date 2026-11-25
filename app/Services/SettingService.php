<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingService
{
    public const CACHE_KEY = 'ksvr_system_settings_v1';

    /**
     * ค่าเริ่มต้นมาตรฐานของระบบ KSVR Hospital (ใช้เมื่อยังไม่เคยบันทึกค่าในฐานข้อมูล)
     */
    public static function defaults(): array
    {
        return [
            // 1. ทั่วไป & ผลัดทหาร
            'hospital_name'            => ['group' => 'general',       'type' => 'string',  'label' => 'ชื่อโรงพยาบาล / หน่วยงาน',           'value' => 'โรงพยาบาลค่ายกฤษณ์สีวะรา (KSVR Hospital)'],
            'active_intake_batch'      => ['group' => 'general',       'type' => 'string',  'label' => 'ผลัดทหารหลักปัจจุบัน',              'value' => '2/69'],
            'announcement_enabled'     => ['group' => 'general',       'type' => 'boolean', 'label' => 'เปิดใช้งานแถบประกาศระบบ',            'value' => false],
            'announcement_type'        => ['group' => 'general',       'type' => 'string',  'label' => 'ระดับสีแถบประกาศ',                  'value' => 'info'],
            'announcement_message'     => ['group' => 'general',       'type' => 'string',  'label' => 'ข้อความประกาศระบบ',                  'value' => ''],

            // 2. แจ้งเตือน & เฝ้าระวังโรคระบาด
            'discord_enabled'          => ['group' => 'notifications', 'type' => 'boolean', 'label' => 'เปิดการแจ้งเตือนผ่าน Discord',        'value' => true],
            'discord_webhook_er'       => ['group' => 'notifications', 'type' => 'string',  'label' => 'Discord Webhook (ห้อง ER / MEWS)',   'value' => (string) config('services.discord.mews_webhook_url', '')],
            'discord_webhook_exec'     => ['group' => 'notifications', 'type' => 'string',  'label' => 'Discord Webhook (ผู้บริหาร / ระบาด)', 'value' => (string) config('services.discord.exec_webhook_url', '')],
            'outbreak_temp_threshold'  => ['group' => 'notifications', 'type' => 'float',   'label' => 'เกณฑ์ไข้สูงเฝ้าระวัง (°C)',            'value' => 38.0],
            'outbreak_min_cases'       => ['group' => 'notifications', 'type' => 'integer', 'label' => 'จำนวนผู้ป่วยสะสมแจ้งเตือนต่อหน่วย',   'value' => 3],
            'outbreak_window_hours'    => ['group' => 'notifications', 'type' => 'integer', 'label' => 'ช่วงเวลาเฝ้าระวัง (ชั่วโมง)',          'value' => 24],

            // 3. ความปลอดภัย HosXP (HIS Safety)
            'hosxp_sync_paused'        => ['group' => 'hosxp',         'type' => 'boolean', 'label' => 'พักการซิงค์ HosXP ชั่วคราว',         'value' => false],
            'hosxp_timeout_seconds'    => ['group' => 'hosxp',         'type' => 'integer', 'label' => 'Connection Timeout (วินาที)',         'value' => 5],
            'hosxp_max_batch_size'     => ['group' => 'hosxp',         'type' => 'integer', 'label' => 'จำนวนสูงสุดต่อการซิงค์กลุ่ม (ราย)',   'value' => 50],

            // 4. เกณฑ์คลินิก (SLA) & PDPA
            'er_sla_red_minutes'       => ['group' => 'clinical',      'type' => 'integer', 'label' => 'SLA วัด V/S ซ้ำ สีแดง (นาที)',       'value' => 15],
            'er_sla_yellow_minutes'    => ['group' => 'clinical',      'type' => 'integer', 'label' => 'SLA วัด V/S ซ้ำ สีเหลือง (นาที)',     'value' => 30],
            'appointment_alert_days'   => ['group' => 'clinical',      'type' => 'integer', 'label' => 'แจ้งเตือนนัดแพทย์ล่วงหน้า (วัน)',      'value' => 1],
            'polling_interval_seconds' => ['group' => 'clinical',      'type' => 'integer', 'label' => 'รอบเช็กแจ้งเตือนอัตโนมัติ (วินาที)',   'value' => 30],
            'pdpa_chronic_roles'       => ['group' => 'clinical',      'type' => 'json',    'label' => 'บทบาทที่เห็นข้อมูลโรคประจำตัว',      'value' => ['admin', 'executive', 'er_nurse', 'opd_nurse', 'screening', 'medical_staff']],
            // 4. เกณฑ์คลินิก (SLA) & PDPA
            'er_sla_red_minutes'        => ['group' => 'clinical',      'type' => 'integer', 'label' => 'SLA วัด V/S ซ้ำ สีแดง (นาที)',         'value' => 15],
            'er_sla_yellow_minutes'     => ['group' => 'clinical',      'type' => 'integer', 'label' => 'SLA วัด V/S ซ้ำ สีเหลือง (นาที)',       'value' => 30],
            'appointment_alert_days'    => ['group' => 'clinical',      'type' => 'integer', 'label' => 'แจ้งเตือนนัดแพทย์ล่วงหน้า (วัน)',        'value' => 1],
            'polling_interval_seconds'  => ['group' => 'clinical',      'type' => 'integer', 'label' => 'รอบเช็กแจ้งเตือนอัตโนมัติ (วินาที)',     'value' => 30],
            'pdpa_chronic_roles'        => ['group' => 'clinical',      'type' => 'json',    'label' => 'บทบาทที่เห็นข้อมูลโรคประจำตัว',        'value' => ['admin', 'executive', 'er_nurse', 'opd_nurse', 'screening', 'medical_staff']],

            // ⏱️ นโยบายกำหนดเวลาล็อกเอาต์อัตโนมัติ (Auto-Logout / Idle Timeout)
            'idle_timeout_pc'           => ['group' => 'clinical',      'type' => 'integer', 'label' => 'เวลาล็อกเอาต์อัตโนมัติ PC (นาที)',       'value' => (int) config('session.idle_timeout_pc', 30)],
            'idle_timeout_mobile'       => ['group' => 'clinical',      'type' => 'integer', 'label' => 'เวลาล็อกเอาต์อัตโนมัติ มือถือ (นาที)',   'value' => (int) config('session.idle_timeout_mobile', 720)],
            'idle_timeout_exempt_roles' => ['group' => 'clinical',      'type' => 'json',    'label' => 'บทบาทที่ยกเว้นการล็อกเอาต์อัตโนมัติ',     'value' => ['er_nurse']],
            
        ];
    }

    /**
     * โหลดค่าตั้งค่าทั้งหมดจาก Cache (0 DB Query หลังโหลดครั้งแรก)
     */
    public static function all(): array
    {
        $defaults = static::defaults();
        $result = [];
        foreach ($defaults as $key => $meta) {
            $result[$key] = $meta['value'];
        }

        try {
            if (!Schema::hasTable('system_settings')) {
                return $result;
            }

            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                return SystemSetting::all()->keyBy('key')->map(function ($row) {
                    return [
                        'value' => $row->value,
                        'type'  => $row->type,
                    ];
                })->toArray();
            });

            foreach ($stored as $key => $item) {
                $type = $defaults[$key]['type'] ?? ($item['type'] ?? 'string');
                $result[$key] = static::castValue($item['value'], $type);
            }
        } catch (\Throwable $e) {
            return $result;
        }

        return $result;
    }

    /**
     * อ่านค่าตั้งค่ารายตัว (เช่น SettingService::get('active_intake_batch', '2/69'))
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * บันทึกค่าตั้งค่าหลายรายการพร้อมกัน และล้าง Cache ทันที
     */
    public static function setMany(array $values, ?int $userId = null): void
    {
        $defaults = static::defaults();

        foreach ($values as $key => $rawVal) {
            if (!isset($defaults[$key])) {
                continue;
            }

            $meta = $defaults[$key];
            $encoded = match ($meta['type']) {
                'boolean' => $rawVal ? '1' : '0',
                'json'    => json_encode(is_array($rawVal) ? array_values($rawVal) : [], JSON_UNESCAPED_UNICODE),
                default   => (string) ($rawVal ?? ''),
            };

            SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'group'      => $meta['group'],
                    'type'       => $meta['type'],
                    'label'      => $meta['label'],
                    'value'      => $encoded,
                    'updated_by' => $userId,
                ]
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float'   => (float) $value,
            'json'    => is_string($value) ? (json_decode($value, true) ?: []) : (array) $value,
            default   => (string) ($value ?? ''),
        };
    }

    /**
     * คืนค่า Discord Webhook URL ที่พร้อมใช้งาน (ไม่เด้งซ้ำกับ .env 100%)
     * - ปิดสวิตช์ discord_enabled -> คืน null (หยุดส่งทุกช่องทางทันที)
     * - ช่อง 'er'   -> ใช้ค่าจากหน้าตั้งค่า ER -> หากว่างค่อยถอยไปใช้ .env
     * - ช่อง 'exec' -> ใช้ค่าจากหน้าตั้งค่า ผู้บริหาร -> หากว่างถอยไปใช้ .env ผู้บริหาร -> หากยังว่างถอยไปใช้ช่อง ER
     */
    public static function getDiscordWebhook(string $channel = 'er'): ?string
    {
        if (!static::get('discord_enabled', true)) {
            return null;
        }

        if ($channel === 'exec') {
            $execUrl = trim((string) static::get('discord_webhook_exec', ''));
            if ($execUrl !== '') {
                return $execUrl;
            }

            $envExec = trim((string) config('services.discord.exec_webhook_url', ''));
            if ($envExec !== '') {
                return $envExec;
            }
        }

        $erUrl = trim((string) static::get('discord_webhook_er', ''));
        if ($erUrl !== '') {
            return $erUrl;
        }

        $envEr = trim((string) config('services.discord.mews_webhook_url', ''));
        return $envEr !== '' ? $envEr : null;
    }
}