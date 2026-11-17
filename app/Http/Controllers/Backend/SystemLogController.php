<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    public function index(Request $request)
    {
        // 🌟 ดึง Log พร้อมข้อมูล User และ โมเดลเป้าหมาย (ป้องกัน N+1 Query)
        $query = SystemLog::with(['user', 'loggable'])->orderBy('created_at', 'desc');

        // 🌟 ระบบค้นหาแบบง่าย (ค้นหาจากชื่อผู้ใช้ หรือ ชื่อการกระทำ)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhere('action', 'like', "%{$search}%");
        }

        // แบ่งหน้าละ 30 รายการ จะได้กวาดสายตาดูได้เยอะๆ ครับ
        $logs = $query->paginate(30)->appends($request->query());

        return view('backend.system_logs.index', compact('logs'));
    }
}