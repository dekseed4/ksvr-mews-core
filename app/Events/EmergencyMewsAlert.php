<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// 🌟 ต้องมี implements ShouldBroadcast เสมอนะครับ!
class EmergencyMewsAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $patientData;

    public function __construct($patientData)
    {
        $this->patientData = $patientData;
    }

    // 🌟 1. ระบุช่องทาง (Channel) ที่จะส่งไป
    public function broadcastOn()
    {
        // ส่งไปที่ Private Channel ชื่อ 'er-alerts'
        return new PrivateChannel('er-alerts');
    }

    // 🌟 2. ระบุชื่อ Event ให้ตรงกับที่ Javascript ดักฟังไว้ (สำคัญมาก!)
    public function broadcastAs()
    {
        return 'mews.emergency';
    }

    // 🌟 3. จัดรูปข้อมูลที่จะแนบไปกับสัญญาณ
    public function broadcastWith()
    {
        return [
            'patientData' => $this->patientData
        ];
    }
}