<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. สร้างหรืออัปเกรดตารางสัญญาณชีพ 06:00 / 18:00 น. ให้มีช่อง mews_score ตามรูปที่ 1
        if (!Schema::hasTable('covid_vital_logs')) {
            Schema::create('covid_vital_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
                $table->unsignedBigInteger('morbidity_id')->nullable()->index();
                $table->date('record_date')->index();
                $table->string('round_time', 10)->default('06:00')->index(); // 06:00, 18:00
                $table->decimal('temperature', 4, 1)->nullable();
                $table->unsignedSmallInteger('heart_rate')->nullable();
                $table->unsignedSmallInteger('systolic_bp')->nullable();
                $table->unsignedSmallInteger('diastolic_bp')->nullable();
                $table->unsignedSmallInteger('respiratory_rate')->nullable();
                $table->unsignedSmallInteger('o2sat')->nullable();
                $table->unsignedTinyInteger('mews_score')->default(0);
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->unique(['patient_id', 'record_date', 'round_time'], 'uniq_vital_patient_date_round');
            });
        } else {
            Schema::table('covid_vital_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('covid_vital_logs', 'mews_score')) {
                    $table->unsignedTinyInteger('mews_score')->default(0)->after('o2sat');
                }
            });
        }

        // 2. ตารางรายการยาประจำตัวผู้ป่วย (สำหรับ CI แยกกัก, โรคจิตเวช, และโรคที่ต้องกินยาต่อเนื่อง)
        if (!Schema::hasTable('patient_medications')) {
            Schema::create('patient_medications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
                // care_group: ci_isolation (ผู้ป่วย CI/แยกนอน), psychiatric (โรคจิตเวช), chronic (โรคประจำตัวกินยาต่อเนื่อง)
                $table->string('care_group', 30)->default('ci_isolation')->index();
                $table->string('drug_name', 200); // เช่น paracetamol 500 mg., Risperidone 2 mg.
                $table->string('dosage', 100); // ปริมาณยา เช่น ครั้งละ 1 เม็ด, 2 เม็ด, 1 ช้อนชา
                // meal_timing: before_meal (ก่อนอาหาร), after_meal (หลังอาหาร), with_meal (พร้อมอาหาร), bedtime_only (ก่อนนอน), prn (เมื่อมีอาการ)
                $table->string('meal_timing', 30)->default('after_meal');

                // มื้อเวลาที่ต้องรับประทาน (เช้า, เที่ยง, เย็น, ก่อนนอน ตามรูปที่ 2)
                $table->boolean('take_morning')->default(false);
                $table->boolean('take_noon')->default(false);
                $table->boolean('take_evening')->default(false);
                $table->boolean('take_bedtime')->default(false);

                $table->date('start_date')->index();
                $table->date('end_date')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->string('instruction_note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 3. ตารางเช็คชื่อการรับประทานยารายวันตามมื้อเวลา (เช้า / เที่ยง / เย็น / ก่อนนอน)
        if (!Schema::hasTable('patient_medication_logs')) {
            Schema::create('patient_medication_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_medication_id')->constrained('patient_medications')->onDelete('cascade');
                $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
                $table->date('log_date')->index();
                // time_slot: morning (เช้า), noon (เที่ยง), evening (เย็น), bedtime (ก่อนนอน)
                $table->string('time_slot', 20)->index();
                // status: taken (รับประทานแล้ว), missed (ขาดยา/ไม่ได้กิน)
                $table->string('status', 20)->default('taken');
                $table->unsignedBigInteger('given_by')->nullable();
                $table->string('remark', 255)->nullable();
                $table->timestamps();

                $table->unique(['patient_medication_id', 'log_date', 'time_slot'], 'uniq_med_log_slot');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_medication_logs');
        Schema::dropIfExists('patient_medications');
    }
};