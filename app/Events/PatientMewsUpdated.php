<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // 🌟 ใช้ ShouldBroadcastNow เพื่อให้ส่งทันทีไม่ต้องรอ Queue
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientMewsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $patientId;

    public function __construct($patientId)
    {
        $this->patientId = $patientId;
    }

    // 🌟 กำหนดช่องสัญญาณ (Channel) แยกตาม ID ของคนไข้
    public function broadcastOn()
    {
        return new Channel('patient.' . $this->patientId);
    }
    
    // 🌟 ตั้งชื่อ Event ให้ฝั่ง JavaScript นำไปดักฟังได้ง่ายๆ
    public function broadcastAs()
    {
        return 'mews.updated';
    }
}