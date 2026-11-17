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
        Schema::table('patients', function (Blueprint $table) {
        // สร้าง Composite Index (จับคู่คอลัมน์ที่มักจะถูก GroupBy คู่กัน)
        $table->index(['intake_batch', 'military_unit_id'], 'idx_batch_unit');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('idx_batch_unit');
        });
    }
};
