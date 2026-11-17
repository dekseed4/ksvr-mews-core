<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            // เก็บวัน-เวลาที่จำหน่ายออกจากหอผู้ป่วย (Admit/Refer) กลับสู่หน่วยฝึก
            $table->timestamp('returned_to_unit_at')->nullable()->after('discharge_details');
            // เก็บหมายเหตุตอนกลับหน่วย (เช่น อาการปกติ, งดฝึกหนัก 3 วัน)
            $table->string('return_note', 255)->nullable()->after('returned_to_unit_at');
        });
    }

    public function down(): void
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            $table->dropColumn(['returned_to_unit_at', 'return_note']);
        });
    }
};