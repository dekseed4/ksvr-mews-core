<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
   public function handle(Request $request, Closure $next, $role): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // ท่าไม้ตาย! แอบดูข้อมูลที่ระบบเห็น (ถ้าโค้ดทำงานถึงตรงนี้ หน้าเว็บจะหยุดแล้วโชว์ตัวหนังสือสีดำๆ)
        // dd($request->user()->roles, $role, $request->user()->hasRole($role)); 

        if (!$request->user()->hasRole($role)) {
            return redirect()->route('backend.dashboard')->with('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้ครับ');
        }

        return $next($request);
    }
}