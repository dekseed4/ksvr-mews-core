<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    // บันทึกข้อมูลอุปกรณ์ (มือถือ / เบราว์เซอร์) ลงฐานข้อมูล
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint'                  => 'required|string|max:500',
            'keys.p256dh'               => 'nullable|string|max:255',
            'keys.auth'                 => 'nullable|string|max:255',
            'contentEncoding'           => 'nullable|string|max:50',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id'          => auth()->id(),
                'public_key'       => $data['keys']['p256dh'] ?? null,
                'auth_token'       => $data['keys']['auth'] ?? null,
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'user_agent'       => substr((string) $request->userAgent(), 0, 250),
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'ลงทะเบียนรับแจ้งเตือนเบื้องหลังสำเร็จ']);
    }

    // ทดสอบยิง Web Push จากเซิร์ฟเวอร์เข้าเครื่องของผู้ใช้ที่กดปุ่มทดสอบ (ลองกดแล้วล็อกหน้าจอมือถือได้ทันที)
    public function sendTestPush(Request $request)
    {
        WebPushService::sendToUsers(
            [auth()->id()],
            '🔔 ทดสอบระบบ KSVR Web Push (24/7)',
            'แจ้งเตือนจากเซิร์ฟเวอร์ทำงานสมบูรณ์! แม้ปิดหน้าเว็บหรือล็อกหน้าจอมือถือ ระบบก็จะเด้งเตือนแบบนี้ครับ',
            route('backend.dashboard'),
            'test-webpush',
            false
        );

        return response()->json(['status' => 'success']);
    }
}