<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            // 🌟 เพิ่มคอลัมน์ใหม่ ชื่อ discharge_details เป็นแบบ nullable (เผื่อกรณีจำหน่ายกลับกองร้อยที่ไม่ต้องกรอก)
            // วางไว้หลังคอลัมน์ discharge_type เพื่อความเป็นระเบียบ
            $table->string('discharge_details')->nullable()->after('discharge_type')->comment('ระบุเลขเตียง/หอผู้ป่วย หรือ รพ.ปลายทาง');
        });
    }

    public function down()
    {
        Schema::table('mews_assessments', function (Blueprint $table) {
            // กรณีต้องการ Rollback
            $table->dropColumn('discharge_details');
        });
    }
};
