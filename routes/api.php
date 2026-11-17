<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;


// Route ทั่วไปที่ใครก็เข้าได้ (ไม่ต้องใช้ Token)
Route::post('/login', [AuthController::class, 'login']);

// กลุ่ม Route ที่ต้องเข้าสู่ระบบและมี Token แนบมาเท่านั้นถึงจะใช้งานได้
Route::middleware('auth:sanctum')->group(function () {
    
    // ดึงข้อมูลผู้ใช้งานปัจจุบัน
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ออกจากระบบ
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // (อนาคต) ใส่ Route สำหรับบันทึกฟอร์ม 4 in 1 หรือดึงข้อมูล HosXP ตรงนี้ได้เลยครับ
});
