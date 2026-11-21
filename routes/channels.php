<?php

use Illuminate\Support\Facades\Broadcast;

// ฟังก์ชันตัวช่วยแปลงค่า roles ของ User ให้เป็น Array เสมอ (รองรับทั้ง JSON string และ Array)
$extractRoles = function ($user): array {
    $raw = $user->roles ?? $user->role ?? [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [$raw];
    }
    return is_array($raw) ? $raw : (array) $raw;
};

// 1. ช่องแจ้งเตือนห้องฉุกเฉิน / OPD / จุดคัดกรอง / ผู้ดูแลระบบ
Broadcast::channel('er-alerts', function ($user) use ($extractRoles) {
    $roles = $extractRoles($user);
    return !empty(array_intersect(['admin', 'er_nurse', 'opd_nurse', 'screening'], $roles));
});

// 2. ช่องแจ้งเตือนผลการรักษากลับไปยังเสนารักษ์ประจำหน่วยฝึก
Broadcast::channel('unit-alerts.{unitId}', function ($user, $unitId) use ($extractRoles) {
    $roles = $extractRoles($user);
    if (in_array('admin', $roles, true)) {
        return true;
    }
    return in_array('medic', $roles, true) && (int) $user->military_unit_id === (int) $unitId;
});