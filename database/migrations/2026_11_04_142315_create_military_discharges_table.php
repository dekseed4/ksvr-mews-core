<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('military_discharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->unsignedBigInteger('military_unit_id')->nullable()->index();
            $table->string('intake_batch', 20)->nullable()->index();

            // โรควินิจฉัยที่ใช้นำปลด (เช่น ธาลัสซีเมีย ตับและม้ามโต)
            $table->string('diagnosis_text', 255)->nullable();

            // สถานะความคืบหน้า: preparing (กำลังเตรียมเอกสาร), unit_sent (หน่วยส่งเอกสารแล้ว), completed (เวชกรรมส่ง ศปน. ครบแล้ว)
            $table->string('process_status', 30)->default('preparing')->index();

            // เก็บตาราง Matrix เอกสาร 7 ช่อง x 3 แถว + หมายเหตุ ในรูปแบบ JSON
            $table->json('documents_matrix')->nullable();

            // ข้อมูลผู้ประสานงานท้ายตาราง
            $table->string('hospital_coordinator', 255)->nullable();
            $table->string('unit_coordinator', 255)->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('military_discharges');
    }
};