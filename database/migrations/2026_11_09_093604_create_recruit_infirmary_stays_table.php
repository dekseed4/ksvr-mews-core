<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruit_infirmary_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->unsignedBigInteger('military_unit_id')->nullable()->index();
            $table->string('intake_batch', 20)->nullable()->index();
            $table->string('mews_case_id', 50)->nullable()->index();

            // สถานที่แยกนอน: unit_infirmary (ห้องพยาบาล/โรงนอนแยกหน่วยฝึก), camp_hospital (ฝากนอนที่ รพ.ค่ายฯ)
            $table->string('location_type', 30)->default('unit_infirmary')->index();
            $table->string('bed_or_zone', 100)->nullable(); // เช่น โซนโรคทางเดินหายใจ, เตียง 3, โรงนอนแยก 2

            // หมวดหมู่สาเหตุ: respiratory (ทางเดินหายใจ), post_admit (พักฟื้นหลัง Admit), musculoskeletal (บาดเจ็บกล้ามเนื้อ/แผล), other (อื่นๆ)
            $table->string('reason_category', 40)->default('respiratory')->index();
            $table->string('diagnosis_text', 255)->nullable(); // อาการหรือโรควินิจฉัย
            $table->text('care_instruction')->nullable(); // คำสั่งการดูแล เช่น งดฝึก 3 วัน, แยกทานอาหาร, สวมหน้ากาก

            // วัน-เวลาที่เริ่มเข้าพัก และวันที่จำหน่ายกลับโรงนอนปกติ
            $table->dateTime('admitted_at')->index();
            $table->dateTime('discharged_at')->nullable()->index();
            $table->string('discharge_note', 255)->nullable();

            // สถานะ: staying (กำลังแยกนอนพัก), returned (กลับโรงนอนปกติแล้ว)
            $table->string('status', 30)->default('staying')->index();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruit_infirmary_stays');
    }
};