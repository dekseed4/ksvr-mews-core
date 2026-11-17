<?php

namespace App\Services;

use App\Models\MewsTemplate;

class MewsCalculatorService
{
    public static function calculate($vitals, $templateId = null)
    {
        $template = $templateId 
            ? MewsTemplate::findOrFail($templateId) 
            : MewsTemplate::where('is_active', true)->where('is_default', true)->first();

        $rules = $template->rules;
        $totalScore = 0;
        $hasScoreThree = false; // 🌟 ตัวดักจับ: ถ้ามีค่าไหนทะลุ 3 คะแนน จะถือว่าวิกฤตทันที

        $numericParams = ['sbp', 'hr', 'rr', 'temp', 'o2sat', 'pain'];
        foreach ($numericParams as $param) {
            if (isset($vitals[$param]) && $vitals[$param] !== null) {
                $val = (float) $vitals[$param];
                foreach ($rules[$param] as $condition) {
                    if ($val >= $condition['min'] && $val <= $condition['max']) {
                        $score = $condition['score'];
                        $totalScore += $score;
                        if ($score == 3) $hasScoreThree = true; // ดักจับคะแนนวิกฤต
                        break;
                    }
                }
            }
        }

        if (isset($vitals['avpu']) && $vitals['avpu'] !== null) {
            foreach ($rules['avpu'] as $condition) {
                if (strtoupper($vitals['avpu']) === strtoupper($condition['value'])) {
                    $score = $condition['score'];
                    $totalScore += $score;
                    if ($score == 3) $hasScoreThree = true;
                    break;
                }
            }
        }

        // 🌟 ประเมินระดับความเสี่ยงอัตโนมัติ (Risk Level)
        $riskLevel = 'normal'; // 0-2 คะแนน
        if ($totalScore >= 5 || $hasScoreThree) {
            $riskLevel = 'emergency'; // แดง (วิกฤต)
        } elseif ($totalScore >= 3) {
            $riskLevel = 'observe';   // เหลือง (เฝ้าระวัง)
        }

        return [
            'score' => $totalScore,
            'risk_level' => $riskLevel,
            'template_used' => $template->name,
            'template_id' => $template->id
        ];
    }
}