<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // เพิ่มฟิลด์ใหม่ต่อท้ายจากฟิลด์เดิม (ใช้ nullable เพื่อไม่ให้ข้อมูลเก่าพัง)
            $table->string('phone', 20)->nullable()->after('blood_group')->comment('เบอร์โทรศัพท์');
            $table->string('house_no', 50)->nullable()->after('phone')->comment('บ้านเลขที่');
            $table->string('village_no', 10)->nullable()->after('house_no')->comment('หมู่ที่');
            $table->string('sub_district', 100)->nullable()->after('village_no')->comment('ตำบล');
            $table->string('district', 100)->nullable()->after('sub_district')->comment('อำเภอ');
            $table->string('province', 100)->nullable()->after('district')->comment('จังหวัด');
            
            $table->string('father_name', 255)->nullable()->after('province')->comment('ชื่อ-สกุลบิดา');
            $table->string('mother_name', 255)->nullable()->after('father_name')->comment('ชื่อ-สกุลมารดา');
            
            $table->string('height', 10)->nullable()->after('mother_name')->comment('ส่วนสูง (cm)');
            $table->string('weight', 10)->nullable()->after('height')->comment('น้ำหนัก (kg)');
            $table->string('chest_size', 20)->nullable()->after('weight')->comment('รอบอก');
            $table->string('bmi', 10)->nullable()->after('chest_size')->comment('ค่า BMI');
            
            $table->string('scar_details', 255)->nullable()->after('bmi')->comment('ตำหนิ/แผลเป็น');
            $table->string('religion', 50)->nullable()->after('scar_details')->comment('ศาสนา');
            $table->string('education', 100)->nullable()->after('religion')->comment('วุฒิการศึกษา');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // กรณีต้องการ Rollback ให้ลบคอลัมน์เหล่านี้ทิ้ง
            $table->dropColumn([
                'phone', 'house_no', 'village_no', 'sub_district', 'district', 'province',
                'father_name', 'mother_name', 'height', 'weight', 'chest_size', 'bmi',
                'scar_details', 'religion', 'education'
            ]);
        });
    }
};