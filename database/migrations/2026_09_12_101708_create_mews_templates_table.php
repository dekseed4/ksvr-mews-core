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
    Schema::create('mews_templates', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // เช่น "MEWS มาตรฐาน", "MEWS สำหรับผู้สูงอายุ"
        $table->text('description')->nullable();
        $table->boolean('is_default')->default(false); // 🌟 ล็อกไว้ว่าอันนี้คือค่ามาตรฐาน ห้ามลบ
        $table->boolean('is_active')->default(false);  // 🌟 เปิด/ปิด การใช้งานสูตรนี้
        $table->json('rules'); // 🌟 เก็บสูตรคำนวณ SBP, HR, RR, Temp, AVPU เป็น JSON
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mews_templates');
    }
};
