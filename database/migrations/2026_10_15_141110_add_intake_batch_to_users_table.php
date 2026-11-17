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
        Schema::table('users', function (Blueprint $table) {
            // เพิ่มคอลัมน์ intake_batch ต่อจาก military_unit_id และทำ Index เพื่อความเร็วในการกรองข้อมูล
            $table->string('intake_batch', 20)->nullable()->after('military_unit_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['intake_batch']);
            $table->dropColumn('intake_batch');
        });
    }
};
