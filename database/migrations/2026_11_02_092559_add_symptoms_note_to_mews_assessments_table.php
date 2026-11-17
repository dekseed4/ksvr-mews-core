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
            // เพิ่มคอลัมน์ symptoms_note ชนิด TEXT รองรับข้อความยาวๆ และเก็บบันทึกต่อจากคอลัมน์ symptoms
            $table->text('symptoms_note')->nullable()->after('symptoms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            $table->dropColumn('symptoms_note');
        });
    }
};