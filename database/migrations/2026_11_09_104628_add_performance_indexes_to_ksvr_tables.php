<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->index(['intake_batch', 'military_unit_id'], 'idx_patients_batch_unit');
            $table->index('hn', 'idx_patients_hn');
        });

        Schema::table('mews_assessments', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'idx_mews_patient_status');
            $table->index(['case_id', 'status'], 'idx_mews_case_status');
            $table->index(['created_at', 'status', 'risk_level'], 'idx_mews_date_status_risk');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('idx_patients_batch_unit');
            $table->dropIndex('idx_patients_hn');
        });

        Schema::table('mews_assessments', function (Blueprint $table) {
            $table->dropIndex('idx_mews_patient_status');
            $table->dropIndex('idx_mews_case_status');
            $table->dropIndex('idx_mews_date_status_risk');
        });
    }
};