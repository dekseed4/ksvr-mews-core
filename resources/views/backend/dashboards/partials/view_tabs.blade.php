@php
    $tabUser = auth()->user();
    $rawTabRoles = $tabUser ? ($tabUser->roles ?? $tabUser->role ?? []) : [];
    $tabRoles = is_string($rawTabRoles)
        ? (json_decode($rawTabRoles, true) ?: [$rawTabRoles])
        : (array) $rawTabRoles;

    $tabIsAdmin = in_array('admin', $tabRoles, true);
    $currentTabView = request()->query('view', 'executive');

    $availableViews = [];
    if ($tabIsAdmin || in_array('executive', $tabRoles, true)) {
        $availableViews[] = [
            'key'        => 'executive',
            'label'      => 'ผู้บริหาร',
            'icon'       => 'fa-chart-pie',
            'active_cls' => 'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-200 border-indigo-300 dark:border-indigo-700',
            'icon_cls'   => 'bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-300',
            'dot_cls'    => 'bg-indigo-500',
        ];
    }
    if ($tabIsAdmin || in_array('er_nurse', $tabRoles, true)) {
        $availableViews[] = [
            'key'        => 'er',
            'label'      => 'ER',
            'icon'       => 'fa-truck-medical',
            'active_cls' => 'bg-rose-50 dark:bg-rose-900/40 text-rose-700 dark:text-rose-200 border-rose-300 dark:border-rose-700',
            'icon_cls'   => 'bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300',
            'dot_cls'    => 'bg-rose-500',
        ];
    }
    if ($tabIsAdmin || in_array('opd_nurse', $tabRoles, true)) {
        $availableViews[] = [
            'key'        => 'opd',
            'label'      => 'OPD',
            'icon'       => 'fa-stethoscope',
            'active_cls' => 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700',
            'icon_cls'   => 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-300',
            'dot_cls'    => 'bg-emerald-500',
        ];
    }
    if ($tabIsAdmin || in_array('screening', $tabRoles, true)) {
        $availableViews[] = [
            'key'        => 'screening',
            'label'      => 'จุดคัดกรอง',
            'icon'       => 'fa-clipboard-check',
            'active_cls' => 'bg-amber-50 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700',
            'icon_cls'   => 'bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-300',
            'dot_cls'    => 'bg-amber-500',
        ];
    }
    if ($tabIsAdmin || in_array('registration', $tabRoles, true)) {
        $availableViews[] = [
            'key'        => 'registration',
            'label'      => 'ทะเบียน',
            'icon'       => 'fa-id-card',
            'active_cls' => 'bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-200 border-sky-300 dark:border-sky-700',
            'icon_cls'   => 'bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-300',
            'dot_cls'    => 'bg-sky-500',
        ];
    }
    if ($tabIsAdmin || in_array('medic', $tabRoles, true)) {
        $availableViews[] = [
            'key'        => 'medic',
            'label'      => 'เสนารักษ์',
            'icon'       => 'fa-briefcase-medical',
            'active_cls' => 'bg-teal-50 dark:bg-teal-900/40 text-teal-700 dark:text-teal-200 border-teal-300 dark:border-teal-700',
            'icon_cls'   => 'bg-teal-100 dark:bg-teal-900/60 text-teal-600 dark:text-teal-300',
            'dot_cls'    => 'bg-teal-500',
        ];
    }
@endphp

@if(count($availableViews) > 1)
<style>
    .ksvr-tabs-scroll::-webkit-scrollbar { display: none; }
    .ksvr-tabs-scroll { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="relative bg-white dark:bg-slate-800 rounded-3xl p-2 shadow-sm border border-slate-100 dark:border-slate-700 flex items-center gap-1.5 min-w-0">

    <!-- ป้ายหัวข้อ "มุมมอง" (แสดงเฉพาะจอใหญ่) -->
    <div class="hidden lg:flex items-center gap-2 pl-3 pr-2 text-xs font-bold text-slate-400 dark:text-slate-400 shrink-0 border-r border-slate-100 dark:border-slate-700">
        <i class="fa-solid fa-layer-group"></i>
        <span>มุมมอง</span>
    </div>

    <!-- ปุ่มเลื่อนซ้าย (แสดงอัตโนมัติเมื่อหน้าจอแคบและมีเมนูซ่อนอยู่ทางซ้าย) -->
    <button type="button" id="ksvr-viewtabs-prev" onclick="ksvrScrollViewTabs(-180)"
            class="hidden w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 hover:bg-emerald-500 hover:text-white text-slate-600 dark:text-slate-200 items-center justify-center shrink-0 transition-colors shadow-sm"
            title="เลื่อนไปทางซ้าย" aria-label="เลื่อนซ้าย">
        <i class="fa-solid fa-chevron-left text-xs"></i>
    </button>

    <!-- แทร็กเลื่อนรายการแท็บ (รองรับทั้งปัดนิ้วบนมือถือ + หมุนลูกกลิ้งเมาส์ + ลากเมาส์) -->
    <div id="ksvr-viewtabs-track"
         class="ksvr-tabs-scroll flex-1 min-w-0 flex items-center gap-2 overflow-x-auto flex-nowrap scroll-smooth touch-pan-x px-1 py-0.5 select-none cursor-grab active:cursor-grabbing">
        @foreach($availableViews as $tab)
            @php $isActiveTab = ($currentTabView === $tab['key']); @endphp
            <a href="{{ route('backend.dashboard', ['view' => $tab['key']]) }}"
               data-active-tab="{{ $isActiveTab ? '1' : '0' }}"
               draggable="false"
               class="shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl text-xs sm:text-sm font-bold border transition-all whitespace-nowrap {{ $isActiveTab ? $tab['active_cls'] . ' shadow-sm' : 'bg-slate-50/80 dark:bg-slate-900/60 hover:bg-slate-100 dark:hover:bg-slate-700/80 text-slate-600 dark:text-slate-300 border-transparent' }}">
                <span class="w-7 h-7 rounded-xl flex items-center justify-center text-xs shrink-0 {{ $isActiveTab ? $tab['icon_cls'] : 'bg-slate-200/70 dark:bg-slate-800 text-slate-500 dark:text-slate-400' }}">
                    <i class="fa-solid {{ $tab['icon'] }}"></i>
                </span>
                <span>{{ $tab['label'] }}</span>
                @if($isActiveTab)
                    <span class="w-1.5 h-1.5 rounded-full {{ $tab['dot_cls'] }} shrink-0"></span>
                @endif
            </a>
        @endforeach
    </div>

    <!-- ปุ่มเลื่อนขวา (แสดงอัตโนมัติเมื่อหน้าจอแคบและมีเมนูซ่อนอยู่ทางขวา) -->
    <button type="button" id="ksvr-viewtabs-next" onclick="ksvrScrollViewTabs(180)"
            class="hidden w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 hover:bg-emerald-500 hover:text-white text-slate-600 dark:text-slate-200 items-center justify-center shrink-0 transition-colors shadow-sm"
            title="เลื่อนไปทางขวา" aria-label="เลื่อนขวา">
        <i class="fa-solid fa-chevron-right text-xs"></i>
    </button>

</div>

@once
<script>
    window.ksvrScrollViewTabs = function(deltaX) {
        const track = document.getElementById('ksvr-viewtabs-track');
        if (track) {
            track.scrollBy({ left: deltaX, behavior: 'smooth' });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        const track   = document.getElementById('ksvr-viewtabs-track');
        const prevBtn = document.getElementById('ksvr-viewtabs-prev');
        const nextBtn = document.getElementById('ksvr-viewtabs-next');
        if (!track) return;

        const updateArrows = () => {
            const hasOverflow = track.scrollWidth > track.clientWidth + 4;
            const canLeft  = hasOverflow && track.scrollLeft > 4;
            const canRight = hasOverflow && (track.scrollLeft + track.clientWidth < track.scrollWidth - 4);

            if (prevBtn) {
                prevBtn.classList.toggle('hidden', !canLeft);
                prevBtn.classList.toggle('flex', canLeft);
            }
            if (nextBtn) {
                nextBtn.classList.toggle('hidden', !canRight);
                nextBtn.classList.toggle('flex', canRight);
            }
        };

        // 1. เลื่อนปุ่มที่กำลังเปิดอยู่ (Active) เข้ามาให้อยู่ในกรอบอัตโนมัติ
        const activeTab = track.querySelector('[data-active-tab="1"]');
        if (activeTab) {
            activeTab.scrollIntoView({ behavior: 'auto', inline: 'center', block: 'nearest' });
        }
        updateArrows();

        track.addEventListener('scroll', updateArrows, { passive: true });
        window.addEventListener('resize', updateArrows);

        // 2. รองรับการหมุนลูกกลิ้งเมาส์ขึ้น-ลง เพื่อเลื่อนแถบเมนูซ้าย-ขวาบนคอมพิวเตอร์/PWA
        track.addEventListener('wheel', function (e) {
            if (track.scrollWidth > track.clientWidth + 2 && Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                e.preventDefault();
                track.scrollLeft += e.deltaY;
            }
        }, { passive: false });

        // 3. รองรับการคลิกค้างแล้วลากซ้าย-ขวาด้วยเมาส์ (ไม่รบกวนการคลิกเปลี่ยนหน้าปกติ)
        let isDragging = false;
        let hasDragged = false;
        let startX = 0;
        let startScrollLeft = 0;

        track.addEventListener('mousedown', (e) => {
            isDragging = true;
            hasDragged = false;
            startX = e.pageX - track.offsetLeft;
            startScrollLeft = track.scrollLeft;
        });

        window.addEventListener('mouseup', () => { isDragging = false; });

        track.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const x = e.pageX - track.offsetLeft;
            const walk = x - startX;
            if (Math.abs(walk) > 6) {
                hasDragged = true;
                e.preventDefault();
                track.scrollLeft = startScrollLeft - walk;
            }
        });

        // ป้องกันไม่ให้เผลอเปิดลิงก์ตอนปล่อยเมาส์หลังลากเลื่อน
        track.addEventListener('click', (e) => {
            if (hasDragged) {
                e.preventDefault();
                hasDragged = false;
            }
        }, true);
    });
</script>
@endonce
@endif