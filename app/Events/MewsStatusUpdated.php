<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// 🌟 เอา use Illuminate\Support\Str; ออกไปได้เลยครับ เพราะไม่ได้ใช้แล้ว

class MewsStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $assessmentData;
    public $unitId; // 🌟 เปลี่ยนจาก unitName เป็น unitId ให้สื่อความหมายตรงกับ Database

    public function __construct($assessmentData, $unitId)
    {
        $this->assessmentData = $assessmentData;
        $this->unitId = $unitId;
    }

    public function broadcastOn()
    {
        // 🌟 ส่งสัญญาณไปที่ช่องประจำหน่วยด้วยตัวเลข ID ตรงๆ เช่น 'unit-alerts.5'
        // (ไม่ต้องใช้ Str::slug แล้ว เพราะตัวเลขปลอดภัยในการทำเป็น URL อยู่แล้วครับ)
        return new PrivateChannel('unit-alerts.' . $this->unitId);
    }

    public function broadcastAs()
    {
        return 'mews.status.updated';
    }
}