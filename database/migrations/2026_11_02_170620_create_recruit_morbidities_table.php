<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruit_morbidities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->unsignedBigInteger('military_unit_id')->nullable()->index();
            $table->string('intake_batch', 20)->nullable()->index();

            // หมวดหมู่หลัก: respiratory (ทางเดินหายใจ), heat (ความร้อน), other_ipd (เจ็บป่วยอื่นๆ นอน รพ.)
            $table->string('category', 30)->index();

            // ชนิดย่อย:
            // - respiratory: covid, flu
            // - heat: heat_rash, heat_edema, heat_cramps, heat_tetany, heat_syncope, mild_rhabdo, severe_rhabdo, heat_exhaustion, heat_stroke
            // - other_ipd: camp_hospital (นอน รพ.ค่ายฯ), public_hospital (นอน รพ.สาธารณสุข)
            $table->string('sub_type', 50)->index();

            // ความรุนแรง (สำหรับความร้อน: mild = ไม่รุนแรง, severe = รุนแรง)
            $table->string('severity', 20)->nullable();

            // สถานที่รักษา (สำหรับทางเดินหายใจ: ci = แยกกัก CI, hospital = นอน รพ.)
            $table->string('treatment_location', 30)->nullable();

            // การวินิจฉัยโรค (เช่น Acute Pharyngitis) และหมายเหตุ
            $table->string('diagnosis_text', 255)->nullable();

            // วันที่เริ่มป่วย/Admit และ วันที่หายป่วย/จำหน่าย (หัวใจสำคัญของการคำนวณ รายใหม่/รายเก่า/สะสม รายวัน)
            $table->date('onset_date')->index();
            $table->string('status', 20)->default('treating')->index(); // treating = รักษาอยู่, recovered = หายป่วย
            $table->date('recovered_date')->nullable()->index();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruit_morbidities');
    }
};