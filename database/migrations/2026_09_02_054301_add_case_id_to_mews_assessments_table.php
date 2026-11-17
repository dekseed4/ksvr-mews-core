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
        // เพิ่ม case_id เข้าไปต่อท้าย patient_id
        $table->string('case_id')->nullable()->after('patient_id')->comment('รหัสรอบการรักษา (เช่น VN)');
    });
}

public function down(): void
{
    Schema::table('mews_assessments', function (Blueprint $table) {
        $table->dropColumn('case_id');
    });
}
};
