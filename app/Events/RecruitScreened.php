<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// 🌟 ต้อง implements ShouldBroadcastNow เพื่อให้ส่งสัญญาณทันที ไม่ต้องรอคิว
class RecruitScreened implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $intakeBatch;

    // รับค่าผลัดที่มีการอัปเดต เพื่อให้แดชบอร์ดรู้ว่าควรรีเฟรชไหม
    public function __construct($intakeBatch)
    {
        $this->intakeBatch = $intakeBatch;
    }

    // กำหนดช่องสถานี (Channel) สำหรับออกอากาศ
    public function broadcastOn(): array
    {
        return [
            new Channel('recruit-dashboard'),
        ];
    }
}