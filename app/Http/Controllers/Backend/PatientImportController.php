<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PatientsImport;
use App\Models\MilitaryUnit;
use App\Services\AuditLog;

class PatientImportController extends Controller
{
    private function canManageAllUnits($user): bool
    {
        $roles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        if (in_array('executive', $roles) && !in_array('admin', $roles)) {
            abort(403, 'สิทธิ์ผู้บริหารไม่สามารถนำเข้าข้อมูลได้ครับ');
        }
        return in_array('admin', $roles) 
            || in_array('registration', $roles) 
            || in_array('er_nurse', $roles) 
            || in_array('opd_nurse', $roles);
    }

    public function index()
    {
        $user = auth()->user();
        $canAll = $this->canManageAllUnits($user);

        $unitsQuery = MilitaryUnit::where('is_active', true)->orderBy('name');
        if (!$canAll) {
            $unitsQuery->where('id', $user->military_unit_id);
        }
        $units = $unitsQuery->get();

        if (!$canAll && !empty($user->intake_batch)) {
            $batches = [$user->intake_batch];
        } else {
            $currentYearBE = date('Y') + 544;
            $batches = [];
            for ($i = 0; $i < 5; $i++) {
                $beYear = $currentYearBE - $i;
                $shortYear = substr($beYear, -2);
                $batches[] = "1/{$shortYear}";
                $batches[] = "2/{$shortYear}";
            }
        }

        return view('backend.patients.import', compact('units', 'batches'));
    }

    public function import(Request $request)
    {
        $user = auth()->user();
        $canAll = $this->canManageAllUnits($user);

        // 🛡️ หากเป็นเสนารักษ์ บังคับล็อกหน่วยฝึกและผลัดตามโปรไฟล์จริง ป้องกันการปลอมแปลงค่าจากหน้าเว็บ
        if (!$canAll) {
            $request->merge([
                'military_unit_id' => $user->military_unit_id,
                'intake_batch'     => $user->intake_batch ?: $request->intake_batch,
            ]);
        }

        $request->validate([
            'military_unit_id' => 'required|exists:military_units,id',
            'intake_batch'     => 'required|string|max:20',
            'excel_file'       => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'military_unit_id.required' => 'กรุณาเลือกสังกัดหน่วยทหารก่อนอัปโหลด',
            'intake_batch.required'     => 'กรุณาเลือกผลัดทหารก่อนอัปโหลด',
        ]);

        try {
            Excel::import(new PatientsImport($request->military_unit_id, $request->intake_batch), $request->file('excel_file'));
            
            $file = $request->file('excel_file');
            AuditLog::record('import_patients_excel', null, [
                'unit_id'         => $request->military_unit_id,
                'intake_batch'    => $request->intake_batch,
                'file_name'       => $file->getClientOriginalName(),
                'file_size_bytes' => $file->getSize()
            ]);

            return redirect()->route('backend.patients.index')
                             ->with('success', 'นำเข้าข้อมูลพลทหารเข้าสู่ระบบสำเร็จเรียบร้อยแล้วครับ!');
        } catch (\Exception $e) {
            Log::error('Excel Import Error: ' . $e->getMessage());
            return back()->with('error', 'ไม่สามารถนำเข้าไฟล์ได้ กรุณาตรวจสอบรูปแบบหัวคอลัมน์ในไฟล์ Excel ให้ตรงตามเทมเพลตครับ');
        }
    }
}