<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'E-Administration') — E-Administration Connect & Sign</title>
    @php
        $useVite = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    @endphp
    @if($useVite)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @php
        /* ── Theming dynamique selon l'administration de l'utilisateur connecté ── */
        $themUser    = auth()->user();
        $themColor   = '#173b9f';   // couleur principale / sidebar
        $themBgColor = null;        // couleur fond sidebar (optionnel)
        $themFavicon = null;        // favicon dans l'onglet navigateur
        $themHeaderLogo = null;     // logo onglet navigateur

        if ($themUser && $themUser->profile_id) {
            $themProfile = \App\Models\AdministrationProfile::with(['emitterAdministration', 'recipientAdministration'])
                ->find($themUser->profile_id);
            if ($themProfile && $themProfile->administration_id) {
                $themAdminId = $themProfile->administration_id;
                $themAdminType = $themProfile->effective_administration_type ?? 'emitter';
                $themPrefix  = 'theme_' . $themAdminType . '_' . $themAdminId . '_';
                $themSettings = \App\Models\AppSetting::whereIn('key', [
                    $themPrefix . 'menu_color',
                    $themPrefix . 'bg_color',
                    $themPrefix . 'favicon',
                    $themPrefix . 'header_logo',
                ])->pluck('value', 'key');

                $themColor      = $themSettings[$themPrefix.'menu_color']  ?? '#173b9f';
                $themBgColor    = $themSettings[$themPrefix.'bg_color']    ?? null;
                $themFaviconPath = $themSettings[$themPrefix.'favicon']   ?? null;
                $themHeaderPath  = $themSettings[$themPrefix.'header_logo'] ?? null;

                if ($themFaviconPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($themFaviconPath)) {
                    $themFavicon = asset('storage/' . $themFaviconPath);
                }
                if ($themHeaderPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($themHeaderPath)) {
                    $themHeaderLogo = asset('storage/' . $themHeaderPath);
                }
            }
        }
        // Pas de fallback global : un super admin sans administration garde la couleur bleue par défaut
    @endphp

    {{-- Favicon dynamique --}}
    @if(($themFavicon ?? null))
        <link rel="icon" type="image/png" href="{{ $themFavicon }}">
    @elseif(($themHeaderLogo ?? null))
        <link rel="icon" type="image/png" href="{{ $themHeaderLogo }}">
    @else
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='{{ urlencode($themColor ?? '#173b9f') }}'/%3E%3Ctext x='16' y='23' text-anchor='middle' font-family='Arial' font-weight='900' font-size='20' fill='white'%3EE%3C/text%3E%3C/svg%3E">
    @endif

    @php
        $themeColorSafe = $themColor ?? '#173b9f';
        $themeBgSafe = $themBgColor ?? null;
    @endphp

    <style>
        :root {
            --menu-color:   {{ $themeColorSafe }};
            --primary:      {{ $themeColorSafe }};
            --primary-dark: {{ $themeColorSafe }};
        }
        /* ── Sidebar ── */
        .sidebar {
            width: 288px; height: 100vh;
            background-color: var(--menu-color);
            @if($themeBgSafe) /* bg-color sert de couleur de fond alternative */
            background: linear-gradient(160deg, var(--menu-color) 0%, {{ $themeBgSafe }} 100%);
            @endif
            transition: background-color .4s ease;
            display: flex; flex-direction: column;
        }
        .sidebar-nav { flex:1; overflow-y:auto; scrollbar-width:thin; scrollbar-color: rgba(255,255,255,.25) transparent; }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,.25);
            border-radius: 4px;
        }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.4); }
        /* ── Scrollbar globale (zone de contenu) ── */
        * { scrollbar-width: thin; scrollbar-color: #86efac #f1f2f5; }
        ::-webkit-scrollbar { width: 7px; height: 7px; }
        ::-webkit-scrollbar-track { background: #f1f2f5; border-radius: 8px; }
        ::-webkit-scrollbar-thumb { background: #86efac; border-radius: 8px; border: 2px solid #f1f2f5; }
        ::-webkit-scrollbar-thumb:hover { background: #4ade80; }
        .main-content { margin-left: 288px; }
        @media(max-width:768px){ .sidebar{display:none} .main-content{margin-left:0} }
        .nav-link {
            display:flex; align-items:center; gap:12px;
            padding:10px 20px; border-radius:12px;
            font-size:.9rem; font-weight:500;
            transition:background .15s;
            color:rgba(255,255,255,.85); text-decoration:none;
        }
        .nav-link:hover { background:rgba(255,255,255,.1); }
        .nav-link.active {
            background:rgba(255,255,255,.18);
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.28);
            color:#fff;
        }
        .nav-link i { width:20px; text-align:center; font-size:.9rem; opacity:.75; }
        .nav-link.active i { opacity:1; }
        .nav-section {
            font-size:.7rem; font-weight:700;
            letter-spacing:.08em; text-transform:uppercase;
            color:rgba(255,255,255,.4); padding:12px 20px 4px;
        }
        /* ── Couleur principale → boutons, focus rings ── */
        .btn-primary, button[type="submit"].btn-primary {
            background-color: var(--primary) !important;
        }
        .btn-primary:hover { filter: brightness(.9); }
        /* Tailwind override pour les boutons bg-[#2453d6] et bg-indigo-* */
        a[class*="bg-[#2453d6]"], button[class*="bg-[#2453d6]"] {
            background-color: var(--primary) !important;
        }
        a[class*="bg-indigo-6"], button[class*="bg-indigo-6"] {
            background-color: var(--primary) !important;
        }
    </style>
</head>
<body class="bg-[#f1f2f5] text-gray-800">

<!-- Sidebar -->
<aside class="sidebar fixed top-0 left-0 text-white z-40">

    {{-- Logo --}}
    @php
        $sidebarLogo        = null;
        $sidebarAdminName   = null;
        $sidebarAdminCode   = null;
        $sidebarDisplayName = 'E-Administration';
        $sidebarUser        = auth()->user();
        if ($sidebarUser && $sidebarUser->profile_id) {
            $sidebarProfile = \App\Models\AdministrationProfile::with(['emitterAdministration', 'recipientAdministration'])
                                ->find($sidebarUser->profile_id);
            if ($sidebarProfile && $sidebarProfile->resolved_administration) {
                $sidebarAdmin     = $sidebarProfile->resolved_administration;
                $sidebarAdminName = $sidebarAdmin->name;
                $sidebarAdminCode = $sidebarAdmin->code ?? null;

                // 1) Libellé affiché : code administration en priorité
                $sidebarDisplayName = ($sidebarAdminCode && trim($sidebarAdminCode) !== '')
                    ? trim($sidebarAdminCode)
                    : ($sidebarAdminName ?: 'E-Administration');

                // 2) Contrôle strict: le logo du menu doit provenir uniquement
                // de l'administration résolue du profil connecté.
                $rawLogoField = $sidebarAdmin->logo ?? null;
                if (!$rawLogoField && isset($sidebarAdmin->metadata['logoPath'])) {
                    $rawLogoField = $sidebarAdmin->metadata['logoPath'];
                }
                if ($rawLogoField) {
                    $rawLogo = ltrim($rawLogoField, '/');
                    if (str_starts_with($rawLogo, 'storage/')) {
                        $storageRel = ltrim(substr($rawLogo, strlen('storage/')), '/');
                        if ($storageRel !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($storageRel)) {
                            $sidebarLogo = asset($rawLogo);
                        }
                    } elseif (str_starts_with($rawLogo, 'images/')) {
                        if (file_exists(public_path($rawLogo))) {
                            $sidebarLogo = asset($rawLogo);
                        }
                    } else {
                        $fallbackRaw = 'images/logos/' . basename($rawLogo);
                        if (file_exists(public_path($fallbackRaw))) {
                            $sidebarLogo = asset($fallbackRaw);
                        }
                    }
                }
            }
        }
    @endphp
    <div class="px-5 py-5 flex-shrink-0 border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="h-11 w-11 rounded-xl bg-white/90 overflow-hidden flex items-center justify-center flex-shrink-0 shadow-sm">
                @if($sidebarLogo)
                    <img src="{{ $sidebarLogo }}" alt="{{ $sidebarAdminName }}"
                         class="h-full w-full object-contain p-0.5">
                @else
                    <span class="font-black text-xl leading-none select-none" style="color:var(--menu-color)">E</span>
                @endif
            </div>
            <div class="min-w-0">
                <div class="text-base font-bold leading-tight truncate">
                    {{ $sidebarDisplayName }}
                </div>
                <div class="text-blue-200 text-[11px] leading-tight">Connect &amp; Sign</div>
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="sidebar-nav px-4 space-y-1 py-2">
    @php
        $permSvc = app(\App\Services\UserPermissionsService::class);
        $authUser = auth()->user();
        $canMenu = fn($key) => $authUser ? $permSvc->can($authUser, $key) : false;
    @endphp

        @if($canMenu('dashboard'))
        <a href="{{ route('dashboard') }}"
           class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i> {{ __('navigation.dashboard') }}
        </a>
        @endif

        @if($canMenu('courrier'))
        @php
            $courrierEntryRoute = route('courrier.enregistrement');
            foreach (['enregistrement', 'liste', 'imputation', 'en-traitement', 'suivi-imputation', 'traite', 'archives'] as $subtabKey) {
                if ($canMenu('courrier.' . $subtabKey)) {
                    $courrierEntryRoute = route('courrier.' . $subtabKey);
                    break;
                }
            }
        @endphp
        <a href="{{ $courrierEntryRoute }}"
           class="nav-link {{ request()->routeIs('courrier.*') ? 'active' : '' }}">
            <i class="fas fa-envelope-open"></i> {{ __('navigation.mail_management') }}
        </a>
        @endif

        @if($canMenu('documents'))
        <a href="{{ route('documents.index') }}"
           class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}">
            <i class="fas fa-file-alt"></i> {{ __('navigation.my_documents') }}
        </a>
        @endif

        @if($canMenu('templates-shared'))
        <a href="{{ route('shared-templates.index') }}"
           class="nav-link {{ request()->routeIs('shared-templates.*') ? 'active' : '' }}">
            <i class="fas fa-layer-group"></i> {{ __('navigation.shared_templates') }}
        </a>
        @endif

        @if($canMenu('workflows'))
        <a href="{{ route('workflows.index') }}"
           class="nav-link {{ request()->routeIs('workflows.*') ? 'active' : '' }}">
            <i class="fas fa-code-branch"></i> {{ __('navigation.workflows') }}
        </a>
        @endif

        @if($canMenu('signatures'))
        <a href="{{ route('signatures.index') }}"
           class="nav-link {{ request()->routeIs('signatures.*') ? 'active' : '' }}">
            <i class="fas fa-pen-nib"></i> {{ __('navigation.signatures') }}
        </a>
        @endif

        @if($canMenu('reception'))
        <a href="{{ route('reception.index') }}"
           class="nav-link {{ request()->routeIs('reception.*') ? 'active' : '' }}">
            <i class="fas fa-inbox"></i> {{ __('navigation.reception') }}
        </a>
        @endif

        @if($canMenu('act-requests'))
        <a href="{{ route('act-requests.index') }}"
           class="nav-link {{ request()->routeIs('act-requests.*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list"></i> {{ __('navigation.act_requests') }}
        </a>
        @endif

        @if($canMenu('personnel'))
        <a href="{{ route('admin.index', ['tab' => 'personnel']) }}"
           class="nav-link {{ request()->routeIs('admin.*') && request('tab') === 'personnel' ? 'active' : '' }}">
            <i class="fas fa-id-badge"></i> Gestion du personnel
        </a>
        @endif

        @if($canMenu('meetings'))
        <a href="{{ route('meetings.index') }}"
           class="nav-link {{ request()->routeIs('meetings.*') || request()->routeIs('meetings.rooms.*') ? 'active' : '' }}">
            <i class="fas fa-people-group"></i> Réunions
        </a>
        @endif

        @if($canMenu('qrcode'))
        <a href="{{ route('qr-verification.index') }}"
           class="nav-link {{ request()->routeIs('qr-verification.*') ? 'active' : '' }}">
            <i class="fas fa-qrcode"></i> {{ __('navigation.qr_verification') }}
        </a>
        @endif

        @if($canMenu('administration'))
        <div class="nav-section">{{ __('navigation.administration') }}</div>
        <a href="{{ route('admin.index') }}"
           class="nav-link {{ request()->routeIs('admin.*') && request('tab') !== 'personnel' ? 'active' : '' }}">
            <i class="fas fa-shield-alt"></i> {{ __('navigation.administration') }}
        </a>
        @endif

    </nav>

</aside>

<!-- Main -->
<div class="main-content min-h-screen flex flex-col">

    <!-- Topbar -->
    <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between sticky top-0 z-30 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 leading-tight">@yield('page-title', 'Tableau de bord')</h1>
            <p class="text-sm text-gray-400 mt-0.5">@yield('page-subtitle', '')</p>
        </div>
        <div class="flex items-center gap-4 flex-shrink-0">
            @if(session('success'))
                <span class="text-green-700 text-sm bg-green-50 border border-green-200 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                    <i class="fas fa-check-circle text-green-500 text-xs"></i>{{ session('success') }}
                </span>
            @endif
            @if(session('error'))
                <span class="text-red-700 text-sm bg-red-50 border border-red-200 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                    <i class="fas fa-exclamation-circle text-red-500 text-xs"></i>{{ session('error') }}
                </span>
            @endif

            {{-- Cloche notifications (dropdown live) --}}
            <div class="relative" id="notif-wrap">
                <button onclick="toggleNotifPanel(event)"
                    class="relative h-9 w-9 flex items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-gray-100 transition focus:outline-none"
                    id="notif-btn" aria-label="Notifications">
                    <i class="fas fa-bell text-sm"></i>
                    <span id="notif-badge"
                        class="hidden absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"></span>
                </button>

                {{-- Dropdown panel --}}
                <div id="notif-panel"
                    class="hidden absolute right-0 top-12 w-96 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden"
                    style="max-height:480px">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                        <span class="text-sm font-semibold text-gray-800">{{ __('messages.notifications') }}</span>
                        <button onclick="markAllNotifRead()" class="text-xs text-blue-600 hover:underline">{{ __('messages.mark_all_read') }}</button>
                    </div>
                    <div id="notif-list" class="overflow-y-auto" style="max-height:360px">
                        <div class="flex items-center justify-center py-10 text-gray-400 text-xs" id="notif-empty">
                            <i class="fas fa-bell-slash mr-2"></i> {{ __('messages.no_notifications') }}
                        </div>
                    </div>
                    <div class="border-t border-gray-100 px-4 py-2 text-center">
                        <a href="{{ route('notifications.index') }}" class="text-xs text-blue-600 hover:underline">{{ __('messages.see_all_notifications') }}</a>
                    </div>
                </div>
            </div>

            {{-- Profil dropdown --}}
            <div class="relative" id="profile-menu-wrap">
                <button onclick="toggleProfileMenu()" id="profile-btn"
                    class="h-9 w-9 rounded-full overflow-hidden border-2 border-[#2453d6]/30 hover:border-[#2453d6] transition flex items-center justify-center font-bold text-sm bg-[#2453d6]/10 text-[#2453d6] flex-shrink-0 focus:outline-none">
                    @if(auth()->user()->avatar && file_exists(public_path(auth()->user()->avatar)))
                        <img src="{{ asset(auth()->user()->avatar) }}" alt="avatar" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                    @endif
                </button>

                {{-- Dropdown --}}
                <div id="profile-dropdown"
                    class="hidden absolute right-0 top-12 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden">

                    {{-- En-tete --}}
                    <div class="px-4 py-4 bg-gradient-to-r from-[#2453d6] to-blue-500 flex items-center gap-3">
                        <div class="relative group flex-shrink-0">
                            <div class="h-12 w-12 rounded-full overflow-hidden bg-white/20 flex items-center justify-center font-bold text-white text-lg border-2 border-white/40">
                                @if(auth()->user()->avatar && file_exists(public_path(auth()->user()->avatar)))
                                    <img src="{{ asset(auth()->user()->avatar) }}" alt="avatar" class="h-full w-full object-cover">
                                @else
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                                @endif
                            </div>
                            <label class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition">
                                <i class="fas fa-camera text-white text-xs"></i>
                                <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" id="avatar-form">
                                    @csrf
                                    <input type="file" name="avatar" accept="image/*" class="hidden" onchange="document.getElementById('avatar-form').submit()">
                                </form>
                            </label>
                        </div>
                        <div class="overflow-hidden min-w-0">
                            <p class="text-white font-semibold text-sm truncate">{{ auth()->user()->name }}</p>
                            <p class="text-blue-200 text-xs truncate">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-0.5 px-2 py-0.5 bg-white/20 rounded-full text-[10px] text-white font-medium">{{ ucfirst(auth()->user()->role ?? 'user') }}</span>
                        </div>
                    </div>

                    {{-- Corps --}}
                    <div class="divide-y divide-gray-50 px-2 py-2">

                        {{-- Nom d'affichage --}}
                        <form method="POST" action="{{ route('profile.display-name') }}" class="px-2 py-3" onsubmit="closeProfileMenu()">
                            @csrf
                            <label class="block text-[11px] text-gray-500 font-medium mb-1.5 uppercase tracking-wide">{{ __('messages.display_name') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="text" name="name" value="{{ auth()->user()->name }}"
                                    class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-300"
                                    placeholder="{{ __('messages.your_name') }}">
                                <button type="submit"
                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition">
                                    {{ __('buttons.ok') }}
                                </button>
                            </div>
                        </form>

                        {{-- Mot de passe --}}
                        <div class="px-2 py-3">
                            <button onclick="togglePwdPanel()" class="w-full flex items-center gap-2 text-sm text-gray-700 hover:text-blue-600 transition font-medium">
                                <i class="fas fa-lock text-gray-400 w-4"></i>
                                {{ __('buttons.change_password') }}
                                <i class="fas fa-chevron-down text-xs ml-auto" id="pwd-chevron"></i>
                            </button>
                            <div id="pwd-panel" class="hidden mt-2">
                                <form method="POST" action="{{ route('profile.password') }}" onsubmit="closeProfileMenu()">
                                    @csrf
                                    <div class="space-y-2">
                                        <input type="password" name="current_password" placeholder="{{ __('auth.current_password') }}"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                                        <input type="password" name="password" placeholder="{{ __('auth.new_password') }}"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                                        <input type="password" name="password_confirmation" placeholder="{{ __('auth.confirm_password') }}"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                                        <button type="submit"
                                            class="w-full py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition">
                                            {{ __('buttons.save_password') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Langue --}}
                        @php $currentLang = session('locale', config('app.locale', 'fr')); @endphp
                        <form method="POST" action="{{ route('profile.language') }}" class="px-2 py-3">
                            @csrf
                            <label class="block text-[11px] text-gray-500 font-medium mb-1.5 uppercase tracking-wide">{{ __('messages.language') }}</label>
                            <div class="flex items-center gap-2">
                                <select name="locale" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                                    <option value="fr" {{ $currentLang === 'fr' ? 'selected' : '' }}>&#127467;&#127479; Fran&ccedil;ais</option>
                                    <option value="en" {{ $currentLang === 'en' ? 'selected' : '' }}>&#127468;&#127463; English</option>
                                    <option value="es" {{ $currentLang === 'es' ? 'selected' : '' }}>&#127469;&#127480; Español</option>
                                    <option value="pt" {{ $currentLang === 'pt' ? 'selected' : '' }}>&#127479;&#127481; Português</option>
                                    <option value="ar" {{ $currentLang === 'ar' ? 'selected' : '' }}>&#127462;&#127466; العربية</option>
                                </select>
                                <button type="submit"
                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition">
                                    {{ __('buttons.ok') }}
                                </button>
                            </div>
                        </form>

                        {{-- Liens --}}
                        <div class="px-2 py-2 space-y-0.5">
                            <a href="{{ route('profile.edit') }}" onclick="closeProfileMenu()"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-user-circle text-gray-400 w-4"></i>
                                {{ __('navigation.my_profile') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-red-500 hover:bg-red-50 transition">
                                    <i class="fas fa-sign-out-alt w-4"></i>
                                    {{ __('messages.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Traductions disponibles en JavaScript (window.__trans) --}}
    <script>
    window.__trans = {
        new: "{{ __('messages.new') }}",
        save: "{{ __('messages.save') }}",
        cancel: "{{ __('messages.cancel') }}",
        delete: "{{ __('messages.delete') }}",
        edit: "{{ __('messages.edit') }}",
        confirm: "{{ __('messages.confirm') }}",
        close: "{{ __('messages.close') }}",
        loading: "{{ __('messages.loading') }}",
        search: "{{ __('messages.search') }}",
        error: "{{ __('messages.error') }}",
        success: "{{ __('messages.success') }}",
        import_files: "{{ __('messages.import_files') }}",
        import_folder: "{{ __('messages.import_folder') }}",
        new_folder: "{{ __('messages.new_folder') }}",
        word_document: "{{ __('messages.word_document') }}",
        no_documents: "{{ __('messages.no_documents') }}",
        see_all: "{{ __('messages.see_all') }}",
        status_signed: "{{ __('messages.status_signed') }}",
        status_draft: "{{ __('messages.status_draft') }}",
        status_pending: "{{ __('messages.status_pending') }}",
        ok: "{{ __('buttons.ok') }}",
        submit: "{{ __('buttons.submit') }}",
        download: "{{ __('buttons.download') }}",
        back: "{{ __('buttons.back') }}",
        locale: "{{ app()->getLocale() }}"
    };
    </script>

    <script>
    function toggleProfileMenu() {
        const dd = document.getElementById('profile-dropdown');
        dd.classList.toggle('hidden');
    }
    function closeProfileMenu() {
        document.getElementById('profile-dropdown').classList.add('hidden');
    }
    function togglePwdPanel() {
        const p = document.getElementById('pwd-panel');
        const c = document.getElementById('pwd-chevron');
        p.classList.toggle('hidden');
        c.classList.toggle('fa-chevron-down');
        c.classList.toggle('fa-chevron-up');
    }
    // Ferme le dropdown si on clique ailleurs
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('profile-menu-wrap');
        if (wrap && !wrap.contains(e.target)) closeProfileMenu();
    });
    </script>
    <main class="flex-1 p-8">
        @if($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
            <ul class="list-disc list-inside space-y-1 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @yield('content')
    </main>
</div>

{{-- ══════════════════════════════════════════════════════
     WIDGET CHAT FLOTTANT
══════════════════════════════════════════════════════ --}}
@php
    $_chatEnabled = \App\Models\AppSetting::where('key','chat_enabled')->value('value');
    $_chatShow    = ($_chatEnabled === null || $_chatEnabled !== '0');
    $_chatMe      = auth()->user();
    if ($_chatMe) {
        $_chatName = $_chatMe->name ?? 'Utilisateur';
        preg_match_all('/\b\w/u', $_chatName, $_m);
        $_chatInit = strtoupper(implode('', array_slice($_m[0], 0, 2)));
        if (!$_chatInit) $_chatInit = strtoupper(substr($_chatName, 0, 2));
    }
@endphp
@if($_chatShow && isset($_chatMe) && $_chatMe)
<div id="chat-widget" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">

    {{-- PANEL --}}
    <div id="chat-panel" class="hidden flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
         style="width:360px;height:530px;">

        {{-- Header --}}
        <div class="bg-[#173b9f] text-white px-4 py-3 flex items-center gap-3 flex-shrink-0">
            <button id="chat-back-btn" onclick="chatBack()" class="hidden text-blue-200 hover:text-white transition mr-1" title="Retour">
                <i class="fas fa-arrow-left text-xs"></i>
            </button>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm truncate" id="chat-header-title">Chat</div>
                <div class="text-xs text-blue-200" id="chat-header-sub">Choisissez un salon</div>
            </div>
            <button onclick="closeChatPanel()" class="text-blue-200 hover:text-white transition text-sm ml-1" title="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- HOME : liste salons / directs --}}
        <div id="chat-home" class="flex flex-col flex-1 overflow-hidden">
            {{-- Onglets --}}
            <div class="flex border-b border-gray-100 flex-shrink-0">
                <button onclick="chatSetTab('salons')" id="tab-salons"
                    class="flex-1 py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 border-b-2 border-[#173b9f] text-[#173b9f] transition">
                    <i class="fas fa-hashtag text-xs"></i> Salons
                </button>
                <button onclick="chatSetTab('directs')" id="tab-directs"
                    class="flex-1 py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 border-b-2 border-transparent text-gray-400 hover:text-gray-700 transition">
                    <i class="fas fa-at text-xs"></i> Directs
                </button>
                <button onclick="chatSetTab('online')" id="tab-online"
                    class="flex-1 py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 border-b-2 border-transparent text-gray-400 hover:text-gray-700 transition">
                    <i class="fas fa-circle text-[8px] text-green-500"></i> Connectés
                </button>
            </div>

            {{-- Liste salons --}}
            <div id="chat-salons-list" class="flex-1 overflow-y-auto py-2">
                @foreach([
                    ['general',    'Général',    'blue',   'fas fa-hashtag', 'Discussions générales'],
                    ['annonces',   'Annonces',   'orange', 'fas fa-bullhorn','Informations officielles'],
                    ['documents',  'Documents',  'green',  'fas fa-file-alt','Partage de documents'],
                    ['signatures', 'Signatures', 'purple', 'fas fa-pen-nib', 'Gestion des signatures'],
                    ['workflows',  'Workflows',  'indigo', 'fas fa-code-branch','Suivi des workflows'],
                ] as [$room, $label, $color, $icon, $desc])
                <button onclick="chatOpenSalon('{{ $room }}','{{ $label }}')"
                    class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition text-left group">
                    <div class="h-9 w-9 rounded-xl bg-{{ $color }}-100 flex items-center justify-center flex-shrink-0">
                        <i class="{{ $icon }} text-{{ $color }}-600 text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800">{{ $label }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $desc }}</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs text-gray-300 group-hover:text-gray-400 transition"></i>
                </button>
                @endforeach
            </div>

            {{-- Liste en ligne par administration --}}
            <div id="chat-online-list" class="hidden flex-1 overflow-y-auto">
                <div id="chat-online-loading" class="px-4 py-6 text-center text-gray-400 text-xs">
                    <i class="fas fa-spinner fa-spin mb-2 block text-lg"></i>Chargement...
                </div>
                <div id="chat-online-empty" class="hidden px-4 py-6 text-center text-gray-400 text-xs">Aucun utilisateur connecté.</div>
                <div id="chat-online-groups" class="py-2"></div>
            </div>

            {{-- Liste directs --}}
            <div id="chat-directs-list" class="hidden flex-col flex-1 overflow-hidden">
                <div class="px-3 py-2 flex-shrink-0">
                    <input type="text" id="chat-user-search" oninput="chatFilterUsers(this.value)"
                        placeholder="Rechercher un utilisateur..."
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div id="chat-users-list" class="flex-1 overflow-y-auto py-1">
                    <div class="px-4 py-8 text-center text-gray-400 text-xs">
                        <i class="fas fa-spinner fa-spin mb-2 block text-lg"></i>Chargement...
                    </div>
                </div>
            </div>
        </div>

        {{-- CONVERSATION --}}
        <div id="chat-conv" class="hidden flex-col flex-1 overflow-hidden">
            <div id="chat-messages" class="flex-1 overflow-y-auto px-3 py-3 space-y-3 bg-gray-50"></div>
            <div class="flex-shrink-0 border-t border-gray-100 bg-white px-3 py-2.5 flex items-center gap-2">
                <div class="h-7 w-7 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                    {{ $_chatInit }}
                </div>
                <textarea id="chat-input" onkeydown="chatHandleKey(event)" oninput="chatAutoResize(this)"
                    placeholder="Écrivez... (Entrée pour envoyer)"
                    rows="1"
                    class="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300 resize-none leading-relaxed max-h-24 overflow-y-auto"></textarea>
                <button onclick="chatSend()"
                    class="h-9 w-9 rounded-xl bg-[#173b9f] text-white flex items-center justify-center hover:bg-blue-700 transition flex-shrink-0">
                    <i class="fas fa-paper-plane text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Bouton flottant --}}
    <button onclick="toggleChatPanel()" id="chat-toggle-btn"
        class="h-14 w-14 rounded-full bg-[#173b9f] text-white shadow-xl hover:bg-blue-700 transition-all flex items-center justify-center relative">
        <i class="fas fa-comments text-xl" id="chat-toggle-icon"></i>
        <span id="chat-unread-badge"
            class="hidden absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">0</span>
    </button>
</div>

<script>
(function() {
    // ── URLs routes (Blade) ───────────────────────────────────
    var CHAT_MESSAGES_URL = '{{ route("chat.messages") }}';
    var CHAT_SEND_URL     = '{{ route("chat.send") }}';
    var CHAT_USERS_URL    = '{{ route("chat.users") }}';
    var CHAT_ONLINE_URL   = '{{ route("chat.online-by-administration") }}';

    // ── État ──────────────────────────────────────────────────
    var _mode     = 'home';  // home | salon | dm
    var _room     = 'general';
    var _roomLabel= 'Général';
    var _recipId  = null;
    var _since    = null;
    var _timer    = null;
    var _users    = [];
    var _tab      = 'salons';
    var _unread   = 0;
    var _open     = false;
    var _COLORS   = ['bg-blue-500','bg-emerald-500','bg-violet-500','bg-rose-500','bg-amber-500','bg-cyan-500'];
    var ME_ID     = '{{ auth()->id() }}';
    var ME_INIT   = '{{ $_chatInit }}';
    var CSRF      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ── Helpers ───────────────────────────────────────────────
    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function bubbleColor(init) {
        var cc = (init||'').charCodeAt(0) + ((init||'').charCodeAt(1)||0);
        return _COLORS[cc % _COLORS.length];
    }
    function scrollBottom() {
        var b = document.getElementById('chat-messages');
        if (b) b.scrollTop = b.scrollHeight;
    }
    function updateBadge() {
        var badge = document.getElementById('chat-unread-badge');
        if (_unread > 0) {
            badge.textContent = _unread > 9 ? '9+' : _unread;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
    function clearUnread() { _unread = 0; updateBadge(); }

    // ── Panel open/close ─────────────────────────────────────
    window.toggleChatPanel = function() {
        _open ? closeChatPanel() : openChatPanel();
    };
    function openChatPanel() {
        _open = true;
        var p = document.getElementById('chat-panel');
        p.classList.remove('hidden'); p.classList.add('flex');
        document.getElementById('chat-toggle-icon').className = 'fas fa-times text-xl';
        clearUnread();
        if (_mode !== 'home') startPoll();
    }
    window.closeChatPanel = function() {
        _open = false;
        var p = document.getElementById('chat-panel');
        p.classList.add('hidden'); p.classList.remove('flex');
        document.getElementById('chat-toggle-icon').className = 'fas fa-comments text-xl';
        stopPoll();
    };

    // ── Tabs ─────────────────────────────────────────────────
    window.chatSetTab = function(tab) {
        _tab = tab;
        var isSalons = tab === 'salons';
        document.getElementById('chat-salons-list').classList.toggle('hidden', !isSalons);
        var dl = document.getElementById('chat-directs-list');
        dl.classList.toggle('hidden', isSalons);
        dl.classList.toggle('flex', !isSalons);
        document.getElementById('tab-salons').className = 'flex-1 py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 border-b-2 transition '
            + (isSalons ? 'border-[#173b9f] text-[#173b9f]' : 'border-transparent text-gray-400 hover:text-gray-700');
        document.getElementById('tab-directs').className = 'flex-1 py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 border-b-2 transition '
            + (!isSalons ? 'border-[#173b9f] text-[#173b9f]' : 'border-transparent text-gray-400 hover:text-gray-700');
        var isOnline  = tab === 'online';
        document.getElementById('chat-online-list').classList.toggle('hidden', !isOnline);
        document.getElementById('tab-online').className = 'flex-1 py-2.5 text-xs font-bold flex items-center justify-center gap-1.5 border-b-2 transition '
            + (isOnline ? 'border-[#173b9f] text-[#173b9f]' : 'border-transparent text-gray-400 hover:text-gray-700');
        document.getElementById('chat-header-sub').textContent = isSalons ? 'Choisissez un salon' : (isOnline ? 'Utilisateurs en ligne' : 'Messages directs');
        if (!isSalons && !isOnline && _users.length === 0) loadUsers();
        if (isOnline) loadOnlineUsers();
    };

    // ── Ouvrir un salon ───────────────────────────────────────
    window.chatOpenSalon = function(room, label) {
        _mode = 'salon'; _room = room; _roomLabel = label;
        _recipId = null; _since = null;
        document.getElementById('chat-header-title').textContent = '# ' + label;
        document.getElementById('chat-header-sub').textContent = 'Salon public';
        showConv();
        clearUnread(); fetchMessages(); startPoll();
        setTimeout(function(){ var i = document.getElementById('chat-input'); if(i) i.focus(); }, 100);
    };

    // ── Ouvrir un DM ─────────────────────────────────────────
    window.chatOpenDm = function(userId, userName, userInit) {
        _mode = 'dm';
        var ids = [ME_ID, userId].sort();
        _room = 'dm_' + ids[0] + '_' + ids[1];
        _roomLabel = userName;
        _recipId = userId;
        _since = null;
        document.getElementById('chat-header-title').textContent = userName;
        document.getElementById('chat-header-sub').textContent = 'Message direct';
        showConv();
        clearUnread(); fetchMessages(); startPoll();
        setTimeout(function(){ var i = document.getElementById('chat-input'); if(i) i.focus(); }, 100);
    };

    function showConv() {
        document.getElementById('chat-home').classList.add('hidden');
        var c = document.getElementById('chat-conv');
        c.classList.remove('hidden'); c.classList.add('flex');
        document.getElementById('chat-back-btn').classList.remove('hidden');
        document.getElementById('chat-messages').innerHTML = '';
    }

    // ── Retour à l'accueil ────────────────────────────────────
    window.chatBack = function() {
        _mode = 'home'; _since = null; stopPoll();
        document.getElementById('chat-conv').classList.add('hidden');
        document.getElementById('chat-conv').classList.remove('flex');
        document.getElementById('chat-home').classList.remove('hidden');
        document.getElementById('chat-back-btn').classList.add('hidden');
        document.getElementById('chat-header-title').textContent = 'Chat';
        document.getElementById('chat-header-sub').textContent = _tab === 'salons' ? 'Choisissez un salon' : 'Messages directs';
    };

    // ── Chargement utilisateurs ───────────────────────────────
    function loadUsers() {
        fetch(CHAT_USERS_URL, { headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(function(r){ return r.json(); })
            .then(function(data){
                _users = data;
                renderUsers(_users);
            }).catch(function(){});
    }
    function renderUsers(users) {
        var el = document.getElementById('chat-users-list');
        if (!users.length) {
            el.innerHTML = '<div class="px-4 py-8 text-center text-gray-400 text-xs">Aucun utilisateur disponible.</div>';
            return;
        }
        el.innerHTML = users.map(function(u){
            var init = String(u.initials||'??').substring(0,2).toUpperCase();
            var cc = (init.charCodeAt(0)||0) + (init.charCodeAt(1)||0);
            var bg = _COLORS[cc % _COLORS.length];
            var safeId   = String(u.id).replace(/'/g,"\\'");
            var safeName = String(u.name||'').replace(/'/g,"\\'");
            return '<button onclick="chatOpenDm(\'' + safeId + '\',\'' + safeName + '\',\'' + init + '\')"'
                + ' class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition text-left group">'
                + '<div class="h-9 w-9 rounded-full ' + bg + ' text-white text-xs font-bold flex items-center justify-center flex-shrink-0">' + esc(init) + '</div>'
                + '<div class="flex-1 min-w-0">'
                + '<p class="text-sm font-semibold text-gray-800">' + esc(u.name||'') + '</p>'
                + '<p class="text-xs text-gray-400">' + esc(u.role||'Utilisateur') + '</p>'
                + '</div>'
                + '<i class="fas fa-chevron-right text-xs text-gray-300 group-hover:text-gray-400 transition"></i>'
                + '</button>';
        }).join('');
    }
    window.chatFilterUsers = function(q) {
        var filtered = _users.filter(function(u){
            return u.name.toLowerCase().indexOf(q.toLowerCase()) !== -1;
        });
        renderUsers(filtered);
    };

    // ── Chargement utilisateurs en ligne par administration ───
    function loadOnlineUsers() {
        var loadingEl = document.getElementById('chat-online-loading');
        var emptyEl   = document.getElementById('chat-online-empty');
        var groupsEl  = document.getElementById('chat-online-groups');
        if (!loadingEl || !groupsEl || !emptyEl) return;

        loadingEl.classList.remove('hidden');
        emptyEl.classList.add('hidden');
        groupsEl.innerHTML = '';

        fetch(CHAT_ONLINE_URL, { headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(function(r){ return r.json(); })
            .then(function(groups){
                var total = 0;
                groups.forEach(function(group) {
                    total += Number(group.count || 0);
                    var usersHtml = (group.users || []).map(function(u) {
                        var init = String(u.initials||'??').substring(0,2).toUpperCase();
                        var cc = (init.charCodeAt(0)||0) + (init.charCodeAt(1)||0);
                        var bg = _COLORS[cc % _COLORS.length];
                        var safeId   = String(u.id).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
                        var safeName = String(u.name||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
                        var isMe = u.is_me ? true : false;
                        var row = '<button'
                            + (isMe ? ' disabled style="cursor:default;opacity:.7;"' : ' onclick="chatOpenDm(\''+safeId+'\',\''+safeName+'\',\''+init+'\')"')
                            + ' class="w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-50 transition text-left">'
                            + '<span style="width:7px;height:7px;border-radius:9999px;background:#22c55e;flex-shrink:0;display:inline-block;"></span>'
                            + '<div class="' + bg + ' h-7 w-7 rounded-full text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">' + esc(init) + '</div>'
                            + '<div class="flex-1 min-w-0 text-left">'
                            + '<p class="text-xs font-semibold text-gray-800 truncate">' + esc(u.name||'') + (isMe ? ' <span class="text-[10px] text-gray-400">(Vous)</span>' : '') + '</p>'
                            + '<p class="text-[10px] text-gray-400">' + esc(u.role||'Utilisateur') + '</p>'
                            + '</div></button>';
                        return row;
                    }).join('');

                    var g = document.createElement('div');
                    g.className = 'border border-gray-100 rounded-xl overflow-hidden mx-2 mb-2';
                    g.innerHTML = '<div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-100">'
                        + '<span class="text-[10px] font-bold text-gray-700 truncate" style="max-width:75%;" title="' + esc(group.administration_name||'') + '">'
                        + esc(group.administration_name || 'Sans administration') + '</span>'
                        + '<span class="text-[10px] text-gray-500 bg-gray-200 rounded-full px-1.5 py-0.5">' + (group.count||0) + '</span>'
                        + '</div>' + usersHtml;
                    groupsEl.appendChild(g);
                });

                document.getElementById('chat-header-sub').textContent = total + ' utilisateur' + (total > 1 ? 's' : '') + ' en ligne';
                if (!groups.length) emptyEl.classList.remove('hidden');
            })
            .catch(function(){})
            .finally(function(){ loadingEl.classList.add('hidden'); });
    }

    // ── Polling messages ──────────────────────────────────────
    function startPoll() {
        stopPoll();
        if (_mode !== 'home') _timer = setInterval(fetchMessages, 2000);
    }
    function stopPoll() {
        if (_timer) { clearInterval(_timer); _timer = null; }
    }
    function fetchMessages() {
        if (_mode === 'home' || !_room) return;
        var url = CHAT_MESSAGES_URL + '?room=' + encodeURIComponent(_room);
        if (_since) url += '&since=' + encodeURIComponent(_since);
        fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(function(r){ return r.json(); })
            .then(function(msgs){
                if (msgs.length) {
                    _since = msgs[msgs.length-1].ts;
                    msgs.forEach(appendMsg);
                    scrollBottom();
                }
            }).catch(function(){});
    }

    function appendMsg(msg) {
        var box = document.getElementById('chat-messages');
        if (!box) return;
        var isMine = msg.mine || msg.sender_id === ME_ID;
        var init = String(msg.initials||'??').substring(0,2).toUpperCase();
        var bg = bubbleColor(init);
        var time = esc(msg.time||'');
        var div = document.createElement('div');
        div.className = 'flex gap-2 ' + (isMine ? 'flex-row-reverse' : 'flex-row');
        if (isMine) {
            div.innerHTML =
                '<div class="max-w-[78%] flex flex-col gap-0.5 items-end">'
                + '<div class="rounded-2xl rounded-tr-sm px-3 py-2 text-sm leading-relaxed break-words bg-[#173b9f] text-white">' + esc(msg.text) + '</div>'
                + '<span class="text-[10px] text-gray-400 px-1">' + time + '</span>'
                + '</div>';
        } else {
            div.innerHTML =
                '<div class="' + bg + ' h-7 w-7 rounded-full text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">' + esc(init) + '</div>'
                + '<div class="max-w-[78%] flex flex-col gap-0.5 items-start">'
                + '<span class="text-[11px] text-gray-500 font-medium px-1">' + esc(msg.name||'') + '</span>'
                + '<div class="rounded-2xl rounded-tl-sm px-3 py-2 text-sm leading-relaxed break-words bg-white shadow-sm border border-gray-100">' + esc(msg.text) + '</div>'
                + '<span class="text-[10px] text-gray-400 px-1">' + time + '</span>'
                + '</div>';
        }
        box.appendChild(div);
        if (!_open) { _unread++; updateBadge(); }
    }

    // ── Envoi ─────────────────────────────────────────────────
    window.chatSend = function() {
        var input = document.getElementById('chat-input');
        var text = input.value.trim();
        if (!text || !_room) return;
        input.value = ''; input.style.height = '';
        var body = { text: text, room: _room };
        if (_recipId) body.recipient_id = _recipId;
        fetch(CHAT_SEND_URL, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With':'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function(r){ return r.json(); })
          .then(function(msg){ if (msg.id) { appendMsg(Object.assign({}, msg, {mine:true})); scrollBottom(); } })
          .catch(function(){});
    };
    window.chatHandleKey = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); chatSend(); }
    };
    window.chatAutoResize = function(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 96) + 'px';
    };

    // ── Badge en arrière-plan (toutes les 20s) ────────────────
    setInterval(function() {
        if (_open) return;
        var since = new Date(Date.now() - 30000).toISOString();
        fetch(CHAT_MESSAGES_URL + '?room=general&since=' + encodeURIComponent(since),
            { headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(function(r){ return r.json(); })
            .then(function(msgs){
                var newOnes = msgs.filter(function(m){ return m.sender_id !== ME_ID; });
                if (newOnes.length) { _unread += newOnes.length; updateBadge(); }
            }).catch(function(){});
    }, 20000);

})();
</script>
@endif

@stack('scripts')

@auth
{{-- ══════════════════════════════════════════════════════════════════════════
     SYSTÈME DE DÉCONNEXION AUTOMATIQUE APRÈS INACTIVITÉ (3 minutes)
     Compte à rebours de 60 s affiché, puis déconnexion + redirection login
     ══════════════════════════════════════════════════════════════════════════ --}}

{{-- Modal compte à rebours --}}
<div id="idle-overlay" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.7);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;box-shadow:0 25px 60px rgba(0,0,0,0.35);padding:40px 48px;max-width:420px;width:90%;text-align:center;position:relative;">
        <div style="width:80px;height:80px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:40px;height:40px;color:#d97706;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h2 style="font-size:1.25rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Session inactive</h2>
        <p style="font-size:0.875rem;color:#64748b;margin:0 0 20px;line-height:1.6;">
            Aucune activité détectée. Vous serez déconnecté(e) dans
        </p>
        <div style="font-size:3.5rem;font-weight:800;color:#dc2626;line-height:1;margin-bottom:8px;">
            <span id="idle-countdown">60</span>
            <span style="font-size:1.25rem;font-weight:500;color:#94a3b8;">s</span>
        </div>
        <div style="background:#f1f5f9;border-radius:8px;height:6px;margin:0 0 28px;overflow:hidden;">
            <div id="idle-progress" style="height:100%;background:linear-gradient(90deg,#3b82f6,#2563eb);width:100%;transition:width 1s linear;"></div>
        </div>
        <button onclick="idleReset()" style="width:100%;padding:12px 24px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:10px;font-size:0.9375rem;font-weight:600;cursor:pointer;margin-bottom:12px;box-shadow:0 4px 12px rgba(59,130,246,0.4);">
            Je suis toujours là
        </button>
        <form id="idle-logout-form" method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" style="width:100%;padding:10px 24px;background:transparent;color:#64748b;border:1px solid #e2e8f0;border-radius:10px;font-size:0.875rem;cursor:pointer;">
                Se déconnecter maintenant
            </button>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';

    var IDLE_TIMEOUT  = 60 * 60;  // 60 minutes en secondes avant d'afficher le modal
    var WARN_DURATION = 60;       // secondes de compte à rebours avant déconnexion auto
    var _idleTimer    = null;
    var _warnTimer    = null;
    var _countdown    = WARN_DURATION;
    var _overlay      = document.getElementById('idle-overlay');
    var _countEl      = document.getElementById('idle-countdown');
    var _progressEl   = document.getElementById('idle-progress');

    function idleShowWarning() {
        _countdown = WARN_DURATION;
        _overlay.style.display = 'flex';
        _countEl.textContent = _countdown;
        _progressEl.style.transition = 'none';
        _progressEl.style.width = '100%';

        // Forcer reflow pour que la transition fonctionne
        void _progressEl.offsetWidth;
        _progressEl.style.transition = 'width 1s linear';
        _progressEl.style.width = '0%';

        clearInterval(_warnTimer);
        _warnTimer = setInterval(function() {
            _countdown--;
            _countEl.textContent = _countdown;
            if (_countdown <= 0) {
                clearInterval(_warnTimer);
                document.getElementById('idle-logout-form').submit();
            }
        }, 1000);
    }

    function idleReset() {
        clearTimeout(_idleTimer);
        clearInterval(_warnTimer);
        _overlay.style.display = 'none';
        _idleTimer = setTimeout(idleShowWarning, IDLE_TIMEOUT * 1000);
    }
    window.idleReset = idleReset;

    // Événements qui réinitialisent l'inactivité
    var EVENTS = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll', 'click'];
    EVENTS.forEach(function(evt) {
        document.addEventListener(evt, idleReset, { passive: true });
    });

    // Démarrage
    _idleTimer = setTimeout(idleShowWarning, IDLE_TIMEOUT * 1000);
})();
</script>

{{-- ═══════════════════════════════════════════════════════
     Notification Bell — dropdown + polling
═══════════════════════════════════════════════════════ --}}
<script>
(function () {
    var AJAX_URL     = '{{ route("notifications.ajaxList") }}';
    var READ_ALL_URL = '{{ route("notifications.readAll") }}';
    var READ_URL     = '{{ url("notifications") }}/';
    var CSRF         = '{{ csrf_token() }}';

    var _open      = false;
    var _lastUnread = 0;

    // ── Type icons & colors ────────────────────────────────────
    var ICONS = {
        document_share:   { icon: 'fa-share-alt',         color: 'bg-blue-50 text-blue-600' },
        workflow_assigned:{ icon: 'fa-project-diagram',    color: 'bg-indigo-50 text-indigo-600' },
        chat_message:     { icon: 'fa-comment-dots',       color: 'bg-emerald-50 text-emerald-600' },
        template_share:   { icon: 'fa-file-alt',           color: 'bg-amber-50 text-amber-600' },
        info:             { icon: 'fa-info-circle',        color: 'bg-gray-100 text-gray-500' },
        workflow:         { icon: 'fa-project-diagram',    color: 'bg-indigo-50 text-indigo-600' },
        signature:        { icon: 'fa-signature',          color: 'bg-purple-50 text-purple-600' },
        validation:       { icon: 'fa-check-circle',       color: 'bg-green-50 text-green-600' },
        system:           { icon: 'fa-cog',                color: 'bg-gray-100 text-gray-500' },
    };

    function typeStyle(type) {
        return ICONS[type] || ICONS['info'];
    }

    function renderItem(n) {
        var s = typeStyle(n.type);
        return '<div id="nitem-' + n.id + '" onclick="notifClick(\'' + n.id + '\',\'' + (n.action_url || '') + '\')" '
            + 'class="flex gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 transition border-b border-gray-50 last:border-0'
            + (n.is_read ? ' opacity-60' : '') + '">'
            + '<div class="flex-shrink-0 h-9 w-9 rounded-full flex items-center justify-center ' + s.color + '">'
            + '<i class="fas ' + s.icon + ' text-sm"></i></div>'
            + '<div class="flex-1 min-w-0">'
            + '<p class="text-xs font-semibold text-gray-800 truncate">' + escHtml(n.title) + '</p>'
            + '<p class="text-xs text-gray-500 line-clamp-2 mt-0.5">' + escHtml(n.message) + '</p>'
            + '<p class="text-[10px] text-gray-400 mt-1">' + escHtml(n.time) + '</p>'
            + '</div>'
            + (!n.is_read ? '<span class="flex-shrink-0 mt-2 h-2 w-2 rounded-full bg-blue-500"></span>' : '')
            + '</div>';
    }

    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function updateBadge(count) {
        var badge = document.getElementById('notif-badge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function loadNotifications() {
        fetch(AJAX_URL, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                updateBadge(data.unread);
                _lastUnread = data.unread;
                var list = document.getElementById('notif-list');
                var empty = document.getElementById('notif-empty');
                if (!list) return;
                if (!data.items || data.items.length === 0) {
                    list.innerHTML = '<div class="flex items-center justify-center py-10 text-gray-400 text-xs" id="notif-empty"><i class="fas fa-bell-slash mr-2"></i> Aucune notification</div>';
                    return;
                }
                list.innerHTML = data.items.map(renderItem).join('');
            })
            .catch(function() {});
    }

    window.toggleNotifPanel = function(e) {
        if (e) e.stopPropagation();
        var panel = document.getElementById('notif-panel');
        if (!panel) return;
        _open = !_open;
        if (_open) {
            panel.classList.remove('hidden');
            loadNotifications();
        } else {
            panel.classList.add('hidden');
        }
    };

    window.notifClick = function(id, url) {
        // Marquer comme lu
        fetch(READ_URL + id + '/read', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' }
        }).then(function() {
            var el = document.getElementById('nitem-' + id);
            if (el) { el.classList.add('opacity-60'); var dot = el.querySelector('.bg-blue-500'); if (dot) dot.remove(); }
            _lastUnread = Math.max(0, _lastUnread - 1);
            updateBadge(_lastUnread);
        });
        if (url) { window.location.href = url; }
    };

    window.markAllNotifRead = function() {
        fetch(READ_ALL_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' }
        }).then(function() {
            updateBadge(0);
            _lastUnread = 0;
            loadNotifications();
        });
    };

    // Fermer en cliquant en dehors
    document.addEventListener('click', function(e) {
        if (!_open) return;
        var wrap = document.getElementById('notif-wrap');
        if (wrap && !wrap.contains(e.target)) {
            _open = false;
            var panel = document.getElementById('notif-panel');
            if (panel) panel.classList.add('hidden');
        }
    });

    // Polling toutes les 30 secondes pour mettre à jour le badge
    loadNotifications();
    setInterval(loadNotifications, 30000);
})();
</script>
@endauth
</body>
</html>
