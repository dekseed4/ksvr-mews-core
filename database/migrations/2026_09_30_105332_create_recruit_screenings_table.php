<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recruit_screenings', function (Blueprint $table) {
            $table->id();
            
            // เชื่อมกับตารางคนไข้ (ทหาร) และตารางหน่วยฝึก
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('military_unit_id')->constrained('military_units');
            
            // เก็บผลัดที่รับรายงานตัว เช่น "2/69"
            $table->string('intake_batch', 20)->index();
            
            // สีที่คัดกรอง (เขียว, เหลือง, แดง)
            $table->enum('color_group', ['green', 'yellow', 'red'])->index();
            
            // อาการเพิ่มเติม (เผื่อพยาบาลอยากพิมพ์โน้ต)
            $table->string('symptoms_note', 255)->nullable();
            
            // รหัสเจ้าหน้าที่ผู้ประเมิน
            $table->foreignId('screener_id')->constrained('users');
            
            $table->timestamps();
            
            // ป้องกันการประเมินซ้ำซ้อน: ทหาร 1 คน ใน 1 ผลัด จะประเมินได้แค่ 1 เรคคอร์ดเท่านั้น
            $table->unique(['patient_id', 'intake_batch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruit_screenings');
    }
};
