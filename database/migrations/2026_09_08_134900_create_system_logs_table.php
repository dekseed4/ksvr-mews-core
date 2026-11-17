<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            
            // 1. ผู้กระทำ (Who)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // 2. การกระทำ (Action) เช่น create, update, delete, login
            $table->string('action'); 
            
            // 3. ข้อมูลเป้าหมาย (Target) ใช้ Polymorphic เพื่อให้เก็บได้ทั้งตาราง Patient, User, Mews
            $table->nullableMorphs('loggable'); 
            
            // 4. รายละเอียดเชิงลึก (Payload) เก็บเป็น JSON เพื่อความยืดหยุ่นแบบ Multi-input 
            // (เช่น เก็บค่าเก่า ค่าใหม่, IP Address, หรือ User Agent)
            $table->json('payload')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_logs');
    }
};
