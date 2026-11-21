<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->index('created_at', 'idx_system_logs_created_at');
            $table->index(['loggable_type', 'created_at'], 'idx_system_logs_type_created');
            $table->index(['action', 'created_at'], 'idx_system_logs_action_created');
            $table->index(['user_id', 'created_at'], 'idx_system_logs_user_created');
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropIndex('idx_system_logs_created_at');
            $table->dropIndex('idx_system_logs_type_created');
            $table->dropIndex('idx_system_logs_action_created');
            $table->dropIndex('idx_system_logs_user_created');
        });
    }
};