<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ซิงค์คอลัมน์ที่ตกหล่นทั้งหมดให้ตรงกับฐานข้อมูลจริง 100%
     */
    public function up(): void
    {
        // =====================================================================
        // 1. ตาราง patients: เพิ่มคอลัมน์สิทธิการรักษา HosXP และข้อมูลโรคประจำตัว
        // =====================================================================
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'pttype_code')) {
                    $table->string('pttype_code', 10)->nullable()->after('hn')->comment('รหัสสิทธิการรักษาจาก HosXP');
                }
                if (!Schema::hasColumn('patients', 'pttype_name')) {
                    $table->string('pttype_name', 150)->nullable()->after('pttype_code')->comment('ชื่อสิทธิการรักษาจาก HosXP');
                }
                if (!Schema::hasColumn('patients', 'chronic_disease_status')) {
                    $table->string('chronic_disease_status', 20)->default('none')->after('blood_group')->comment('สถานะโรคประจำตัว: none, yes, other');
                }
                if (!Schema::hasColumn('patients', 'chronic_disease_detail')) {
                    $table->string('chronic_disease_detail', 255)->nullable()->after('chronic_disease_status')->comment('รายละเอียดโรคประจำตัว');
                }
                if (!Schema::hasColumn('patients', 'duty_status')) {
                    $table->string('duty_status', 30)->default('active')->index();
                }
                if (!Schema::hasColumn('patients', 'duty_status_note')) {
                    $table->string('duty_status_note', 255)->nullable();
                }
            });
        }

        // =====================================================================
        // 2. ตาราง patient_medications: เพิ่ม take_prn และ custom_slot_text (e-MAR)
        // =====================================================================
        if (Schema::hasTable('patient_medications')) {
            Schema::table('patient_medications', function (Blueprint $table) {
                if (!Schema::hasColumn('patient_medications', 'take_prn')) {
                    $table->boolean('take_prn')->default(false)->after('take_bedtime')->comment('ใช้ยาเวลามีอาการ / ยาทา / ยานวด');
                }
                if (!Schema::hasColumn('patient_medications', 'custom_slot_text')) {
                    $table->string('custom_slot_text', 255)->nullable()->after('take_prn')->comment('ข้อความระบุเวลาหรือวิธีใช้ยาเอง');
                }
                if (!Schema::hasColumn('patient_medications', 'instruction_note')) {
                    $table->string('instruction_note', 255)->nullable();
                }
            });

            DB::statement("ALTER TABLE patient_medications MODIFY COLUMN meal_timing VARCHAR(50) NOT NULL DEFAULT 'after_meal'");
            DB::statement("ALTER TABLE patient_medications MODIFY COLUMN dosage VARCHAR(100) NULL");
        }

        // =====================================================================
        // 3. ตาราง patient_medication_logs: ขยายความยาวคอลัมน์ time_slot รองรับ prn
        // =====================================================================
        if (Schema::hasTable('patient_medication_logs')) {
            DB::statement("ALTER TABLE patient_medication_logs MODIFY COLUMN time_slot VARCHAR(30) NOT NULL");
        }

        // =====================================================================
        // 4. ตาราง recruit_morbidities: ขยายความยาวคอลัมน์ category และ severity
        // =====================================================================
        if (Schema::hasTable('recruit_morbidities')) {
            DB::statement("ALTER TABLE recruit_morbidities MODIFY COLUMN category VARCHAR(50) NOT NULL");
            DB::statement("ALTER TABLE recruit_morbidities MODIFY COLUMN severity VARCHAR(30) NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                $dropCols = [];
                foreach (['pttype_code', 'pttype_name', 'chronic_disease_status', 'chronic_disease_detail'] as $col) {
                    if (Schema::hasColumn('patients', $col)) {
                        $dropCols[] = $col;
                    }
                }
                if (!empty($dropCols)) {
                    $table->dropColumn($dropCols);
                }
            });
        }

        if (Schema::hasTable('patient_medications')) {
            Schema::table('patient_medications', function (Blueprint $table) {
                $dropMedCols = [];
                foreach (['take_prn', 'custom_slot_text'] as $col) {
                    if (Schema::hasColumn('patient_medications', $col)) {
                        $dropMedCols[] = $col;
                    }
                }
                if (!empty($dropMedCols)) {
                    $table->dropColumn($dropMedCols);
                }
            });
        }
    }
};
