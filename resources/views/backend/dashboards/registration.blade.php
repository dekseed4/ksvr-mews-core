@extends('layouts.backend')

@section('title', 'แดชบอร์ดงานเวชระเบียน - KSVR Hospital')
@section('page_header', 'ศูนย์ควบคุมข้อมูลผู้ป่วย (Registration Control)')

@section('content')
<div class="max-w-7xl mx-auto mt-6 pb-12 space-y-6">

    @include('backend.dashboards.partials.view_tabs')

    <!-- 🔍 1. โซนค้นหาหลัก (Hero Search) -->
    <div class="bg-slate-800 rounded-3xl p-8 md:p-12 shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 -mt-10 -mr-10 text-slate-700 opacity-50 text-9xl pointer-events-none">
            <i class="fa-solid fa-id-card-clip"></i>
        </div>
        
        <div class="relative z-10 max-w-3xl mx-auto text-center space-y-6">
            <div class="w-16 h-16 bg-blue-500 rounded-2xl mx-auto flex items-center justify-center text-3xl text-white shadow-inner mb-4">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">ค้นหาประวัติผู้ป่วย</h2>
            <p class="text-slate-300 font-medium">กรอกเลขบัตร ปชช., รหัสทหาร, HN หรือ ชื่อ-นามสกุล อย่างใดอย่างหนึ่ง</p>
            
            <form action="{{ route('backend.patients.processSearch') }}" method="POST" class="mt-6 flex flex-col sm:flex-row gap-3">
                @csrf
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fa-solid fa-keyboard text-slate-400"></i>
                    </div>
                    <input type="text" name="search_term" 
                           class="w-full pl-11 pr-4 py-4 rounded-xl border-0 focus:ring-4 focus:ring-blue-500/50 bg-white shadow-inner text-lg font-medium placeholder:text-slate-400 placeholder:font-normal" 
                           placeholder="พิมพ์ข้อมูลเพื่อค้นหา..." autofocus required autocomplete="off">
                </div>
                <button type="submit" class="py-4 px-8 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold text-lg shadow-md transition-all hover:-translate-y-0.5">
                    ค้นหาข้อมูล
                </button>
            </form>
        </div>
    </div>

    <!-- 📊 2. โซนกล่องสถิติ (Logistics & Integrity) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- กล่องยอดรวม -->
        <a href="{{ route('backend.patients.index') }}" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all">
            <div>
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1">ผู้ป่วยในระบบทั้งหมด</p>
                <h3 class="text-3xl font-black text-slate-800">{{ number_format($totalPatients) }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-14 h-14 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center text-2xl group-hover:bg-slate-100 group-hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-users"></i>
            </div>
        </a>

        <!-- กล่องสถานะรอ Sync HosXP (คลิกเพื่อดูเฉพาะคนที่ยังไม่มี HN ได้ทันที) -->
        <a href="{{ route('backend.patients.index', ['hn_status' => 'unsynced']) }}" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md hover:border-rose-300 transition-all relative overflow-hidden">
            <div class="relative z-10">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                    ยังไม่ผูกรหัส HN (รอ Sync) <i class="fa-solid fa-arrow-up-right-from-square text-[11px] text-rose-400"></i>
                </p>
                <div class="flex items-baseline gap-2">
                    <h3 id="unsynced-counter" class="text-3xl font-black {{ $unsyncedPatients > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ number_format($unsyncedPatients) }}
                    </h3>
                    <span class="text-sm font-medium text-slate-400">รายการ (คลิกจัดการ)</span>
                </div>
            </div>
            <div class="w-14 h-14 rounded-full {{ $unsyncedPatients > 0 ? 'bg-rose-50 text-rose-500' : 'bg-emerald-50 text-emerald-500' }} flex items-center justify-center text-2xl relative z-10">
                <i class="fa-solid {{ $unsyncedPatients > 0 ? 'fa-triangle-exclamation animate-pulse' : 'fa-check-double' }}"></i>
            </div>
            @if($unsyncedPatients > 0)
                <div class="absolute bottom-0 left-0 w-full h-1 bg-rose-500"></div>
            @endif
        </a>

        <!-- กล่องเคสฉุกเฉินที่เปิดอยู่ -->
        <button type="button" onclick="showActivePatientsModal()" class="w-full text-left bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md hover:border-amber-300 transition-all focus:outline-none">
            <div>
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1 flex items-center gap-2">
                    เคส MEWS ที่กำลังเปิดอยู่ <i class="fa-solid fa-hand-pointer text-amber-400 animate-bounce"></i>
                </p>
                <div class="flex items-baseline gap-2">
                    <h3 class="text-3xl font-black text-amber-500">{{ number_format($activeCases) }}</h3>
                    <span class="text-sm font-medium text-slate-400">เคส (คลิกดูรายชื่อ)</span>
                </div>
            </div>
            <div class="w-14 h-14 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-2xl group-hover:bg-amber-500 group-hover:text-white transition-colors shadow-sm">
                <i class="fa-solid fa-truck-medical"></i>
            </div>
        </button>

    </div>

    <!-- 📋 3. โซนตารางสิ่งที่ต้องทำ (To-Do List: Missing HN - พร้อมปุ่มซิงค์ HosXP ทันที!) -->
    @if(count($actionNeededPatients) > 0)
    <div class="bg-white rounded-3xl shadow-sm border border-rose-100 overflow-hidden">
        <div class="px-6 py-4 bg-rose-50/50 border-b border-rose-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-rose-800 flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-list"></i> ผู้ป่วยที่ยังไม่มีรหัส HN (5 รายการล่าสุดที่รอซิงค์จาก HosXP)
                </h3>
                <p class="text-xs text-rose-600/80 mt-0.5">เมื่อซิงค์สำเร็จแล้ว รายการที่ยังไม่มี HN ลำดับถัดไปจะเลื่อนขึ้นมาแสดงแทนอัตโนมัติ</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <!-- 🌟 ปุ่มซิงค์ด่วนทั้ง 5 รายการในตารางนี้ -->
                <button type="button" onclick="syncTop5ActionNeeded()" id="btn-sync-top5" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-rotate" id="icon-sync-top5"></i> ซิงค์ทั้ง {{ count($actionNeededPatients) }} รายการนี้
                </button>
                <!-- 🌟 ลิงก์ดูเฉพาะคนที่ยังไม่มี HN ทั้งหมด -->
                <a href="{{ route('backend.patients.index', ['hn_status' => 'unsynced']) }}" class="px-3.5 py-2 bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 rounded-xl text-xs font-bold transition-colors shadow-sm">
                    ดูผู้ที่ยังไม่มี HN ทั้งหมด ({{ number_format($unsyncedPatients) }}) <i class="fa-solid fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-bold border-b border-slate-100">ยศ ชื่อ-นามสกุล</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100">เลขบัตร ปชช.</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100">สังกัด</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100 text-center">สถานะ HN</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100 text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @foreach($actionNeededPatients as $pt)
                    <tr id="reg-row-{{ $pt->id }}" class="hover:bg-slate-50 transition-colors group border-b border-slate-50 last:border-0">
                        <td id="reg-name-{{ $pt->id }}" class="px-6 py-4 font-bold text-slate-800">{{ $pt->rank }}{{ $pt->first_name }} {{ $pt->last_name }}</td>
                        <td class="px-6 py-4 font-medium text-slate-500 font-mono">{{ $pt->cid }}</td>
                        <td class="px-6 py-4 text-slate-500">
                            {{ $pt->militaryUnit->short_name ?? $pt->militaryUnit->name ?? $pt->company ?? '-' }}
                            @if($pt->intake_batch)
                                <span class="text-xs text-indigo-500 font-semibold">(ผลัด {{ $pt->intake_batch }})</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span id="reg-hn-badge-{{ $pt->id }}" class="px-2.5 py-1 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg text-xs font-bold">
                                รอซิงค์ HN
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex items-center justify-end gap-2">
                                <!-- 🌟 ปุ่มกดซิงค์ HosXP ทันทีจากหน้า Dashboard ทะเบียน -->
                                <button type="button" id="btn-reg-sync-{{ $pt->id }}" onclick="syncPatientInRegDashboard({{ $pt->id }})" 
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold transition-colors text-xs shadow-sm">
                                    <i class="fa-solid fa-rotate"></i> ซิงค์ HosXP
                                </button>
                                <a href="{{ route('backend.patients.show', $pt->id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 text-slate-700 hover:bg-blue-600 hover:text-white rounded-lg font-bold transition-colors text-xs shadow-sm">
                                    <i class="fa-solid fa-user-pen"></i> ดูข้อมูล
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
    const activePatients = @json($activePatientsList);
    const top5ActionIds = @json($actionNeededPatients->pluck('id'));
    const patientShowUrlTemplate = "{{ route('backend.patients.show', '__ID__') }}";
    const singleSyncUrlTemplate = "{{ route('backend.patients.sync_hosxp', '__ID__') }}";

    // 🌟 ป้องกันเบราว์เซอร์แสดงหน้าเก่าจากแคช (bfcache) เมื่อกดปุ่ม Back กลับมาหน้านี้
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // 🔄 ฟังก์ชันซิงค์ HosXP รายบุคคลจากตารางหน้า Registration Dashboard
    function syncPatientInRegDashboard(patientId, forceSync = false, updateName = false) {
        const btn = document.getElementById('btn-reg-sync-' + patientId);
        const icon = btn ? btn.querySelector('i') : null;
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        fetch(singleSyncUrlTemplate.replace('__ID__', patientId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ force_sync: forceSync, update_name: updateName })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');

            if (data.status === 'synced') {
                const badge = document.getElementById('reg-hn-badge-' + patientId);
                if (badge) {
                    badge.className = 'px-2.5 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-xs font-black';
                    badge.innerHTML = '<i class="fa-solid fa-check mr-1"></i>HN: ' + data.hn;
                }
                if (data.updated_name) {
                    const nameEl = document.getElementById('reg-name-' + patientId);
                    if (nameEl) nameEl.innerText = data.updated_name;
                }
                if (btn) btn.remove();

                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2500 });
            } else if (data.status === 'mismatch_registrar') {
                Swal.fire({
                    icon: 'warning',
                    title: 'พบข้อมูลชื่อ-นามสกุลไม่ตรงกัน!',
                    html: `
                        <div class="text-left text-sm space-y-3 mt-2">
                            <p class="text-slate-600">เลขบัตร ปชช. <b class="font-mono text-slate-800">${data.cid}</b> พบใน HosXP (<b>HN: ${data.hn}</b>) แต่ชื่อไม่ตรงกัน:</p>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <div class="text-xs text-slate-400 font-bold uppercase">ชื่อในระบบ KSVR MEWS:</div>
                                <div class="text-base font-bold text-slate-800">${data.mews_name}</div>
                            </div>
                            <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-200">
                                <div class="text-xs text-indigo-400 font-bold uppercase">ชื่อในระบบ HosXP รพ.:</div>
                                <div class="text-base font-bold text-indigo-800">${data.hosxp_name}</div>
                            </div>
                        </div>
                    `,
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-user-check mr-1"></i> ผูก HN + ใช้ชื่อตาม HosXP',
                    denyButtonText: 'ผูกเฉพาะ HN (คงชื่อเดิม)',
                    cancelButtonText: 'ข้ามไปก่อน',
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        denyButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        cancelButton: 'bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) syncPatientInRegDashboard(patientId, true, true);
                    else if (result.isDenied) syncPatientInRegDashboard(patientId, true, false);
                });
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: data.message, showConfirmButton: false, timer: 3500 });
            }
        });
    }

    // 🔄 ฟังก์ชันซิงค์ทั้ง 5 รายการในตารางพร้อมกัน
    function syncTop5ActionNeeded(forceSync = false, updateName = false, customIds = null) {
        const ids = customIds || top5ActionIds;
        if (!ids || ids.length === 0) return;

        const btn = document.getElementById('btn-sync-top5');
        const icon = document.getElementById('icon-sync-top5');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        fetch("{{ route('backend.patients.bulk_sync_hosxp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                patient_ids: ids,
                force_sync: forceSync,
                update_name: updateName
            })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');

            if (data.status === 'success') {
                if (data.mismatched && data.mismatched.length > 0 && data.is_registrar_admin) {
                    const mismatchIds = data.mismatched.map(m => m.id);
                    let listHtml = `<div class="text-left text-xs space-y-2 mt-3 max-h-[40vh] overflow-y-auto pr-1">`;
                    data.mismatched.forEach(m => {
                        listHtml += `
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl">
                                <div class="font-bold text-slate-800">ปชช: <span class="font-mono">${m.cid}</span> <span class="text-blue-600 ml-1">(HN: ${m.hn})</span></div>
                                <div class="text-slate-600 mt-1">ระบบ MEWS: <b>${m.mews_name}</b></div>
                                <div class="text-indigo-700">ระบบ HosXP: <b>${m.hosxp_name}</b></div>
                            </div>
                        `;
                    });
                    listHtml += `</div>`;

                    Swal.fire({
                        icon: 'warning',
                        title: `ซิงค์สำเร็จ ${data.synced_count} ราย | ชื่อไม่ตรง ${data.mismatched.length} ราย`,
                        html: `พบรายชื่อไม่ตรงกับ HosXP ดังนี้:${listHtml}`,
                        width: '36rem',
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'ผูก HN + ใช้ชื่อตาม HosXP',
                        denyButtonText: 'ผูกเฉพาะ HN',
                        cancelButtonText: 'ข้ามไปก่อน',
                        customClass: {
                            popup: 'rounded-3xl p-6',
                            confirmButton: 'bg-indigo-600 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                            denyButton: 'bg-emerald-600 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                            cancelButton: 'bg-slate-200 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) syncTop5ActionNeeded(true, true, mismatchIds);
                        else if (result.isDenied) syncTop5ActionNeeded(true, false, mismatchIds);
                        else if (data.synced_count > 0) window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: data.synced_count > 0 ? 'success' : 'info',
                        title: `ซิงค์สำเร็จ ${data.synced_count} รายการ`,
                        text: data.not_found_count > 0 ? `ยังไม่พบประวัติใน HosXP จำนวน ${data.not_found_count} รายการ` : 'อัปเดตข้อมูลเรียบร้อยแล้ว',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        if (data.synced_count > 0) window.location.reload();
                    });
                }
            }
        });
    }

    function showActivePatientsModal() {
        if (activePatients.length === 0) {
            Swal.fire({
                icon: 'success',
                title: 'เยี่ยมมาก!',
                text: 'ขณะนี้ไม่มีเคส MEWS ที่ค้างอยู่ในระบบ',
                confirmButtonColor: '#10b981'
            });
            return;
        }

        let htmlContent = '<div class="space-y-3 mt-4 text-left max-h-[60vh] overflow-y-auto pr-1">';
        
        activePatients.forEach(function(mews) {
            let patient = mews.patient;
            let patientUrl = patientShowUrlTemplate.replace('__ID__', patient.id);
            
            let unitName = 'ไม่ระบุกองร้อย';
            if (patient.military_unit && (patient.military_unit.short_name || patient.military_unit.name)) {
                unitName = patient.military_unit.short_name || patient.military_unit.name;
                if (patient.battalion) unitName += ' (' + patient.battalion + ')';
            } else if (patient.company) {
                unitName = patient.company;
                if (patient.battalion) unitName += ' (' + patient.battalion + ')';
            }

            let hnBadge = patient.hn
                ? `<span class="bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-md text-xs font-bold border border-blue-200">HN: ${patient.hn}</span>` 
                : `<span class="bg-rose-100 text-rose-700 px-2.5 py-0.5 rounded-md text-xs font-bold border border-rose-200 animate-pulse">ยังไม่มี HN</span>`;

            let actionButtonsHtml = !patient.hn ? `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-slate-200/70">
                    <button type="button" onclick="copyToClipboard('${patient.cid || ''}')" class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 py-2 px-3 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้ ปชช.
                    </button>
                    <a href="${patientUrl}" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-3 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-user-pen"></i> ดูข้อมูล / อัปเดต HN
                    </a>
                </div>
            ` : `
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t border-slate-200/70">
                    <button type="button" onclick="copyToClipboard('${patient.cid || ''}')" class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 py-2 px-2.5 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้ ปชช.
                    </button>
                    <button type="button" onclick="copyToClipboard('${patient.hn}')" class="w-full bg-blue-100 hover:bg-blue-200 text-blue-700 py-2 px-2.5 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้ HN
                    </button>
                    <a href="${patientUrl}" class="w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 py-2 px-2.5 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1">
                        <i class="fa-solid fa-address-card"></i> ดูข้อมูลพลทหาร
                    </a>
                </div>
            `;

            htmlContent += `
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl hover:bg-white hover:shadow-sm transition-all">
                    <div class="flex justify-between items-start mb-2 gap-2">
                        <a href="${patientUrl}" class="font-bold text-slate-800 hover:text-indigo-600 text-base transition-colors">
                            ${patient.rank || ''}${patient.first_name || ''} ${patient.last_name || ''}
                        </a>
                        ${hnBadge}
                    </div>
                    <div class="text-sm text-slate-600 space-y-1 mb-3">
                        <p><i class="fa-regular fa-id-card w-5 text-center text-slate-400"></i> ปชช: <b class="font-mono">${patient.cid || '-'}</b></p>
                        <p><i class="fa-solid fa-tent w-5 text-center text-slate-400"></i> สังกัด: ${unitName} ${patient.intake_batch ? `<span class="text-xs text-indigo-600 font-bold">(ผลัด ${patient.intake_batch})</span>` : ''}</p>
                        <p class="text-amber-600 text-xs mt-2 font-medium"><i class="fa-regular fa-clock"></i> อัปเดตล่าสุด: ${new Date(mews.updated_at).toLocaleTimeString('th-TH')} น.</p>
                    </div>
                    ${actionButtonsHtml}
                </div>
            `;
        });
        
        htmlContent += '</div>';

        Swal.fire({
            title: '<span class="text-amber-600"><i class="fa-solid fa-clipboard-user mr-2"></i>รายชื่อเคสที่กำลังเปิดอยู่</span>',
            html: htmlContent,
            showCloseButton: true,
            showConfirmButton: false,
            width: '34rem',
            customClass: { container: 'z-[99999]', popup: 'rounded-3xl' }
        });
    }

    function copyToClipboard(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: 'ก๊อปปี้ ' + text + ' แล้ว',
                showConfirmButton: false, timer: 1500
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.Echo) {
            window.Echo.channel('mews-channel')
                .listen('.mews.updated', (e) => {
                    window.location.reload();
                });
        }
        setInterval(() => {
            window.location.reload();
        }, 180000); 
    });
</script>
@endsection