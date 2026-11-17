<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. เพิ่มเข้าตาราง users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('military_unit_id')->nullable()->after('email')
                  ->constrained('military_units')->nullOnDelete();
            
            // ลบคอลัมน์ company เดิมทิ้ง (ถ้าคุณศุภกิจเคยสร้างไว้แล้ว)
            // $table->dropColumn('company'); 
        });

        // 2. เพิ่มเข้าตาราง patients
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('military_unit_id')->nullable()->after('military_id')
                  ->constrained('military_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['military_unit_id']);
            $table->dropColumn('military_unit_id');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['military_unit_id']);
            $table->dropColumn('military_unit_id');
        });
    }
};
