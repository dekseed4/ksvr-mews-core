<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'duty_status')) {
                // สถานะ: active (มารายงานตัว/ปกติ), absent (ไม่มารายงานตัว), imprisoned (ต้องโทษ), deceased (เสียชีวิต)
                $table->string('duty_status', 30)->default('active')->after('service_duration')->index();
            }
            if (!Schema::hasColumn('patients', 'duty_status_note')) {
                // หมายเหตุเพิ่มเติม เช่น ขาดหนีตั้งแต่แรกรับ, เรือนจำ มทบ.29, สาเหตุการเสียชีวิต
                $table->string('duty_status_note', 255)->nullable()->after('duty_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('patients', 'duty_status')) $cols[] = 'duty_status';
            if (Schema::hasColumn('patients', 'duty_status_note')) $cols[] = 'duty_status_note';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};