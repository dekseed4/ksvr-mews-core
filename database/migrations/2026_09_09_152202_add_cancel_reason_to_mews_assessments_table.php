<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            // 🌟 เพิ่มคอลัมน์เก็บเหตุผลการยกเลิก (เป็น nullable เผื่อเคสปกติที่ไม่ได้ยกเลิก)
            $table->string('cancel_reason')->nullable()->after('status')->comment('เหตุผลที่ยกเลิกเคส');
        });
    }

    public function down()
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });
    }
};
