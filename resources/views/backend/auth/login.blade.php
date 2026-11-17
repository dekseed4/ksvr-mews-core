<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>เข้าสู่ระบบ - KSVR Hospital System</title>
    
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1.0">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=1.0">
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .bg-login-pattern {
            background-color: #f1f5f9;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 20px 20px;
        }
    </style>
</head>
<body class="bg-login-pattern min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-100">
        
        <!-- 🌟 ส่วนหัว -->
        <div class="bg-white p-8 pb-4 text-center border-b border-slate-50">
            <img src="{{ asset('images/logo5.png') }}" 
                 alt="KSVR Hospital Logo" 
                 class="h-24 sm:h-28 mx-auto object-contain drop-shadow-sm mb-5 hover:scale-105 transition-transform duration-500">
                 
            <h2 class="text-3xl font-black text-slate-800 tracking-tight">KSVR <span class="text-emerald-500">MEWS</span></h2>
            <p class="text-sm text-slate-500 font-medium mt-1.5 px-4 bg-slate-50 inline-block py-1 rounded-full border border-slate-100">
                <i class="fa-solid fa-notes-medical text-emerald-500 mr-1.5"></i>ระบบบันทึกและประเมินสัญญาณชีพ
            </p>
        </div>

        <!-- ส่วนฟอร์ม -->
        <div class="p-8 pt-6">
            <h3 class="text-lg font-medium text-slate-700 mb-6 text-center">เข้าสู่ระบบสำหรับเจ้าหน้าที่</h3>

            <!-- แสดง Error ถ้ารหัสผิด -->
            @if($errors->any())
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg">
                    <div class="flex items-center">
                        <i class="fa-solid fa-circle-exclamation text-red-500 mr-3"></i>
                        <p class="text-sm text-red-700">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf

                <!-- 🌟 ช่อง Smart Login: รองรับ Username, เบอร์โทรศัพท์ หรือ อีเมล -->
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700">
                        ชื่อผู้ใช้งาน / เบอร์โทรศัพท์ / อีเมล
                    </label>
                    <div class="mt-2 relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-id-badge text-lg"></i>
                        </div>

                        <input id="username" type="text" name="username" value="{{ old('username') }}" 
                            required autofocus autocapitalize="off"
                            placeholder="กรอก Username, เบอร์โทร หรือ อีเมล"
                            class="block w-full rounded-xl border-slate-300 py-3 pl-11 pr-4 text-slate-900 placeholder:text-slate-400 placeholder:text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-lg transition-colors">
                    </div>
                </div>

                <!-- ช่องรหัสผ่าน พร้อมปุ่มดูรหัส -->
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">รหัสผ่าน</label>
                    <div class="mt-2 relative">
                        <input id="password" type="password" name="password" required
                            class="block w-full rounded-xl border-slate-300 py-3 pl-4 pr-12 text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-lg transition-colors">
                        
                        <button type="button" id="togglePassword" 
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-emerald-600 focus:outline-none transition-colors">
                            <i class="fa-solid fa-eye text-lg transition-transform hover:scale-110" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- 🌟 แถว Checkbox จดจำฉัน + ปุ่มลืมรหัสผ่าน -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember" type="checkbox" 
                            class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600 cursor-pointer">
                        <label for="remember_me" class="ml-2.5 block text-xs sm:text-sm font-medium text-slate-700 cursor-pointer">
                            จดจำฉันในระบบ
                        </label>
                    </div>

                    <!-- 🔑 ปุ่มเปิด Modal ลืมรหัสผ่าน -->
                    <button type="button" onclick="openForgotPasswordModal()" 
                            class="text-xs sm:text-sm font-semibold text-emerald-600 hover:text-emerald-700 hover:underline focus:outline-none transition-colors">
                        <i class="fa-solid fa-key mr-1 text-xs"></i>ลืมรหัสผ่าน?
                    </button>
                </div>

                <!-- ปุ่มเข้าสู่ระบบ -->
                <div>
                    <button type="submit" 
                            class="flex w-full justify-center rounded-xl bg-emerald-600 py-3.5 px-4 text-lg font-bold text-white shadow-md hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all">
                        เข้าสู่ระบบ (Login)
                    </button>
                </div>
            </form>
            
            <div class="mt-6 text-center text-xs text-slate-500">
                <p>&copy; 2026 KSVR Hospital. All rights reserved.</p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 🔐 Modal ลืมรหัสผ่าน (Hybrid Smart Reset: ตั้งรหัสเองทันที หรือ แจ้ง Admin) -->
    <!-- ========================================================================= -->
    <div id="forgotPasswordModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-100 overflow-hidden animate-fade-in">
            
            <!-- Header ของ Modal -->
            <div class="bg-slate-800 px-6 py-4 flex items-center justify-between text-white">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-400 flex items-center justify-center">
                        <i class="fa-solid fa-unlock-keyhole"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-base">กู้คืนรหัสผ่าน (Password Recovery)</h3>
                        <p class="text-[11px] text-slate-300">เลือกรูปแบบการกู้คืนรหัสผ่านที่สะดวกที่สุด</p>
                    </div>
                </div>
                <button type="button" onclick="closeForgotPasswordModal()" class="w-8 h-8 rounded-full bg-slate-700 hover:bg-rose-500 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- ปุ่มสลับแท็บ (Tab Switcher) -->
            <div class="p-3 bg-slate-50 border-b border-slate-100 grid grid-cols-2 gap-2">
                <button type="button" id="tab-btn-self" onclick="switchResetTab('self')" 
                        class="py-2 px-3 rounded-xl text-xs font-bold transition-all bg-white text-emerald-700 shadow-sm border border-slate-200/80 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-bolt text-emerald-500"></i> ตั้งรหัสใหม่ทันที
                </button>
                <button type="button" id="tab-btn-admin" onclick="switchResetTab('admin')" 
                        class="py-2 px-3 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-headset text-indigo-500"></i> แจ้งแอดมินรีเซ็ตให้
                </button>
            </div>

            <!-- แท็บที่ 1: ยืนยันตัวตนแล้วตั้งรหัสผ่านใหม่ทันที -->
            <div id="tab-content-self" class="p-6 space-y-4">
                <div class="bg-emerald-50/70 border border-emerald-100 rounded-xl p-3 text-xs text-emerald-800">
                    <i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> กรอก <b>Username</b> คู่กับ <b>เบอร์โทรศัพท์ (หรืออีเมล)</b> ที่ลงทะเบียนไว้เพื่อตั้งรหัสผ่านใหม่ได้ทันที
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ชื่อผู้ใช้งาน (Username) <span class="text-rose-500">*</span></label>
                    <input type="text" id="reset_username" placeholder="เช่น admin, er01, medic01..." 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">เบอร์โทรศัพท์ หรือ อีเมลที่ใช้ยืนยันตัวตน <span class="text-rose-500">*</span></label>
                    <input type="text" id="reset_verify_info" placeholder="กรอกเบอร์โทร 10 หลัก หรือ อีเมลของคุณ" 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">รหัสผ่านใหม่ <span class="text-rose-500">*</span></label>
                        <input type="password" id="reset_new_password" placeholder="อย่างน้อย 6 ตัวอักษร" 
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">ยืนยันรหัสผ่านใหม่ <span class="text-rose-500">*</span></label>
                        <input type="password" id="reset_confirm_password" placeholder="กรอกซ้ำอีกครั้ง" 
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="pt-3 flex gap-2">
                    <button type="button" onclick="closeForgotPasswordModal()" class="w-1/3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-sm transition-colors">
                        ยกเลิก
                    </button>
                    <button type="button" id="btn-submit-self-reset" onclick="submitSelfReset()" class="w-2/3 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check-circle"></i> เปลี่ยนรหัสผ่านทันที
                    </button>
                </div>
            </div>

            <!-- แท็บที่ 2: แจ้งผู้ดูแลระบบ (กรณีจำเบอร์/อีเมลไม่ได้) -->
            <div id="tab-content-admin" class="hidden p-6 space-y-4">
                <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl p-3 text-xs text-indigo-800">
                    <i class="fa-solid fa-paper-plane text-indigo-600 mr-1"></i> ระบบจะส่งสัญญาณแจ้งเตือนด่วนไปยัง <b>ผู้ดูแลระบบ (IT KSVR)</b> เพื่อรีเซ็ตรหัสผ่านให้คุณทันที
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ชื่อ-นามสกุล หรือ Username ของคุณ <span class="text-rose-500">*</span></label>
                    <input type="text" id="admin_req_identifier" placeholder="ระบุชื่อ-นามสกุล หรือ Username..." 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">เบอร์โทรติดต่อกลับ หรือ แผนก/หน่วยฝึก</label>
                    <input type="text" id="admin_req_contact" placeholder="เช่น ห้องฉุกเฉิน ER, หน่วยฝึก ร.3 พัน.1, เบอร์โทร..." 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="pt-3 flex gap-2">
                    <button type="button" onclick="closeForgotPasswordModal()" class="w-1/3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-sm transition-colors">
                        ยกเลิก
                    </button>
                    <button type="button" id="btn-submit-admin-req" onclick="submitAdminNotify()" class="w-2/3 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bell"></i> ส่งคำขอถึงแอดมิน
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('togglePassword');
            const passInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (toggleBtn && passInput && eyeIcon) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (passInput.type === 'password') {
                        passInput.type = 'text';
                        eyeIcon.classList.remove('fa-eye');
                        eyeIcon.classList.add('fa-eye-slash', 'text-emerald-600');
                    } else {
                        passInput.type = 'password';
                        eyeIcon.classList.remove('fa-eye-slash', 'text-emerald-600');
                        eyeIcon.classList.add('fa-eye');
                    }
                });
            }
        });

        function openForgotPasswordModal() {
            const loginUserVal = document.getElementById('username')?.value || '';
            if (loginUserVal) {
                document.getElementById('reset_username').value = loginUserVal;
                document.getElementById('admin_req_identifier').value = loginUserVal;
            }
            document.getElementById('forgotPasswordModal').classList.remove('hidden');
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('hidden');
        }

        function switchResetTab(tab) {
            const btnSelf = document.getElementById('tab-btn-self');
            const btnAdmin = document.getElementById('tab-btn-admin');
            const contentSelf = document.getElementById('tab-content-self');
            const contentAdmin = document.getElementById('tab-content-admin');

            if (tab === 'self') {
                contentSelf.classList.remove('hidden');
                contentAdmin.classList.add('hidden');
                btnSelf.className = 'py-2 px-3 rounded-xl text-xs font-bold transition-all bg-white text-emerald-700 shadow-sm border border-slate-200/80 flex items-center justify-center gap-1.5';
                btnAdmin.className = 'py-2 px-3 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1.5';
            } else {
                contentSelf.classList.add('hidden');
                contentAdmin.classList.remove('hidden');
                btnAdmin.className = 'py-2 px-3 rounded-xl text-xs font-bold transition-all bg-white text-indigo-700 shadow-sm border border-slate-200/80 flex items-center justify-center gap-1.5';
                btnSelf.className = 'py-2 px-3 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1.5';
            }
        }

        function submitSelfReset() {
            const username = document.getElementById('reset_username').value.trim();
            const verifyInfo = document.getElementById('reset_verify_info').value.trim();
            const password = document.getElementById('reset_new_password').value;
            const passwordConfirmation = document.getElementById('reset_confirm_password').value;

            if (!username || !verifyInfo || !password) {
                Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบถ้วน', text: 'กรุณากรอกข้อมูลให้ครบทุกช่องครับ', confirmButtonColor: '#10b981' });
                return;
            }

            if (password !== passwordConfirmation) {
                Swal.fire({ icon: 'warning', title: 'รหัสผ่านไม่ตรงกัน', text: 'กรุณากรอกรหัสผ่านใหม่ทั้ง 2 ช่องให้ตรงกันครับ', confirmButtonColor: '#10b981' });
                return;
            }

            const btn = document.getElementById('btn-submit-self-reset');
            btn.disabled = true;

            fetch("{{ route('password.self_reset') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    username: username,
                    verify_info: verifyInfo,
                    password: password,
                    password_confirmation: passwordConfirmation
                })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                if (data.status === 'success') {
                    closeForgotPasswordModal();
                    document.getElementById('username').value = username;
                    document.getElementById('password').value = '';
                    document.getElementById('password').focus();
                    Swal.fire({ icon: 'success', title: 'เปลี่ยนรหัสผ่านสำเร็จ!', text: data.message, confirmButtonColor: '#10b981' });
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถรีเซ็ตรหัสผ่านได้', text: data.message || 'ข้อมูลไม่ถูกต้อง', confirmButtonColor: '#ef4444' });
                }
            })
            .catch(() => {
                btn.disabled = false;
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ในขณะนี้' });
            });
        }

        function submitAdminNotify() {
            const identifier = document.getElementById('admin_req_identifier').value.trim();
            const contact = document.getElementById('admin_req_contact').value.trim();

            if (!identifier) {
                Swal.fire({ icon: 'warning', title: 'กรุณาระบุชื่อหรือ Username', text: 'เพื่อให้แอดมินทราบว่าต้องรีเซ็ตรหัสผ่านให้บัญชีใดครับ', confirmButtonColor: '#4f46e5' });
                return;
            }

            const btn = document.getElementById('btn-submit-admin-req');
            btn.disabled = true;

            fetch("{{ route('password.notify_admin') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    identifier: identifier,
                    contact: contact
                })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                closeForgotPasswordModal();
                Swal.fire({ icon: 'success', title: 'ส่งคำขอเรียบร้อยแล้ว', text: data.message, confirmButtonColor: '#4f46e5' });
            })
            .catch(() => {
                btn.disabled = false;
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถส่งคำขอได้ในขณะนี้' });
            });
        }
    </script>
</body>
</html>