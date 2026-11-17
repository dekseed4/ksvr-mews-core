<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PasswordResetController extends Controller
{
    /**
     * 🔄 1. รีเซ็ตรหัสผ่านด้วยตนเอง (ยืนยัน Username คู่กับ เบอร์โทรศัพท์ หรือ อีเมล)
     */
    public function selfReset(Request $request)
    {
        // 🛡️ ป้องกันการยิงสุ่มเดา (จำกัด 5 ครั้ง ต่อ 15 นาที ต่อ 1 IP)
        $throttleKey = 'pwd-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status'  => 'error',
                'message' => "คุณทำรายการผิดพลาดเกินจำนวนครั้งที่กำหนด กรุณารออีก " . ceil($seconds / 60) . " นาทีแล้วลองใหม่ครับ"
            ], 429);
        }

        $request->validate([
            'username'    => 'required|string|max:100',
            'verify_info' => 'required|string|max:150',
            'password'    => 'required|string|min:8|confirmed',
        ], [
            'username.required'    => 'กรุณากรอกชื่อผู้ใช้งาน (Username)',
            'verify_info.required' => 'กรุณากรอกเบอร์โทรศัพท์ หรือ อีเมล เพื่อยืนยันตัวตน',
            'password.min'         => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษรเพื่อความปลอดภัย',
            'password.confirmed'   => 'รหัสผ่านยืนยันไม่ตรงกัน',
        ]);

        $username = trim($request->username);
        $verifyInput = trim($request->verify_info);
        $cleanPhoneInput = preg_replace('/[^0-9]/', '', $verifyInput);

        $user = User::where('username', $username)->first();

        // เช็คความถูกต้องของ Email และ เบอร์โทร (เบอร์โทรต้องมีความยาวอย่างน้อย 9 หลักเพื่อป้องกันค่าว่างตรงกัน)
        $isEmailMatch = $user && !empty($user->email) && strtolower(trim($user->email)) === strtolower($verifyInput);
        
        $isPhoneMatch = false;
        if ($user && !empty($user->phone) && strlen($cleanPhoneInput) >= 9) {
            $userCleanPhone = preg_replace('/[^0-9]/', '', $user->phone);
            $isPhoneMatch = (strlen($userCleanPhone) >= 9 && $userCleanPhone === $cleanPhoneInput);
        }

        if (!$user || (!$isEmailMatch && !$isPhoneMatch)) {
            RateLimiter::hit($throttleKey, 900); // นับครั้งที่ผิด (ล็อก 15 นาที)
            // 🛡️ ใช้ข้อความแจ้งเตือนรวมเพื่อป้องกันการไล่เช็คว่ามี Username นี้ในระบบหรือไม่ (Username Enumeration Protection)
            return response()->json([
                'status'  => 'error',
                'message' => 'ชื่อผู้ใช้งาน หรือข้อมูลยืนยันตัวตนไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง หรือเลือกแท็บแจ้งผู้ดูแลระบบครับ'
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        $user->password = Hash::make($request->password);
        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        if (class_exists('\App\Services\AuditLog')) {
            \App\Services\AuditLog::record('self_reset_password', $user, [
                'username' => $user->username,
                'ip'       => $request->ip()
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'ตั้งรหัสผ่านใหม่สำเร็จแล้ว! คุณสามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้ทันทีครับ'
        ]);
    }

    /**
     * 📢 2. แจ้งขอให้ Admin รีเซ็ตรหัสผ่านให้ (พร้อม Rate Limiter ป้องกันการสแปมเข้า Discord)
     */
    public function notifyAdmin(Request $request)
    {
        $throttleKey = 'pwd-notify-admin:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'คุณส่งคำขอไปแล้ว กรุณารอเจ้าหน้าที่ตรวจสอบและติดต่อกลับครับ'
            ], 429);
        }
        RateLimiter::hit($throttleKey, 600);

        $request->validate([
            'identifier' => 'required|string|max:100',
            'contact'    => 'nullable|string|max:100',
        ]);

        $identifier = trim($request->identifier);
        $contact    = trim($request->contact ?: 'ไม่ได้ระบุ');

        $foundUser = User::where('username', $identifier)
            ->orWhere('name', 'LIKE', "%{$identifier}%")
            ->orWhere('email', $identifier)
            ->first();

        $userInfoText = $foundUser 
            ? "{$foundUser->name} (Username: `{$foundUser->username}`)" 
            : "ไม่พบในระบบ (ข้อมูลที่แจ้ง: `{$identifier}`)";

        $webhookUrl = config('services.discord.mews_webhook_url') ?: env('DISCORD_WEBHOOK_URL');
        if (!empty($webhookUrl)) {
            try {
                Http::timeout(5)->post($webhookUrl, [
                    'content' => "🔐 **แจ้งขอรีเซ็ตรหัสผ่าน (Forgot Password Request)**\n"
                               . "👤 **ผู้ขอ:** {$userInfoText}\n"
                               . "📞 **ช่องทางติดต่อกลับ:** {$contact}\n"
                               . "🌐 **IP Address:** `{$request->ip()}`\n"
                               . "🕒 **เวลา:** " . now()->addYears(543)->format('d/m/Y H:i น.')
                ]);
            } catch (\Throwable $e) {
                Log::warning('Discord Password Reset Alert Failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'ส่งคำขอไปยังผู้ดูแลระบบ (IT KSVR) เรียบร้อยแล้วครับ เจ้าหน้าที่จะดำเนินการตรวจสอบและติดต่อกลับโดยเร็วที่สุด'
        ]);
    }
}