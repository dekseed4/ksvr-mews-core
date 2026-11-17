<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MilitaryUnit;
use Illuminate\Http\Request;
use App\Services\AuditLog;

class MilitaryUnitController extends Controller
{
    /**
     * 1. หน้าแสดงรายชื่อหน่วยทหารทั้งหมด (Index)
     */
    public function index(Request $request) // 🌟 อย่าลืมเพิ่ม Request $request ตรงนี้นะครับ
    {
        // 🌟 เปลี่ยนจาก get() เป็น paginate(20) เพื่อความลื่นไหลของ UI และลดภาระ Database
        $militaryUnits = MilitaryUnit::orderBy('battalion')
                                     ->orderBy('name')
                                     ->paginate(20)
                                     ->appends($request->query());

        return view('backend.military_units.index', compact('militaryUnits'));
    }

    /**
     * 2. หน้าฟอร์มสำหรับเพิ่มหน่วยทหารใหม่ (Create)
     */
    public function create()
    {
        return view('backend.military_units.create');
    }

    /**
     * 3. ประมวลผลบันทึกข้อมูลหน่วยทหารใหม่ลงฐานข้อมูล (Store)
     */
    public function store(Request $request)
    {
        // 🌟 กรองข้อมูล: ป้องกันการใส่ค่าว่างในช่องบังคับ หรือใส่ข้อความยาวเกินไป
        $request->validate([
            'name'       => 'required|string|max:255',
            'short_name' => 'nullable|string|max:100',
            'battalion'  => 'nullable|string|max:255',
            'is_active'  => 'required|boolean',
        ], [
            'name.required' => 'กรุณากรอกชื่อหน่วยทหาร (เต็ม)',
        ]);

        try {
            // บันทึกข้อมูล
            $unit =MilitaryUnit::create([
                'name'       => $request->name,
                'short_name' => $request->short_name,
                'battalion'  => $request->battalion,
                'is_active'  => $request->is_active,
            ]);

            // 📝 LOG: บันทึกว่ามีการเพิ่มข้อมูลหน่วยทหาร/กองร้อยใหม่ในระบบ
            AuditLog::record('create_military_unit', $unit, [ // สมมติว่าตัวแปรที่เพิ่งสร้างชื่อ $unit
                'unit_name' => $unit->name,
                'short_name' => $unit->short_name,
                'battalion' => $unit->battalion
            ]);
            return redirect()->route('backend.military-units.index')
                             ->with('success', 'เพิ่มข้อมูลหน่วยทหารใหม่เรียบร้อยแล้วครับ!');
                             
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Create Military Unit Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง');
        }
    }

    /**
     * 4. หน้าฟอร์มแก้ไขข้อมูลหน่วยทหาร (Edit)
     */
    public function edit($id)
    {
        // ค้นหาข้อมูล ถ้าไม่เจอระบบจะโยนหน้า 404 ให้เลย (findOrFail)
        $militaryUnit = MilitaryUnit::findOrFail($id);
        
        return view('backend.military_units.edit', compact('militaryUnit'));
    }

    /**
     * 5. ประมวลผลการอัปเดตข้อมูล (Update)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'short_name' => 'nullable|string|max:100',
            'battalion'  => 'nullable|string|max:255',
            'is_active'  => 'required|boolean',
        ], [
            'name.required' => 'กรุณากรอกชื่อหน่วยทหาร (เต็ม)',
        ]);

        try {
            $militaryUnit = MilitaryUnit::findOrFail($id);
            
            $militaryUnit->update([
                'name'       => $request->name,
                'short_name' => $request->short_name,
                'battalion'  => $request->battalion,
                'is_active'  => $request->is_active,
            ]);

            // 📝 LOG: บันทึกว่ามีการแก้ไขข้อมูลพื้นฐานของหน่วยทหาร
            AuditLog::record('update_military_unit', $militaryUnit, [
                'updated_name' => $militaryUnit->name,
                'is_active' => $militaryUnit->is_active // แอบดูว่ามีการเปิด-ปิดการใช้งาน (Active/Inactive) หรือไม่
            ]);

            return redirect()->route('backend.military-units.index')
                             ->with('success', 'แก้ไขข้อมูลหน่วยทหารเรียบร้อยแล้วครับ!');
                             
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Update Military Unit Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล');
        }
    }

    /**
     * 6. ลบข้อมูลหน่วยทหาร (Destroy)
     */
    public function destroy($id)
    {
        try {
            $militaryUnit = MilitaryUnit::findOrFail($id);

            // 🌟 [System Admin Insight]: ตรวจสอบก่อนลบ!
            // ป้องกันฐานข้อมูลพัง (Foreign Key Constraint) หากหน่วยนี้ยังมีเสนารักษ์หรือคนไข้สังกัดอยู่
            if ($militaryUnit->users()->exists() || $militaryUnit->patients()->exists()) {
                return back()->with('error', 'ไม่อนุญาตให้ลบ! เนื่องจากหน่วยทหารนี้มีข้อมูล "บุคลากร" หรือ "ผู้ป่วย" ผูกอยู่ แนะนำให้ใช้วิธี "ปิดการใช้งาน" แทนครับ');
            }

            // 📝 ดึงชื่อหน่วยมาเก็บไว้ก่อนข้อมูลจะถูกลบทิ้ง
            $deletedUnitName = $militaryUnit->name;
            $deletedUnitId = $militaryUnit->id;

            $militaryUnit->delete();

            // 📝 LOG: บันทึกการลบหน่วยทหารออกจากระบบ
            AuditLog::record('delete_military_unit', null, [
                'deleted_unit_id' => $deletedUnitId,
                'deleted_unit_name' => $deletedUnitName
            ]);

            return redirect()->route('backend.military-units.index')
                             ->with('success', 'ลบข้อมูลหน่วยทหารสำเร็จแล้ว!');
                             
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Delete Military Unit Error: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการลบข้อมูล');
        }
    }
}