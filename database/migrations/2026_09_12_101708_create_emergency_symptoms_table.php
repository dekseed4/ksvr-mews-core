<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('emergency_symptoms', function (Blueprint $table) {
        $table->id();
        $table->string('category'); // กลุ่มโรค เช่น MI, Stroke, Trauma
        $table->string('code')->unique(); // รหัสอ้างอิง เช่น mi_chest_pain
        $table->string('name'); // ชื่ออาการที่แสดงให้ User เห็น
        $table->boolean('is_active')->default(true); // เปิด/ปิด อาการนี้ได้
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_symptoms');
    }
};
