<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\MilitaryUnit;
use App\Models\MilitaryDischarge;
use Illuminate\Http\Request;

class MilitaryDischargeController extends Controller
{
    // โครงสร้างเริ่มต้นของตารางเอกสาร 7 ช่อง x 3 ขั้นตอน
    public static function defaultMatrix(): array
    {
        $emptyRow = [
            'medical_history'    => '',
            'old_hospital_cert'  => '-',
            'camp_hospital_cert' => '',
            'unit_memo'          => '',
            'military_profile'   => '',
            'photos_6'           => '',
            'form_p25'           => '',
            'remark'             => '',
        ];

        return [
            'unit_received' => $emptyRow,
            'unit_sent'     => $emptyRow,
            'med_sent_spn'  => $emptyRow,
        ];
    }

    public function index(Request $request)
    {
        $batches = Patient::whereNotNull('intake_batch')
            ->where('intake_batch', '!=', '')
            ->distinct()
            ->pluck('intake_batch')
            ->sortDesc();

        $defaultBatch = $batches->first() ?? '1/69';
        $selectedBatch = $request->input('intake_batch', $defaultBatch);
        $statusFilter = $request->input('status', '');

        $query = MilitaryDischarge::with(['patient.militaryUnit', 'militaryUnit'])
            ->orderBy('created_at', 'desc');

        if ($selectedBatch !== 'all') {
            $query->where('intake_batch', $selectedBatch);
        }
        if (!empty($statusFilter)) {
            $query->where('process_status', $statusFilter);
        }

        $discharges = $query->get();

        // คำนวณสถิติ KPI
        $allInBatchQuery = MilitaryDischarge::query();
        if ($selectedBatch !== 'all') {
            $allInBatchQuery->where('intake_batch', $selectedBatch);
        }
        $allInBatch = $allInBatchQuery->get();

        $summary = [
            'total'     => $allInBatch->count(),
            'preparing' => $allInBatch->where('process_status', 'preparing')->count(),
            'unit_sent' => $allInBatch->where('process_status', 'unit_sent')->count(),
            'completed' => $allInBatch->where('process_status', 'completed')->count(),
        ];

        // ดึงรายชื่อพลทหารในผลัดสำหรับค้นหาเพื่อเพิ่มเคสนำปลด
        $patientsQuery = Patient::with('militaryUnit')->orderBy('first_name');
        if ($selectedBatch !== 'all') {
            $patientsQuery->where('intake_batch', $selectedBatch);
        }
        $batchPatients = $patientsQuery->get([
            'id', 'cid', 'hn', 'rank', 'first_name', 'last_name', 
            'military_unit_id', 'battalion', 'intake_batch',
            'chronic_disease_status', 'chronic_disease_detail'
        ]);

        // รายชื่อพลทหารที่มีโรคประจำตัวแต่ยังไม่ได้อยู่ในทะเบียนนำปลด (เพื่อแนะนำด่วน)
        $existingPatientIds = $allInBatch->pluck('patient_id')->toArray();
        $suggestedChronicPatients = $batchPatients->filter(function ($pt) use ($existingPatientIds) {
            return in_array($pt->chronic_disease_status, ['yes', 'other']) && !in_array($pt->id, $existingPatientIds);
        })->values();

        return view('backend.military_discharges.index', compact(
            'batches', 'selectedBatch', 'statusFilter',
            'discharges', 'summary', 'batchPatients', 'suggestedChronicPatients'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id'           => 'required|exists:patients,id',
            'diagnosis_text'       => 'required|string|max:255',
            'hospital_coordinator' => 'required|string|max:255',
            'unit_coordinator'     => 'nullable|string|max:255',
        ], [
            'patient_id.required'           => 'กรุณาค้นหาและคลิกเลือกรายชื่อพลทหารที่ต้องการนำปลด',
            'diagnosis_text.required'       => 'กรุณาระบุโรควินิจฉัยที่ใช้ในการนำปลดทหาร',
            'hospital_coordinator.required' => 'กรุณาระบุชื่อและเบอร์โทรผู้ประสานงาน รพ.ค่ายกฤษณ์สีวะรา',
        ]);

        $patient = Patient::findOrFail($data['patient_id']);

        $existing = MilitaryDischarge::where('patient_id', $patient->id)->first();
        if ($existing) {
            return back()->with('error', "พลฯ {$patient->first_name} มีรายชื่ออยู่ในทะเบียนเอกสารนำปลดทหารแล้วครับ");
        }

        MilitaryDischarge::create([
            'patient_id'           => $patient->id,
            'military_unit_id'     => $patient->military_unit_id,
            'intake_batch'         => $patient->intake_batch ?: '1/69',
            'diagnosis_text'       => $data['diagnosis_text'],
            'process_status'       => 'preparing',
            'documents_matrix'     => self::defaultMatrix(),
            'hospital_coordinator' => $data['hospital_coordinator'] ?: 'ร.อ.หญิง จริยา ลินโพธิ์สาน โทร. 0887976378',
            'unit_coordinator'     => $data['unit_coordinator'] ?? null,
            'recorded_by'          => auth()->id(),
        ]);

        return back()->with('success', "ขึ้นทะเบียนเอกสารนำปลดทหารของ {$patient->rank}{$patient->first_name} เรียบร้อยแล้ว");
    }

    public function update(Request $request, $id)
    {
        $discharge = MilitaryDischarge::findOrFail($id);

        $request->validate([
            'diagnosis_text'       => 'required|string|max:255',
            'process_status'       => 'required|in:preparing,unit_sent,completed',
            'hospital_coordinator' => 'nullable|string|max:255',
            'unit_coordinator'     => 'nullable|string|max:255',
            'matrix'               => 'nullable|array',
        ]);

        $discharge->update([
            'diagnosis_text'       => $request->diagnosis_text,
            'process_status'       => $request->process_status,
            'documents_matrix'     => $request->input('matrix', self::defaultMatrix()),
            'hospital_coordinator' => $request->hospital_coordinator,
            'unit_coordinator'     => $request->unit_coordinator,
        ]);

        return back()->with('success', 'บันทึกการอัปเดตตารางเอกสารนำปลดทหารเรียบร้อยแล้ว!');
    }

    public function destroy($id)
    {
        $discharge = MilitaryDischarge::findOrFail($id);
        $discharge->delete();

        return back()->with('success', 'ลบรายการออกจากทะเบียนนำปลดทหารเรียบร้อยแล้ว');
    }
}