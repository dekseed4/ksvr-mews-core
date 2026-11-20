@php
    $tabUser = auth()->user();
    $rawTabRoles = $tabUser ? ($tabUser->roles ?? $tabUser->role ?? []) : [];
    $tabRoles = is_string($rawTabRoles) ? (json_decode($rawTabRoles, true) ?: [$rawTabRoles]) : (array) $rawTabRoles;
    $isTabAdmin = in_array('admin', $tabRoles);

    $currentView = request()->query('view');

    $dashboardTabs = [
        [
            'key'        => 'executive',
            'label'      => 'ผู้บริหาร',
            'icon'       => 'fa-chart-pie',
            'active_icon'=> 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300',
            'active_dot' => 'bg-indigo-500',
            'active_ring'=> 'ring-indigo-200/80 dark:ring-indigo-500/40',
            'allowed'    => $isTabAdmin || in_array('executive', $tabRoles),
        ],
        [
            'key'        => 'er',
            'label'      => 'ER',
            'icon'       => 'fa-truck-medical',
            'active_icon'=> 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
            'active_dot' => 'bg-rose-500',
            'active_ring'=> 'ring-rose-200/80 dark:ring-rose-500/40',
            'allowed'    => $isTabAdmin || in_array('er_nurse', $tabRoles),
        ],
        [
            'key'        => 'opd',
            'label'      => 'OPD',
            'icon'       => 'fa-stethoscope',
            'active_icon'=> 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
            'active_dot' => 'bg-emerald-500',
            'active_ring'=> 'ring-emerald-200/80 dark:ring-emerald-500/40',
            'allowed'    => $isTabAdmin || in_array('opd_nurse', $tabRoles),
        ],
        [
            'key'        => 'screening',
            'label'      => 'จุดคัดกรอง',
            'icon'       => 'fa-clipboard-check',
            'active_icon'=> 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
            'active_dot' => 'bg-amber-500',
            'active_ring'=> 'ring-amber-200/80 dark:ring-amber-500/40',
            'allowed'    => $isTabAdmin || in_array('screening', $tabRoles),
        ],
        [
            'key'        => 'registration',
            'label'      => 'ทะเบียน',
            'icon'       => 'fa-id-card-clip',
            'active_icon'=> 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300',
            'active_dot' => 'bg-blue-500',
            'active_ring'=> 'ring-blue-200/80 dark:ring-blue-500/40',
            'allowed'    => $isTabAdmin || in_array('registration', $tabRoles),
        ],
        [
            'key'        => 'medic',
            'label'      => 'เสนารักษ์',
            'icon'       => 'fa-walkie-talkie',
            'active_icon'=> 'bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300',
            'active_dot' => 'bg-teal-500',
            'active_ring'=> 'ring-teal-200/80 dark:ring-teal-500/40',
            'allowed'    => $isTabAdmin || in_array('medic', $tabRoles),
        ],
    ];

    $visibleTabs = array_values(array_filter($dashboardTabs, fn($t) => $t['allowed']));
@endphp

@if(count($visibleTabs) > 1)
<style>
    .ksvr-tab-scroll::-webkit-scrollbar { display: none; }
    .ksvr-tab-scroll { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="relative w-full">
    <div class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-2xl p-1.5 border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
        <div class="relative">
            <!-- แถบแท็บเลื่อนได้แบบซ่อน Scrollbar -->
            <div id="dashboard-view-tabs" class="ksvr-tab-scroll flex items-center gap-1.5 overflow-x-auto scroll-smooth pr-6 sm:pr-0">
                <span class="hidden lg:inline-flex items-center gap-1.5 pl-3 pr-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider shrink-0 select-none">
                    <i class="fa-solid fa-layer-group text-[10px]"></i> มุมมอง
                </span>

                @foreach($visibleTabs as $tab)
                    @php
                        $isActive = $currentView === $tab['key'];
                    @endphp
                    <a href="{{ route('backend.dashboard', ['view' => $tab['key']]) }}"
                       data-active="{{ $isActive ? '1' : '0' }}"
                       class="group inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all duration-200 shrink-0 {{ $isActive ? 'bg-slate-900/5 dark:bg-slate-700/90 text-slate-900 dark:text-white shadow-sm ring-1 ' . $tab['active_ring'] : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100/80 dark:hover:bg-slate-700/40' }}">
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs transition-colors {{ $isActive ? $tab['active_icon'] : 'bg-slate-100 dark:bg-slate-700/70 text-slate-400 dark:text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200' }}">
                            <i class="fa-solid {{ $tab['icon'] }}"></i>
                        </span>
                        <span>{{ $tab['label'] }}</span>
                        @if($isActive)
                            <span class="w-1.5 h-1.5 rounded-full {{ $tab['active_dot'] }}"></span>
                        @endif
                    </a>
                @endforeach
            </div>

            <!-- เงาไล่ระดับขอบขวาเฉพาะมือถือ บอกใบ้ว่าปัดเลื่อนซ้าย-ขวาได้ -->
            <div class="sm:hidden pointer-events-none absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white dark:from-slate-800 to-transparent rounded-r-xl"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const activeTab = document.querySelector('#dashboard-view-tabs a[data-active="1"]');
        if (activeTab && window.innerWidth < 768) {
            activeTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    });
</script>
@endif