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
        Schema::create('military_units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('ชื่อเต็มหน่วย เช่น กองร้อยที่ 1');
            $table->string('short_name')->nullable()->comment('ชื่อย่อ เช่น ร้อย.1');
            $table->string('battalion')->nullable()->comment('กองพันที่สังกัด เช่น ร.3 พัน.1');
            $table->boolean('is_active')->default(true)->comment('สถานะการใช้งาน');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('military_units');
    }
};
