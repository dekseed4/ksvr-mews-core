<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('mews_assessments', function (Blueprint $table) {
        // ใช้ nullable() เพราะเคสที่ยังไม่จบ จะยังไม่มีค่านี้ครับ
        $table->string('discharge_type')->nullable()->after('status')->comment('ประเภทการจำหน่าย: recovered(กลับหน่วย), refer(ส่งต่อ), admitted(นอน รพ.)');
    });
}

public function down(): void
{
    Schema::table('mews_assessments', function (Blueprint $table) {
        $table->dropColumn('discharge_type');
    });
}
};
