<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
            'device_name' => 'required', // เอาไว้ระบุว่าเป็นอุปกรณ์เครื่องไหน เช่น iPad, Android
        ]);

        // ค้นหาผู้ใช้งานจาก Username
        $user = User::where('username', $request->username)->first();

        // ตรวจสอบรหัสผ่าน
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'ข้อมูลการเข้าสู่ระบบไม่ถูกต้อง'
            ], 401);
        }

        // สร้าง Token ให้กับผู้ใช้งาน
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // ลบ Token ปัจจุบันที่ใช้ล็อกอินเข้ามา
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'ออกจากระบบสำเร็จ'
        ]);
    }
}
