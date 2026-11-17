<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MewsTemplate;
use App\Models\EmergencySymptom;

class MewsSettingsSeeder extends Seeder
{
    public function run()
    {
        // 🌟 1. สร้างสูตร MEWS มาตรฐาน (อ้างอิงจากตาราง KSVR MEWS ของโรงพยาบาลค่ายกฤษณ์สีวะรา)
        // ใช้ updateOrCreate เพื่อป้องกันการสร้างข้อมูลซ้ำถ้ารัน Seeder หลายรอบ
        MewsTemplate::updateOrCreate(
            ['is_default' => true], // ค้นหาจากตัวที่เป็นค่ามาตรฐาน
            [
                'name' => 'KSVR MEWS มาตรฐาน (Standard)',
                'description' => 'เกณฑ์การประเมินสัญญาณชีพผู้ป่วยผู้ใหญ่ทั่วไป (อ้างอิงตามตาราง KSVR)',
                'is_active' => true,
                'rules' => [ 
                    'sbp' => [
                        ['min' => 0,   'max' => 70,  'score' => 3],
                        ['min' => 71,  'max' => 80,  'score' => 2],
                        ['min' => 81,  'max' => 100, 'score' => 1],
                        ['min' => 101, 'max' => 140, 'score' => 0], 
                        ['min' => 141, 'max' => 179, 'score' => 1],
                        ['min' => 180, 'max' => 199, 'score' => 2],
                        ['min' => 200, 'max' => 999, 'score' => 3],
                    ],
                    'hr' => [
                        ['min' => 0,   'max' => 39,  'score' => 3],
                        ['min' => 40,  'max' => 50,  'score' => 2],
                        ['min' => 51,  'max' => 59,  'score' => 1],
                        ['min' => 60,  'max' => 100, 'score' => 0], 
                        ['min' => 101, 'max' => 110, 'score' => 1],
                        ['min' => 111, 'max' => 120, 'score' => 2],
                        ['min' => 121, 'max' => 999, 'score' => 3],
                    ],
                    'rr' => [
                        ['min' => 0,   'max' => 8,   'score' => 2],
                        ['min' => 9,   'max' => 11,  'score' => 1],
                        ['min' => 12,  'max' => 20,  'score' => 0], 
                        ['min' => 21,  'max' => 25,  'score' => 1],
                        ['min' => 26,  'max' => 29,  'score' => 2],
                        ['min' => 30,  'max' => 99,  'score' => 3],
                    ],
                    'temp' => [
                        ['min' => 0,    'max' => 35.5, 'score' => 2],
                        ['min' => 35.6, 'max' => 36.4, 'score' => 1],
                        ['min' => 36.5, 'max' => 37.4, 'score' => 0], 
                        ['min' => 37.5, 'max' => 38.0, 'score' => 1],
                        ['min' => 38.1, 'max' => 39.5, 'score' => 2],
                        ['min' => 39.6, 'max' => 50.0, 'score' => 3],
                    ],
                    // 🌟 เพิ่ม O2 Sat (ตามตาราง)
                    'o2sat' => [
                        ['min' => 0,   'max' => 84,  'score' => 3],
                        ['min' => 85,  'max' => 89,  'score' => 2],
                        ['min' => 90,  'max' => 94,  'score' => 1],
                        ['min' => 95,  'max' => 100, 'score' => 0],
                    ],
                    // 🌟 เพิ่ม Pain Score (ตามตาราง)
                    'pain' => [
                        ['min' => 0,   'max' => 0,   'score' => 0],
                        ['min' => 1,   'max' => 3,   'score' => 1],
                        ['min' => 4,   'max' => 6,   'score' => 2],
                        ['min' => 7,   'max' => 10,  'score' => 3],
                    ],
                    'avpu' => [
                        ['value' => 'A', 'score' => 0], // Alert
                        ['value' => 'P', 'score' => 1], // 🌟 Response to pain (สลับเป็น 1 ตามตาราง KSVR)
                        ['value' => 'V', 'score' => 2], // 🌟 Response to voice (สลับเป็น 2 ตามตาราง KSVR)
                        ['value' => 'U', 'score' => 3], // Unresponsive
                    ]
                ]
            ]
        );
        
        // 🌟 2. นำข้อมูลอาการฉุกเฉินทั้งหมดเข้า Database
        $symptoms = [
            // กลุ่ม MI (กล้ามเนื้อหัวใจขาดเลือด)
            ['category' => 'MI', 'code' => 'mi_chest_pain', 'name' => 'เจ็บแน่นหน้าอกเหมือนโดนกดทับ (MI)'],
            ['category' => 'MI', 'code' => 'mi_dyspnea', 'name' => 'เหนื่อย หายใจไม่อิ่ม ใจสั่น (MI)'],
            ['category' => 'MI', 'code' => 'mi_referred_pain', 'name' => 'เจ็บร้าวไปไหล่ซ้าย กราม แขน (MI)'],

            // กลุ่ม Stroke (หลอดเลือดสมอง)
            ['category' => 'Stroke', 'code' => 'stroke_dizzy', 'name' => 'เวียนศีรษะบ้านหมุน เดินเซ (Stroke)'],
            ['category' => 'Stroke', 'code' => 'stroke_vision', 'name' => 'ตามัว มองเห็นภาพซ้อน (Stroke)'],
            ['category' => 'Stroke', 'code' => 'stroke_facial_weakness', 'name' => 'หน้าเบี้ยว ปากเบี้ยวครึ่งซีก (Stroke)'],
            ['category' => 'Stroke', 'code' => 'stroke_arm_leg_weakness', 'name' => 'แขนขาอ่อนแรงครึ่งซีก (Stroke)'],
            ['category' => 'Stroke', 'code' => 'stroke_speech', 'name' => 'พูดไม่ชัด สื่อสารไม่เข้าใจ (Stroke)'],

            // กลุ่ม Heat Injury (โรคลมร้อน)
            ['category' => 'Heat Injury', 'code' => 'heat_confusion', 'name' => 'ซึมสับสน ชัก หมดสติ (Heat Injury)'],
            ['category' => 'Heat Injury', 'code' => 'heat_fatigue', 'name' => 'อ่อนเพลียไม่มีแรง เหงื่อออกมาก (Heat Injury)'],
            ['category' => 'Heat Injury', 'code' => 'heat_pale', 'name' => 'หน้าซีดหน้ามืด เป็นลม (Heat Injury)'],
            ['category' => 'Heat Injury', 'code' => 'heat_cramp', 'name' => 'ตะคริว หายใจหอบเร็วลึก (Heat Injury)'],
            ['category' => 'Heat Injury', 'code' => 'heat_numbness', 'name' => 'ชาปลายมือ/เท้า (Heat Injury)'],
            ['category' => 'Heat Injury', 'code' => 'heat_urine', 'name' => 'ปัสสาวะสีเข้ม (Heat Injury)'],

            // กลุ่ม Anaphylaxis (แพ้รุนแรง)
            ['category' => 'Anaphylaxis', 'code' => 'ana_rash', 'name' => 'ผื่นแดง ลมพิษ ปากบวม (Anaphylaxis)'],
            ['category' => 'Anaphylaxis', 'code' => 'ana_resp', 'name' => 'หายใจไม่สะดวก หอบเหนื่อย (Anaphylaxis)'],
            ['category' => 'Anaphylaxis', 'code' => 'ana_sys', 'name' => 'กล้ามเนื้ออ่อนแรง เป็นลม (Anaphylaxis)'],
            ['category' => 'Anaphylaxis', 'code' => 'ana_gi', 'name' => 'ปวดท้อง คลื่นไส้ อาเจียน (Anaphylaxis)'],
            ['category' => 'Anaphylaxis', 'code' => 'ana_chest', 'name' => 'แน่นหน้าอก (Anaphylaxis)'],

            // กลุ่มอื่นๆ
            ['category' => 'Appendicitis', 'code' => 'appendicitis', 'name' => 'ปวดท้อง RLQ (Appendicitis)'],
            ['category' => 'Sepsis', 'code' => 'sepsis_drowsy', 'name' => 'ซึม (Sepsis)'],
            ['category' => 'Sepsis', 'code' => 'sepsis_tachypnea', 'name' => 'หายใจหอบ (Sepsis)'],
            ['category' => 'Trauma', 'code' => 'trauma', 'name' => 'เกิดอุบัติเหตุภายใน 24 ชม. (Trauma)'],
            
        ];

        // 🌟 ใช้ updateOrCreate เพื่อป้องกัน Error กรณีข้อมูลซ้ำ
        foreach ($symptoms as $sym) {
            EmergencySymptom::updateOrCreate(
                ['code' => $sym['code']], // ค้นหาจาก Code
                [
                    'category' => $sym['category'],
                    'name' => $sym['name'],
                    'is_active' => true
                ]
            );
        }
    }
}