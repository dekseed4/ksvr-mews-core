<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BackendAuthController extends Controller
{
    // 1. แสดงหน้าฟอร์มล็อกอิน
    public function showLoginForm()
    {
        // ถ้าล็อกอินอยู่แล้ว ให้เด้งไปหน้าแดชบอร์ดเลย
        if (Auth::check()) {
            return redirect()->route('backend.dashboard');
        }
        
        return view('backend.auth.login');
    }

    // 2. ตรวจสอบข้อมูลและล็อกอิน
    public function login(Request $request)
{
    // 1. ตรวจสอบว่ากรอกข้อมูลมาครบถ้วน
    $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ], [
        'username.required' => 'กรุณากรอกชื่อผู้ใช้งาน เบอร์โทรศัพท์ หรืออีเมล',
        'password.required' => 'กรุณากรอกรหัสผ่าน',
    ]);

    // 2. ดึงค่าที่ผู้ใช้กรอกและตัดช่องว่างหน้า-หลังทิ้ง
    $rawInput = trim($request->input('username'));

    // 🌟 3. เผื่อกรณีเจ้าหน้าที่เผลอพิมพ์ขีดคั่นเบอร์โทร เช่น "081-234-5678" หรือเคาะวรรค
    // เราลองสร้างตัวแปรที่ตัดขีดและช่องว่างออก เพื่อเช็คว่าเป็นเบอร์โทรหรือไม่
    $cleanPhone = str_replace(['-', ' '], '', $rawInput);

    // 🌟 4. วิเคราะห์อัตโนมัติว่าเป็น Email, เบอร์โทรศัพท์ หรือ Username
    if (filter_var($rawInput, FILTER_VALIDATE_EMAIL)) {
        $fieldType = 'email';
        $loginValue = $rawInput;
    } elseif (preg_match('/^[0-9]{9,10}$/', $cleanPhone)) {
        $fieldType = 'phone';
        $loginValue = $cleanPhone; // ใช้เบอร์ที่คลีนขีดออกแล้วไปเช็คกับ Database
    } else {
        $fieldType = 'username';
        $loginValue = $rawInput;
    }

    // 5. ส่งไปตรวจสอบกับฐานข้อมูล KSVR พร้อมเช็คสถานะ Checkbox "จดจำฉัน" (remember)
    $credentials = [
        $fieldType => $loginValue,
        'password' => $request->input('password'),
    ];

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        // ป้องกัน Session Fixation Attack
        $request->session()->regenerate();

        // (ตัวเลือกเสริม) บันทึกลง AuditLog ของระบบ KSVR ว่าล็อกอินสำเร็จด้วยช่องทางไหน
        if (class_exists(\App\Services\AuditLog::class)) {
            \App\Services\AuditLog::record('user_login', auth()->user(), [
                'login_via' => $fieldType
            ]);
        }

        return redirect()->intended(route('backend.dashboard'));
    }

    // 6. หากรหัสผิด หรือไม่พบข้อมูล ให้เด้งกลับพร้อมข้อความแจ้งเตือนและคืนค่าเดิมกลับไปที่ช่องกรอก
    return back()->withErrors([
        'username' => 'ข้อมูลผู้ใช้งาน เบอร์โทรศัพท์ หรือรหัสผ่านไม่ถูกต้องครับ',
    ])->onlyInput('username');
}

    // 3. ออกจากระบบ
    public function logout(Request $request)
    {
        Auth::logout();

        // ล้างข้อมูล Session ทั้งหมด
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // ส่งกลับไปหน้าล็อกอิน
        return redirect()->route('login');
    }

    public function showForceChangePasswordForm()
    {
        return view('backend.auth.force-change');
    }

    public function processForceChangePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed', // ต้องมีช่องยืนยันรหัสผ่าน
        ], [
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
            'password.confirmed' => 'รหัสผ่านทั้งสองช่องไม่ตรงกัน',
        ]);

        $user = \Illuminate\Support\Facades\Auth::user();
        $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        $user->must_change_password = false; // ปลดล็อกสถานะ
        $user->save();

        return redirect()->route('backend.dashboard')->with('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว ยินดีต้อนรับเข้าสู่ระบบครับ!');
    }
}