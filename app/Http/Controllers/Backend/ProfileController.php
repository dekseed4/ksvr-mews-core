<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Services\AuditLog;

class ProfileController extends Controller
{
    // แสดงหน้าแก้ไขโปรไฟล์
    public function edit()
    {
        $user = Auth::user();
        return view('backend.profile.edit', compact('user'));
    }

    // ประมวลผลการบันทึกข้อมูลโปรไฟล์ทั่วไป
    public function update(Request $request)
    {
        $user = Auth::user();

        // 🌟 1. คลีนขีด (-) และช่องว่างออกจากเบอร์โทรศัพท์อัตโนมัติ (เผื่อผู้ใช้พิมพ์ 081-234-5678)
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => str_replace(['-', ' '], '', trim($request->phone))
            ]);
        }

        // 🌟 2. ตรวจสอบความถูกต้อง (ยกเว้นอีเมลและเบอร์โทรของตัวเองตอนเช็คซ้ำ)
        $request->validate([
            'rank'  => 'required|string|max:50',
            'name'  => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => ['required', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone,' . $user->id],
        ], [
            'rank.required'  => 'กรุณาเลือกยศหรือคำนำหน้าชื่อ',
            'name.required'  => 'กรุณากรอกชื่อ-นามสกุล',
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email'    => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique'   => 'อีเมลนี้ถูกใช้งานในระบบแล้ว กรุณาใช้อีเมลอื่น',
            'phone.required' => 'กรุณากรอกเบอร์โทรศัพท์ (ใช้สำหรับเข้าสู่ระบบได้)',
            'phone.regex'    => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (กรอกเฉพาะตัวเลข 9-10 หลัก)',
            'phone.unique'   => 'เบอร์โทรศัพท์นี้ถูกใช้งานในระบบแล้ว กรุณาใช้เบอร์อื่น',
        ]);

        // 🌟 3. อัปเดตข้อมูลลงฐานข้อมูล
        $user->update([
            'rank'  => $request->rank,
            'name'  => trim($request->name),
            'email' => trim($request->email),
            'phone' => $request->phone,
        ]);

        // 📝 บันทึก Audit Log การแก้ไขโปรไฟล์ตัวเอง
        if (class_exists(AuditLog::class)) {
            AuditLog::record('update_own_profile', $user, [
                'email' => $user->email,
                'phone' => $user->phone,
            ]);
        }

        return redirect()->route('backend.profile.edit')
                         ->with('success', 'อัปเดตข้อมูลโปรไฟล์และเบอร์โทรศัพท์เรียบร้อยแล้วครับ');
    }

    // ประมวลผลการเปลี่ยนรหัสผ่าน
    public function updatePassword(Request $request)
    {
        // 1. ตรวจสอบความถูกต้องของข้อมูล (Validation)
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)], 
        ], [
            'current_password.required'         => 'กรุณากรอกรหัสผ่านปัจจุบัน',
            'current_password.current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้องครับ',
            'password.required'                 => 'กรุณากรอกรหัสผ่านใหม่',
            'password.confirmed'                => 'การยืนยันรหัสผ่านใหม่ไม่ตรงกันครับ',
            'password.min'                      => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร',
        ]);

        $user = $request->user();

        // 2. อัปเดตรหัสผ่านใหม่ และปลดสถานะการบังคับเปลี่ยนรหัสผ่านชั่วคราว
        $user->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        // 📝 บันทึก Audit Log การเปลี่ยนรหัสผ่านด้วยตัวเอง
        if (class_exists(AuditLog::class)) {
            AuditLog::record('change_own_password', $user, [
                'username' => $user->username
            ]);
        }

        // 🌟 3. ส่ง session ชื่อ 'password_success' ให้ตรงกับที่ดักรอไว้ในกล่องที่ 2 ของหน้า Blade
        return back()->with('password_success', 'อัปเดตรหัสผ่านใหม่เรียบร้อยแล้ว ระบบมีความปลอดภัยสูงสุดครับ');
    }
}