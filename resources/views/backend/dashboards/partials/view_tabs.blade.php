@php
    $tabUser = auth()->user();
    $rawTabRoles = $tabUser ? ($tabUser->roles ?? $tabUser->role ?? []) : [];
    $tabRoles = is_string($rawTabRoles) ? (json_decode($rawTabRoles, true) ?: [$rawTabRoles]) : (array) $rawTabRoles;
    $isTabAdmin = in_array('admin', $tabRoles);

    $currentView = request()->query('view');

    $dashboardTabs = [
        [
            'key'     => 'executive',
            'label'   => 'ผู้บริหาร',
            'icon'    => 'fa-chart-pie',
            'allowed' => $isTabAdmin || in_array('executive', $tabRoles),
        ],
        [
            'key'     => 'er',
            'label'   => 'ER',
            'icon'    => 'fa-truck-medical',
            'allowed' => $isTabAdmin || in_array('er_nurse', $tabRoles),
        ],
        [
            'key'     => 'opd',
            'label'   => 'OPD',
            'icon'    => 'fa-stethoscope',
            'allowed' => $isTabAdmin || in_array('opd_nurse', $tabRoles),
        ],
        [
            'key'     => 'screening',
            'label'   => 'จุดคัดกรอง',
            'icon'    => 'fa-clipboard-check',
            'allowed' => $isTabAdmin || in_array('screening', $tabRoles),
        ],
        [
            'key'     => 'registration',
            'label'   => 'ทะเบียน',
            'icon'    => 'fa-id-card-clip',
            'allowed' => $isTabAdmin || in_array('registration', $tabRoles),
        ],
        [
            'key'     => 'medic',
            'label'   => 'เสนารักษ์',
            'icon'    => 'fa-walkie-talkie',
            'allowed' => $isTabAdmin || in_array('medic', $tabRoles),
        ],
        
    ];

    $visibleTabs = array_values(array_filter($dashboardTabs, fn($t) => $t['allowed']));
@endphp

@if(count($visibleTabs) > 1)
<div class="flex justify-start sm:justify-start overflow-x-auto pb-1 -mx-1 px-1">
    <div class="inline-flex items-center gap-1 p-1.5 rounded-full bg-slate-100/90 dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700 shadow-inner">
        @foreach($visibleTabs as $tab)
            @php
                $isActive = $currentView === $tab['key'];
            @endphp
            <a href="{{ route('backend.dashboard', ['view' => $tab['key']]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs sm:text-sm font-bold whitespace-nowrap transition-all duration-200 {{ $isActive ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200/80 dark:ring-slate-600' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-700/50' }}">
                <i class="fa-solid {{ $tab['icon'] }} text-sm {{ $isActive ? 'text-slate-900 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}"></i>
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif