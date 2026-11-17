<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MewsTemplate;
use App\Models\EmergencySymptom;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLog; // 🌟 1. นำเข้า Service สำหรับบันทึก Log

class MewsSettingController extends Controller
{
    // =======================================================
    // 🟢 โซนที่ 1: การจัดการ อาการฉุกเฉินเฉพาะโรค (Symptoms)
    // =======================================================

    public function symptomsIndex()
    {
        $symptoms = EmergencySymptom::orderBy('category')->orderBy('name')->get();
        return view('backend.settings.symptoms', compact('symptoms'));
    }

    public function storeSymptom(Request $request)
    {
        $request->validate([
            'category' => 'required|string|max:100',
            'code'     => 'required|string|max:100|unique:emergency_symptoms,code',
            'name'     => 'required|string|max:255',
        ], [
            'code.unique' => 'รหัสอ้างอิงนี้มีในระบบแล้ว กรุณาใช้รหัสอื่น'
        ]);

        $symptom = EmergencySymptom::create([
            'category'  => $request->category,
            'code'      => strtolower(trim($request->code)), 
            'name'      => $request->name,
            'is_active' => true,
        ]);

        // 📝 LOG: บันทึกการสร้างอาการฉุกเฉิน
        AuditLog::record('create_emergency_symptom', null, [
            'symptom_id' => $symptom->id,
            'category'   => $symptom->category,
            'name'       => $symptom->name
        ]);

        return back()->with('success', 'เพิ่มอาการฉุกเฉินใหม่เรียบร้อยแล้ว');
    }

    public function updateSymptom(Request $request, $id)
    {
        $symptom = EmergencySymptom::findOrFail($id);
        
        if ($request->has('toggle_active')) {
            $symptom->update(['is_active' => !$symptom->is_active]);
            
            // 📝 LOG: บันทึกการเปิด/ปิดสถานะ
            AuditLog::record('toggle_emergency_symptom', null, [
                'symptom_id' => $symptom->id,
                'name'       => $symptom->name,
                'new_status' => $symptom->is_active ? 'active' : 'inactive'
            ]);

            return back()->with('success', 'อัปเดตสถานะการใช้งานเรียบร้อยแล้ว');
        }

        $request->validate([
            'category' => 'required|string|max:100',
            'name'     => 'required|string|max:255',
        ]);

        $symptom->update($request->only(['category', 'name']));

        // 📝 LOG: บันทึกการแก้ไขข้อมูล
        AuditLog::record('update_emergency_symptom', null, [
            'symptom_id' => $symptom->id,
            'new_name'   => $symptom->name
        ]);

        return back()->with('success', 'แก้ไขข้อมูลอาการฉุกเฉินเรียบร้อยแล้ว');
    }

    public function destroySymptom($id)
    {
        $symptom = EmergencySymptom::findOrFail($id);
        
        // 📝 LOG: บันทึกก่อนลบข้อมูล
        AuditLog::record('delete_emergency_symptom', null, [
            'symptom_id'   => $symptom->id,
            'deleted_name' => $symptom->name
        ]);
        
        $symptom->delete();
        
        return back()->with('success', 'ลบอาการฉุกเฉินออกจากระบบเรียบร้อยแล้ว');
    }


    // =======================================================
    // 🔵 โซนที่ 2: การจัดการ รูปแบบสูตรคำนวณ (MEWS Templates)
    // =======================================================

    public function mewsIndex()
    {
        $templates = MewsTemplate::orderBy('is_default', 'desc')->get();
        return view('backend.settings.mews', compact('templates'));
    }

    public function storeMewsTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $defaultRules = [
            'sbp' => [], 'hr' => [], 'rr' => [], 'temp' => [], 'avpu' => []
        ];

        $template = MewsTemplate::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_default' => false,
            'is_active' => false,
            'rules' => $defaultRules
        ]);

        // 📝 LOG: บันทึกการสร้างโครงสร้างสูตรใหม่
        AuditLog::record('create_mews_template', null, [
            'template_id'   => $template->id,
            'template_name' => $template->name
        ]);

        return back()->with('success', 'สร้างรูปแบบสูตรคำนวณใหม่เรียบร้อยแล้ว กรุณากด "กำหนดเกณฑ์" เพื่อตั้งค่าตัวเลข');
    }

    public function mewsBuilder($id)
    {
        $template = MewsTemplate::findOrFail($id);
        return view('backend.settings.mews_builder', compact('template'));
    }

    public function updateMewsBuilder(Request $request, $id)
    {
        $request->validate([
            'rules' => 'required|array', 
        ]);

        $template = MewsTemplate::findOrFail($id);

        if ($template->is_default) {
            return redirect()->route('backend.settings.mews.index')
                             ->withErrors('ไม่อนุญาตให้แก้ไข "ค่ามาตรฐาน" ของระบบครับ');
        }

        $rules = $request->rules;
        $formattedRules = [];

        $numericParams = ['sbp', 'hr', 'rr', 'temp', 'o2sat', 'pain']; 
        
        foreach ($numericParams as $param) {
            $formattedRules[$param] = [];
            if (isset($rules[$param])) {
                foreach ($rules[$param] as $rule) {
                    $formattedRules[$param][] = [
                        'min' => (float) $rule['min'],
                        'max' => (float) $rule['max'],
                        'score' => (int) $rule['score'],
                    ];
                }
            }
        }

        $formattedRules['avpu'] = [];
        if (isset($rules['avpu'])) {
            foreach ($rules['avpu'] as $rule) {
                $formattedRules['avpu'][] = [
                    'value' => strtoupper($rule['value']),
                    'score' => (int) $rule['score'],
                ];
            }
        }

        $template->update(['rules' => $formattedRules]);

        // 📝 LOG: บันทึกการแก้ไขเกณฑ์ตัวเลขสูตร (สำคัญมาก!)
        AuditLog::record('update_mews_rules_criteria', null, [
            'template_id'   => $template->id,
            'template_name' => $template->name
        ]);

        return redirect()->route('backend.settings.mews.index')
                         ->with('success', "อัปเดตเกณฑ์ของ {$template->name} เรียบร้อยแล้ว");
    }

    public function toggleMewsTemplate($id)
    {
        $template = MewsTemplate::findOrFail($id);
        
        if (!$template->is_active) {
            MewsTemplate::where('id', '!=', $id)->update(['is_active' => false]);
            $template->update(['is_active' => true]);
            $message = 'เปิดใช้งานสูตรนี้ และปิดสูตรอื่นๆ อัตโนมัติเรียบร้อยแล้ว';
            $status = 'activated';
        } else {
            $template->update(['is_active' => false]);
            $message = 'ปิดการใช้งานสูตรนี้เรียบร้อยแล้ว';
            $status = 'deactivated';
        }

        // 📝 LOG: บันทึกการเปิด/ปิด สลับสูตร
        AuditLog::record('toggle_mews_template', null, [
            'template_id'   => $template->id,
            'template_name' => $template->name,
            'new_status'    => $status
        ]);

        return back()->with('success', $message);
    }

    public function updateMewsTemplateInfo(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $template = MewsTemplate::findOrFail($id);

        if ($template->is_default) {
            return back()->withErrors('ไม่อนุญาตให้แก้ไขข้อมูลของ "ค่ามาตรฐาน" เพื่อป้องกันความสับสนครับ');
        }

        $template->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // 📝 LOG: บันทึกการแก้ชื่อสูตร
        AuditLog::record('update_mews_template_info', null, [
            'template_id'   => $template->id,
            'new_name'      => $template->name
        ]);

        return back()->with('success', 'อัปเดตชื่อและคำอธิบายเรียบร้อยแล้ว');
    }
    
    public function duplicateMewsTemplate($id)
    {
        $original = MewsTemplate::findOrFail($id);
        
        $newTemplate = $original->replicate();
        $newTemplate->name = $original->name . ' (สำเนา)';
        $newTemplate->is_default = false; 
        $newTemplate->is_active = false;  
        $newTemplate->save();

        // 📝 LOG: บันทึกการคัดลอกสูตร
        AuditLog::record('duplicate_mews_template', null, [
            'original_id'   => $original->id,
            'new_id'        => $newTemplate->id,
            'new_name'      => $newTemplate->name
        ]);

        return back()->with('success', 'คัดลอกสูตรคำนวณเรียบร้อยแล้ว สามารถกด "กำหนดเกณฑ์" เพื่อแก้ไขได้เลยครับ');
    }

    public function destroyMewsTemplate($id)
    {
        $template = MewsTemplate::findOrFail($id);

        if ($template->is_default) {
            return back()->withErrors('ไม่สามารถลบ "ค่ามาตรฐาน" ของระบบได้ครับ');
        }

        if ($template->is_active) {
            return back()->withErrors('ไม่สามารถลบสูตรที่กำลัง "ใช้งานอยู่" ได้ กรุณาสลับไปใช้สูตรอื่นก่อนครับ');
        }

        // 📝 LOG: บันทึกก่อนลบสูตร
        AuditLog::record('delete_mews_template', null, [
            'template_id'   => $template->id,
            'deleted_name'  => $template->name
        ]);

        $template->delete();

        return back()->with('success', 'ลบรูปแบบสูตรคำนวณออกจากระบบเรียบร้อยแล้ว');
    }
}