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
        Schema::table('patients', function (Blueprint $table) {
        // เพิ่ม 3 ฟิลด์ใหม่ (ตั้งให้เป็น nullable เผื่อคนไข้ทั่วไปที่ไม่ได้เป็นทหารเกณฑ์)
        $table->string('volunteer_status', 100)->nullable()->comment('สถานะเข้ารับราชการ เช่น ร้องขอ, จับได้');
        $table->string('privilege_reduction', 100)->nullable()->comment('การขอสิทธิลด เช่น ขอสิทธิลด');
        $table->string('service_duration', 50)->nullable()->comment('เวลารับราชการ เช่น 6 เดือน, 1 ปี');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
        // ลบทิ้งกรณีสั่ง Rollback
        $table->dropColumn(['volunteer_status', 'privilege_reduction', 'service_duration']);
    });
    }
};
