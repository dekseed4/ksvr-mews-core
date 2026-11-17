<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackendAuthController;
use App\Http\Controllers\BackendUserController;
use App\Http\Controllers\Backend\PatientController; 
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\RecruitScreeningController;
use App\Http\Controllers\Backend\PasswordResetController;
use Illuminate\Support\Facades\Broadcast;


Broadcast::routes();

// ==========================================
// 1. โซนสาธารณะ (Public Routes) - ทุกคนเข้าถึงได้
// ==========================================
Route::get('/', function () {
    // ถ้าล็อกอินแล้วให้เด้งไป Dashboard อัตโนมัติ
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('backend.dashboard');
    }
    // ถ้ายังไม่ล็อกอินให้ไปหน้า Login
    return redirect()->route('login');
});

// ระบบ Authentication
Route::get('/login', [BackendAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [BackendAuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [BackendAuthController::class, 'logout'])->name('logout');


// ==========================================
// 2. โซนระบบหลังบ้าน (Protected Routes) - ต้องล็อกอินก่อนเท่านั้น
// ==========================================
Route::middleware(['auth'])->group(function () {
    
    // เส้นทางสำหรับเปลี่ยนรหัสผ่าน (หน้าเว็บนี้จะไม่โดน Middleware force.password บล็อค)
    Route::get('/force-change-password', [BackendAuthController::class, 'showForceChangePasswordForm'])->name('password.force.edit');
    Route::post('/force-change-password', [BackendAuthController::class, 'processForceChangePassword'])->name('password.force.update');
    
    Route::post('/forgot-password/self-reset', [PasswordResetController::class, 'selfReset'])
        ->middleware('throttle:5,1') // ป้องกันการสุ่มรหัสเกิน 5 ครั้งต่อนาที
        ->name('password.self_reset');

    Route::post('/forgot-password/notify-admin', [PasswordResetController::class, 'notifyAdmin'])
        ->middleware('throttle:3,1')
        ->name('password.notify_admin');

    // 🌟 ศูนย์ช่วยเหลือ (Help Center)
    Route::get('/backend/help', function () {
        return view('backend.help.index');
    })->name('backend.help.index');
    
    // === โซนระบบหลัก (ครอบด้วย Middleware บังคับเปลี่ยนรหัส) ===
    Route::middleware(['force.password'])->group(function () {   

        // หน้า Dashboard (ศูนย์รวมเมนูหลัก)
        Route::get('/backend/dashboard', [DashboardController::class, 'index'])->name('backend.dashboard');

        // ==========================================
        // 2.1 โซนคัดกรองผู้ป่วยและ MEWS (ใช้งานโดย: เสนารักษ์, พยาบาล, แพทย์)
        // ==========================================
        
        // 📍 1. ระบบค้นหาผู้ป่วย (เพิ่ม Route สำหรับ Auto-complete)
        Route::get('/backend/patients/search', [PatientController::class, 'showSearchForm'])->name('backend.patients.search');
        Route::post('/backend/patients/search', [PatientController::class, 'processSearch'])->name('backend.patients.processSearch');
        
        Route::get('/backend/patients/autocomplete', [PatientController::class, 'autocomplete'])->name('backend.patients.autocomplete');
        
        // 📍 2. จัดการโปรไฟล์ส่วนตัว
        Route::get('/backend/profile', [\App\Http\Controllers\Backend\ProfileController::class, 'edit'])->name('backend.profile.edit');
        Route::put('/backend/profile', [\App\Http\Controllers\Backend\ProfileController::class, 'update'])->name('backend.profile.update');
        Route::put('/profile/password', [\App\Http\Controllers\Backend\ProfileController::class, 'updatePassword'])->name('backend.profile.password.update');
        // 📍 3. ระบบจัดการตารางรายชื่อคนไข้ (พลทหาร)
        Route::get('/backend/patients', [PatientController::class, 'index'])->name('backend.patients.index');
        Route::get('/backend/patients/import', [\App\Http\Controllers\Backend\PatientImportController::class, 'index'])->name('backend.patients.import');
        Route::post('/backend/patients/import', [\App\Http\Controllers\Backend\PatientImportController::class, 'import'])->name('backend.patients.import.process');

        Route::get('/backend/patients/create', [\App\Http\Controllers\Backend\PatientController::class, 'create'])->name('backend.patients.create');
        Route::post('/backend/patients', [\App\Http\Controllers\Backend\PatientController::class, 'store'])->name('backend.patients.store');
        Route::get('/backend/patients/{patient}', [\App\Http\Controllers\Backend\PatientController::class, 'show'])->name('backend.patients.show');
        Route::get('/backend/patients/{patient}/edit', [\App\Http\Controllers\Backend\PatientController::class, 'edit'])->name('backend.patients.edit');
        Route::put('/backend/patients/{patient}', [\App\Http\Controllers\Backend\PatientController::class, 'update'])->name('backend.patients.update');
        Route::patch('/backend/patients/{id}/duty-status', [\App\Http\Controllers\Backend\PatientController::class, 'updateDutyStatus'])->name('backend.patients.update_duty_status');

        // 📍 4. ระบบประเมินสัญญาณชีพ (MEWS)
        Route::get('/backend/mews/create/{patient_id}', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'create'])->name('backend.mews.create');
        Route::post('/backend/mews/store', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'store'])->name('backend.mews.store');
        
        // 🌟 ระบบจัดการ MEWS (CRUD)
        Route::get('/backend/mews/{mews}', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'show'])->name('backend.mews.show');
        Route::get('/backend/mews/{mews}/edit', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'edit'])->name('backend.mews.edit');
        Route::put('/backend/mews/{mews}', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'update'])->name('backend.mews.update');
        Route::delete('/backend/mews/{mews}', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'destroy'])->name('backend.mews.destroy');
        // 🌟 เปลี่ยนสถานะเป็น จบการรักษา (Discharge)
        Route::patch('/backend/mews/{mews}/discharge', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'discharge'])->name('backend.mews.discharge');
        Route::patch('/backend/mews/{id}/return-to-unit', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'returnToUnit'])->name('backend.mews.return_to_unit');
        
        Route::get('/mews/{id}/print-form', [\App\Http\Controllers\Backend\MewsAssessmentController::class, 'printForm'])->name('backend.mews.print_form');
        Route::get('/api/hosxp/search', [\App\Http\Controllers\Backend\PatientController::class, 'searchHosxp'])->name('backend.api.hosxp.search');
        Route::patch('/mews/{id}/cancel', [App\Http\Controllers\Backend\MewsAssessmentController::class, 'cancel'])->name('backend.mews.cancel');
        Route::post('/backend/patients/{id}/sync-hosxp', [\App\Http\Controllers\Backend\PatientController::class, 'syncHosxp'])->name('backend.patients.sync_hosxp');
        Route::post('/backend/patients/bulk-sync-hosxp', [\App\Http\Controllers\Backend\PatientController::class, 'bulkSyncHosxp'])->name('backend.patients.bulk_sync_hosxp');
        
        Route::post('/backend/ai/extract-vitals', [\App\Http\Controllers\Backend\AiController::class, 'extractVitals'])->name('backend.ai.extract_vitals');
        Route::post('/backend/ai/triage-copilot', [\App\Http\Controllers\Backend\AiController::class, 'triageCopilot'])->name('backend.ai.triage_copilot');
        Route::post('/backend/ai/ask-executive', [\App\Http\Controllers\Backend\AiController::class, 'askExecutive'])->name('backend.ai.ask_executive');

        
        // 🌟 Route พิเศษสำหรับ SysAdmin เช็ครายชื่อ AI Models ที่ API Key เราเข้าถึงได้
        Route::get('/backend/ai/list-models', function () {
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) return "กรุณาใส่ GEMINI_API_KEY ในไฟล์ .env ก่อนครับ";

            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
            
            return $response->json();
        });

        Route::prefix('recruit-screenings')->name('backend.recruit_screenings.')->group(function () {
            Route::get('/dashboard', [RecruitScreeningController::class, 'dashboard'])->name('dashboard');
            Route::get('/patients-by-color', [RecruitScreeningController::class, 'getPatientsByColor'])->name('patients_by_color');
            
            // 🌟 3 เส้นทางนี้คือหัวใจของหน้าสแกน 🌟
            Route::get('/scan', [RecruitScreeningController::class, 'scanIndex'])->name('scan'); // โชว์หน้าสแกน
            Route::post('/search', [RecruitScreeningController::class, 'searchPatient'])->name('search'); // หาชื่อจากเลข ปชช.
            Route::post('/store', [RecruitScreeningController::class, 'storeScreening'])->name('store'); // บันทึกสี

            // 🌟 API สำหรับลบข้อมูลการคัดกรอง (ใช้ตอนกดปุ่มลบ หรือกดปุ่มแก้ไข)
            Route::delete('/patients-by-color/api/{id}', [RecruitScreeningController::class, 'destroyScreening'])->name('destroy_api');
        });

        // 📊 ระบบรายงานอาการเจ็บป่วยทหารใหม่ประจำวัน (Daily Morbidity Report)
        Route::prefix('backend/morbidity-reports')->name('backend.morbidity_reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Backend\RecruitMorbidityReportController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Backend\RecruitMorbidityReportController::class, 'store'])->name('store');
            Route::patch('/{id}/recover', [\App\Http\Controllers\Backend\RecruitMorbidityReportController::class, 'markRecovered'])->name('recover');
            Route::delete('/{id}', [\App\Http\Controllers\Backend\RecruitMorbidityReportController::class, 'destroy'])->name('destroy');
            Route::get('/fetch-hosxp-dx', [\App\Http\Controllers\Backend\RecruitMorbidityReportController::class, 'fetchHosxpDiagnosis'])->name('fetch_hosxp_dx');
        });
        
        // 📅 ระบบการนัดพบแพทย์ของทหารใหม่ (Recruit Medical Appointments)
        Route::prefix('backend/recruit-appointments')->name('backend.recruit_appointments.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Backend\RecruitAppointmentController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Backend\RecruitAppointmentController::class, 'store'])->name('store');
            Route::patch('/{id}/status', [\App\Http\Controllers\Backend\RecruitAppointmentController::class, 'updateStatus'])->name('update_status');
            Route::delete('/{id}', [\App\Http\Controllers\Backend\RecruitAppointmentController::class, 'destroy'])->name('destroy');
            Route::post('/sync-hosxp', [\App\Http\Controllers\Backend\RecruitAppointmentController::class, 'syncFromHosxp'])->name('sync_hosxp');
        });

        // 🛌 ระบบจัดการนอนห้องพยาบาลหน่วยฝึก / แยกโรงนอน (Unit Infirmary & Isolation Tracking)
        Route::prefix('backend/recruit-infirmary')->name('backend.recruit_infirmary.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Backend\RecruitInfirmaryController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Backend\RecruitInfirmaryController::class, 'store'])->name('store');
            Route::put('/{id}', [\App\Http\Controllers\Backend\RecruitInfirmaryController::class, 'update'])->name('update'); // 🌟 เพิ่ม Route สำหรับแก้ไขข้อมูล
            Route::patch('/{id}/discharge', [\App\Http\Controllers\Backend\RecruitInfirmaryController::class, 'dischargeToBarracks'])->name('discharge');
            Route::delete('/{id}', [\App\Http\Controllers\Backend\RecruitInfirmaryController::class, 'destroy'])->name('destroy');
        });

        // 💊 ระบบใบบันทึกสัญญาณชีพ (06:00/18:00) & ตารางรับประทานยา (e-MAR)
        Route::prefix('backend/care-chart')->name('backend.care_chart.')->group(function () {
            Route::get('/patient/{patient}', [\App\Http\Controllers\Backend\RecruitCareChartController::class, 'showChart'])->name('show');
            Route::post('/vital', [\App\Http\Controllers\Backend\RecruitCareChartController::class, 'storeVital'])->name('store_vital');
            Route::post('/medication', [\App\Http\Controllers\Backend\RecruitCareChartController::class, 'storeMedication'])->name('store_medication');
            Route::delete('/medication/{id}', [\App\Http\Controllers\Backend\RecruitCareChartController::class, 'destroyMedication'])->name('destroy_medication');
            Route::post('/medication/toggle-log', [\App\Http\Controllers\Backend\RecruitCareChartController::class, 'toggleMedicationLog'])->name('toggle_med_log');
            Route::get('/hosxp-drugs/{patient}', [\App\Http\Controllers\Backend\RecruitCareChartController::class, 'fetchHosxpMedications'])->name('hosxp_drugs');
        });

        Route::post('/backend/push/subscribe', [\App\Http\Controllers\Backend\PushSubscriptionController::class, 'subscribe'])->name('backend.push.subscribe');
        Route::post('/backend/push/test', [\App\Http\Controllers\Backend\PushSubscriptionController::class, 'sendTestPush'])->name('backend.push.test');

        // 📁 ระบบติดตามเอกสารนำปลดทหารกองประจำการ (Military Discharge Tracking)
            Route::prefix('backend/military-discharges')->name('backend.military_discharges.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Backend\MilitaryDischargeController::class, 'index'])->name('index');
                Route::post('/', [\App\Http\Controllers\Backend\MilitaryDischargeController::class, 'store'])->name('store');
                Route::put('/{id}', [\App\Http\Controllers\Backend\MilitaryDischargeController::class, 'update'])->name('update');
                Route::delete('/{id}', [\App\Http\Controllers\Backend\MilitaryDischargeController::class, 'destroy'])->name('destroy');
            });

        // ==========================================
        // 2.2 โซนจัดการผู้ใช้งาน (Restricted) - ใช้งานได้เฉพาะ Admin เท่านั้น!
        // ==========================================
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/backend/users', [BackendUserController::class, 'index'])->name('backend.users.index');
            Route::get('/backend/users/create', [BackendUserController::class, 'create'])->name('backend.users.create');
            Route::post('/backend/users', [BackendUserController::class, 'store'])->name('backend.users.store');
            
            Route::post('/backend/users/{id}/reset-password', [BackendUserController::class, 'resetPassword'])->name('backend.users.reset_password');
            // เพิ่ม Route สำหรับแก้ไขและอัปเดตข้อมูล
            Route::get('/backend/users/{user}/edit', [\App\Http\Controllers\BackendUserController::class, 'edit'])->name('backend.users.edit');
            Route::put('/backend/users/{user}', [\App\Http\Controllers\BackendUserController::class, 'update'])->name('backend.users.update');
            Route::delete('/backend/users/{user}', [\App\Http\Controllers\BackendUserController::class, 'destroy'])->name('backend.users.destroy');
    
            Route::delete('/backend/patients/{patient}', [\App\Http\Controllers\Backend\PatientController::class, 'destroy'])->name('backend.patients.destroy');
            // 🌟 เพิ่มฟังก์ชันลบหลายรายการ (ต้องอยู่ใต้ role:admin)
            Route::delete('/backend/patients/bulk-delete', [\App\Http\Controllers\Backend\PatientController::class, 'bulkDestroy'])->name('backend.patients.bulk_destroy');

            Route::resource('military-units', \App\Http\Controllers\Backend\MilitaryUnitController::class, ['as' => 'backend']);
            Route::get('/system-logs', [\App\Http\Controllers\Backend\SystemLogController::class, 'index'])->name('backend.system_logs.index');
       
            // ==========================================
            // ⚙️ ระบบตั้งค่า (Settings)
            // ==========================================
            Route::prefix('settings')->name('backend.settings.')->group(function () {
                
                // 🟢 1. หน้าจัดการอาการฉุกเฉิน (Symptoms)
                Route::get('/symptoms', [\App\Http\Controllers\Backend\MewsSettingController::class, 'symptomsIndex'])->name('symptoms.index');
                Route::post('/symptoms', [\App\Http\Controllers\Backend\MewsSettingController::class, 'storeSymptom'])->name('symptoms.store');
                Route::put('/symptoms/{id}', [\App\Http\Controllers\Backend\MewsSettingController::class, 'updateSymptom'])->name('symptoms.update');
                Route::delete('/symptoms/{id}', [\App\Http\Controllers\Backend\MewsSettingController::class, 'destroySymptom'])->name('symptoms.destroy');

                // 🔵 2. หน้าจัดการสูตรคำนวณ MEWS (Templates & Editor)
                Route::get('/mews-templates', [\App\Http\Controllers\Backend\MewsSettingController::class, 'mewsIndex'])->name('mews.index');
                
                // 🌟 เพิ่มบรรทัดนี้ สำหรับรับข้อมูลสร้างสูตรใหม่
                Route::post('/mews-templates', [\App\Http\Controllers\Backend\MewsSettingController::class, 'storeMewsTemplate'])->name('mews.store');
                
                Route::get('/mews-templates/{id}/builder', [\App\Http\Controllers\Backend\MewsSettingController::class, 'mewsBuilder'])->name('mews.builder');
                Route::put('/mews-templates/{id}/builder', [\App\Http\Controllers\Backend\MewsSettingController::class, 'updateMewsBuilder'])->name('mews.update_builder');
                // 🌟 เปิด/ปิด การใช้งานสูตร (Toggle Active)
                Route::post('/mews-templates/{id}/toggle', [\App\Http\Controllers\Backend\MewsSettingController::class, 'toggleMewsTemplate'])->name('mews.toggle');
                // 🌟 แก้ไขชื่อและคำอธิบายสูตร
                Route::put('/mews-templates/{id}/info', [\App\Http\Controllers\Backend\MewsSettingController::class, 'updateMewsTemplateInfo'])->name('mews.update_info');
                // 🌟 คัดลอกสูตร (Duplicate)
                Route::post('/mews-templates/{id}/duplicate', [\App\Http\Controllers\Backend\MewsSettingController::class, 'duplicateMewsTemplate'])->name('mews.duplicate');
                // 🌟 ลบสูตรคำนวณ MEWS (เฉพาะสูตรที่สร้างขึ้นมาเอง)
                Route::delete('/mews-templates/{id}', [\App\Http\Controllers\Backend\MewsSettingController::class, 'destroyMewsTemplate'])->name('mews.destroy');
            });
        });
    });
});