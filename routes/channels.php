<?php

use Illuminate\Support\Facades\Broadcast;

// 🌟 1. ช่องสัญญาณรวมฉุกเฉิน (อนุญาตเฉพาะพยาบาล ER หรือ Admin)
Broadcast::channel('er-alerts', function ($user) {
    $roles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
    return in_array('admin', $roles) || in_array('er_nurse', $roles);
});

// 🌟 2. ช่องสัญญาณส่วนตัวประจำกองร้อย (เช็คด้วย ID ของหน่วยทหาร)
Broadcast::channel('unit-alerts.{unitId}', function ($user, $unitId) {
    $roles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
    return (in_array('admin', $roles) || in_array('medic', $roles)) && $user->military_unit_id == $unitId;
});