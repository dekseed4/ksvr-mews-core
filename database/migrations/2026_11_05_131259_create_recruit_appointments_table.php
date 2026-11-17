<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruit_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->unsignedBigInteger('military_unit_id')->nullable()->index();
            $table->string('intake_batch', 20)->nullable()->index();

            // รหัสอ้างอิง oapp_id จากตาราง oapp ของ HosXP (ป้องกันการซิงค์ข้อมูลซ้ำ)
            $table->unsignedBigInteger('hosxp_oapp_id')->nullable()->unique();

            // ประเภทโรงพยาบาล: camp_hospital (รพ.ค่ายฯ), public_hospital (รพ.สาธารณสุข)
            $table->string('hospital_type', 30)->default('camp_hospital')->index();
            $table->string('hospital_name', 150)->nullable(); // เช่น รพ.ค่ายกฤษณ์สีวะรา, รพ.สกลนคร

            // วันและเวลานัดพบแพทย์
            $table->date('appointment_date')->index();
            $table->string('appointment_time', 20)->nullable();

            // คลินิก/แผนก, ชื่อแพทย์ และสาเหตุการนัด/การเตรียมตัว
            $table->string('clinic_name', 150)->nullable();
            $table->string('doctor_name', 150)->nullable();
            $table->string('app_cause', 255)->nullable();
            $table->text('note')->nullable();

            // สถานะ: scheduled (รอพบแพทย์), completed (ไปพบแพทย์แล้ว), postponed (เลื่อนนัด), cancelled (ยกเลิก)
            $table->string('status', 30)->default('scheduled')->index();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruit_appointments');
    }
};