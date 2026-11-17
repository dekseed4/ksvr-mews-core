@extends('layouts.backend')

@section('title', 'ศูนย์ช่วยเหลือ (Help Center)')
@section('page_header', 'คู่มือการใช้งานระบบ (Help Center)')

@section('content')
<div class="max-w-7xl mx-auto mt-6 pb-12">
    
    <!-- 🟢 Header Banner -->
    <div class="bg-gradient-to-r from-indigo-600 to-blue-500 rounded-3xl p-8 md:p-10 text-white shadow-lg relative overflow-hidden mb-8">
        <div class="relative z-10 md:w-2/3">
            <h2 class="text-3xl md:text-4xl font-black mb-3">เรียนรู้วิธีใช้งานระบบ KSVR MEWS</h2>
            <p class="text-indigo-100 text-sm md:text-base leading-relaxed">
                ดูวิดีโอสาธิตการใช้งานสั้นๆ (GIF) เพื่อทำความเข้าใจขั้นตอนการทำงาน ตั้งแต่การประเมินสัญญาณชีพ ไปจนถึงการจบเคสการรักษา
            </p>
        </div>
        <i class="fa-solid fa-circle-play absolute -right-10 -bottom-10 text-9xl text-white opacity-10 transform -rotate-12"></i>
    </div>
    <!-- 🌟🌟 ส่วนของ Infographic Workflow (เพิ่มใหม่) 🌟🌟 -->
    <div class="mb-12">
        <h3 class="text-xl md:text-2xl font-black text-slate-800 dark:text-white mb-6 flex items-center justify-center gap-3">
            <i class="fa-solid fa-route text-indigo-500"></i> ภาพรวมกระบวนการทำงาน KSVR MEWS
        </h3>

        <!-- กล่องพื้นหลัง Infographic -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 md:p-10 shadow-sm border border-slate-100 dark:border-slate-700 relative overflow-hidden">
            
            <!-- ลวดลายตกแต่งพื้นหลัง -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50 dark:bg-indigo-900/20 rounded-full blur-3xl -mr-20 -mt-20 z-0"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-rose-50 dark:bg-rose-900/20 rounded-full blur-3xl -ml-20 -mb-20 z-0"></div>

            <div class="relative z-10 flex flex-col lg:flex-row justify-between items-center lg:items-stretch gap-6 lg:gap-4">
                
                <!-- 🚀 ขั้นตอนที่ 1: เสนารักษ์ -->
                <div class="flex-1 w-full flex flex-col">
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 border-2 border-emerald-400 rounded-2xl p-6 h-full text-center relative hover:shadow-lg hover:-translate-y-1 transition-all">
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-emerald-500 text-white font-black px-4 py-1 rounded-full shadow-md text-sm border-2 border-white dark:border-slate-800">
                            STEP 1
                        </div>
                        <div class="w-20 h-20 mx-auto bg-white dark:bg-slate-800 rounded-full flex items-center justify-center text-4xl text-emerald-500 shadow-sm mt-2 mb-4 border border-emerald-100 dark:border-emerald-800">
                            <i class="fa-solid fa-walkie-talkie"></i>
                        </div>
                        <h4 class="font-bold text-lg text-emerald-800 dark:text-emerald-400 mb-2">เสนารักษ์ (กองร้อย)</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">ค้นหาประวัติจาก HosXP คีย์ข้อมูลสัญญาณชีพ และประเมินอาการฉุกเฉินเฉพาะโรค</p>
                        
                        <!-- 💡 ช่องสำหรับใส่รูปภาพประกอบเล็กๆ (ถ้ามี) -->
                        <div class="mt-auto bg-white dark:bg-slate-900 p-2 rounded-xl border border-emerald-100 dark:border-slate-700">
                            <img src="https://placehold.co/400x200/d1fae5/059669?text=ภาพการคีย์ข้อมูล" alt="ขั้นตอน 1" class="w-full h-auto rounded-lg object-cover">
                        </div>
                    </div>
                </div>

                <!-- ➡️ ลูกศรเชื่อม (แสดงแนวนอนบนคอม, แนวตั้งบนมือถือ) -->
                <div class="flex items-center justify-center lg:rotate-0 rotate-90 text-slate-300 dark:text-slate-600">
                    <i class="fa-solid fa-angles-right text-4xl animate-pulse"></i>
                </div>

                <!-- 🚀 ขั้นตอนที่ 2: ห้องฉุกเฉิน -->
                <div class="flex-1 w-full flex flex-col">
                    <div class="bg-rose-50 dark:bg-rose-900/20 border-2 border-rose-400 rounded-2xl p-6 h-full text-center relative hover:shadow-lg hover:-translate-y-1 transition-all ring-4 ring-rose-100 dark:ring-rose-900/30">
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-rose-500 text-white font-black px-4 py-1 rounded-full shadow-md text-sm border-2 border-white dark:border-slate-800">
                            STEP 2
                        </div>
                        <div class="w-20 h-20 mx-auto bg-white dark:bg-slate-800 rounded-full flex items-center justify-center text-4xl text-rose-500 shadow-sm mt-2 mb-4 border border-rose-100 dark:border-rose-800">
                            <i class="fa-solid fa-truck-medical animate-bounce"></i>
                        </div>
                        <h4 class="font-bold text-lg text-rose-800 dark:text-rose-400 mb-2">พยาบาล (ห้อง ER)</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">รับการแจ้งเตือน Real-time เด้งกลางจอ และเปิดแฟ้มประเมินผู้ป่วยซ้ำเมื่อมาถึงจุดคัดกรอง</p>
                        
                        <div class="mt-auto bg-white dark:bg-slate-900 p-2 rounded-xl border border-rose-100 dark:border-slate-700">
                            <img src="https://placehold.co/400x200/ffe4e6/e11d48?text=ภาพการรับแจ้งเตือน" alt="ขั้นตอน 2" class="w-full h-auto rounded-lg object-cover">
                        </div>
                    </div>
                </div>

                <!-- ➡️ ลูกศรเชื่อม -->
                <div class="flex items-center justify-center lg:rotate-0 rotate-90 text-slate-300 dark:text-slate-600">
                    <i class="fa-solid fa-angles-right text-4xl animate-pulse"></i>
                </div>

                <!-- 🚀 ขั้นตอนที่ 3: จบเคส -->
                <div class="flex-1 w-full flex flex-col">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-400 rounded-2xl p-6 h-full text-center relative hover:shadow-lg hover:-translate-y-1 transition-all">
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-blue-500 text-white font-black px-4 py-1 rounded-full shadow-md text-sm border-2 border-white dark:border-slate-800">
                            STEP 3
                        </div>
                        <div class="w-20 h-20 mx-auto bg-white dark:bg-slate-800 rounded-full flex items-center justify-center text-4xl text-blue-500 shadow-sm mt-2 mb-4 border border-blue-100 dark:border-blue-800">
                            <i class="fa-solid fa-house-chimney-medical"></i>
                        </div>
                        <h4 class="font-bold text-lg text-blue-800 dark:text-blue-400 mb-2">แพทย์ / การรักษา</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">สั่งการรักษา (จำหน่าย, Admit, Refer) พร้อมแจ้งเตือนสถานะกลับไปยังเสนารักษ์ต้นสังกัดอัตโนมัติ</p>
                        
                        <div class="mt-auto bg-white dark:bg-slate-900 p-2 rounded-xl border border-blue-100 dark:border-slate-700">
                            <img src="https://placehold.co/400x200/dbeafe/2563eb?text=ภาพการจบเคส" alt="ขั้นตอน 3" class="w-full h-auto rounded-lg object-cover">
                        </div>
                    </div>
                </div>

            </div>

            <!-- กล่องสรุปด้านล่าง (Executive) -->
            <div class="mt-8 relative z-10 bg-slate-800 dark:bg-slate-900 rounded-2xl p-4 md:p-6 border border-slate-700 flex flex-col md:flex-row items-center gap-6 shadow-md">
                <div class="w-16 h-16 bg-slate-700 rounded-full flex items-center justify-center text-3xl text-amber-400 shrink-0 shadow-inner">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="text-center md:text-left">
                    <h4 class="font-bold text-lg text-white mb-1">ภาพรวมสำหรับผู้บริหาร และแอดมิน (Executive / Admin)</h4>
                    <p class="text-sm text-slate-300">ติดตามสถิติการใช้งานแบบ Real-time, ตรวจสอบรายชื่อทั้งหมด และจัดการผู้ใช้งานผ่านหน้า Dashboard กลาง</p>
                </div>
            </div>

        </div>
    </div>
    <!-- 🌟🌟 สิ้นสุดส่วน Infographic 🌟🌟 -->
    <!-- 🌟 หมวดหมู่การใช้งาน (Grid Cards) -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 md:gap-8">
        
        <!-- การ์ดที่ 1: เสนารักษ์ -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group">
            <div class="aspect-video bg-slate-100 dark:bg-slate-900 rounded-2xl mb-5 overflow-hidden border border-slate-200 dark:border-slate-700 relative">
                <!-- 💡 ใส่รูป GIF ของจริงตรงนี้ -->
                <img src="https://placehold.co/600x338/1e293b/38bdf8?text=GIF+1:+ประเมินคนไข้ใหม่" alt="ขั้นตอนประเมิน" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <div class="absolute top-2 right-2 bg-slate-900/70 backdrop-blur-sm text-white text-[10px] font-bold px-2 py-1 rounded-lg">
                    <i class="fa-solid fa-stopwatch mr-1"></i> 15 วิ
                </div>
            </div>
            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2.5 py-1 rounded-full mb-3 inline-block">สำหรับเสนารักษ์</span>
            <h4 class="font-bold text-slate-800 dark:text-white text-lg mb-2 line-clamp-1">1. วิธีเริ่มประเมิน MEWS ภาคสนาม</h4>
            <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">การค้นหารายชื่อจากฐานข้อมูล การคีย์สัญญาณชีพ และการติ๊กเลือกอาการฉุกเฉินเฉพาะโรคเพื่อส่งเคสเข้าห้อง ER</p>
        </div>

        <!-- การ์ดที่ 2: พยาบาล ER -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group">
            <div class="aspect-video bg-slate-100 dark:bg-slate-900 rounded-2xl mb-5 overflow-hidden border border-slate-200 dark:border-slate-700 relative">
                <img src="https://placehold.co/600x338/1e293b/f43f5e?text=GIF+2:+รับเคสฉุกเฉิน" alt="ขั้นตอนรับเคส" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            </div>
            <span class="text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30 px-2.5 py-1 rounded-full mb-3 inline-block">สำหรับห้องฉุกเฉิน</span>
            <h4 class="font-bold text-slate-800 dark:text-white text-lg mb-2 line-clamp-1">2. การรับเคสและประเมินซ้ำ</h4>
            <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">เมื่อมีแจ้งเตือนสีแดงเด้งขึ้นมา วิธีการกดดูรายละเอียด และการเปิดแฟ้มเพื่อประเมินซ้ำเมื่อผู้ป่วยมาถึง ER</p>
        </div>

        <!-- การ์ดที่ 3: จบเคส -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group">
            <div class="aspect-video bg-slate-100 dark:bg-slate-900 rounded-2xl mb-5 overflow-hidden border border-slate-200 dark:border-slate-700 relative">
                <img src="https://placehold.co/600x338/1e293b/a855f7?text=GIF+3:+จบการรักษา" alt="ขั้นตอนจบเคส" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            </div>
            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-1 rounded-full mb-3 inline-block">กระบวนการทั่วไป</span>
            <h4 class="font-bold text-slate-800 dark:text-white text-lg mb-2 line-clamp-1">3. การจบเคส (Discharge) และการลบข้อมูล</h4>
            <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">การเลือกสถานะจำหน่าย/Admit/Refer และวิธีการกดยกเลิกเคสเมื่อมีการคีย์ข้อมูลผิดพลาด</p>
        </div>

    </div>
</div>
@endsection