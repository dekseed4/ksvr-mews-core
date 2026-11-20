<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\AuditLog;

class BackendUserController extends Controller
{
    public function index(Request $request)
    {
        // 1. รับค่า Tab จาก URL (ค่าเริ่มต้นเป็น 'all')
        $activeTab = $request->query('role', 'all');

        // 2. เริ่มต้น Query พร้อมดึงข้อมูลหน่วยทหารล่วงหน้า (Eager Loading ป้องกัน N+1 Query)
        $query = User::with('militaryUnit')->where('id', '!=', '1');

        // 3. กรองข้อมูลตามสิทธิ์
        if ($activeTab !== 'all') {
            $query->where(function($q) use ($activeTab) {
                $q->whereJsonContains('roles', $activeTab)
                  ->orWhere('roles', 'LIKE', '%"'.$activeTab.'"%')
                  ->orWhere('roles', $activeTab);
            });
        }

        // 4. ดึงข้อมูลพร้อมแบ่งหน้า (Pagination)
        $users = $query->orderBy('created_at', 'desc')
                       ->paginate(20)
                       ->appends($request->query());

        return view('backend.users.index', compact('users', 'activeTab'));
    }
    
    public function create()
    {
        $units = \App\Models\MilitaryUnit::where('is_active', true)->orderBy('short_name')->get();
        $batches = $this->getIntakeBatches();

        return view('backend.users.create', compact('units', 'batches'));
    }

    public function store(Request $request)
    {
        // คลีนขีดและช่องว่างออกจากเบอร์โทรศัพท์ก่อนตรวจสอบ (เผื่อพิมพ์ 081-234-5678)
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => str_replace(['-', ' '], '', trim($request->phone))
            ]);
        }

        // เช็คก่อนว่าผู้ใช้งานคนนี้ถูกติ๊กสิทธิ์ "เสนารักษ์ (medic)" มาหรือไม่
        $isMedic = is_array($request->roles) && in_array('medic', $request->roles);

        $request->validate([
            'rank'     => 'required|string|max:50',
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'phone'    => ['required', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone'],
            'roles'    => 'required|array|min:1',
            // 🌟 เพิ่ม medical_staff เข้าไปในรายการสิทธิ์ที่ระบบอนุญาต
            'roles.*'  => 'in:admin,executive,er_nurse,opd_nurse,medic,registration,medical_staff,screening',
            
            // บังคับกรอกหน่วยฝึกและผลัดที่ "เฉพาะเมื่อเป็นเสนารักษ์เท่านั้น"
            'military_unit_id' => in_array('medic', $request->roles ?? []) ? 'required|exists:military_units,id' : 'nullable',
            'intake_batch'     => in_array('medic', $request->roles ?? []) ? 'required|string|max:20' : 'nullable',
        ], [
            'military_unit_id.required' => 'สำหรับเสนารักษ์ กรุณาระบุสังกัด/หน่วยฝึกด้วยครับ',
            'military_unit_id.exists'   => 'ไม่พบข้อมูลสังกัดนี้ในระบบ กรุณาเลือกใหม่',
            'intake_batch.required'     => 'สำหรับเสนารักษ์ กรุณาระบุผลัดที่ประจำการด้วยครับ',
            'username.unique'           => 'ชื่อผู้ใช้งาน (Username) นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น',
            'email.unique'              => 'อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น',
            'phone.required'            => 'กรุณากรอกเบอร์โทรศัพท์',
            'phone.regex'               => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (กรอกเฉพาะตัวเลข 9-10 หลัก)',
            'phone.unique'              => 'เบอร์โทรศัพท์นี้ถูกใช้งานในระบบแล้ว กรุณาใช้เบอร์อื่น',
            'roles.required'            => 'กรุณาเลือกสิทธิ์การใช้งานอย่างน้อย 1 สิทธิ์',
            'roles.*.in'                => 'พบข้อมูลสิทธิ์การใช้งานที่ไม่ถูกต้องในระบบ',
        ]);

        // ถ้าไม่ใช่เสนารักษ์ บังคับล้างค่าหน่วยฝึกและผลัดที่เป็น null เสมอ
        $unitId = $isMedic ? $request->military_unit_id : null;
        $batch  = $isMedic ? $request->intake_batch : null;

        // สุ่มรหัสผ่านชั่วคราว 8 ตัวอักษร
        $tempPassword = Str::random(8); 

        $newUser = new User();
        $newUser->forceFill([
            'rank'                 => $request->rank,
            'name'                 => $request->name,
            'username'             => trim($request->username),
            'email'                => trim($request->email),
            'phone'                => $request->phone,
            'roles'                => $request->roles,
            'military_unit_id'     => $unitId,
            'intake_batch'         => $batch,
            'password'             => Hash::make($tempPassword),
            'must_change_password' => true,
        ])->save();

        AuditLog::record('create_user', $newUser, [
            'username'       => $newUser->username,
            'phone'          => $newUser->phone,
            'assigned_roles' => $newUser->roles,
            'unit_id'        => $newUser->military_unit_id,
            'intake_batch'   => $newUser->intake_batch,
        ]);

        return redirect()->route('backend.users.index')
                         ->with('success', 'สร้างผู้ใช้งานสำเร็จ!')
                         ->with('temp_password', $tempPassword) 
                         ->with('temp_username', $newUser->username);
    }

    public function resetPassword($id)
    {
        $user = User::findOrFail($id);

        if ($user->id == 1 && auth()->id() != 1) {
            return redirect()->route('backend.users.index')
                             ->with('error', 'ไม่อนุญาตให้รีเซ็ตรหัสผ่านของ Super Administrator ครับ');
        }

        $newTempPassword = Str::random(8);

        $user->update([
            'password'             => Hash::make($newTempPassword),
            'must_change_password' => true,
        ]);

        AuditLog::record('reset_password', $user, [
            'target_username' => $user->username
        ]);

        return redirect()->route('backend.users.index')
                         ->with('success', 'รีเซ็ตรหัสผ่านชั่วคราวให้ '.$user->name.' สำเร็จ!')
                         ->with('temp_password', $newTempPassword)
                         ->with('temp_username', $user->username);
    }

    public function edit(User $user)
    {
        $units = \App\Models\MilitaryUnit::where('is_active', true)->orderBy('short_name')->get();
        $batches = $this->getIntakeBatches();

        return view('backend.users.edit', compact('user', 'units', 'batches'));
    }

    public function update(Request $request, User $user)
    {
        // ป้องกันคนพยายามแก้สิทธิ์ Super Admin (ID = 1)
        if ($user->id == 1 && auth()->id() != 1) {
            return redirect()->route('backend.users.index')
                             ->with('error', 'ระบบป้องกันความปลอดภัย: ไม่อนุญาตให้แก้ไขข้อมูลของ Super Administrator ครับ');
        }

        // คลีนขีดและช่องว่างออกจากเบอร์โทรศัพท์ก่อนตรวจสอบ
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => str_replace(['-', ' '], '', trim($request->phone))
            ]);
        }

        $isMedic = is_array($request->roles) && in_array('medic', $request->roles);

        $request->validate([
            'rank'     => 'required|string|max:50',
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email'    => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone'    => ['required', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone,' . $user->id],
            'roles'    => 'required|array|min:1',
            // 🌟 เพิ่ม medical_staff และ screening เข้าไปในรายการสิทธิ์ที่ระบบอนุญาต
            'roles.*'  => 'in:admin,executive,er_nurse,opd_nurse,medic,registration,medical_staff,screening',
            
            'military_unit_id' => $isMedic ? 'required|exists:military_units,id' : 'nullable',
            'intake_batch'     => $isMedic ? 'required|string|max:20' : 'nullable',
        ], [
            'military_unit_id.required' => 'สำหรับเสนารักษ์ กรุณาระบุสังกัด/หน่วยฝึกด้วยครับ',
            'military_unit_id.exists'   => 'ไม่พบข้อมูลสังกัดนี้ในระบบ กรุณาเลือกใหม่',
            'intake_batch.required'     => 'สำหรับเสนารักษ์ กรุณาระบุผลัดที่ประจำการด้วยครับ',
            'username.unique'           => 'ชื่อผู้ใช้งาน (Username) นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น',
            'email.unique'              => 'อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น',
            'phone.required'            => 'กรุณากรอกเบอร์โทรศัพท์',
            'phone.regex'               => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (กรอกเฉพาะตัวเลข 9-10 หลัก)',
            'phone.unique'              => 'เบอร์โทรศัพท์นี้ถูกใช้งานในระบบแล้ว กรุณาใช้เบอร์อื่น',
            'roles.required'            => 'กรุณาเลือกสิทธิ์การใช้งานอย่างน้อย 1 สิทธิ์',
            'roles.*.in'                => 'พบข้อมูลสิทธิ์การใช้งานที่ไม่ถูกต้องในระบบ',
        ]);

        $unitId = $isMedic ? $request->military_unit_id : null;
        $batch  = $isMedic ? $request->intake_batch : null;

        $user->forceFill([
            'rank'             => $request->rank,
            'name'             => $request->name,
            'username'         => trim($request->username),
            'email'            => trim($request->email),
            'phone'            => $request->phone,
            'roles'            => $request->roles, 
            'military_unit_id' => $unitId, 
            'intake_batch'     => $batch,
        ])->save();

        AuditLog::record('update_user', $user, [
            'updated_roles'        => $request->roles,
            'updated_unit_id'      => $unitId,
            'updated_intake_batch' => $batch,
        ]);

        return redirect()->route('backend.users.index')
                         ->with('success', 'แก้ไขข้อมูลคุณ '.$user->name.' เรียบร้อยแล้วครับ!');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('backend.users.index')
                             ->with('error', 'ระบบไม่อนุญาตให้ลบบัญชีของตัวคุณเองครับ!');
        }

        if ($user->id == 1) {
            return redirect()->route('backend.users.index')
                             ->with('error', 'ระบบป้องกันความปลอดภัย: ห้ามลบบัญชี Super Administrator ออกจากระบบเด็ดขาด!');
        }

        $deletedName = $user->name;
        $deletedUsername = $user->username;

        $user->delete();

        AuditLog::record('delete_user', null, [
            'deleted_name'     => $deletedName,
            'deleted_username' => $deletedUsername
        ]);

        return redirect()->route('backend.users.index')
                         ->with('success', 'ลบบัญชีผู้ใช้งานของคุณ ' . $deletedName . ' ออกจากระบบเรียบร้อยแล้วครับ');
    }

    private function getIntakeBatches(): array
    {
        $currentYearBE = date('Y') + 544;
        $batches = [];
        
        for ($i = 0; $i < 5; $i++) {
            $beYear = $currentYearBE - $i;
            $shortYear = substr($beYear, -2);
            $batches[] = "1/{$shortYear}";
            $batches[] = "2/{$shortYear}";
        }
        return $batches;
    }
}