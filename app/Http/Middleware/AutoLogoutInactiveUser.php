<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class AutoLogoutInactiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        // คืนค่าจำนวนนาที (> 0) หรือ 0 หากได้รับยกเว้นไม่ต้องล็อกเอาต์อัตโนมัติ
        $timeoutMinutes = $this->resolveTimeoutMinutes($request);

        // แชร์ค่าให้ layouts/backend.blade.php ใช้กติกาเดียวกันเป๊ะ
        View::share('idleTimeoutMinutes', $timeoutMinutes);

        // กรณีได้รับยกเว้น (ER / มือถือที่ตั้งค่า 0)
        if ($timeoutMinutes <= 0) {
            if (!$request->headers->has('X-KSVR-Background-Poll')) {
                $request->session()->put('last_activity_at', time());
            }
            return $next($request);
        }

        $timeoutSeconds = $timeoutMinutes * 60;
        $lastActivity = $request->session()->get('last_activity_at');

        // ตรวจสอบว่าปล่อยหน้าจอทิ้งไว้เกินกำหนดหรือไม่
        if ($lastActivity && (time() - $lastActivity) > $timeoutSeconds) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->headers->has('X-KSVR-Background-Poll')) {
                return response()->json([
                    'message' => 'หมดเวลาการเชื่อมต่อเนื่องจากไม่มีการใช้งานเกินกำหนด กรุณาเข้าสู่ระบบใหม่',
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()->route('login')
                ->with('session_expired', "ระบบออกจากระบบอัตโนมัติ เนื่องจากไม่มีการใช้งานเกิน {$timeoutMinutes} นาที เพื่อความปลอดภัยของข้อมูลผู้ป่วย");
        }

        // อัปเดตเวลาใช้งานล่าสุดเฉพาะ Request ที่เกิดจากผู้ใช้จริง (ไม่รวม Background Polling ทุก 30 วิ)
        if (
            !$request->headers->has('X-KSVR-Background-Poll') &&
            !$request->is('api/*') &&
            !$request->is('broadcasting/*')
        ) {
            $request->session()->put('last_activity_at', time());
        }

        return $next($request);
    }

    /**
     * คืนค่านาทีที่อนุญาตให้ว่างงาน (คืนค่า 0 = ไม่ล็อกเอาต์อัตโนมัติ)
     */
    protected function resolveTimeoutMinutes(Request $request): int
    {
        $user = Auth::user();

        // อ่าน Role ด้วยตรรกะเดียวกับ layouts/backend.blade.php
        $rawRoles = $user->roles ?? $user->role ?? [];
        $userRoles = is_string($rawRoles)
            ? (json_decode($rawRoles, true) ?: [$rawRoles])
            : (array) $rawRoles;

        // ดึงค่าตั้งค่าจาก SettingService (Cache) หากไม่พบให้ Fallback กลับไปใช้ค่าจาก config/session.php
        $pcTimeout     = (int) config('session.idle_timeout_pc', 30);
        $mobileTimeout = (int) config('session.idle_timeout_mobile', 720);
        $exemptRoles   = ['er_nurse'];

        if (class_exists(\App\Services\SettingService::class)) {
            try {
                $pcTimeout     = (int) \App\Services\SettingService::get('idle_timeout_pc', $pcTimeout);
                $mobileTimeout = (int) \App\Services\SettingService::get('idle_timeout_mobile', $mobileTimeout);
                $exemptRoles   = (array) \App\Services\SettingService::get('idle_timeout_exempt_roles', $exemptRoles);
            } catch (\Throwable $e) {
                // ใช้ค่า Fallback จาก config ต่อไปโดยไม่ทำให้หน้าเว็บล่ม
            }
        }

        // 1. ตรวจสอบบทบาทที่ Admin ติ๊กยกเว้นไว้ในหน้าตั้งค่าระบบ (เช่น er_nurse)
        if (!empty($exemptRoles) && !empty(array_intersect($userRoles, $exemptRoles))) {
            return 0;
        }

        // 2. อุปกรณ์มือถือ / แท็บเล็ต / PWA
        if ($this->isMobileDevice($request)) {
            return max(0, $mobileTimeout);
        }

        // 3. คอมพิวเตอร์ทั่วไป (PC)
        return max(0, $pcTimeout);
    }

    protected function isMobileDevice(Request $request): bool
    {
        $userAgent = $request->userAgent() ?? '';
        return (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent);
    }
}