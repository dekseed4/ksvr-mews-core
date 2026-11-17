<?php

namespace App\Services;

use App\Models\SystemLog;

class AuditLog
{
    /**
     * บันทึกประวัติการใช้งานระบบ (Audit Trail)
     *
     * @param string $action ชื่อการกระทำ (เช่น 'create_patient', 'sync_hosxp')
     * @param mixed $model โมเดลเป้าหมาย (เช่น $patient) หรือ null ถ้าเป็นเหตุการณ์ทั่วไป
     * @param array $payload ข้อมูลเชิงลึกที่ต้องการเก็บเพิ่มเติม (เช่น ค่าเก่า-ค่าใหม่)
     * @return void
     */
    public static function record($action, $model = null, $payload = [])
    {
        try {
            SystemLog::create([
                'user_id' => auth()->id(), // ใครเป็นคนทำ (ดึงจากคนที่ Login อยู่)
                'action' => $action,       // ทำอะไร
                'loggable_type' => $model ? get_class($model) : null, // โมเดลไหน
                'loggable_id' => $model ? $model->id : null,          // ID อะไร
                'payload' => empty($payload) ? null : $payload,       // รายละเอียด (แปลงเป็น JSON อัตโนมัติ)
            ]);
        } catch (\Exception $e) {
            // 🌟 ทริค System Admin: ถ้าเซฟ Log พัง ระบบหลักต้องไม่พังตาม ให้เก็บลงไฟล์ Log ของเซิร์ฟเวอร์แทน
            \Illuminate\Support\Facades\Log::error('AuditLog Failed: ' . $e->getMessage());
        }
    }
}