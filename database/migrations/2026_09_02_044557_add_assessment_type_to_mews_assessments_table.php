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
        Schema::table('mews_assessments', function (Blueprint $table) {
            // 🌟 เพิ่มคอลัมน์ assessment_type ต่อท้ายคอลัมน์ status
            $table->enum('assessment_type', ['field', 'er', 'ward'])
                  ->default('field')
                  ->after('status')
                  ->comment('ประเภทการประเมิน: ภาคสนาม (field), ห้องฉุกเฉิน (er), หอผู้ป่วย (ward)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            // 🌟 สำรองเผื่อต้องการ Rollback ให้ลบคอลัมน์นี้ทิ้ง
            $table->dropColumn('assessment_type');
        });
    }
};