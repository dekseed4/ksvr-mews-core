<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ใช้คำสั่ง SQL ตรงๆ เพื่อความชัวร์ 100% ในกรณีที่คอลัมน์เดิมถูกตั้งเป็น ENUM
        DB::statement("ALTER TABLE mews_assessments MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");
    }

    public function down()
    {
        // สำรองไว้เผื่อกรณีย้อนกลับ (Rollback)
        DB::statement("ALTER TABLE mews_assessments MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");
    }
};
