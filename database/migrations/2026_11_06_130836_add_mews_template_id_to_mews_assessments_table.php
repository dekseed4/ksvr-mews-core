<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. เพิ่มคอลัมน์ mews_template_id ต่อจาก evaluator_id
        if (!Schema::hasColumn('mews_assessments', 'mews_template_id')) {
            Schema::table('mews_assessments', function (Blueprint $table) {
                $table->unsignedBigInteger('mews_template_id')->nullable()->after('evaluator_id')->index();
            });
        }

        // 2. อัปเดตข้อมูลเคสเก่าทั้งหมดในระบบให้ผูกกับสูตรที่กำลัง "ใช้งานอยู่ (is_active = 1)" ปัจจุบันอัตโนมัติ
        if (Schema::hasTable('mews_templates')) {
            $activeTemplateId = DB::table('mews_templates')->where('is_active', true)->value('id')
                             ?? DB::table('mews_templates')->orderBy('id', 'desc')->value('id');

            if ($activeTemplateId) {
                DB::table('mews_assessments')
                    ->whereNull('mews_template_id')
                    ->update(['mews_template_id' => $activeTemplateId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mews_assessments', 'mews_template_id')) {
            Schema::table('mews_assessments', function (Blueprint $table) {
                $table->dropColumn('mews_template_id');
            });
        }
    }
};