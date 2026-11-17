<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            
            // ข้อมูลบุคคลพื้นฐาน
            $table->string('cid', 13)->unique()->comment('เลขบัตร ปชช. (ใช้เป็น Key หลักในการค้นหา)');
            $table->string('hn', 20)->nullable()->comment('รหัส HN จาก HosXP (เผื่อเชื่อมโยงข้อมูล)');
            $table->string('rank', 50)->nullable()->comment('ยศ');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('dob')->nullable()->comment('วันเกิด (เพื่อนำไปคำนวณอายุแบบ Real-time)');
            $table->string('blood_group', 5)->nullable();

            // ข้อมูลเฉพาะทางทหาร (ยืดหยุ่นรองรับทุกค่าย)
            $table->string('military_id')->nullable()->comment('หมายเลขประจำตัวทหาร');
            $table->string('camp_name')->default('ค่ายกฤษณ์สีวะรา')->comment('ชื่อค่ายทหาร');
            $table->string('battalion')->nullable()->comment('กรม/กองพัน เช่น ร.3 พัน.1');
            $table->string('company')->nullable()->comment('กองร้อย/หน่วยฝึก เช่น หน่วยฝึกที่ 1');
            $table->string('intake_batch')->nullable()->comment('ผลัดที่/ปี เช่น 1/67');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};