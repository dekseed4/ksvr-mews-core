<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ลบคอลัมน์ role เดิมทิ้ง
            $table->dropColumn('role');
            // สร้างคอลัมน์ roles แบบ json แทน (ใส่ default เป็น array เปล่า)
            $table->json('roles')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
            $table->string('role', 50)->default('user')->after('email');
        });
    }
};