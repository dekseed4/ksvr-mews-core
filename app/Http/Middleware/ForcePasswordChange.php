<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        // ถ้าผู้ใช้ล็อกอินอยู่ และสถานะต้องเปลี่ยนรหัสเป็น true
        if (auth()->check() && auth()->user()->must_change_password) {
            
            // ยกเว้นหน้าเปลี่ยนรหัส และหน้า Logout เพื่อไม่ให้เกิด Loop นรก (Infinite Loop)
            if (!$request->routeIs('password.force.*') && !$request->routeIs('logout')) {
                return redirect()->route('password.force.edit')->with('warning', 'คุณต้องเปลี่ยนรหัสผ่านชั่วคราวก่อนเริ่มใช้งานระบบครับ');
            }
        }

        return $next($request);
    }
}