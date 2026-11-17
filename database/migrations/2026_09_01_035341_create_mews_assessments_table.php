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
            $table->foreignId('user_id')->comment('ID เสนารักษ์ที่ประเมิน')->constrained('users');
            
            // ข้อมูลผู้ป่วย (ดึงจาก HosXP มาเก็บไว้เป็น Snapshot ป้องกันข้อมูล HosXP หาย/เปลี่ยน)
            $table->string('cid', 13)->comment('เลขบัตร ปชช.');
            $table->string('hn', 20)->nullable()->comment('รหัส HN จาก HosXP');
            $table->string('patient_name');
            $table->integer('age')->nullable();

            // ข้อมูลการคัดกรองเบื้องต้น
            $table->text('chief_complaint')->nullable()->comment('อาการสำคัญ');
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->string('underlying_disease')->nullable();
            $table->string('allergy_history')->nullable();

            // สัญญาณชีพ (Vital Signs)
            $table->decimal('temperature', 4, 1)->nullable(); // BT
            $table->integer('pulse')->nullable(); // HR
            $table->integer('respiratory_rate')->nullable(); // RR
            $table->integer('systolic_bp')->nullable(); // SBP
            $table->integer('diastolic_bp')->nullable(); // DBP
            $table->integer('o2_sat')->nullable(); 

            // การประเมินอื่นๆ
            $table->enum('avpu', ['alert', 'confused', 'voice', 'pain', 'unresponsive'])->default('alert');
            $table->integer('pain_score')->default(0)->comment('0-10');
            $table->integer('total_mews_score')->default(0);

            // ภาวะฉุกเฉิน (Emergency Condition - เก็บเป็น JSON เพื่อความยืดหยุ่น)
            $table->json('emergency_conditions')->nullable()->comment('เช่น ["heat_injury", "stroke"]');

            // สถานะการตอบกลับจากพยาบาล
            $table->enum('status', ['pending', 'reviewed', 'transfer'])->default('pending');
            $table->text('nurse_note')->nullable()->comment('บันทึกจากพยาบาล');
            $table->foreignId('nurse_id')->nullable()->comment('ID พยาบาลที่รับเคส')->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mews_assessments');
    }
};