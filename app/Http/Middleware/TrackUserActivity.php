<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // ข้าม GET AJAX (เช่น Polling กระดิ่งทุก 15 วิ) เพื่อไม่ให้หน้าจอที่เปิดทิ้งไว้โชว์ว่าออนไลน์ตลอดคืน
            $isBackgroundPoll = $request->isMethod('GET') && $request->ajax();

            if (!$isBackgroundPoll) {
                $userId = Auth::id();
                $now = now();

                // 1. บันทึกสถานะออนไลน์ใน Cache (ถือว่าออนไลน์หากมีการใช้งานภายใน 5 นาทีล่าสุด)
                Cache::put("user-is-online-{$userId}", true, $now->copy()->addMinutes(5));

                // 2. อัปเดตลงฐานข้อมูลแบบ Throttle (เขียนลง DB แค่ทุก 2 นาที และไม่กระตุ้น Model Event/Audit Log)
                $throttleKey = "user-last-seen-db-{$userId}";
                if (!Cache::has($throttleKey)) {
                    DB::table('users')
                        ->where('id', $userId)
                        ->update([
                            'last_seen_at'  => $now,
                            'last_login_ip' => $request->ip(),
                        ]);

                    Cache::put($throttleKey, true, $now->copy()->addMinutes(2));
                }
            }
        }

        return $next($request);
    }
}