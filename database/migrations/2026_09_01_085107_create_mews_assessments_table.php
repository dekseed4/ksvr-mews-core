<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mews_assessments', function (Blueprint $table) {
            $table->id();
            
            // 1. ความสัมพันธ์ (Relationships)
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            
            // 2. ข้อมูลการเจ็บป่วยเบื้องต้น (เปลี่ยนเป็น json รองรับ Array จาก Checkbox)
            $table->json('symptoms')->nullable()->comment('อาการฉุกเฉินเฉพาะโรค (JSON Array)');
            
            // 3. สัญญาณชีพ (Vital Signs) สำหรับคำนวณ MEWS
            $table->decimal('temperature', 4, 1)->nullable()->comment('อุณหภูมิร่างกาย');
            $table->integer('heart_rate')->nullable()->comment('ชีพจร/อัตราการเต้นหัวใจ');
            $table->integer('respiratory_rate')->nullable()->comment('อัตราการหายใจ');
            $table->integer('systolic_bp')->nullable()->comment('ความดันโลหิตตัวบน');
            $table->integer('o2sat')->nullable()->comment('ออกซิเจนในเลือด (O2 Sat)'); // 🌟 เพิ่มใหม่
            $table->string('consciousness', 20)->default('A')->comment('ระดับความรู้สึกตัว (A, V1, V2, P, U)');
            
            // 4. ผลลัพธ์และการจัดการ
            $table->integer('total_score')->default(0)->comment('คะแนน MEWS รวม');
            
            // 🌟 เพิ่มใหม่: ระดับความเสี่ยงทางคลินิก (ส่งมาจาก JavaScript)
            $table->enum('risk_level', ['normal', 'observe', 'emergency'])->default('normal')->comment('ระดับความเสี่ยง: ปกติ, เฝ้าระวัง, ฉุกเฉิน');
            
            // สถานะการทำงานของเจ้าหน้าที่ (Workflow)
            $table->enum('status', ['pending', 'acknowledged', 'completed'])->default('pending')->comment('สถานะ: รอพยาบาลรับทราบ, รับทราบแล้ว, จบเคส');
            
            $table->timestamps();
            $table->softDeletes(); // 🌟 เพิ่ม Soft Deletes ป้องกันลบพลาด
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mews_assessments');
    }
};