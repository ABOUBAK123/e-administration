@extends('layouts.app')
@section('title', 'Administration')
@section('page-title', 'Administration')
@section('page-subtitle', 'Configurez l\'ensemble de votre application')
@section('content')

@verbatim
<style>
.adm-modal { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.45); align-items:center; justify-content:center; }
.adm-modal.open { display:flex; }
.adm-modal-box { background:#fff; border-radius:1.25rem; padding:2rem; width:100%; max-width:520px; box-shadow:0 20px 60px rgba(0,0,0,.2); position:relative; max-height:90vh; overflow-y:auto; }
.adm-tab-label { display:none; }
@media (min-width: 640px) { .adm-tab-label { display:inline; } }
</style>
@endverbatim

@php
$tab = request('tab', 'overview');
$personnelTab = request('personnel_tab', 'dashboard');
$allTabs = [
  'overview'           => ['fas fa-th-large',          'Aperçu',                '#6366f1', null],
  'templates'          => ['fas fa-file-code',         'Templates',             '#0ea5e9', 'administration.templates'],
  'emitters'           => ['fas fa-building',          'Émetteurs',             '#8b5cf6', 'administration.emitters'],
  'recipients'         => ['fas fa-paper-plane',       'Destinataires',         '#ec4899', 'administration.recipients'],
  'sub-entities'       => ['fas fa-sitemap',           'Entités sous tutelle',  '#14b8a6', 'administration.sub-entities'],
  'requested-acts'     => ['fas fa-clipboard-list',    'Actes demandés',        '#f59e0b', 'administration.requested-acts'],
    'direction-types'    => ['fas fa-tags',               'Types de direction',    '#10b981', 'administration.direction-types'],
    'routing'            => ['fas fa-route',              'Routage',               '#f97316', 'administration.routing'],
    'onlyoffice'         => ['fas fa-edit',               'OnlyOffice',            '#06b6d4', 'administration.onlyoffice'],
    'users'              => ['fas fa-users',              'Utilisateurs',          '#3b82f6', 'administration.users'],
    'theming'            => ['fas fa-paint-brush',        'Apparence',             '#a855f7', 'administration.theming'],
    'email-notifications'=> ['fas fa-envelope-open-text', 'Notifications E-mail',  '#ef4444', 'administration.email-notifications'],
    'signature-provider' => ['fas fa-key',                'API Signature',         '#f59e0b', 'administration.signature-provider'],
    'user-profiles'      => ['fas fa-user-shield',       'Rôles',                 '#64748b', 'administration.user-profiles'],
    'instructions'       => ['fas fa-list-check',        'Instructions',          '#0891b2', 'administration.instructions'],
    'courrier-archiving' => ['fas fa-archive',           'Archivage courrier',    '#78716c', 'administration.courrier-archiving'],
    'antivirus'          => ['fas fa-shield-virus',       'Antivirus',             '#dc2626', 'administration.antivirus'],
];
$permSvcAdmin = app(\App\Services\UserPermissionsService::class);
$permSetAdmin = $permSvcAdmin->permissionsSet(auth()->user());
$canManageAdministration = ($permSetAdmin['isElevated'] ?? false) || ((auth()->user()->role ?? '') === 'admin');
$tabs = array_filter($allTabs, function($v) use ($permSetAdmin) {
    $perm = $v[3];
    if ($perm === null) return true;
    if ($permSetAdmin['isElevated']) return true;
    if (isset($permSetAdmin['permissions'][$perm])) return true;
    // Afficher l'onglet si au moins un sous-onglet est accordé (ex: personnel.dashboard → personnel)
    foreach ($permSetAdmin['permissions'] as $k => $_) {
        if (str_starts_with($k, $perm . '.')) return true;
    }
    return false;
});

if ($tab !== 'personnel' && !array_key_exists($tab, $tabs)) {
  $tab = 'overview';
}
@endphp

{{-- Bandeau d'information pour les admins d'administration (scope restreint) --}}
@php
  $currentProfileName = auth()->user()?->profile?->name ?? '';
  $normalizedProfileName = mb_strtoupper(trim(str_replace(['_', '-'], ' ', $currentProfileName)), 'UTF-8');
  $isAgentRhProfile = str_contains($normalizedProfileName, 'AGENT RH');
@endphp
@if(isset($adminScope) && $adminScope && $tab === 'personnel' && $isAgentRhProfile)
@php
    $scopedAdminLabel = null;
    if ($adminScope['type'] === 'emitter') {
        $scopedAdminLabel = \App\Models\IssuingAdministration::find($adminScope['id'])?->name;
    } else {
        $scopedAdminLabel = \App\Models\RecipientAdministration::find($adminScope['id'])?->name;
    }
@endphp
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
    <i class="fas fa-building text-blue-500 flex-shrink-0"></i>
    <span>Vous gérez l'administration <strong>{{ $scopedAdminLabel ?? $adminScope['id'] }}</strong>. Seules les données de cette administration sont visibles et modifiables.</span>
</div>
@endif

{{-- Barre d'onglets --}}
@if($tab === 'personnel')
{{-- Barre des sous-onglets Personnel uniquement --}}
@php
  $_personnelNavTabs = [
    'dashboard' => ['fas fa-chart-line', __('personnel.ui.tabs.dashboard')],
    'employees' => ['fas fa-id-card', __('personnel.ui.tabs.employees')],
    'agent-space' => ['fas fa-user-clock', __('personnel.ui.tabs.agent_space')],
    'leave'     => ['fas fa-calendar-check', __('personnel.ui.tabs.leave')],
    'training'  => ['fas fa-graduation-cap', __('personnel.ui.tabs.training')],
    'career'    => ['fas fa-ranking-star', __('personnel.ui.tabs.career')],
  ];
  // Le parent 'personnel' est toujours ajouté automatiquement par le contrôleur quand
  // un sous-onglet est coché — sa présence NE signifie PAS "accès total".
  // Règle : s'il existe des sous-permissions spécifiques (personnel.xxx), n'afficher QUE celles-ci.
  // S'il n'existe AUCUNE sous-permission spécifique (uniquement 'personnel'), afficher tout.
  $_personnelAllPermsKeys = array_keys($permSetAdmin['permissions'] ?? []);
  $_personnelSpecificChildren = array_filter($_personnelAllPermsKeys, fn($k) => str_starts_with($k, 'personnel.'));
  $_personnelHasSpecificChildren = !empty($_personnelSpecificChildren);
  $_personnelNavTabsFiltered = array_filter($_personnelNavTabs, function($v, $k) use ($permSetAdmin, $_personnelHasSpecificChildren) {
      if ($permSetAdmin['isElevated'] ?? false) return true;
      if ($_personnelHasSpecificChildren) {
          // N'afficher que les sous-onglets explicitement cochés
          return isset($permSetAdmin['permissions']['personnel.' . $k]);
      }
      // Pas de sous-permission spécifique : le parent seul = accès complet
      return isset($permSetAdmin['permissions']['personnel']);
  }, ARRAY_FILTER_USE_BOTH);
  // Si l'onglet actif n'est pas accessible, rediriger vers le premier accessible
  if (!isset($_personnelNavTabsFiltered[$personnelTab])) {
      $personnelTab = array_key_first($_personnelNavTabsFiltered) ?? 'dashboard';
  }
@endphp
<div class="flex flex-wrap gap-1.5 mb-6 bg-white rounded-2xl border border-gray-200 p-2 shadow-sm">
    @foreach($_personnelNavTabsFiltered as $key => [$icon, $label])
    <a href="{{ route('admin.index', ['tab' => 'personnel', 'personnel_tab' => $key]) }}"
       title="{{ $label }}"
       class="flex items-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition
              {{ $personnelTab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' }}">
        <i class="fa {{ $icon }} text-sm flex-shrink-0"></i>
        <span class="adm-tab-label">{{ $label }}</span>
    </a>
    @endforeach
</div>
@else
<div class="flex flex-wrap gap-1.5 mb-6 bg-white rounded-2xl border border-gray-200 p-2 shadow-sm">
    @foreach($tabs as $key => [$icon, $label, $color, $perm])
    <a href="{{ route('admin.index', ['tab' => $key]) }}"
       title="{{ $label }}"
       class="flex items-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition
              {{ $tab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' }}">
        <i class="fa {{ $icon }} text-sm flex-shrink-0"></i>
        <span class="adm-tab-label">{{ $label }}</span>
    </a>
    @endforeach
</div>
@endif

{{-- ══════════════════════ APERÇU ══════════════════════ --}}
@if($tab === 'overview')
@php
    $overviewAdminScope = $adminScope ?? null;
    $overviewAdminName  = null;
    if ($overviewAdminScope) {
        if ($overviewAdminScope['type'] === 'emitter') {
            $overviewAdminName = \App\Models\IssuingAdministration::find($overviewAdminScope['id'])?->name;
        } else {
            $overviewAdminName = \App\Models\RecipientAdministration::find($overviewAdminScope['id'])?->name;
        }
    }
@endphp
@if($overviewAdminScope)
<div class="mb-5 flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3">
    <i class="fas fa-building text-blue-500"></i>
    <div>
        <span class="text-sm font-semibold text-blue-800">Périmètre : </span>
        <span class="text-sm text-blue-700">{{ $overviewAdminName ?? $overviewAdminScope['id'] }}</span>
        <span class="ml-2 text-xs text-blue-500 bg-blue-100 rounded-full px-2 py-0.5">{{ $overviewAdminScope['type'] === 'emitter' ? 'Émetteur' : 'Destinataire' }}</span>
    </div>
</div>
@else
<div class="mb-5 flex items-center gap-3 bg-violet-50 border border-violet-200 rounded-xl px-4 py-3">
    <i class="fas fa-globe text-violet-500"></i>
    <span class="text-sm font-semibold text-violet-800">Super Admin — toutes les administrations</span>
</div>
@endif
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach([
        ['fas fa-users',      $stats['users'],      'Utilisateurs',  'blue'],
        ['fas fa-file-alt',   $stats['documents'],  'Documents',      'green'],
        ['fas fa-pen-nib',    $stats['signatures'], 'Signatures',     'purple'],
        ['fas fa-code-branch',$stats['workflows'],  'Workflows',      'orange'],
    ] as [$icon, $count, $label, $color])
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="h-10 w-10 rounded-xl bg-{{ $color }}-100 flex items-center justify-center">
                <i class="fa {{ $icon }} text-{{ $color }}-500"></i>
            </div>
            <span class="text-2xl font-black text-gray-800">{{ number_format($count) }}</span>
        </div>
        <p class="text-sm font-semibold text-gray-600">{{ $label }}</p>
    </div>
    @endforeach
</div>
@php
$_oc = [
    'templates'          => method_exists($templates, 'total') ? $templates->total() : $templates->count(),
    'emitters'           => $emitters->count(),
    'recipients'         => $recipients->count(),
    'sub-entities'       => $subEntities->count(),
    'requested-acts'     => $requestedActs->count(),
    'direction-types'    => $directionTypes->count(),
    'routing'            => method_exists($routingRules, 'total') ? $routingRules->total() : $routingRules->count(),
    'users'              => method_exists($users, 'total') ? $users->total() : $users->count(),
    'user-profiles'      => method_exists($profiles, 'total') ? $profiles->total() : $profiles->count(),
    'instructions'       => $instructions->count(),
    'signature-provider' => $sigProviders->count(),
];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach([
        ['templates',          'fas fa-file-code',          'Templates',             'Modèles de documents configurables.',      'sky'],
        ['emitters',           'fas fa-building',           'Émetteurs',             'Administrations émettrices.',              'violet'],
        ['recipients',         'fas fa-paper-plane',        'Destinataires',         'Administrations destinataires.',           'pink'],
        ['direction-types',    'fas fa-tags',               'Types de direction',    'Catégories de directions.',                'emerald'],
        ['routing',            'fas fa-route',              'Routage',               'Règles d\'acheminement des documents.',    'orange'],
        ['users',              'fas fa-users',              'Utilisateurs',          'Gérer les comptes utilisateurs.',          'blue'],
        ['theming',            'fas fa-paint-brush',        'Apparence',             'Couleurs et logo de l\'application.',      'purple'],
        ['email-notifications','fas fa-envelope-open-text', 'Notifications E-mail',  'Configuration SMTP et e-mails.',          'red'],
        ['signature-provider', 'fas fa-key',                'API Signature',         'Fournisseur de signature électronique.',   'amber'],
        ['user-profiles',      'fas fa-user-shield',        'Rôles',                 'Profils et permissions utilisateurs.',     'slate'],
        ['instructions',       'fas fa-list-check',         'Instructions',           'Instructions de traitement des courriers.','cyan'],
        ['sub-entities',       'fas fa-sitemap',            'Entités sous tutelle',  'Structures rattachées aux administrations.','teal'],
        ['requested-acts',     'fas fa-clipboard-list',     'Actes demandés',        'Types d\'actes configurables.',            'yellow'],
        ['onlyoffice',         'fas fa-edit',               'OnlyOffice',            'Serveur d\'édition collaborative.',        'cyan'],
        ['courrier-archiving', 'fas fa-archive',            'Archivage courrier',    'Délai d\'archivage automatique des courriers.','stone'],
    ] as [$t, $icon, $title, $desc, $color])
    @php $cnt = $_oc[$t] ?? null; @endphp
    <a href="{{ route('admin.index', ['tab' => $t]) }}"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition group">
        <div class="flex items-start justify-between mb-3">
            <div class="h-11 w-11 rounded-xl bg-{{ $color }}-100 flex items-center justify-center">
                <i class="fa {{ $icon }} text-{{ $color }}-600"></i>
            </div>
            @if($cnt !== null)
            <span class="text-2xl font-black text-gray-800">{{ number_format($cnt) }}</span>
            @endif
        </div>
        <h3 class="font-bold text-gray-800 mb-1 text-sm">{{ $title }}</h3>
        <p class="text-xs text-gray-400">{{ $desc }}</p>
    </a>
    @endforeach
</div>

{{-- ══════════════════════ GESTION DU PERSONNEL ══════════════════════ --}}
@elseif($tab === 'personnel')
@php
  $personnelTabs = [
    'dashboard' => ['fas fa-chart-line', __('personnel.ui.tabs.dashboard')],
    'employees' => ['fas fa-id-card', __('personnel.ui.tabs.employees')],
    'agent-space' => ['fas fa-user-clock', __('personnel.ui.tabs.agent_space')],
    'leave' => ['fas fa-calendar-check', __('personnel.ui.tabs.leave')],
    'training' => ['fas fa-graduation-cap', __('personnel.ui.tabs.training')],
    'career' => ['fas fa-ranking-star', __('personnel.ui.tabs.career')],
  ];
  if (!array_key_exists($personnelTab, $personnelTabs)) {
    $personnelTab = 'dashboard';
  }
  $editingPersonnel = $selectedPersonnelEmployee;
@endphp

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-6">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h2 class="text-xl font-bold text-gray-800">{{ __('personnel.ui.module.title') }}</h2>
      <p class="text-sm text-gray-500 mt-1">{{ __('personnel.ui.module.description') }}</p>
    </div>
    @if($personnelTab === 'training')
    @php
      $_tpCurrentUser = auth()->user();
      $_tpProfile = $_tpCurrentUser?->profile_id ? \App\Models\AdministrationProfile::find($_tpCurrentUser->profile_id) : null;
      $_tpProfileName = mb_strtoupper(trim(str_replace(['_','-'],' ',$_tpProfile?->name ?? '')), 'UTF-8');
      $_tpIsAgentRh = str_contains($_tpProfileName, 'AGENT RH');
      $_tpIsSuperAdmin = $_tpCurrentUser?->role === 'admin';
      // Supérieur hiérarchique : a des collaborateurs dont il est user_id
      $_tpIsSuperior = \App\Models\PersonnelEmployee::where('user_id', $_tpCurrentUser?->id)->exists();
      $_tpCanSeeRequests = $_tpIsAgentRh || $_tpIsSuperAdmin || $_tpIsSuperior;
    @endphp
    @if($_tpCanSeeRequests)
    <button type="button"
      onclick="document.getElementById('modal-training-requests').classList.remove('hidden')"
      class="ml-auto flex-shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold shadow transition">
      <i class="fas fa-graduation-cap text-xs"></i>
      Voir demandes de formations
    </button>
    @endif
    @elseif($personnelTab === 'dashboard')
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 min-w-0">
      <div class="rounded-xl bg-teal-50 border border-teal-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-teal-700">{{ __('personnel.ui.stats.employees') }}</div>
        <div class="text-2xl font-black text-gray-800">{{ number_format($personnelStats['employees'] ?? 0) }}</div>
      </div>
      <div class="rounded-xl bg-cyan-50 border border-cyan-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-cyan-700">{{ __('personnel.ui.stats.documents') }}</div>
        <div class="text-2xl font-black text-gray-800">{{ number_format($personnelStats['documents'] ?? 0) }}</div>
      </div>
      <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-emerald-700">{{ __('personnel.ui.stats.active') }}</div>
        <div class="text-2xl font-black text-gray-800">{{ number_format($personnelStats['active'] ?? 0) }}</div>
      </div>
      <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-amber-700">{{ __('personnel.ui.stats.new_hires_year', ['year' => now()->year]) }}</div>
        <div class="text-2xl font-black text-gray-800">{{ number_format($personnelStats['newThisYear'] ?? 0) }}</div>
      </div>
    </div>
    @endif
  </div>
</div>

@if($personnelTab === 'dashboard')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-6">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-2">{{ __('personnel.ui.dashboard.vision_title') }}</h3>
    <p class="text-sm text-gray-500 mb-4">{{ __('personnel.ui.dashboard.vision_description') }}</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      @foreach([
        [__('personnel.ui.dashboard.cards.employees_title'), __('personnel.ui.dashboard.cards.employees_desc'), 'fas fa-id-card', 'teal'],
        [__('personnel.ui.dashboard.cards.leave_title'), __('personnel.ui.dashboard.cards.leave_desc'), 'fas fa-calendar-check', 'sky'],
        [__('personnel.ui.dashboard.cards.training_title'), __('personnel.ui.dashboard.cards.training_desc'), 'fas fa-graduation-cap', 'violet'],
        [__('personnel.ui.dashboard.cards.career_title'), __('personnel.ui.dashboard.cards.career_desc'), 'fas fa-ranking-star', 'amber'],
      ] as [$title, $desc, $icon, $color])
      <div class="rounded-2xl border border-gray-200 p-4 bg-gray-50">
        <div class="h-10 w-10 rounded-xl bg-{{ $color }}-100 flex items-center justify-center mb-3">
          <i class="fa {{ $icon }} text-{{ $color }}-600"></i>
        </div>
        <h4 class="font-semibold text-gray-800 mb-1">{{ $title }}</h4>
        <p class="text-sm text-gray-500">{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.dashboard.workflow_title') }}</h3>
    <div class="space-y-3">
      @foreach([
        [__('personnel.ui.dashboard.workflow.approvals_title'), __('personnel.ui.dashboard.workflow.approvals_desc')],
        [__('personnel.ui.dashboard.workflow.notifications_title'), __('personnel.ui.dashboard.workflow.notifications_desc')],
        [__('personnel.ui.dashboard.workflow.reminders_title'), __('personnel.ui.dashboard.workflow.reminders_desc')],
        [__('personnel.ui.dashboard.workflow.steering_title'), __('personnel.ui.dashboard.workflow.steering_desc')],
        [__('personnel.ui.dashboard.workflow.attendance_title'), __('personnel.ui.dashboard.workflow.attendance_desc')],
      ] as [$title, $desc])
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800">{{ $title }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $desc }}</div>
      </div>
      @endforeach
    </div>

    <div class="mt-5 pt-5 border-t border-gray-200">
      <div class="flex items-center justify-between gap-3 mb-3">
        <h4 class="font-semibold text-gray-800">{{ __('personnel.audit.title') }}</h4>
        <span class="text-xs text-gray-500">{{ __('personnel.audit.recent_actions', ['count' => $personnelRecentActivity->count()]) }}</span>
      </div>
      <div class="space-y-3">
        @forelse($personnelRecentActivity as $activity)
        @php
          $subjectLabels = [
            'PersonnelEmployee' => __('personnel.audit.subjects.employee_record'),
            'PersonnelEmployeeDocument' => __('personnel.audit.subjects.employee_document'),
            'PersonnelLeaveType' => __('personnel.audit.subjects.leave_type'),
            'PersonnelLeaveRequest' => __('personnel.audit.subjects.leave_request'),
            'PersonnelTraining' => __('personnel.audit.subjects.training'),
            'PersonnelTrainingEnrollment' => __('personnel.audit.subjects.training_enrollment'),
            'PersonnelEmployeeSkill' => __('personnel.audit.subjects.skill'),
          ];
          $subjectLabel = $subjectLabels[class_basename($activity->subject_type)] ?? __('personnel.audit.subjects.hr_item');
          $eventRaw = $activity->event ?: 'updated';
          $eventKey = 'personnel.audit.events.' . $eventRaw;
          $eventLabel = __($eventKey);
          if ($eventLabel === $eventKey) {
              $eventLabel = \Illuminate\Support\Str::headline($eventRaw);
          }

          $descriptionRaw = (string) ($activity->description ?: '');
          $descriptionMap = [
            'created' => __('personnel.audit.events.created'),
            'updated' => __('personnel.audit.events.updated'),
            'deleted' => __('personnel.audit.events.deleted'),
            'restored' => __('personnel.audit.events.restored'),
          ];
          $descriptionLabel = $descriptionRaw !== ''
            ? ($descriptionMap[$descriptionRaw] ?? $descriptionRaw)
            : __('personnel.audit.default_description');
        @endphp
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
          <div class="font-semibold text-gray-800">{{ $eventLabel }} {{ $subjectLabel }}</div>
          <div class="text-sm text-gray-500 mt-1">{{ $descriptionLabel }}</div>
          <div class="text-xs text-gray-400 mt-2">{{ $activity->causer?->name ?? __('personnel.audit.system') }} · {{ optional($activity->created_at)->diffForHumans() }}</div>
        </div>
        @empty
        <div class="rounded-xl border border-gray-200 p-4 text-sm text-gray-500 bg-gray-50">{{ __('personnel.audit.empty') }}</div>
        @endforelse
      </div>
    </div>
  </section>
</div>
@elseif($personnelTab === 'employees')
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h3 class="text-base font-bold text-gray-800">{{ __('personnel.ui.employees.import_title') }}</h3>
      <p class="text-sm text-gray-500 mt-1">{{ __('personnel.ui.employees.import_description') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <a href="{{ route('admin.personnel.employees.template') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
        <i class="fas fa-file-excel text-emerald-600"></i>
        {{ __('personnel.ui.employees.download_template') }}
      </a>
      <form method="POST" enctype="multipart/form-data" action="{{ route('admin.personnel.employees.import') }}" class="flex items-center gap-2">
        @csrf
        <input type="hidden" name="personnel_tab" value="employees">
        <input type="file" name="employees_file" accept=".xlsx,.xls,.csv" required class="border border-gray-300 rounded-xl px-3 py-2 text-sm">
        <button type="submit" class="px-4 py-2 bg-[#2453d6] text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.employees.import_button') }}</button>
      </form>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800">{{ __('personnel.ui.employees.sheet_title') }}</h3>
        <p class="text-sm text-gray-500">{{ __('personnel.ui.employees.sheet_description') }}</p>
      </div>
      @if($editingPersonnel)
      <a href="{{ route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'employees']) }}" class="text-xs font-semibold text-[#2453d6]">{{ __('personnel.ui.employees.form_btn_new') }}</a>
      @endif
    </div>

    @php $empMeta = $editingPersonnel?->metadata ?? []; @endphp
    @if($editingPersonnel)
    <form id="create-linked-user-form" method="POST" action="{{ route('admin.personnel.employees.create-user', $editingPersonnel) }}" class="hidden">
      @csrf
      <input type="hidden" name="personnel_tab" value="employees">
    </form>
    @endif
    <form id="employee-registration-form" method="POST" enctype="multipart/form-data" action="{{ $editingPersonnel ? route('admin.personnel.employees.update', $editingPersonnel) : route('admin.personnel.employees.store') }}" class="space-y-6">
      @csrf
      @if($editingPersonnel)
        @method('PUT')
      @endif
      <input type="hidden" name="personnel_tab" value="employees">

      {{-- ── Section Informations Personnelles ── --}}
      <div>
        <h5 class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3 pb-2 border-b border-gray-100">Informations Personnelles</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input type="text" name="last_name" required value="{{ old('last_name', $editingPersonnel->last_name ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" required value="{{ old('first_name', $editingPersonnel->first_name ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Date de naissance</label>
            <input type="date" name="birth_date" value="{{ old('birth_date', optional($editingPersonnel?->birth_date)->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Lieu de naissance</label>
            <input type="text" name="birth_place" value="{{ old('birth_place', $editingPersonnel->birth_place ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">NNI</label>
            <input type="text" name="meta_nni" value="{{ old('meta_nni', $empMeta['nni'] ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400" placeholder="Numéro National d'Identité">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Téléphone</label>
            <input type="text" name="phone" value="{{ old('phone', $editingPersonnel->phone ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ old('email', $editingPersonnel->email ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Adresse</label>
            <textarea name="address" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400">{{ old('address', $editingPersonnel->address ?? '') }}</textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Situation familiale</label>
            <select name="marital_status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              @foreach(['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf/Veuve', 'Union libre'] as $ms)
              <option value="{{ $ms }}" {{ old('marital_status', $editingPersonnel->marital_status ?? '') === $ms ? 'selected' : '' }}>{{ $ms }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre d'enfants</label>
            <input type="number" name="meta_children_count" min="0" value="{{ old('meta_children_count', $empMeta['children_count'] ?? 0) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Personne à contacter en urgence</label>
            <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $editingPersonnel->emergency_contact_name ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Téléphone urgence</label>
            <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $editingPersonnel->emergency_contact_phone ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Photo de l'agent (carte virtuelle)</label>
            <input type="file" id="employee-photo-input" name="employee_photo" accept="image/*" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-orange-100 file:px-3 file:py-1.5 file:text-orange-700 hover:file:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-teal-400">
            @if(!empty($empMeta['photo_path']))
            <p class="mt-1 text-xs text-gray-500">Photo actuelle enregistrée: {{ basename($empMeta['photo_path']) }}</p>
            @endif
          </div>
        </div>
      </div>

      {{-- ── Section Informations Professionnelles ── --}}
      <div>
        <h5 class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3 pb-2 border-b border-gray-100">Informations Professionnelles</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.employees.form_admin_type') }}</label>
            <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="emitter" {{ old('administration_type', $editingPersonnel->administration_type ?? ($adminScope['type'] ?? 'emitter')) === 'emitter' ? 'selected' : '' }}>{{ __('personnel.ui.employees.admin_emitter') }}</option>
              <option value="recipient" {{ old('administration_type', $editingPersonnel->administration_type ?? ($adminScope['type'] ?? 'emitter')) === 'recipient' ? 'selected' : '' }}>{{ __('personnel.ui.employees.admin_recipient') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.employees.form_administration') }}</label>
            <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">{{ __('personnel.ui.employees.form_select_admin') }}</option>
              @foreach($emitters as $e)
              <option value="{{ $e->id }}" {{ old('administration_id', $editingPersonnel->administration_id ?? ($adminScope['id'] ?? '')) === $e->id ? 'selected' : '' }}>{{ $e->name }} {{ __('personnel.ui.employees.admin_emitter_bracket') }}</option>
              @endforeach
              @foreach($recipients as $r)
              <option value="{{ $r->id }}" {{ old('administration_id', $editingPersonnel->administration_id ?? ($adminScope['id'] ?? '')) === $r->id ? 'selected' : '' }}>{{ $r->name }} {{ __('personnel.ui.employees.admin_recipient_bracket') }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Matricule <span class="text-red-500">*</span></label>
            <input type="text" name="employee_number" value="{{ old('employee_number', $editingPersonnel->employee_number ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Direction Générale</label>
            @php
              $dirGenEntities = $subEntities->filter(function($se) {
                  $typeName = mb_strtoupper(trim($se->directionType?->name ?? ''));
                  return in_array($typeName, ['DIRECTION GENERALE', 'DIRECTION GÉNÉRALE'], true);
              })->values();
            @endphp
            <select name="meta_direction_generale" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              @foreach($dirGenEntities as $dge)
              <option value="{{ $dge->name }}" {{ old('meta_direction_generale', $empMeta['direction_generale'] ?? '') === $dge->name ? 'selected' : '' }}>{{ $dge->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Direction Centrale</label>
            @php
              $dirCentraleEntities = $subEntities->filter(function($se) {
                  $typeName = mb_strtoupper(trim($se->directionType?->name ?? ''));
                  return in_array($typeName, ['DIRECTION CENTRALE', 'DIRECTION CENTRALE'], true);
              })->values();
            @endphp
            <select id="select_direction_centrale" name="meta_direction_centrale" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              @foreach($dirCentraleEntities as $dce)
              <option value="{{ $dce->name }}" data-code="{{ $dce->code }}" {{ old('meta_direction_centrale', $empMeta['direction_centrale'] ?? '') === $dce->name ? 'selected' : '' }}>{{ $dce->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Sous-Direction</label>
            @php
              $allSubEntitiesJson = $subEntities->map(fn($se) => [
                  'name'        => $se->name,
                  'code'        => $se->code,
                  'parent_code' => $se->parent_code,
              ])->values()->toJson();
              $currentSousDir = old('meta_sous_direction', $empMeta['sous_direction'] ?? '');
            @endphp
            <select id="select_sous_direction" name="meta_sous_direction" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Service</label>
            <select id="select_service" name="meta_service" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
            </select>
            <script>
            (function () {
              var allEntities = {!! $allSubEntitiesJson !!};
              var currentVal  = @json($currentSousDir);
              var selDC  = document.getElementById('select_direction_centrale');
              var selSD  = document.getElementById('select_sous_direction');
              var selSVC = document.getElementById('select_service');
              var currentSVC = @json(old('meta_service', $empMeta['service'] ?? ''));

              function populateService(parentCode) {
                var prev = selSVC.value || currentSVC;
                selSVC.innerHTML = '<option value="">Sélectionner</option>';
                if (!parentCode) return;
                allEntities.forEach(function(e) {
                  if ((e.parent_code || '').toUpperCase() === parentCode.toUpperCase()) {
                    var opt = document.createElement('option');
                    opt.value = e.name;
                    opt.textContent = e.name;
                    if (e.name === prev) opt.selected = true;
                    selSVC.appendChild(opt);
                  }
                });
              }

              function populateSousDirection(parentCode) {
                var prev = selSD.value || currentVal;
                selSD.innerHTML = '<option value="">Sélectionner</option>';
                selSVC.innerHTML = '<option value="">Sélectionner</option>';
                if (!parentCode) return;
                allEntities.forEach(function(e) {
                  if ((e.parent_code || '').toUpperCase() === parentCode.toUpperCase()) {
                    var opt = document.createElement('option');
                    opt.value = e.name;
                    opt.dataset.code = e.code || '';
                    opt.textContent = e.name;
                    if (e.name === prev) opt.selected = true;
                    selSD.appendChild(opt);
                  }
                });
                var selSDOpt = selSD.options[selSD.selectedIndex];
                populateService(selSDOpt ? (selSDOpt.dataset.code || '') : '');
              }

              var initOpt = selDC.options[selDC.selectedIndex];
              populateSousDirection(initOpt ? (initOpt.dataset.code || '') : '');

              selDC.addEventListener('change', function() {
                var opt = selDC.options[selDC.selectedIndex];
                populateSousDirection(opt ? (opt.dataset.code || '') : '');
              });

              selSD.addEventListener('change', function() {
                var opt = selSD.options[selSD.selectedIndex];
                populateService(opt ? (opt.dataset.code || '') : '');
              });
            })();
            </script>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Catégorie</label>
            <select name="meta_categorie" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              @foreach(['A','B','C','D'] as $cat)
              <option value="{{ $cat }}" {{ old('meta_categorie', $empMeta['categorie'] ?? '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Grade</label>
            <select name="meta_grade" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              @foreach(collect($personnelJobReferences)->where('reference_type', 'grade') as $gradeRef)
              <option value="{{ $gradeRef->label }}" {{ old('meta_grade', $empMeta['grade'] ?? '') === $gradeRef->label ? 'selected' : '' }}>{{ $gradeRef->label }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Emploi</label>
            <select name="job_title" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              @foreach(collect($personnelJobReferences)->where('reference_type', 'employment') as $employmentRef)
              <option value="{{ $employmentRef->label }}" {{ old('job_title', $editingPersonnel->job_title ?? '') === $employmentRef->label ? 'selected' : '' }}>{{ $employmentRef->label }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Lieu de Travail</label>
            <input type="text" name="meta_lieu_travail" value="{{ old('meta_lieu_travail', $empMeta['lieu_travail'] ?? '') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Date d'embauche <span class="text-red-500">*</span></label>
            <input type="date" name="hire_date" value="{{ old('hire_date', optional($editingPersonnel?->hire_date)->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.employees.form_employment_status') }}</label>
            <select name="employment_status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              @foreach(['active', 'probation', 'suspended', 'inactive'] as $empStatus)
              <option value="{{ $empStatus }}" {{ old('employment_status', $editingPersonnel->employment_status ?? 'active') === $empStatus ? 'selected' : '' }}>{{ __('personnel.ui.employees.status_' . $empStatus) }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.employees.form_superior') }}</label>
            <select name="user_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">{{ __('personnel.ui.employees.form_no_superior') }}</option>
              @foreach($allUsers as $u)
              <option value="{{ $u->id }}" {{ old('user_id', $editingPersonnel->user_id ?? '') === $u->id ? 'selected' : '' }}>{{ $u->name }} - {{ $u->email }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Compte utilisateur</label>
            <select name="linked_user_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Aucun compte lié</option>
              @foreach($allUsers as $u)
              <option value="{{ $u->id }}" {{ old('linked_user_id', $editingPersonnel->linked_user_id ?? '') === $u->id ? 'selected' : '' }}>{{ $u->name }} - {{ $u->email }}</option>
              @endforeach
            </select>
            @if($editingPersonnel)
            <div class="mt-2 flex flex-wrap items-center gap-2">
              @if($editingPersonnel->linked_user_id)
              <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1 text-xs font-semibold">Compte utilisateur déjà lié</span>
              @elseif(!empty($editingPersonnel->email))
              <button type="submit" form="create-linked-user-form" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-[#2453d6] text-white text-xs font-semibold hover:bg-[#1d43ad]">
                <i class="fas fa-user-plus"></i>
                Créer le compte utilisateur
              </button>
              <span class="text-xs text-gray-500">Le compte sera créé à partir du nom, de l'e-mail et de l'administration de l'employé.</span>
              @else
              <span class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2">Renseignez d'abord l'e-mail de l'employé pour créer son compte utilisateur.</span>
              @endif
            </div>
            @endif
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.employees.form_sub_entity') }}</label>
            <select name="sub_entity_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">{{ __('personnel.ui.employees.form_no_entity') }}</option>
              @foreach($subEntities as $subEntity)
              <option value="{{ $subEntity->id }}" {{ old('sub_entity_id', $editingPersonnel->sub_entity_id ?? '') === $subEntity->id ? 'selected' : '' }}>{{ $subEntity->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.employees.form_notes') }}</label>
            <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400">{{ old('notes', $editingPersonnel->notes ?? '') }}</textarea>
          </div>
        </div>
      </div>

      <div class="pt-2 flex flex-wrap gap-3">
        <button type="button" id="btn-open-virtual-card" class="px-6 py-2.5 bg-[#2453d6] hover:bg-[#1d43ad] text-white font-semibold rounded-xl text-sm inline-flex items-center gap-2">
          <i class="fas fa-id-badge"></i>
          Éditer la carte virtuelle de l'Agent
        </button>
        <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm">{{ $editingPersonnel ? __('personnel.ui.employees.form_btn_update') : __('personnel.ui.employees.form_btn_create') }}</button>
      </div>
    </form>

    <div id="virtual-agent-card-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm p-4 sm:p-6 lg:p-8">
      <div class="w-full max-w-[88vw] min-w-[320px] mx-auto bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h4 class="text-base font-bold text-gray-800">Carte virtuelle de l'Agent</h4>
          <button type="button" id="btn-close-virtual-card" class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm">Fermer</button>
        </div>

        <div class="p-5 bg-gray-50">
          @php
            $_armoiriePath = public_path('images/armoirie_ci.png');
            $_armoirieSrc = file_exists($_armoiriePath)
              ? 'data:image/png;base64,' . base64_encode(file_get_contents($_armoiriePath))
              : asset('images/armoirie_ci.png');
          @endphp
          <div id="virtual-agent-card-preview" style="background-color:#f0fff4;border:3px solid #f97316;border-radius:16px;padding:16px;width:100%;max-width:450px;min-height:320px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,0.08);position:relative;overflow:hidden;">
            <div aria-hidden="true" style="position:absolute;inset:0;background-image:url('{{ $_armoirieSrc }}');background-repeat:no-repeat;background-position:center;background-size:72% auto;opacity:0.08;pointer-events:none;"></div>
            <div style="position:relative;z-index:1;">

            {{-- En-tête : République + Armoirie + Devise --}}
            <div style="text-align:center;margin-bottom:10px;">
              <div style="font-size:15px;font-weight:900;letter-spacing:0.05em;color:#111;">REPUBLIQUE DE CÔTE D'IVOIRE</div>
              <div style="margin-top:8px;display:flex;justify-content:center;">
                <img src="{{ $_armoirieSrc }}" alt="Armoirie de la Côte d'Ivoire" style="height:70px;width:auto;display:block;margin:0 auto;">
              </div>
              <div style="font-size:12px;font-weight:700;color:#c2410c;margin-top:6px;">Union - Discipline - Travail</div>
            </div>

            {{-- Administration encadrée --}}
            <div style="border:2px solid #f97316;border-radius:8px;padding:6px 12px;text-align:center;margin-bottom:10px;">
              <div id="vac-admin-name" style="font-size:15px;font-weight:800;color:#111;">ADMINISTRATION</div>
            </div>

            {{-- Corps compact : grille infos + colonne photo/signature pour réduire la hauteur --}}
            <div style="display:flex;gap:14px;align-items:stretch;">
              <div style="flex:1;display:grid;grid-template-columns:minmax(0,1fr);row-gap:5px;font-size:13px;color:#1f2937;line-height:1.45;">
                <div><strong>Nom :</strong> <span id="vac-last-name">-</span></div>
                <div><strong>Prénoms :</strong> <span id="vac-first-name">-</span></div>
                <div><strong>Né(e) le :</strong> <span id="vac-birth-date">-</span></div>
                <div><strong>Lieu de naissance :</strong> <span id="vac-birth-place">-</span></div>
                <div><strong>Matricule :</strong> <span id="vac-employee-number">-</span></div>
                <div><strong>Emploi :</strong> <span id="vac-job-title">-</span></div>
                <div><strong>Date de prise de service :</strong> <span id="vac-hire-date">-</span></div>
                <div><strong>NNI :</strong> <span id="vac-nni">-</span></div>
                <div><strong>Situation familiale :</strong> <span id="vac-marital-status">-</span></div>
              </div>

              <div style="width:150px;display:flex;flex-direction:column;justify-content:space-between;gap:8px;">
                <div style="width:150px;height:160px;border:2px solid #f97316;border-radius:8px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                  <div id="vac-photo-container" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <img id="vac-photo-preview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:6px;">
                    <span id="vac-photo-placeholder" style="font-size:10px;color:#9ca3af;text-align:center;padding:4px;line-height:1.35;">
                      <svg width="28" height="28" fill="none" stroke="#f97316" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto 4px;"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                      Photo depuis<br>le formulaire
                    </span>
                  </div>
                </div>

                <div style="text-align:center;">
                  <div style="font-size:10px;color:#6b7280;">Signé par :</div>
                  <div style="margin-top:10px;border-top:1px solid #9ca3af;padding-top:4px;font-size:9px;font-weight:800;color:#111;text-transform:uppercase;letter-spacing:0.04em;">Le Directeur des Ressources Humaines</div>
                </div>
              </div>
            </div>
            </div>
          </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 bg-white flex items-center justify-end gap-2">
          @if($editingPersonnel)
          <form id="virtual-card-transmit-form" method="POST" action="{{ route('admin.personnel.employees.virtual-card.transmit', $editingPersonnel) }}" class="hidden">
            @csrf
            <input type="hidden" name="personnel_tab" value="employees">
            <input type="hidden" name="card_html" id="virtual-card-html-input" value="">
            <input type="hidden" name="signature_zone_page" value="1">
            <input type="hidden" name="signature_zone_x" value="70">
            <input type="hidden" name="signature_zone_y" value="84">
            <input type="hidden" name="signature_zone_width" value="26">
            <input type="hidden" name="signature_zone_height" value="10">
          </form>
          <button type="button" id="btn-transmit-virtual-card" class="px-4 py-2 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold">Transmettre pour signature</button>
          @endif
          <button type="button" id="btn-print-virtual-card" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Imprimer</button>
        </div>
      </div>
    </div>

    <script>
    (function () {
      var form = document.getElementById('employee-registration-form');
      var openBtn = document.getElementById('btn-open-virtual-card');
      var closeBtn = document.getElementById('btn-close-virtual-card');
      var printBtn = document.getElementById('btn-print-virtual-card');
      var transmitBtn = document.getElementById('btn-transmit-virtual-card');
      var transmitForm = document.getElementById('virtual-card-transmit-form');
      var transmitCardHtmlInput = document.getElementById('virtual-card-html-input');
      var modal = document.getElementById('virtual-agent-card-modal');
      var card = document.getElementById('virtual-agent-card-preview');
      if (!form || !openBtn || !closeBtn || !modal || !card) return;

      function textByName(name) {
        var el = form.querySelector('[name="' + name + '"]');
        return el ? String(el.value || '').trim() : '';
      }

      function selectedTextByName(name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) return '';
        var idx = el.selectedIndex;
        if (idx < 0 || !el.options[idx]) return '';
        return String(el.options[idx].text || '').trim();
      }

      function formatDate(isoDate) {
        if (!isoDate) return '-';
        var parts = isoDate.split('-');
        if (parts.length !== 3) return isoDate;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
      }

      function setText(id, value, fallback) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = value && value !== '' ? value : (fallback || '-');
      }

      function cleanAdminName(text) {
        // Supprime les suffixes entre crochets ajoutés dans les options du select (ex: [Émetteur], [Destinataire])
        return text.replace(/\s*\[.*?\]\s*$/, '').trim();
      }

      function refreshCard() {
        var adminText = cleanAdminName(selectedTextByName('administration_id'));
        var maritalStatus = selectedTextByName('marital_status');
        var jobTitle = selectedTextByName('job_title');
        var nniValue = textByName('meta_nni') || textByName('nni');
        setText('vac-last-name', textByName('last_name').toUpperCase(), '-');
        setText('vac-first-name', textByName('first_name'), '-');
        setText('vac-birth-date', formatDate(textByName('birth_date')), '-');
        setText('vac-birth-place', textByName('birth_place'), '-');
        setText('vac-employee-number', textByName('employee_number'), '-');
        setText('vac-job-title', jobTitle, '-');
        setText('vac-hire-date', formatDate(textByName('hire_date')), '-');
        setText('vac-nni', nniValue, '-');
        setText('vac-marital-status', maritalStatus, '-');
        setText('vac-admin-name', adminText && adminText !== '{{ __('personnel.ui.employees.form_select_admin') }}' ? adminText : 'ADMINISTRATION');
      }

      function openModal() {
        refreshCard();
        modal.classList.remove('hidden');
      }

      function closeModal() {
        modal.classList.add('hidden');
      }

      openBtn.addEventListener('click', openModal);
      closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
      });

      // Photo upload preview from registration form field
      var photoInput = document.getElementById('employee-photo-input');
      var photoPreview = document.getElementById('vac-photo-preview');
      var photoPlaceholder = document.getElementById('vac-photo-placeholder');
      var existingPhotoUrl = @json(!empty($empMeta['photo_path']) ? asset('storage/' . $empMeta['photo_path']) : '');

      function applyPhotoPreview(src) {
        if (!photoPreview || !src) return;
        photoPreview.src = src;
        photoPreview.style.display = 'block';
        if (photoPlaceholder) photoPlaceholder.style.display = 'none';
      }

      if (existingPhotoUrl) {
        applyPhotoPreview(existingPhotoUrl);
      }

      if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
          var file = photoInput.files && photoInput.files[0];
          if (!file) return;
          var reader = new FileReader();
          reader.onload = function (e) {
            applyPhotoPreview(e.target.result);
          };
          reader.readAsDataURL(file);
        });
      }

      if (transmitBtn && transmitForm && transmitCardHtmlInput) {
        transmitBtn.addEventListener('click', function () {
          refreshCard();
          transmitCardHtmlInput.value = card.outerHTML;
          transmitForm.submit();
        });
      }

      if (printBtn) {
        printBtn.addEventListener('click', function () {
          var w = window.open('', '_blank', 'width=900,height=700');
          if (!w) return;
          w.document.write('<html><head><title>Carte virtuelle agent</title><style>body{font-family:Arial,sans-serif;padding:30px;background:#fff;}input[type=file]{display:none!important;}</style></head><body>' + card.outerHTML + '</body></html>');
          w.document.close();
          w.focus();
          w.print();
        });
      }
    })();
    </script>
  </section>

  <section class="xl:col-span-3 space-y-5">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
      <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.employees.table_title') }}</h3>
      <div class="mb-4">
        <input
          id="employees-directory-search"
          type="text"
          placeholder="Rechercher un agent (nom, matricule, administration, emploi...)"
          class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400"
        >
      </div>
      <div class="overflow-x-auto">
        <table id="employees-directory-table" class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="py-3 pr-4">{{ __('personnel.ui.employees.table_col_agent') }}</th>
              <th class="py-3 pr-4">{{ __('personnel.ui.employees.table_col_admin') }}</th>
              <th class="py-3 pr-4">Emploi</th>
              <th class="py-3 pr-4">{{ __('personnel.ui.employees.table_col_status') }}</th>
              <th class="py-3 pr-4">{{ __('personnel.ui.employees.table_col_docs') }}</th>
              <th class="py-3">{{ __('personnel.ui.employees.table_col_action') }}</th>
            </tr>
          </thead>
          @php
            $emitterCodesMap = collect($emitters ?? [])->mapWithKeys(fn($e) => [$e->id => ($e->code ?? $e->name)]);
            $recipientCodesMap = collect($recipients ?? [])->mapWithKeys(fn($r) => [$r->id => ($r->code ?? $r->name)]);
          @endphp
          <tbody>
            @forelse($personnelEmployees as $employee)
            @php
              $adminCode = $employee->administration_type === 'recipient'
                ? ($recipientCodesMap[$employee->administration_id] ?? null)
                : ($emitterCodesMap[$employee->administration_id] ?? null);
            @endphp
            <tr class="border-b border-gray-100">
              <td class="py-3 pr-4">
                <div class="font-semibold text-gray-800">{{ $employee->full_name }}</div>
                <div class="text-xs text-gray-500">{{ $employee->employee_number ?: __('personnel.ui.employees.table_no_employee_number') }}</div>
              </td>
              <td class="py-3 pr-4 text-gray-600">{{ $adminCode ?: __('personnel.ui.employees.table_not_assigned') }}</td>
              <td class="py-3 pr-4 text-gray-600">{{ $employee->job_title ?: __('personnel.ui.employees.table_not_defined') }}</td>
              <td class="py-3 pr-4">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">{{ __('personnel.ui.statuses.' . $employee->employment_status) }}</span>
              </td>
              <td class="py-3 pr-4 text-gray-600">{{ $employee->documents->count() }}</td>
              <td class="py-3">
                <a href="{{ route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'employees', 'selected_employee' => $employee->id]) }}" class="text-sm font-semibold text-[#2453d6]">{{ __('personnel.ui.employees.table_btn_open') }}</a>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="py-8 text-center text-sm text-gray-400">{{ __('personnel.ui.employees.table_empty') }}</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <script>
      (function () {
        var searchInput = document.getElementById('employees-directory-search');
        var table = document.getElementById('employees-directory-table');
        if (!searchInput || !table) return;

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));

        searchInput.addEventListener('input', function () {
          var q = (searchInput.value || '').trim().toLowerCase();
          rows.forEach(function (row) {
            if (row.querySelector('td[colspan]')) {
              row.style.display = '';
              return;
            }
            var txt = (row.textContent || '').toLowerCase();
            row.style.display = q === '' || txt.indexOf(q) !== -1 ? '' : 'none';
          });
        });
      })();
      </script>
      @if(method_exists($personnelEmployees, 'links'))
      <div class="mt-4">{{ $personnelEmployees->appends(['tab' => 'personnel', 'personnel_tab' => 'employees', 'selected_employee' => request('selected_employee')])->links() }}</div>
      @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
      <div class="flex items-center justify-between gap-3 mb-4">
        <div>
          <h3 class="text-lg font-bold text-gray-800">{{ __('personnel.ui.employees.documents_title') }}</h3>
          <p class="text-sm text-gray-500">{{ __('personnel.ui.employees.documents_description') }}</p>
        </div>
        @if(!$editingPersonnel)
        <span class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-full px-3 py-1">{{ __('personnel.ui.employees.doc_select_hint') }}</span>
        @endif
      </div>

      @if($editingPersonnel)
      <form method="POST" enctype="multipart/form-data" action="{{ route('admin.personnel.employees.documents.store', $editingPersonnel) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
        @csrf
        <input type="hidden" name="personnel_tab" value="employees">
        <input type="text" name="label" placeholder="{{ __('personnel.ui.employees.doc_placeholder_label') }}" class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
        <select name="category" class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
          <option value="job_description">{{ __('personnel.ui.employees.doc_cat_job_desc') }}</option>
          <option value="cv">{{ __('personnel.ui.employees.doc_cat_cv') }}</option>
          <option value="certificate">{{ __('personnel.ui.employees.doc_cat_certificate') }}</option>
          <option value="visa">{{ __('personnel.ui.employees.doc_cat_visa') }}</option>
          <option value="badge">{{ __('personnel.ui.employees.doc_cat_badge') }}</option>
          <option value="other">{{ __('personnel.ui.employees.doc_cat_other') }}</option>
        </select>
        <input type="file" name="document" class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
        <button type="submit" class="px-5 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.employees.doc_btn_add') }}</button>
      </form>

      <div class="space-y-3">
        @forelse($editingPersonnel->documents as $doc)
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 rounded-xl border border-gray-200 p-4">
          <div>
            <div class="font-semibold text-gray-800">{{ $doc->label }}</div>
            <div class="text-xs text-gray-500">{{ $doc->category }} | {{ $doc->original_name }} | {{ $doc->mime_type ?: 'type inconnu' }}</div>
          </div>
          <a href="{{ route('admin.personnel.documents.download', $doc) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            <i class="fas fa-download"></i>
            {{ __('personnel.ui.employees.doc_btn_download') }}
          </a>
        </div>
        @empty
        <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.employees.doc_empty') }}</div>
        @endforelse
      </div>
      @else
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.employees.doc_private_hint') }}</div>
      @endif
    </div>
  </section>
@elseif($personnelTab === 'agent-space')
@php
  $agentSpaceEmployee = $selectedPersonnelEmployee;
  if ($agentSpaceCanSearchAll && !$agentSpaceEmployee && request('selected_employee')) {
    $agentSpaceEmployee = $personnelEmployeeDirectory->firstWhere('id', request('selected_employee'));
  }
  if ($agentSpaceCanSearchAll && !$agentSpaceEmployee && request('selected_employee_number')) {
    $_selectedEmployeeNumber = trim((string) request('selected_employee_number'));
    if ($_selectedEmployeeNumber !== '') {
      $agentSpaceEmployee = $personnelEmployeeDirectory->first(function ($employee) use ($_selectedEmployeeNumber) {
        return strcasecmp(trim((string) ($employee->employee_number ?? '')), $_selectedEmployeeNumber) === 0;
      });
    }
  }
  $agentSpaceEmployeeNumberInput = $agentSpaceCanSearchAll
    ? trim((string) request('selected_employee_number', $agentSpaceEmployee?->employee_number ?? ''))
    : trim((string) ($agentSpaceEmployee?->employee_number ?? ''));
  $agentSpaceLeaveRequests = collect();
  $agentSpaceTrainingEnrollments = collect();
  $agentSpaceCareerHistory = collect();
  $agentSpaceMutationRequests = collect();
  $agentSpaceMutationTargets = collect();
  if ($agentSpaceEmployee) {
    $agentSpaceLeaveRequests = $personnelLeaveRequests
      ->where('employee_id', $agentSpaceEmployee->id)
      ->sortByDesc('created_at')
      ->take(8)
      ->values();
    $agentSpaceTrainingEnrollments = $personnelTrainingEnrollments
      ->where('employee_id', $agentSpaceEmployee->id)
      ->sortByDesc('created_at')
      ->take(8)
      ->values();
    $agentSpaceCareerHistory = $personnelCareerEvents
      ->where('employee_id', $agentSpaceEmployee->id)
      ->filter(fn($event) => in_array($event->event_type, ['mobility', 'job_change'], true))
      ->sortByDesc('effective_date')
      ->take(12)
      ->values();

    $agentSpaceMutationRequests = ($personnelMutationRequests ?? collect())
      ->where('employee_id', $agentSpaceEmployee->id)
      ->sortByDesc('created_at')
      ->values();

    $agentSpaceMutationTargets = $subEntities
      ->where('scope_type', $agentSpaceEmployee->administration_type)
      ->where('scope_id', $agentSpaceEmployee->administration_id)
      ->filter(function ($entity) use ($agentSpaceEmployee) {
        return !empty($entity->code) && (string) $entity->code !== (string) ($agentSpaceEmployee->subEntity?->code ?? '');
      })
      ->sortBy('name')
      ->values();
  }
  $agentSpaceTab = request('agent_space_tab', 'profile');
  $agentSpaceTabs = [
    'profile' => ['fas fa-user', __('personnel.ui.agent_space.subtab_profile')],
    'leave' => ['fas fa-calendar-check', __('personnel.ui.agent_space.subtab_leave')],
    'training' => ['fas fa-graduation-cap', __('personnel.ui.agent_space.subtab_training')],
    'mutation' => ['fas fa-right-left', 'Mutation'],
    'documents' => ['fas fa-folder-open', __('personnel.ui.agent_space.subtab_documents')],
  ];
  if (!array_key_exists($agentSpaceTab, $agentSpaceTabs)) {
    $agentSpaceTab = 'profile';
  }
@endphp

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h3 class="text-lg font-bold text-gray-800">{{ __('personnel.ui.agent_space.title') }}</h3>
      <p class="text-sm text-gray-500 mt-1">{{ __('personnel.ui.agent_space.description') }}</p>
    </div>
    @if($agentSpaceCanSearchAll)
    <form method="GET" action="{{ route('admin.index') }}" class="flex items-center gap-2">
      <input type="hidden" name="tab" value="personnel">
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="{{ $agentSpaceTab }}">
      <label class="text-sm font-semibold text-gray-700">{{ __('personnel.ui.agent_space.employee_number_label') }}</label>
      <input
        type="text"
        name="selected_employee_number"
        value="{{ $agentSpaceEmployeeNumberInput }}"
        placeholder="{{ __('personnel.ui.agent_space.employee_number_placeholder') }}"
        class="border border-gray-300 rounded-xl px-3 py-2 text-sm"
      >
      <button type="submit" class="px-3 py-2 rounded-xl bg-[#2453d6] text-white text-sm font-semibold">{{ __('personnel.ui.agent_space.search_btn') }}</button>
    </form>
    @else
    <span class="inline-flex items-center rounded-full bg-gray-100 border border-gray-200 text-gray-700 text-xs font-semibold px-3 py-1">
      Espace personnel: vos propres informations et demandes
    </span>
    @endif
  </div>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap gap-2">
    @foreach($agentSpaceTabs as $key => [$icon, $label])
    <a href="{{ route('admin.index', array_merge(['tab' => 'personnel', 'personnel_tab' => 'agent-space', 'agent_space_tab' => $key], $agentSpaceCanSearchAll ? ($agentSpaceEmployee ? ['selected_employee' => $agentSpaceEmployee->id, 'selected_employee_number' => $agentSpaceEmployee->employee_number] : ($agentSpaceEmployeeNumberInput !== '' ? ['selected_employee_number' => $agentSpaceEmployeeNumberInput] : [])) : [])) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition {{ $agentSpaceTab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
      <i class="fa {{ $icon }} text-sm"></i>
      <span>{{ $label }}</span>
    </a>
    @endforeach
  </div>
</div>

@if(!$agentSpaceEmployee)
<div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 mb-5">
  @if($agentSpaceEmployeeNumberInput !== '')
    {{ __('personnel.ui.agent_space.employee_number_not_found') }}
  @else
    Votre compte utilisateur n'est associé à aucun dossier agent. Contactez les RH pour rattacher votre profil.
  @endif
</div>
@else

@if($agentSpaceTab === 'profile')
@php
  $agentAdminLabel = $agentSpaceEmployee->administration_type === 'recipient'
    ? ($recipients->firstWhere('id', $agentSpaceEmployee->administration_id)?->name ?? '-')
    : ($emitters->firstWhere('id', $agentSpaceEmployee->administration_id)?->name ?? '-');
  $agentMeta = $agentSpaceEmployee->metadata ?? [];
  $agentMaritalLabels = [
    'Célibataire' => 'Célibataire', 'Marié(e)' => 'Marié(e)',
    'Divorcé(e)' => 'Divorcé(e)', 'Veuf/Veuve' => 'Veuf/Veuve', 'Union libre' => 'Union libre',
  ];
@endphp

{{-- ── Informations Personnelles ── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-5">
  <h4 class="text-base font-bold text-gray-800 mb-5">Informations Personnelles</h4>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    @php
      $profileFields = [
        ['Nom', $agentSpaceEmployee->last_name ?: '-'],
        ['Prénom', $agentSpaceEmployee->first_name ?: '-'],
        ['Date de naissance', optional($agentSpaceEmployee->birth_date)->format('d/m/Y') ?: '-'],
        ['Lieu de naissance', $agentSpaceEmployee->birth_place ?: '-'],
        ['NNI', $agentMeta['nni'] ?? '-'],
        ['Téléphone', $agentSpaceEmployee->phone ?: '-'],
        ['Email', $agentSpaceEmployee->email ?: '-'],
      ];
    @endphp
    @foreach($profileFields as [$label, $val])
    <div>
      <label class="block text-sm text-gray-500 mb-1">{{ $label }}</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $val }}</div>
    </div>
    @endforeach

    <div class="md:col-span-2">
      <label class="block text-sm text-gray-500 mb-1">Adresse</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 text-sm font-medium text-gray-800 min-h-[4rem]">{{ $agentSpaceEmployee->address ?: '-' }}</div>
    </div>

    <div>
      <label class="block text-sm text-gray-500 mb-1">Situation familiale</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentSpaceEmployee->marital_status ?: 'Célibataire' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Nombre d'enfants</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['children_count'] ?? '0' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Personne à contacter en urgence</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentSpaceEmployee->emergency_contact_name ?: '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Téléphone urgence</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentSpaceEmployee->emergency_contact_phone ?: '-' }}</div>
    </div>
  </div>
</div>

{{-- ── Informations Professionnelles (read-only) ── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-5">
  <div class="flex items-center justify-between mb-5">
    <h4 class="text-base font-bold text-gray-800">Informations Professionnelles</h4>
    <span class="text-xs text-amber-600 font-semibold flex items-center gap-1">
      <i class="fas fa-lock text-amber-500"></i> Lecture seule - Contactez les RH pour modifications
    </span>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm text-gray-500 mb-1">Matricule <span class="text-red-400">*</span></label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentSpaceEmployee->employee_number ?: 'N/A' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Direction Générale</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['direction_generale'] ?? '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Direction Centrale</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['direction_centrale'] ?? '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Sous-Direction</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['sous_direction'] ?? '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Service</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['service'] ?? '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Catégorie</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['categorie'] ?? '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Grade</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['grade'] ?? '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Emploi</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentSpaceEmployee->job_title ?: '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Lieu de Travail</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ $agentMeta['lieu_travail'] ?? '-' }}</div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Date d'embauche <span class="text-red-400">*</span></label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800">{{ optional($agentSpaceEmployee->hire_date)->format('d/m/Y') ?: '-' }}</div>
    </div>
  </div>
</div>

{{-- ── Historique mobilité ── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
  <h4 class="text-base font-bold text-gray-800 mb-3">{{ __('personnel.ui.agent_space.mobility_history_title') }}</h4>
  <div class="space-y-2">
    @forelse($agentSpaceCareerHistory as $event)
    <div class="rounded-xl border border-gray-200 px-3 py-2">
      <div class="text-sm font-semibold text-gray-800">{{ $event->title }}</div>
      <div class="text-xs text-gray-500">{{ optional($event->effective_date)->format('d/m/Y') ?: '-' }} · {{ $event->event_type === 'mobility' ? __('personnel.ui.agent_space.event_mutation') : __('personnel.ui.agent_space.event_assignment') }} · {{ __('personnel.ui.statuses.' . $event->status) }}</div>
    </div>
    @empty
    <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">{{ __('personnel.ui.agent_space.no_mobility') }}</div>
    @endforelse
  </div>
</div>
@elseif($agentSpaceTab === 'leave')
@php
  // Employee-scoped leave types
  $empLeaveTypes = $personnelLeaveTypes
    ->where('administration_type', $agentSpaceEmployee->administration_type)
    ->where('administration_id', $agentSpaceEmployee->administration_id)
    ->where('is_active', true)
    ->values();

  // Annual leave type (code = ANNUAL or ANNUEL)
  $annualLeaveType = $empLeaveTypes->first(function ($t) {
    $code = strtoupper(trim((string) ($t->code ?? '')));
    return in_array($code, ['ANNUAL', 'ANNUEL'], true);
  });

  // Employee leave requests
  $myLeaveRequests = $agentSpaceLeaveRequests;

  // Annual leave stats (current year)
  $annualQuota      = $annualLeaveType ? (int) $annualLeaveType->default_days : 0;
  $annualApproved   = $annualLeaveType
    ? $myLeaveRequests
        ->where('leave_type_id', $annualLeaveType->id)
        ->whereIn('status', ['approved'])
        ->whereNotNull('start_date')
        ->filter(fn($r) => optional($r->start_date)->year === now()->year)
        ->sum(fn($r) => (float) ($r->approved_days ?? $r->requested_days))
    : 0;
  $annualPending    = $myLeaveRequests
    ->where('status', 'pending')
    ->count();
  $annualRemaining  = max(0, $annualQuota - $annualApproved);

  // Status badge colours
  $leaveBadge = [
    'pending'   => 'bg-amber-100 text-amber-800',
    'approved'  => 'bg-green-100 text-green-700',
    'rejected'  => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-gray-100 text-gray-500',
  ];
  $leaveStatusLabels = [
    'pending'   => 'En attente',
    'approved'  => 'approuvée',
    'rejected'  => 'refusée',
    'cancelled' => 'annulée',
  ];

  // Other leave types (not ANNUAL/ANNUEL)
  $otherLeaveTypes = $empLeaveTypes->reject(function ($t) {
    $code = strtoupper(trim((string) ($t->code ?? '')));
    return in_array($code, ['ANNUAL', 'ANNUEL'], true);
  })->values();
@endphp

{{-- ── Header + Action Buttons ── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
  <h3 class="text-xl font-bold text-gray-800">Mes Congés</h3>
  <div class="flex flex-wrap gap-2">
    @if($annualLeaveType)
    <button type="button"
      onclick="document.getElementById('modal-annual-leave').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#2453d6] hover:bg-blue-700 text-white text-sm font-semibold shadow transition">
      <i class="fas fa-calendar-check text-xs"></i> Congé Annuel
    </button>
    @endif
    <button type="button"
      onclick="document.getElementById('modal-other-leave').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow transition">
      <i class="fas fa-plus text-xs"></i> Autre congé
    </button>
  </div>
</div>

{{-- ── Stats Cards ── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="rounded-2xl bg-[#2453d6] text-white p-5 flex items-center justify-between shadow">
    <div>
      <div class="text-4xl font-black">{{ $annualRemaining }}</div>
      <div class="text-sm font-semibold mt-1 opacity-90">Jours Restants</div>
      <div class="text-xs mt-0.5 opacity-70">Sur {{ $annualQuota }} jours acquis</div>
    </div>
    <div class="opacity-40"><i class="fas fa-calendar-check text-4xl"></i></div>
  </div>
  <div class="rounded-2xl bg-emerald-500 text-white p-5 flex items-center justify-between shadow">
    <div>
      <div class="text-4xl font-black">{{ (int) $annualApproved }}</div>
      <div class="text-sm font-semibold mt-1 opacity-90">Jours Pris (Validés)</div>
      <div class="text-xs mt-0.5 opacity-70">Congés annuels confirmés</div>
    </div>
    <div class="opacity-40"><i class="fas fa-calendar-minus text-4xl"></i></div>
  </div>
  <div class="rounded-2xl bg-amber-500 text-white p-5 flex items-center justify-between shadow">
    <div>
      <div class="text-4xl font-black">{{ $annualPending }}</div>
      <div class="text-sm font-semibold mt-1 opacity-90">En Attente</div>
      <div class="text-xs mt-0.5 opacity-70">Demandes à valider</div>
    </div>
    <div class="opacity-40"><i class="fas fa-hourglass-half text-4xl"></i></div>
  </div>
</div>

{{-- ── History of requests ── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <h4 class="text-base font-bold text-gray-800 mb-4">Historique des Demandes</h4>
  <div class="space-y-3">
    @forelse($myLeaveRequests as $lr)
    @php
      $lrDays    = $lr->approved_days ?? $lr->requested_days;
      $badge     = $leaveBadge[$lr->status] ?? 'bg-gray-100 text-gray-500';
      $label     = $leaveStatusLabels[$lr->status] ?? $lr->status;

      // Workflow circuit
      $wfMeta    = is_array($lr->metadata) ? $lr->metadata : [];
      $wf        = is_array($wfMeta['approval_workflow'] ?? null) ? $wfMeta['approval_workflow'] : null;
      $wfSteps   = $wf ? (array) ($wf['steps']   ?? []) : [];
      $wfHistory = $wf ? (array) ($wf['history']  ?? []) : [];
      $wfCurIdx  = $wf ? (int)   ($wf['current_step_index'] ?? 0) : 0;

      // Build step-status map from history
      $wfStepInfo = [];
      foreach ($wfHistory as $h) {
          $si = (int) ($h['step_index'] ?? -1);
          if ($si >= 0 && !isset($wfStepInfo[$si])) {
              $wfStepInfo[$si] = [
                  'status'  => $h['status'] ?? '',
                  'comment' => $h['comment'] ?? '',
                  'at'      => $h['acted_at'] ?? '',
              ];
          }
      }
    @endphp
    <div class="rounded-xl border border-gray-200 px-4 py-3">
      {{-- Header row: info + statut badge --}}
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
        <div>
          <div class="text-sm font-bold text-gray-800">{{ $lr->leaveType?->name ?? 'Type supprimé' }}</div>
          @if($lr->start_date && $lr->end_date)
          <div class="text-xs text-gray-500 mt-0.5">
            Du {{ $lr->start_date->format('d/m/Y') }} au {{ $lr->end_date->format('d/m/Y') }}
          </div>
          @endif
          <div class="text-xs text-gray-400 mt-0.5">
            Durée : {{ $lrDays ? number_format((float) $lrDays, 0) . ' jour(s)' : '-' }}
            &nbsp;·&nbsp; Demandé le : {{ $lr->created_at->format('d/m/Y') }}
          </div>
          @if($lr->reason)
          <div class="text-xs text-gray-400 mt-0.5 italic">"{{ Str::limit($lr->reason, 80) }}"</div>
          @endif
        </div>
        <span class="inline-block flex-shrink-0 text-xs font-semibold px-3 py-1 rounded-full {{ $badge }}">{{ $label }}</span>
      </div>

      {{-- Circuit de validation horizontal --}}
      @if(!empty($wfSteps))
      <div class="mt-3 pt-3 border-t border-gray-100">
        <div class="text-[11px] text-gray-400 font-medium mb-2 flex items-center gap-1">
          <i class="fas fa-project-diagram text-[9px]"></i> Circuit de validation
        </div>
        <div class="flex items-start overflow-x-auto pb-1 gap-0">
          @foreach($wfSteps as $si => $step)
          @php
            $info       = $wfStepInfo[$si] ?? null;
            $isApproved = $info && $info['status'] === 'approved';
            $isRejected = $info && $info['status'] === 'rejected';
            $isCurrent  = !$info && $si === $wfCurIdx && $lr->status === 'pending';
            // Final approval: last step approved = fully approved
            $isFinalApproved = $isApproved && $lr->status === 'approved' && $loop->last;

            if ($isApproved) {
                $circleClass = 'bg-green-500 border-green-500 text-white shadow-green-200';
                $iconClass   = 'fas fa-check';
                $connColor   = 'bg-green-400';
            } elseif ($isRejected) {
                $circleClass = 'bg-red-500 border-red-500 text-white shadow-red-200';
                $iconClass   = 'fas fa-times';
                $connColor   = 'bg-gray-200';
            } elseif ($isCurrent) {
                $circleClass = 'bg-amber-400 border-amber-400 text-white shadow-amber-200';
                $iconClass   = 'fas fa-hourglass-half';
                $connColor   = 'bg-gray-200';
            } else {
                $circleClass = 'bg-white border-gray-300 text-gray-400';
                $iconClass   = 'far fa-circle';
                $connColor   = 'bg-gray-200';
            }

            $profileLabel = trim((string) ($step['profile'] ?? '')) ?: ('Étape ' . ($si + 1));
            $isDrh = ($step['kind'] ?? '') === 'drh_final';
            $hasMotif = $isRejected && !empty($info['comment']);
          @endphp

          {{-- Nœud --}}
          <div class="flex flex-col items-center flex-shrink-0" style="min-width:68px">
            <div class="w-9 h-9 rounded-full border-2 {{ $circleClass }} flex items-center justify-center text-xs shadow-sm">
              <i class="{{ $iconClass }}"></i>
            </div>
            <div class="text-[10px] text-center mt-1 font-medium text-gray-600 leading-tight" style="max-width:64px">
              {{ Str::limit($profileLabel, 22) }}
            </div>
            @if($isDrh)
            <div class="text-[9px] text-indigo-500 font-semibold -mt-0.5">DRH</div>
            @endif
            @if($hasMotif)
            <button type="button"
              onclick="openMotifPopup({{ json_encode($info['comment']) }}, {{ json_encode($profileLabel) }}, {{ json_encode($info['at']) }})"
              class="mt-1 text-[10px] px-2 py-0.5 rounded-full bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 transition font-semibold whitespace-nowrap">
              <i class="fas fa-comment-alt text-[8px]"></i> Motif
            </button>
            @endif
          </div>

          {{-- Connecteur (pas après le dernier) --}}
          @if(!$loop->last)
          <div class="flex-shrink-0 self-start" style="margin-top:16px; width:28px">
            <div class="h-0.5 w-full {{ $connColor }} rounded"></div>
          </div>
          @endif
          @endforeach
        </div>
      </div>
      @endif
    </div>
    @empty
    <div class="rounded-xl bg-gray-50 border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
      <i class="fas fa-calendar-times text-gray-300 text-2xl mb-2 block"></i>
      Aucune demande de congé pour le moment.
    </div>
    @endforelse
  </div>
</div>

{{-- ── Modal motif de rejet ── --}}
<div id="motif-popup" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
        <i class="fas fa-comment-alt text-red-500"></i>
        Motif du rejet &mdash; <span id="motif-profile-name" class="text-red-600 ml-1"></span>
      </h3>
      <button type="button" onclick="closeMotifPopup()" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="px-6 py-5 space-y-2">
      <p id="motif-comment-text" class="text-sm text-gray-700 leading-relaxed"></p>
      <p id="motif-date-text" class="text-xs text-gray-400"></p>
    </div>
    <div class="px-6 py-3 border-t border-gray-100 flex justify-end">
      <button type="button" onclick="closeMotifPopup()"
        class="px-4 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
        Fermer
      </button>
    </div>
  </div>
</div>
@push('scripts')
<script>
function openMotifPopup(comment, profile, date) {
    document.getElementById('motif-comment-text').textContent = comment || 'Aucun motif renseigné.';
    document.getElementById('motif-profile-name').textContent = profile || '';
    var d = '';
    if (date) {
        try { d = 'Le ' + new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }); } catch(e) { d = date; }
    }
    document.getElementById('motif-date-text').textContent = d;
    var modal = document.getElementById('motif-popup');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeMotifPopup() {
    var modal = document.getElementById('motif-popup');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
document.getElementById('motif-popup').addEventListener('click', function(e) {
    if (e.target === this) closeMotifPopup();
});
</script>
@endpush

{{-- ── Special absences / permissions section ── --}}
@php
  $specialRequests = $myLeaveRequests->whereNotIn('leave_type_id', $annualLeaveType ? [$annualLeaveType->id] : []);
@endphp
@if($specialRequests->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
  <h4 class="text-base font-bold text-gray-800 mb-4">Permissions spéciales d'absence</h4>
  <div class="space-y-3">
    @foreach($specialRequests as $lr)
    @php
      $badge = $leaveBadge[$lr->status] ?? 'bg-gray-100 text-gray-500';
      $label = $leaveStatusLabels[$lr->status] ?? $lr->status;
    @endphp
    <div class="rounded-xl border border-gray-200 px-4 py-3 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
      <div>
        <div class="text-sm font-bold text-gray-800">{{ $lr->leaveType?->name ?? 'Type supprimé' }}</div>
        @if($lr->start_date && $lr->end_date)
        <div class="text-xs text-gray-500 mt-0.5">Du {{ $lr->start_date->format('d/m/Y') }} au {{ $lr->end_date->format('d/m/Y') }}</div>
        @endif
        <div class="text-xs text-gray-400 mt-0.5">Demandé le : {{ $lr->created_at->format('d/m/Y') }}</div>
      </div>
      <span class="inline-block flex-shrink-0 text-xs font-semibold px-3 py-1 rounded-full {{ $badge }}">{{ $label }}</span>
    </div>
    @endforeach
  </div>
</div>
@endif

{{-- ══════════ MODAL — Congé Annuel ══════════ --}}
@if($annualLeaveType)
<div id="modal-annual-leave"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
    <div class="p-6">
      {{-- Modal header --}}
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-xl font-bold text-gray-800">🚀 Demande de Congé Annuel</h2>
          <p class="text-sm text-gray-500 mt-0.5">Choisissez librement vos dates de congés annuels</p>
        </div>
        <button type="button" onclick="document.getElementById('modal-annual-leave').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 transition">
          <i class="fas fa-times text-lg"></i>
        </button>
      </div>

      {{-- Balance card --}}
      <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 mb-5">
        <div class="text-sm font-bold text-blue-800 mb-3 flex items-center gap-2">
          <i class="fas fa-calendar-check text-blue-500"></i> Votre Solde Congés Annuels
        </div>
        <div class="grid grid-cols-3 gap-3 text-center">
          <div class="bg-white rounded-xl border border-blue-100 py-3">
            <div class="text-2xl font-black text-blue-600">{{ $annualQuota }}</div>
            <div class="text-xs text-gray-500 mt-0.5">Quota annuel</div>
          </div>
          <div class="bg-white rounded-xl border border-amber-100 py-3">
            <div class="text-2xl font-black text-amber-500">{{ (int) $annualApproved }}</div>
            <div class="text-xs text-gray-500 mt-0.5">Jours utilisés</div>
          </div>
          <div class="bg-white rounded-xl border border-green-100 py-3">
            <div class="text-2xl font-black text-green-600">{{ $annualRemaining }}</div>
            <div class="text-xs text-gray-500 mt-0.5">Jours restants</div>
          </div>
        </div>
      </div>

      {{-- Form --}}
      <form method="POST" action="{{ route('admin.personnel.leave-requests.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <input type="hidden" name="personnel_tab" value="agent-space">
        <input type="hidden" name="agent_space_tab" value="leave">
        <input type="hidden" name="employee_id" value="{{ $agentSpaceEmployee->id }}">
        <input type="hidden" name="selected_employee" value="{{ $agentSpaceEmployee->id }}">
        <input type="hidden" name="leave_type_id" value="{{ $annualLeaveType->id }}">
        <input type="hidden" name="annual_segments_json" id="annual-segments-json" value="">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            <i class="fas fa-hashtag mr-1 text-blue-400"></i> Nombre de jours demandés <span class="text-red-500">*</span>
          </label>
          <input type="number" id="annual-days-count" name="requested_days"
            value="{{ max(1, (int) $annualRemaining) }}"
            min="1" max="{{ $annualRemaining }}" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
          <p class="text-xs text-gray-400 mt-1">Vous pouvez fractionner vos 30 jours en plusieurs demandes. Solde disponible : <strong class="text-blue-700">{{ $annualRemaining }} jour(s)</strong>.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              <i class="fas fa-calendar mr-1 text-blue-400"></i> Date de départ <span class="text-red-500">*</span>
            </label>
            <input type="date" id="annual-start-input" required
              class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              <i class="fas fa-calendar mr-1 text-blue-400"></i> Date de fin (automatique)
            </label>
            <input type="date" id="annual-end-input" readonly
              class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 cursor-not-allowed">
          </div>
        </div>

        <div>
          <input type="hidden" name="start_date" id="annual-start" value="{{ old('start_date') }}">
          <input type="hidden" name="end_date" id="annual-end" value="{{ old('end_date') }}">
        </div>

        <div id="annual-days-preview" class="hidden rounded-xl bg-blue-50 border border-blue-100 px-4 py-2.5">
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <span class="text-sm font-bold text-blue-700" id="annual-days-preview-text"></span>
            <div class="flex gap-4 text-center">
              <div>
                <div class="text-lg font-black text-amber-500" id="annual-preview-used">0</div>
                <div class="text-xs text-gray-500">Jours utilisés</div>
              </div>
              <div>
                <div class="text-lg font-black text-green-600" id="annual-preview-remaining">{{ $annualRemaining }}</div>
                <div class="text-xs text-gray-500">Jours restants</div>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            🔥 Motif <span class="text-gray-400 font-normal">(optionnel)</span>
          </label>
          <textarea name="reason" rows="3"
            placeholder="Vacances en famille, repos, etc."
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-400">{{ old('reason') }}</textarea>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            📎 Pièce justificative <span class="text-gray-400 font-normal">(optionnel)</span>
          </label>
          <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-gray-300 file:text-sm file:font-semibold file:bg-white file:text-gray-700 hover:file:bg-gray-50">
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
            class="flex-1 py-3 rounded-xl bg-[#2453d6] hover:bg-blue-700 text-white text-sm font-bold shadow transition">
            <i class="fas fa-paper-plane mr-2"></i> Envoyer la demande
          </button>
          <button type="button"
            onclick="document.getElementById('modal-annual-leave').classList.add('hidden')"
            class="px-5 py-3 rounded-xl border border-gray-300 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
            Annuler
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

{{-- ══════════ MODAL — Autre Congé ══════════ --}}
<div id="modal-other-leave"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
    <div class="p-6">
      {{-- Modal header --}}
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold text-gray-800">Nouvelle demande de congé</h2>
        <button type="button" onclick="document.getElementById('modal-other-leave').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 transition">
          <i class="fas fa-times text-lg"></i>
        </button>
      </div>

      @if($otherLeaveTypes->isEmpty())
      <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-4 text-sm text-amber-800">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Aucun autre type de congé configuré pour cette administration.
      </div>
      @else
      <form method="POST" action="{{ route('admin.personnel.leave-requests.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <input type="hidden" name="personnel_tab" value="agent-space">
        <input type="hidden" name="agent_space_tab" value="leave">
        <input type="hidden" name="employee_id" value="{{ $agentSpaceEmployee->id }}">
        <input type="hidden" name="selected_employee" value="{{ $agentSpaceEmployee->id }}">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Type de congé <span class="text-red-500">*</span>
          </label>
          <select name="leave_type_id" id="other-leave-type" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
            <option value="">Sélectionner un type</option>
            @foreach($otherLeaveTypes as $lt)
            <option value="{{ $lt->id }}"
              data-requires-attachment="{{ $lt->requires_attachment ? '1' : '0' }}"
              data-default-days="{{ (int) $lt->default_days }}">
              {{ $lt->name }}
            </option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Nombre de jours <span class="text-red-500">*</span>
          </label>
          <input type="number" name="requested_days" id="other-days" min="1" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-400">
          <p class="text-xs text-gray-400 mt-1">Saisissez le nombre de jours, la date de fin sera calculée automatiquement.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              Date de début <span class="text-red-500">*</span>
            </label>
            <input type="date" name="start_date" id="other-start" required
              value="{{ old('start_date') }}"
              class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              Date de fin <span class="text-gray-400 font-normal text-xs">(automatique)</span>
            </label>
            <input type="date" name="end_date" id="other-end" readonly
              class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 cursor-not-allowed">
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Motif</label>
          <textarea name="reason" rows="3"
            placeholder="Précisez le motif de votre demande..."
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-400">{{ old('reason') }}</textarea>
        </div>

        <div id="other-attachment-wrap">
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Pièces justificatives <span id="other-attachment-required" class="text-gray-400 font-normal">(optionnel)</span>
          </label>
          <input type="file" name="attachment" id="other-attachment" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-gray-300 file:text-sm file:font-semibold file:bg-white file:text-gray-700 hover:file:bg-gray-50">
          <p class="text-xs text-gray-400 mt-1">Formats acceptés : PDF, JPG, PNG, DOC, DOCX (max 5 Mo par fichier)</p>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
            class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow transition">
            <i class="fas fa-paper-plane mr-2"></i> Envoyer la demande
          </button>
          <button type="button"
            onclick="document.getElementById('modal-other-leave').classList.add('hidden')"
            class="px-5 py-3 rounded-xl border border-gray-300 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
            Annuler
          </button>
        </div>
      </form>
      @endif
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  // Annual leave — fixed days (remaining balance) + auto end date from start date
  var annualStart = document.getElementById('annual-start');
  var annualEnd = document.getElementById('annual-end');
  var annualStartInput = document.getElementById('annual-start-input');
  var annualEndInput = document.getElementById('annual-end-input');
  var annualDaysCount = document.getElementById('annual-days-count');
  var annualPreview = document.getElementById('annual-days-preview');
  var annualSegmentsJson = document.getElementById('annual-segments-json');

  var annualQuota = {{ $annualQuota }};
  var annualAlreadyApproved = {{ (int) $annualApproved }};
  var annualMaxAllowed = {{ $annualRemaining }};
  var annualPreviewText = document.getElementById('annual-days-preview-text');
  var annualPreviewUsed = document.getElementById('annual-preview-used');
  var annualPreviewRemaining = document.getElementById('annual-preview-remaining');

  function addDaysIso(isoDate, days) {
    var base = new Date(isoDate + 'T00:00:00');
    if (isNaN(base.getTime())) return '';
    base.setDate(base.getDate() + days);
    var y = base.getFullYear();
    var m = String(base.getMonth() + 1).padStart(2, '0');
    var d = String(base.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function updateAnnualAutoRange() {
    if (!annualStartInput || !annualDaysCount || !annualEndInput) return;

    var s = annualStartInput.value;
    var days = parseInt(annualDaysCount.value || '0', 10);

    // Clamp days to valid range
    if (days < 1) days = 1;
    if (days > annualMaxAllowed) {
      days = annualMaxAllowed;
      annualDaysCount.value = days;
    }

    if (!s || isNaN(days) || days <= 0) {
      annualEndInput.value = '';
      if (annualStart) annualStart.value = '';
      if (annualEnd) annualEnd.value = '';
      if (annualSegmentsJson) annualSegmentsJson.value = '';
      if (annualPreview) annualPreview.classList.add('hidden');
      return;
    }

    var e = addDaysIso(s, days - 1);
    annualEndInput.value = e;
    if (annualStart) annualStart.value = s;
    if (annualEnd) annualEnd.value = e;
    if (annualSegmentsJson) {
      annualSegmentsJson.value = JSON.stringify([{ start_date: s, end_date: e, days: days }]);
    }

    var used = annualAlreadyApproved + days;
    var remaining = Math.max(0, annualQuota - used);
    if (annualPreviewText) annualPreviewText.textContent = days + ' jour(s) demandé(s) — après approbation il restera ' + remaining + ' jour(s)';
    if (annualPreviewUsed) annualPreviewUsed.textContent = used;
    if (annualPreviewRemaining) annualPreviewRemaining.textContent = remaining;
    if (annualPreview) annualPreview.classList.remove('hidden');
  }

  if (annualStartInput) annualStartInput.addEventListener('change', updateAnnualAutoRange);
  if (annualDaysCount) annualDaysCount.addEventListener('input', updateAnnualAutoRange);
  if (annualDaysCount) annualDaysCount.addEventListener('change', updateAnnualAutoRange);
  updateAnnualAutoRange();

  // Other leave — auto-compute days
  var otherStart = document.getElementById('other-start');
  var otherEnd   = document.getElementById('other-end');
  var otherDays  = document.getElementById('other-days');
  var otherType  = document.getElementById('other-leave-type');
  var otherAttachReq = document.getElementById('other-attachment-required');
  var otherAttachment = document.getElementById('other-attachment');

  // Other leave: end date = start date + days - 1 (auto computed)
  function updateOtherEndDate() {
    if (!otherStart || !otherEnd || !otherDays) return;
    var s = otherStart.value;
    var days = parseInt(otherDays.value || '0', 10);
    if (s && days >= 1) {
      otherEnd.value = addDaysIso(s, days - 1);
    } else {
      otherEnd.value = '';
    }
  }
  if (otherStart) otherStart.addEventListener('change', updateOtherEndDate);
  if (otherDays)  otherDays.addEventListener('input',   updateOtherEndDate);
  if (otherDays)  otherDays.addEventListener('change',  updateOtherEndDate);

  // Show required indicator when attachment needed
  if (otherType) {
    otherType.addEventListener('change', function () {
      var opt = this.options[this.selectedIndex];
      if (!opt) return;
      var req = opt.getAttribute('data-requires-attachment') === '1';
      if (otherAttachReq) otherAttachReq.textContent = req ? '(obligatoire)' : '(optionnel)';
      if (req) {
        otherAttachReq.className = 'text-red-500 font-semibold';
        if (otherAttachment) otherAttachment.setAttribute('required', 'required');
      } else {
        otherAttachReq.className = 'text-gray-400 font-normal';
        if (otherAttachment) otherAttachment.removeAttribute('required');
      }
    });
  }
})();
</script>
@endpush
@elseif($agentSpaceTab === 'training')
@php
  $agentSpaceTrainings = $personnelTrainings
    ->where('administration_type', $agentSpaceEmployee->administration_type)
    ->where('administration_id', $agentSpaceEmployee->administration_id)
    ->values();
@endphp
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3">{{ __('personnel.ui.agent_space.training_request_title') }}</h4>
    @if($agentSpaceTrainings->isEmpty())
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-4 text-sm text-amber-800">{{ __('personnel.ui.agent_space.no_trainings') }}</div>
    @else
    <form method="POST" action="{{ route('admin.personnel.training-enrollments.store') }}" class="space-y-3">
      @csrf
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="training">
      <input type="hidden" name="employee_id" value="{{ $agentSpaceEmployee->id }}">
      <input type="hidden" name="status" value="planned">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.agent_space.training_label') }}</label>
        <select name="training_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
          <option value="">{{ __('personnel.ui.agent_space.select') }}</option>
          @foreach($agentSpaceTrainings as $training)
          <option value="{{ $training->id }}">{{ $training->title }}</option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <input type="date" name="planned_start_date" value="{{ old('planned_start_date') }}" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
        <input type="date" name="planned_end_date" value="{{ old('planned_end_date') }}" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
      </div>
      <textarea name="notes" rows="2" placeholder="{{ __('personnel.ui.agent_space.training_goal_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">{{ old('notes') }}</textarea>
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.agent_space.btn_submit_request') }}</button>
    </form>
    @endif
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3">{{ __('personnel.ui.agent_space.requested_training_title') }}</h4>
    <div class="space-y-2">
      @forelse($agentSpaceTrainingEnrollments as $enrollment)
      @php
        $_enrStatus      = (string) ($enrollment->status ?? 'planned');
        $_enrWorkflow    = is_array(data_get($enrollment->metadata, 'approval_workflow')) ? data_get($enrollment->metadata, 'approval_workflow') : [];
        $_enrSteps       = collect(data_get($_enrWorkflow, 'steps', []))->values();
        $_enrCurrentIdx  = (int) data_get($_enrWorkflow, 'current_step_index', 0);
        $_enrHistory     = collect(data_get($_enrWorkflow, 'history', []));
        $_enrHasWorkflow = !empty($_enrWorkflow) && $_enrSteps->isNotEmpty();

        $_enrRejHist     = $_enrHistory->first(fn($h) => (string) data_get($h, 'status', '') === 'rejected');
        $_enrRejIdx      = $_enrRejHist !== null ? (int) data_get($_enrRejHist, 'step_index', -1) : -1;

        // 'planned' = approuvée entièrement (équivalent de 'validated' dans les mutations)
        $_enrCircStatus  = $_enrStatus === 'rejected' ? 'rejected' : ($_enrStatus === 'pending' ? 'pending' : 'validated');

        $_enrTotalSteps  = $_enrSteps->count();
        if ($_enrCircStatus === 'validated') {
          $_enrDone = $_enrTotalSteps;
        } elseif ($_enrCircStatus === 'rejected') {
          $_enrDone = max(0, $_enrRejIdx);
        } else {
          $_enrDone = min(max(0, $_enrCurrentIdx), $_enrTotalSteps);
        }

        $_enrBadges = [
          'pending'     => ['bg' => 'bg-amber-100 text-amber-800',    'label' => 'En attente'],
          'planned'     => ['bg' => 'bg-emerald-100 text-emerald-700', 'label' => 'Approuvée'],
          'in_progress' => ['bg' => 'bg-blue-100 text-blue-700',      'label' => 'En cours'],
          'completed'   => ['bg' => 'bg-emerald-100 text-emerald-700', 'label' => 'Terminée'],
          'cancelled'   => ['bg' => 'bg-gray-100 text-gray-600',      'label' => 'Annulée'],
          'rejected'    => ['bg' => 'bg-red-100 text-red-700',        'label' => 'Rejetée'],
        ];
        $_enrBadge = $_enrBadges[$_enrStatus] ?? ['bg' => 'bg-gray-100 text-gray-700', 'label' => ucfirst($_enrStatus)];

        $_enrKindLabels = [
          'chef_service'   => 'Chef de service',
          'sous_directeur' => 'Sous-Directeur',
          'directeur'      => 'Directeur',
          'drh_final'      => 'DRH / Ressources Humaines',
        ];

        $_enrCircuitId   = 'enr_circuit_' . $enrollment->id;
        $_enrCircuitOpen = ($_enrStatus === 'pending');
        $_enrNextStep    = ($_enrCircStatus === 'pending' && $_enrSteps->has($_enrCurrentIdx)) ? $_enrSteps->get($_enrCurrentIdx) : null;
        $_enrNextName    = $_enrNextStep ? ($allUsers->firstWhere('id', (string) data_get($_enrNextStep, 'user_id'))?->name ?? 'Valideur') : null;
        $_enrNextKind    = $_enrNextStep ? ($_enrKindLabels[data_get($_enrNextStep, 'kind', '')] ?? data_get($_enrNextStep, 'profile', '')) : null;
        $_enrPct         = $_enrTotalSteps > 0 ? (int) round(($_enrDone / $_enrTotalSteps) * 100) : 0;
      @endphp

      <div class="rounded-xl border border-gray-200 px-4 py-3">

        {{-- En-tête --}}
        <div class="flex items-center justify-between gap-2">
          <div class="text-sm font-semibold text-gray-800 truncate">{{ $enrollment->training?->title ?? __('personnel.ui.agent_space.deleted_training') }}</div>
          <span class="flex-shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $_enrBadge['bg'] }}">{{ $_enrBadge['label'] }}</span>
        </div>

        {{-- Dates & soumission --}}
        <div class="text-xs text-gray-400 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
          @if($enrollment->planned_start_date || $enrollment->planned_end_date)
          <span><i class="far fa-calendar mr-1"></i>{{ optional($enrollment->planned_start_date)->format('d/m/Y') ?: '-' }}@if($enrollment->planned_end_date) → {{ optional($enrollment->planned_end_date)->format('d/m/Y') }}@endif</span>
          @endif
          <span><i class="far fa-clock mr-1"></i>Soumise le {{ optional($enrollment->created_at)->format('d/m/Y H:i') ?: '-' }}</span>
        </div>

        @if($enrollment->notes)
        <div class="text-xs text-gray-500 mt-1.5 italic">{{ Str::limit($enrollment->notes, 100) }}</div>
        @endif

        {{-- ── Circuit de validation (uniquement si demande via espace agent) ── --}}
        @if($_enrHasWorkflow)
        <div class="mt-3 rounded-xl overflow-hidden border
          {{ $_enrCircStatus === 'validated' ? 'border-emerald-200' : ($_enrCircStatus === 'rejected' ? 'border-red-200' : 'border-blue-200') }}">

          {{-- Header cliquable --}}
          <button type="button"
            onclick="(function(el){ el.classList.toggle('hidden') })(document.getElementById('{{ $_enrCircuitId }}'))"
            class="w-full flex items-center justify-between px-3 py-2.5 text-left gap-2
              {{ $_enrCircStatus === 'validated' ? 'bg-emerald-50' : ($_enrCircStatus === 'rejected' ? 'bg-red-50' : 'bg-blue-50') }}">
            <div class="flex items-center gap-2">
              @if($_enrCircStatus === 'validated')
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white shadow-sm">
                  <i class="fas fa-check-double text-xs"></i>
                </span>
                <span class="text-xs font-bold text-emerald-700">Demande approuvée</span>
              @elseif($_enrCircStatus === 'rejected')
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white shadow-sm">
                  <i class="fas fa-times text-xs"></i>
                </span>
                <span class="text-xs font-bold text-red-700">Demande rejetée</span>
              @else
                <span class="relative flex items-center justify-center w-6 h-6">
                  <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                  <span class="relative flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white shadow-sm">
                    <i class="fas fa-hourglass-half text-xs"></i>
                  </span>
                </span>
                <span class="text-xs font-bold text-blue-700">Circuit de validation en cours</span>
              @endif
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs font-semibold
                {{ $_enrCircStatus === 'validated' ? 'text-emerald-600' : ($_enrCircStatus === 'rejected' ? 'text-red-600' : 'text-blue-600') }}">
                {{ $_enrDone }}/{{ $_enrTotalSteps }}
              </span>
              <div class="w-14 h-1.5 rounded-full bg-white/60 overflow-hidden border
                {{ $_enrCircStatus === 'validated' ? 'border-emerald-200' : ($_enrCircStatus === 'rejected' ? 'border-red-200' : 'border-blue-200') }}">
                <div class="h-full rounded-full
                  {{ $_enrCircStatus === 'rejected' ? 'bg-red-500' : ($_enrCircStatus === 'validated' ? 'bg-emerald-500' : 'bg-blue-500') }}"
                  style="width: {{ $_enrPct }}%"></div>
              </div>
              <i class="fas fa-chevron-down text-xs opacity-40"></i>
            </div>
          </button>

          {{-- Corps (ouvert si en attente) --}}
          <div id="{{ $_enrCircuitId }}" class="{{ $_enrCircuitOpen ? '' : 'hidden' }} bg-white px-3 py-4">

            {{-- Barre pleine --}}
            <div class="mb-4 h-2 w-full rounded-full bg-gray-100 overflow-hidden border border-gray-200">
              <div class="h-full rounded-full transition-all duration-700
                {{ $_enrCircStatus === 'rejected' ? 'bg-red-400' : ($_enrCircStatus === 'validated' ? 'bg-emerald-500' : 'bg-blue-500') }}"
                style="width: {{ $_enrPct }}%"></div>
            </div>

            {{-- Timeline --}}
            <div class="relative">
              @if($_enrSteps->count() > 1)
              <div class="absolute left-[19px] top-5 bottom-5 w-0.5 bg-gray-200"></div>
              @endif
              <div class="space-y-4">
                @foreach($_enrSteps as $idx => $step)
                @php
                  $sName    = $allUsers->firstWhere('id', (string) data_get($step, 'user_id'))?->name ?? 'Valideur';
                  $sKind    = (string) data_get($step, 'kind', '');
                  $sProf    = (string) data_get($step, 'profile', $sKind);
                  $sKindLbl = $_enrKindLabels[$sKind] ?? $sProf;
                  $sWds     = preg_split('/\s+/', trim($sName));
                  $sInits   = strtoupper(substr($sWds[0] ?? '', 0, 1)) . strtoupper(substr($sWds[1] ?? '', 0, 1));
                  $sHist    = $_enrHistory->first(fn($h) => (int) data_get($h, 'step_index') === $idx);
                  $sAt      = $sHist ? data_get($sHist, 'acted_at') : null;
                  $sCmt     = $sHist ? data_get($sHist, 'comment') : null;

                  if ($_enrCircStatus === 'validated') {
                    $sState = 'done';
                  } elseif ($_enrCircStatus === 'rejected') {
                    if ($_enrRejIdx >= 0 && $idx === $_enrRejIdx) $sState = 'rejected';
                    elseif ($_enrRejIdx >= 0 && $idx < $_enrRejIdx) $sState = 'done';
                    else $sState = 'todo';
                  } else {
                    if ($idx < $_enrCurrentIdx) $sState = 'done';
                    elseif ($idx === $_enrCurrentIdx) $sState = 'current';
                    else $sState = 'todo';
                  }
                @endphp
                <div class="relative flex items-start gap-3">

                  {{-- Icône --}}
                  <div class="relative z-10 flex-shrink-0">
                    @if($sState === 'done')
                      <div class="w-10 h-10 rounded-full bg-emerald-500 border-2 border-emerald-200 flex items-center justify-center shadow">
                        <i class="fas fa-check text-white text-xs"></i>
                      </div>
                    @elseif($sState === 'rejected')
                      <div class="w-10 h-10 rounded-full bg-red-500 border-2 border-red-200 flex items-center justify-center shadow">
                        <i class="fas fa-times text-white text-xs"></i>
                      </div>
                    @elseif($sState === 'current')
                      <div class="relative w-10 h-10">
                        <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                        <div class="relative w-10 h-10 rounded-full bg-blue-500 border-2 border-blue-200 flex items-center justify-center shadow">
                          <i class="fas fa-hourglass-half text-white text-xs"></i>
                        </div>
                      </div>
                    @else
                      <div class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-300 flex items-center justify-center">
                        <span class="text-xs font-bold text-gray-400">{{ $idx + 1 }}</span>
                      </div>
                    @endif
                  </div>

                  {{-- Contenu --}}
                  <div class="flex-1 min-w-0 pb-1">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                      <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                          <span class="text-xs font-bold
                            {{ $sState === 'done' ? 'text-emerald-700' : ($sState === 'rejected' ? 'text-red-700' : ($sState === 'current' ? 'text-blue-700' : 'text-gray-400')) }}">
                            {{ $sKindLbl }}
                          </span>
                          @if($sState === 'done')
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                              <i class="fas fa-check text-xs"></i> Validé
                            </span>
                          @elseif($sState === 'rejected')
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                              <i class="fas fa-times text-xs"></i> Rejeté
                            </span>
                          @elseif($sState === 'current')
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 animate-pulse">
                              <i class="fas fa-clock text-xs"></i> En attente
                            </span>
                          @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs text-gray-400 bg-gray-100">À venir</span>
                          @endif
                        </div>
                        <div class="flex items-center gap-1.5 mt-1">
                          <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                            {{ $sState === 'done' ? 'bg-emerald-100 text-emerald-700' : ($sState === 'rejected' ? 'bg-red-100 text-red-700' : ($sState === 'current' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400')) }}">
                            {{ $sInits ?: '?' }}
                          </div>
                          <span class="text-xs font-medium {{ $sState === 'todo' ? 'text-gray-400' : 'text-gray-700' }} truncate">{{ $sName }}</span>
                        </div>
                      </div>
                      @if($sAt)
                      <div class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                        <i class="far fa-clock mr-0.5"></i>{{ \Carbon\Carbon::parse($sAt)->format('d/m/Y à H:i') }}
                      </div>
                      @endif
                    </div>
                    @if($sCmt)
                    <div class="mt-2 rounded-lg px-3 py-2 text-xs flex items-start gap-2
                      {{ $sState === 'rejected' ? 'bg-red-50 border border-red-100 text-red-700' : 'bg-gray-50 border border-gray-100 text-gray-600' }}">
                      <i class="fas fa-comment-alt mt-0.5 opacity-60 flex-shrink-0"></i>
                      <span>{{ $sCmt }}</span>
                    </div>
                    @endif
                  </div>
                </div>
                @endforeach
              </div>
            </div>

            {{-- Callout prochain valideur --}}
            @if($_enrCircStatus === 'pending' && $_enrNextStep)
            <div class="mt-4 rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-user-check text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-blue-900">Prochain valideur</div>
                <div class="text-xs text-blue-700 truncate">{{ $_enrNextName }} · {{ $_enrNextKind }}</div>
              </div>
            </div>
            @endif

            {{-- Callout approuvée --}}
            @if($_enrCircStatus === 'validated')
            <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-emerald-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-check-double text-white text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-bold text-emerald-800">Formation approuvée par tous les responsables</div>
                <div class="text-xs text-emerald-600">Votre demande a été validée. La formation est planifiée.</div>
              </div>
            </div>
            @endif

            {{-- Callout rejetée --}}
            @if($_enrCircStatus === 'rejected' && data_get($enrollment->metadata, 'rejection_reason'))
            <div class="mt-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-red-500 flex items-center justify-center shadow-sm mt-0.5">
                <i class="fas fa-ban text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-red-800">Motif de rejet</div>
                <div class="text-xs text-red-700">{{ data_get($enrollment->metadata, 'rejection_reason') }}</div>
              </div>
            </div>
            @endif

          </div>{{-- fin corps --}}
        </div>{{-- fin circuit --}}
        @endif

        {{-- Rejet sans workflow (assignation directe) --}}
        @if($_enrStatus === 'rejected' && !$_enrHasWorkflow && data_get($enrollment->metadata, 'rejection_reason'))
        <div class="mt-2 rounded-lg bg-red-50 border border-red-100 px-3 py-2 text-xs text-red-700">
          <i class="fas fa-ban mr-1 opacity-60"></i>{{ data_get($enrollment->metadata, 'rejection_reason') }}
        </div>
        @endif

      </div>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">{{ __('personnel.ui.agent_space.no_training_requests') }}</div>
      @endforelse
    </div>
  </section>
</div>
@elseif($agentSpaceTab === 'mutation')
@php
  $mutationStatusBadge = [
    'pending' => 'bg-amber-100 text-amber-800',
    'validated' => 'bg-emerald-100 text-emerald-700',
    'rejected' => 'bg-red-100 text-red-700',
  ];
  $mutationStatusLabel = [
    'pending' => 'En attente',
    'validated' => 'Validée',
    'rejected' => 'Rejetée',
  ];
@endphp
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
      <h4 class="text-base font-bold text-gray-800">Nouvelle demande de mutation</h4>
      <span class="text-xs rounded-full bg-blue-50 text-blue-700 px-3 py-1 border border-blue-100">Circuit hiérarchique automatique</span>
    </div>

    @if($agentSpaceMutationTargets->isEmpty())
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-4 text-sm text-amber-800">
      Aucune entité de destination disponible pour votre administration.
    </div>
    @else
    <form method="POST" action="{{ route('admin.personnel.mutation-requests.store') }}" enctype="multipart/form-data" class="space-y-3">
      @csrf
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="mutation">
      <input type="hidden" name="employee_id" value="{{ $agentSpaceEmployee->id }}">

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Entité de destination</label>
        <select name="target_sub_entity_code" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm" required>
          <option value="">Sélectionner une entité</option>
          @foreach($agentSpaceMutationTargets as $target)
          <option value="{{ $target->code }}" {{ old('target_sub_entity_code') === $target->code ? 'selected' : '' }}>
            {{ $target->name }} ({{ $target->code }})
          </option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Objet de la demande</label>
        <input type="text" name="summary" value="{{ old('summary') }}" placeholder="Ex: Mutation pour rapprochement de service"
          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Justification</label>
        <textarea name="notes" rows="4" placeholder="Précisez les raisons de votre demande..."
          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">{{ old('notes') }}</textarea>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Pièces justificatives</label>
        <div class="space-y-3">
          <div class="flex gap-2">
            <input type="file" id="mutationAttachmentInput" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
              class="flex-1 text-sm text-gray-700 border border-gray-300 rounded-xl px-3 py-2" />
            <button type="button" id="mutationAttachBtn" class="px-4 py-2 bg-slate-200 text-slate-800 rounded-xl text-sm font-semibold hover:bg-slate-300 transition">
              <i class="fas fa-plus mr-1"></i> Joindre
            </button>
          </div>
          <div id="mutationAttachmentsList" class="space-y-2"></div>
        </div>
        <p class="text-xs text-gray-500 mt-2">Ajoutez plusieurs justificatifs en cliquant sur le bouton "Joindre".</p>
      </div>

      <script>
        (function() {
          const attachmentInput = document.getElementById('mutationAttachmentInput');
          const attachBtn = document.getElementById('mutationAttachBtn');
          const attachmentsList = document.getElementById('mutationAttachmentsList');
          let files = [];

          attachBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const file = attachmentInput.files[0];
            if (!file) {
              alert('Veuillez sélectionner un fichier.');
              return;
            }

            const maxSize = 20 * 1024 * 1024;
            const allowedMimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'application/zip'];

            if (file.size > maxSize) {
              alert('Le fichier ne doit pas dépasser 20 Mo.');
              return;
            }
            if (!allowedMimes.includes(file.type)) {
              alert('Format non autorisé. Acceptés: PDF, DOC, DOCX, JPG, PNG, ZIP.');
              return;
            }

            files.push(file);
            renderAttachments();
            attachmentInput.value = '';
            attachmentInput.focus();
          });

          attachmentInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              attachBtn.click();
            }
          });

          function renderAttachments() {
            attachmentsList.innerHTML = '';
            files.forEach((file, index) => {
              const div = document.createElement('div');
              div.className = 'flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2';
              div.innerHTML = `
                <div class="flex items-center gap-2 min-w-0">
                  <i class="fas fa-file text-slate-600"></i>
                  <span class="text-sm text-slate-700 truncate">${file.name}</span>
                  <span class="text-xs text-slate-500 whitespace-nowrap">(${(file.size / 1024).toFixed(1)} KB)</span>
                </div>
                <button type="button" class="text-red-600 hover:text-red-800 text-sm font-semibold"
                  onclick="mutationRemoveFile(${index})">Supprimer</button>
              `;
              attachmentsList.appendChild(div);
            });
          }

          window.mutationRemoveFile = function(index) {
            files.splice(index, 1);
            renderAttachments();
          };

          const form = document.querySelector('form[action="{{ route('admin.personnel.mutation-requests.store') }}"]');
          if (form) {
            const originalAction = form.action;
            form.addEventListener('submit', function(e) {
              if (files.length === 0) return;

              e.preventDefault();

              const formData = new FormData(this);
              files.forEach(file => {
                formData.append('attachments[]', file);
              });

              fetch(originalAction, {
                method: 'POST',
                body: formData
              })
              .then(response => {
                if (response.redirected) {
                  window.location.href = response.url;
                } else {
                  return response.text();
                }
              })
              .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'envoi.');
              });
            });
          }
        })();
      </script>
      <div class="pt-4">
        <button type="submit" class="w-full px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
          Envoyer la demande
        </button>
      </div>
    </form>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3">Mes demandes de mutation</h4>
    <div class="space-y-3">
      @forelse($agentSpaceMutationRequests as $request)
      @php
        $status = (string) ($request->status ?? 'pending');
        $badgeClass = $mutationStatusBadge[$status] ?? 'bg-gray-100 text-gray-700';
        $label = $mutationStatusLabel[$status] ?? ucfirst($status);
        $targetName = data_get($request->metadata, 'mutation_request.target_sub_entity_name', $request->new_job_title ?: '-');
        $sourceName = data_get($request->metadata, 'mutation_request.source_sub_entity_name', $request->previous_job_title ?: '-');
        $workflow = is_array(data_get($request->metadata, 'approval_workflow')) ? data_get($request->metadata, 'approval_workflow') : [];
        $steps = collect(data_get($workflow, 'steps', []))->values();
        $currentStepIndex = (int) data_get($workflow, 'current_step_index', 0);
        $history = collect(data_get($workflow, 'history', []));
        $rejectedHistory = $history->first(function ($item) {
          return (string) data_get($item, 'status', '') === 'rejected';
        });
        $rejectedStepIndex = $rejectedHistory !== null ? (int) data_get($rejectedHistory, 'step_index', -1) : -1;
        $attachments = is_array(data_get($request->metadata, 'mutation_request.attachments')) ? data_get($request->metadata, 'mutation_request.attachments') : [];
        $totalSteps = $steps->count();
        if ($status === 'validated') {
          $completedSteps = $totalSteps;
        } elseif ($status === 'rejected') {
          $completedSteps = max(0, $rejectedStepIndex);
        } else {
          $completedSteps = min(max(0, $currentStepIndex), $totalSteps);
        }
      @endphp
      <div class="rounded-xl border border-gray-200 px-4 py-3">
        <div class="flex items-center justify-between gap-3">
          <div class="text-sm font-semibold text-gray-800">{{ $request->title }}</div>
          <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $label }}</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ $sourceName }} <i class="fas fa-arrow-right mx-1"></i> {{ $targetName }}</div>
        <div class="text-xs text-gray-400 mt-1">Soumise le {{ optional($request->created_at)->format('d/m/Y H:i') ?: '-' }}</div>
        @if(!empty($request->summary))
        <div class="text-xs text-gray-500 mt-2">{{ $request->summary }}</div>
        @endif

        @if(!empty($attachments))
        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
          <div class="text-xs font-semibold text-slate-700">Pièce justificative</div>
          <div class="mt-2 flex flex-wrap gap-2">
            @foreach($attachments as $attachmentIndex => $attachment)
            <a href="{{ route('admin.personnel.mutation-requests.attachment.download', [$request, $attachmentIndex]) }}"
              class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-100">
              <i class="fas fa-file-upload"></i> {{ $attachment['original_name'] ?? basename($attachment['path'] ?? '') }}
            </a>
            @endforeach
          </div>
        </div>
        @endif

        @if($steps->isNotEmpty())
        @php
          $kindLabels = [
            'target_entity_manager' => 'Responsable entité cible',
            'chef_service'          => 'Chef de service',
            'sous_directeur'        => 'Sous-Directeur',
            'directeur'             => 'Directeur',
            'drh_final'             => 'DRH / Ressources Humaines',
          ];
          $progressPct = $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0;
          $circuitId   = 'circuit_' . $request->id;
          $circuitOpen = ($status === 'pending');
          $nextStep    = ($status === 'pending' && $steps->has($currentStepIndex)) ? $steps->get($currentStepIndex) : null;
          $nextApproverName = $nextStep ? ($allUsers->firstWhere('id', (string) data_get($nextStep, 'user_id'))?->name ?? 'Valideur') : null;
          $nextKindLabel    = $nextStep ? ($kindLabels[data_get($nextStep, 'kind', '')] ?? data_get($nextStep, 'profile', '')) : null;
        @endphp

        {{-- Circuit de validation --}}
        <div class="mt-3 rounded-xl overflow-hidden border
          {{ $status === 'validated' ? 'border-emerald-200' : ($status === 'rejected' ? 'border-red-200' : 'border-blue-200') }}">

          {{-- Header cliquable --}}
          <button type="button"
            onclick="(function(el){ el.classList.toggle('hidden') })(document.getElementById('{{ $circuitId }}'))"
            class="w-full flex items-center justify-between px-3 py-2.5 text-left gap-2
              {{ $status === 'validated' ? 'bg-emerald-50' : ($status === 'rejected' ? 'bg-red-50' : 'bg-blue-50') }}">

            <div class="flex items-center gap-2">
              @if($status === 'validated')
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white shadow-sm">
                  <i class="fas fa-check-double text-xs"></i>
                </span>
                <span class="text-xs font-bold text-emerald-700">Circuit terminé — Mutation approuvée</span>
              @elseif($status === 'rejected')
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white shadow-sm">
                  <i class="fas fa-times text-xs"></i>
                </span>
                <span class="text-xs font-bold text-red-700">Demande rejetée</span>
              @else
                <span class="relative flex items-center justify-center w-6 h-6">
                  <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                  <span class="relative flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white shadow-sm">
                    <i class="fas fa-hourglass-half text-xs"></i>
                  </span>
                </span>
                <span class="text-xs font-bold text-blue-700">Circuit de validation en cours</span>
              @endif
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs font-semibold
                {{ $status === 'validated' ? 'text-emerald-600' : ($status === 'rejected' ? 'text-red-600' : 'text-blue-600') }}">
                {{ $completedSteps }}/{{ $totalSteps }}
              </span>
              <div class="w-14 h-1.5 rounded-full bg-white/60 overflow-hidden border
                {{ $status === 'validated' ? 'border-emerald-200' : ($status === 'rejected' ? 'border-red-200' : 'border-blue-200') }}">
                <div class="h-full rounded-full
                  {{ $status === 'rejected' ? 'bg-red-500' : ($status === 'validated' ? 'bg-emerald-500' : 'bg-blue-500') }}"
                  style="width: {{ $progressPct }}%"></div>
              </div>
              <i class="fas fa-chevron-down text-xs opacity-40"></i>
            </div>
          </button>

          {{-- Corps du circuit (ouvert si en attente) --}}
          <div id="{{ $circuitId }}" class="{{ $circuitOpen ? '' : 'hidden' }} bg-white px-3 py-4">

            {{-- Barre de progression pleine --}}
            <div class="mb-4 h-2 w-full rounded-full bg-gray-100 overflow-hidden border border-gray-200">
              <div class="h-full rounded-full transition-all duration-700
                {{ $status === 'rejected' ? 'bg-red-400' : ($status === 'validated' ? 'bg-emerald-500' : 'bg-blue-500') }}"
                style="width: {{ $progressPct }}%"></div>
            </div>

            {{-- Timeline des étapes --}}
            <div class="relative">
              {{-- Ligne verticale de connexion --}}
              @if($steps->count() > 1)
              <div class="absolute left-[19px] top-5 bottom-5 w-0.5 bg-gray-200"></div>
              @endif

              <div class="space-y-4">
                @foreach($steps as $idx => $step)
                @php
                  $sApproverName  = $allUsers->firstWhere('id', (string) data_get($step, 'user_id'))?->name ?? 'Valideur';
                  $sKind          = (string) data_get($step, 'kind', '');
                  $sProfile       = (string) data_get($step, 'profile', $sKind);
                  $sKindLabel     = $kindLabels[$sKind] ?? $sProfile;
                  $sWords         = preg_split('/\s+/', trim($sApproverName));
                  $sInitials      = strtoupper(substr($sWords[0] ?? '', 0, 1)) . strtoupper(substr($sWords[1] ?? '', 0, 1));

                  $sHistoryEntry  = $history->first(fn($h) => (int) data_get($h, 'step_index') === $idx);
                  $sActedAt       = $sHistoryEntry ? data_get($sHistoryEntry, 'acted_at') : null;
                  $sComment       = $sHistoryEntry ? data_get($sHistoryEntry, 'comment') : null;

                  if ($status === 'validated') {
                    $sState = 'done';
                  } elseif ($status === 'rejected') {
                    if ($rejectedStepIndex >= 0 && $idx === $rejectedStepIndex) $sState = 'rejected';
                    elseif ($rejectedStepIndex >= 0 && $idx < $rejectedStepIndex) $sState = 'done';
                    else $sState = 'todo';
                  } else {
                    if ($idx < $currentStepIndex) $sState = 'done';
                    elseif ($idx === $currentStepIndex) $sState = 'current';
                    else $sState = 'todo';
                  }
                @endphp

                <div class="relative flex items-start gap-3">
                  {{-- Icône de l'étape --}}
                  <div class="relative z-10 flex-shrink-0">
                    @if($sState === 'done')
                      <div class="w-10 h-10 rounded-full bg-emerald-500 border-2 border-emerald-200 flex items-center justify-center shadow">
                        <i class="fas fa-check text-white text-xs"></i>
                      </div>
                    @elseif($sState === 'rejected')
                      <div class="w-10 h-10 rounded-full bg-red-500 border-2 border-red-200 flex items-center justify-center shadow">
                        <i class="fas fa-times text-white text-xs"></i>
                      </div>
                    @elseif($sState === 'current')
                      <div class="relative w-10 h-10">
                        <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                        <div class="relative w-10 h-10 rounded-full bg-blue-500 border-2 border-blue-200 flex items-center justify-center shadow">
                          <i class="fas fa-hourglass-half text-white text-xs"></i>
                        </div>
                      </div>
                    @else
                      <div class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-300 flex items-center justify-center">
                        <span class="text-xs font-bold text-gray-400">{{ $idx + 1 }}</span>
                      </div>
                    @endif
                  </div>

                  {{-- Contenu de l'étape --}}
                  <div class="flex-1 min-w-0 pb-1">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                      <div class="min-w-0">
                        {{-- Rôle + badge état --}}
                        <div class="flex items-center gap-1.5 flex-wrap">
                          <span class="text-xs font-bold
                            {{ $sState === 'done' ? 'text-emerald-700' : ($sState === 'rejected' ? 'text-red-700' : ($sState === 'current' ? 'text-blue-700' : 'text-gray-400')) }}">
                            {{ $sKindLabel }}
                          </span>
                          @if($sState === 'done')
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                              <i class="fas fa-check text-xs"></i> Validé
                            </span>
                          @elseif($sState === 'rejected')
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                              <i class="fas fa-times text-xs"></i> Rejeté
                            </span>
                          @elseif($sState === 'current')
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 animate-pulse">
                              <i class="fas fa-clock text-xs"></i> En attente
                            </span>
                          @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs text-gray-400 bg-gray-100">
                              À venir
                            </span>
                          @endif
                        </div>

                        {{-- Nom du responsable avec avatar initiales --}}
                        <div class="flex items-center gap-1.5 mt-1">
                          <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                            {{ $sState === 'done' ? 'bg-emerald-100 text-emerald-700' : ($sState === 'rejected' ? 'bg-red-100 text-red-700' : ($sState === 'current' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400')) }}">
                            {{ $sInitials ?: '?' }}
                          </div>
                          <span class="text-xs font-medium {{ $sState === 'todo' ? 'text-gray-400' : 'text-gray-700' }} truncate">
                            {{ $sApproverName }}
                          </span>
                        </div>
                      </div>

                      {{-- Date de l'action --}}
                      @if($sActedAt)
                      <div class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                        <i class="far fa-clock mr-0.5"></i>
                        {{ \Carbon\Carbon::parse($sActedAt)->format('d/m/Y à H:i') }}
                      </div>
                      @endif
                    </div>

                    {{-- Commentaire / motif de rejet --}}
                    @if($sComment)
                    <div class="mt-2 rounded-lg px-3 py-2 text-xs flex items-start gap-2
                      {{ $sState === 'rejected' ? 'bg-red-50 border border-red-100 text-red-700' : 'bg-gray-50 border border-gray-100 text-gray-600' }}">
                      <i class="fas fa-comment-alt mt-0.5 opacity-60 flex-shrink-0"></i>
                      <span>{{ $sComment }}</span>
                    </div>
                    @endif
                  </div>
                </div>
                @endforeach
              </div>
            </div>

            {{-- Callout "Prochain valideur" --}}
            @if($status === 'pending' && $nextStep)
            <div class="mt-4 rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-user-check text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-blue-900">Prochain valideur</div>
                <div class="text-xs text-blue-700 truncate">
                  {{ $nextApproverName }}
                  <span class="mx-1 opacity-40">·</span>
                  {{ $nextKindLabel }}
                </div>
              </div>
            </div>
            @endif

            {{-- Callout "Validée" --}}
            @if($status === 'validated')
            <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-emerald-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-check-double text-white text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-bold text-emerald-800">Mutation approuvée par tous les responsables</div>
                <div class="text-xs text-emerald-600">Décision finale le {{ optional($request->updated_at)->format('d/m/Y') }}</div>
              </div>
            </div>
            @endif

            {{-- Callout "Rejetée" avec motif global --}}
            @if($status === 'rejected' && data_get($request->metadata, 'rejection_reason'))
            <div class="mt-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-red-500 flex items-center justify-center shadow-sm mt-0.5">
                <i class="fas fa-ban text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-red-800">Motif de rejet</div>
                <div class="text-xs text-red-700">{{ data_get($request->metadata, 'rejection_reason') }}</div>
              </div>
            </div>
            @endif

          </div>{{-- fin corps circuit --}}
        </div>{{-- fin circuit --}}
        @endif

      </div>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">Aucune demande de mutation pour le moment.</div>
      @endforelse
    </div>
  </section>
</div>
@endif
@else
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3">CV Agent</h4>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.personnel.employees.documents.store', $agentSpaceEmployee) }}" class="space-y-3">
      @csrf
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="documents">
      <input type="hidden" name="category" value="cv">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.agent_space.label_label') }}</label>
        <input type="text" name="label" value="{{ old('label', 'CV Agent') }}" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.agent_space.document_label') }}</label>
        <input type="file" name="document" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm" required>
      </div>
      <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold">Uploader mon CV</button>
    </form>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3">Mes Documents</h4>

    <div class="mb-4">
      <h5 class="text-sm font-semibold text-gray-700 mb-2">Cartes virtuelles signées</h5>
      <div class="space-y-2">
        @forelse($agentSpaceEmployee->documents->where('category', 'virtual_card_signed')->sortByDesc('created_at')->take(8) as $doc)
        <a href="{{ route('admin.personnel.documents.download', $doc) }}" class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
          <span>{{ $doc->label }}</span>
          <i class="fas fa-download text-gray-400"></i>
        </a>
        @empty
        <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">Aucune carte signée disponible.</div>
        @endforelse
      </div>
    </div>

    <div class="pt-3 border-t border-gray-100">
      <h5 class="text-sm font-semibold text-gray-700 mb-2">CV Agent</h5>
      <div class="space-y-2">
      @forelse($agentSpaceEmployee->documents->where('category', 'cv')->sortByDesc('created_at')->take(8) as $doc)
      <a href="{{ route('admin.personnel.documents.download', $doc) }}" class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
        <span>{{ $doc->label }}</span>
        <i class="fas fa-download text-gray-400"></i>
      </a>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">Aucun CV disponible.</div>
      @endforelse
      </div>
    </div>
  </section>
</div>
@endif
@endif

@elseif($personnelTab === 'leave')
@php
  $leaveSubtab = request('leave_subtab', 'validation');
  $leaveSubtabsAll = [
    'validation' => ['fas fa-user-check', 'Validation'],
    'parameters' => ['fas fa-sliders', 'Paramètres'],
    'recent' => ['fas fa-clock-rotate-left', 'Historique'],
  ];
  $_leaveParentPerm = 'personnel.leave';
  $_leaveSubPermPrefix = 'personnel.leave.';
  $_leavePermKeys = array_keys($permSetAdmin['permissions'] ?? []);
  $_hasSpecificLeaveChildren = !empty(array_filter($_leavePermKeys, fn($k) => str_starts_with($k, $_leaveSubPermPrefix)));
  $leaveSubtabs = array_filter($leaveSubtabsAll, function ($v, $k) use ($permSetAdmin, $_leaveParentPerm, $_hasSpecificLeaveChildren) {
    if (($permSetAdmin['isElevated'] ?? false) === true) {
      return true;
    }
    if ($_hasSpecificLeaveChildren) {
      return isset($permSetAdmin['permissions']['personnel.leave.' . $k]);
    }
    return isset($permSetAdmin['permissions'][$_leaveParentPerm]);
  }, ARRAY_FILTER_USE_BOTH);
  if (empty($leaveSubtabs)) {
    $leaveSubtabs = [];
  }
  if (!array_key_exists($leaveSubtab, $leaveSubtabs)) {
    $leaveSubtab = array_key_first($leaveSubtabs) ?? 'validation';
  }
  $jobReferencesByType = collect($personnelJobReferences ?? [])->groupBy('reference_type');
@endphp

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap items-center gap-2">
    @if(empty($leaveSubtabs))
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      Aucun sous-onglet Congés & permissions n'est autorisé pour ce rôle.
    </div>
    @endif
    @foreach($leaveSubtabs as $key => [$icon, $label])
    <a href="{{ route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'leave', 'leave_subtab' => $key]) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition {{ $leaveSubtab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
      <i class="fa {{ $icon }} text-sm"></i>
      <span>{{ $label }}</span>
    </a>
    @endforeach
    @if($leaveGlobalVisibility)
    <span class="ml-auto inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700 text-xs font-semibold px-3 py-1">Visibilité globale AGENT RH / SUPER ADMIN</span>
    @endif
  </div>
</div>

@if(empty($leaveSubtabs))
@elseif($leaveSubtab === 'parameters')
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800">Catalogue des congés & permissions</h3>
        <p class="text-sm text-gray-500">Configuration des types et ZIP des pièces justificatives.</p>
      </div>
      <span class="text-xs rounded-full bg-sky-50 text-sky-700 border border-sky-100 px-3 py-1">{{ $personnelLeaveTypes->count() }} type(s)</span>
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.personnel.leave-types.store') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="personnel_tab" value="leave">
      <input type="hidden" name="leave_subtab" value="parameters">

      @if($adminScope)
      <input type="hidden" name="administration_type" value="{{ $adminScope['type'] }}">
      <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
      <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800 font-medium">{{ __('personnel.ui.leave.admin_locked') }}</div>
      @else
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.leave.admin_type_label') }}</label>
          <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="emitter" {{ old('administration_type', 'emitter') === 'emitter' ? 'selected' : '' }}>{{ __('personnel.ui.leave.admin_emitter') }}</option>
            <option value="recipient" {{ old('administration_type') === 'recipient' ? 'selected' : '' }}>{{ __('personnel.ui.leave.admin_recipient') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.leave.admin_type_label') }}</label>
          <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="">{{ __('personnel.ui.leave.admin_select') }}</option>
            @foreach($emitters as $e)
            <option value="{{ $e->id }}" {{ old('administration_id') === $e->id ? 'selected' : '' }}>{{ $e->name }} {{ __('personnel.ui.leave.admin_emitter_bracket') }}</option>
            @endforeach
            @foreach($recipients as $r)
            <option value="{{ $r->id }}" {{ old('administration_id') === $r->id ? 'selected' : '' }}>{{ $r->name }} {{ __('personnel.ui.leave.admin_recipient_bracket') }}</option>
            @endforeach
          </select>
        </div>
      </div>
      @endif

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.leave.form_name') }}</label>
          <input type="text" name="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.leave.form_code') }}</label>
          <input type="text" name="code" value="{{ old('code') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.leave.form_unit') }}</label>
          <select name="unit" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="day" {{ old('unit', 'day') === 'day' ? 'selected' : '' }}>{{ __('personnel.ui.leave.form_unit_day') }}</option>
            <option value="hour" {{ old('unit') === 'hour' ? 'selected' : '' }}>{{ __('personnel.ui.leave.form_unit_hour') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.leave.form_default_days') }}</label>
          <input type="number" step="0.5" min="0" name="default_days" value="{{ old('default_days') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">ZIP justificatifs</label>
        <input type="file" name="justification_zip" accept=".zip" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>

      <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="requires_attachment" value="1" {{ old('requires_attachment') ? 'checked' : '' }}> {{ __('personnel.ui.leave.form_requires_attachment') }}</label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_paid" value="1" {{ old('is_paid') ? 'checked' : '' }}> {{ __('personnel.ui.leave.form_is_paid') }}</label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}> {{ __('personnel.ui.leave.form_is_active') }}</label>
      </div>

      <button type="submit" class="px-6 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.leave.btn_save_type') }}</button>
    </form>

    <div class="mt-5 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-100">
            <th class="py-2 pr-4">Type</th>
            <th class="py-2 pr-4">Code</th>
            <th class="py-2">ZIP</th>
            <th class="py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($personnelLeaveTypes as $leaveType)
          <tr class="border-b border-gray-100">
            <td class="py-2 pr-4 text-gray-700 font-semibold">{{ $leaveType->name }}</td>
            <td class="py-2 pr-4 text-gray-500">{{ $leaveType->code }}</td>
            <td class="py-2">
              @if($leaveType->justification_zip_path)
              <a href="{{ route('admin.personnel.leave-types.justification-zip.download', $leaveType) }}" class="text-[#2453d6] text-xs font-semibold">Télécharger ZIP</a>
              @else
              <span class="text-xs text-gray-400"></span>
              @endif
            </td>
            <td class="py-2 text-right whitespace-nowrap">
              <button type="button"
                      onclick="document.getElementById('leave-type-edit-{{ $leaveType->id }}').classList.toggle('hidden')"
                      class="inline-flex items-center px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">
                Modifier
              </button>
              <form method="POST" action="{{ route('admin.personnel.leave-types.destroy', $leaveType) }}" class="inline-block ml-2" onsubmit="return confirm('Supprimer ce type de congé ?');">
                @csrf
                @method('DELETE')
                <input type="hidden" name="personnel_tab" value="leave">
                <input type="hidden" name="leave_subtab" value="parameters">
                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-red-200 text-red-700 text-xs font-semibold hover:bg-red-50 transition">
                  Supprimer
                </button>
              </form>
            </td>
          </tr>
          <tr id="leave-type-edit-{{ $leaveType->id }}" class="hidden border-b border-gray-100 bg-gray-50">
            <td colspan="4" class="py-3 pr-2">
              <form method="POST" enctype="multipart/form-data" action="{{ route('admin.personnel.leave-types.update', $leaveType) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="personnel_tab" value="leave">
                <input type="hidden" name="leave_subtab" value="parameters">

                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Nom</label>
                  <input type="text" name="name" value="{{ $leaveType->name }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Code</label>
                  <input type="text" name="code" value="{{ $leaveType->code }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Unité</label>
                  <select name="unit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="day" {{ $leaveType->unit === 'day' ? 'selected' : '' }}>Jour</option>
                    <option value="hour" {{ $leaveType->unit === 'hour' ? 'selected' : '' }}>Heure</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Jours par défaut</label>
                  <input type="number" step="0.5" min="0" name="default_days" value="{{ $leaveType->default_days }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Report max</label>
                  <input type="number" step="0.5" min="0" name="carry_over_days" value="{{ $leaveType->carry_over_days }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">ZIP justificatifs</label>
                  <input type="file" name="justification_zip" accept=".zip" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                </div>
                <div class="md:col-span-3">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
                  <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ $leaveType->description }}</textarea>
                </div>
                <div class="md:col-span-3 flex flex-wrap items-center gap-4">
                  <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="requires_attachment" value="1" {{ $leaveType->requires_attachment ? 'checked' : '' }}> Pièce obligatoire</label>
                  <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_paid" value="1" {{ $leaveType->is_paid ? 'checked' : '' }}> Congé payé</label>
                  <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" {{ $leaveType->is_active ? 'checked' : '' }}> Actif</label>
                  <button type="submit" class="ml-auto inline-flex items-center px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-blue-700 transition">Enregistrer</button>
                </div>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="py-3 text-center text-gray-400">Aucun type configuré.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="mb-4">
      <h3 class="text-lg font-bold text-gray-800">Référentiel Grades / Emplois / Fonctions</h3>
      <p class="text-sm text-gray-500">Formulaire de création des grades, emplois et fonctions.</p>
    </div>

    <form method="POST" action="{{ route('admin.personnel.job-references.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
      @csrf
      <input type="hidden" name="personnel_tab" value="leave">
      <input type="hidden" name="leave_subtab" value="parameters">
      @if($adminScope)
      <input type="hidden" name="administration_type" value="{{ $adminScope['type'] }}">
      <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
      @else
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.leave.admin_type_label') }}</label>
        <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
          <option value="emitter">{{ __('personnel.ui.leave.admin_emitter') }}</option>
          <option value="recipient">{{ __('personnel.ui.leave.admin_recipient') }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Administration</label>
        <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
          @foreach($emitters as $e)
          <option value="{{ $e->id }}">{{ $e->name }} {{ __('personnel.ui.leave.admin_emitter_bracket') }}</option>
          @endforeach
          @foreach($recipients as $r)
          <option value="{{ $r->id }}">{{ $r->name }} {{ __('personnel.ui.leave.admin_recipient_bracket') }}</option>
          @endforeach
        </select>
      </div>
      @endif
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Type de référence</label>
        <select name="reference_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
          <option value="grade">Grade</option>
          <option value="employment">Emploi</option>
          <option value="function">Fonction</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Libellé</label>
        <input type="text" name="label" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
      </div>
      <div class="md:col-span-2">
        <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-semibold">Enregistrer la référence</button>
      </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
      @foreach(['grade' => 'Grades', 'employment' => 'Emplois', 'function' => 'Fonctions'] as $refType => $refLabel)
      <div class="rounded-xl border border-gray-200 p-3">
        <h4 class="text-sm font-bold text-gray-800 mb-2">{{ $refLabel }}</h4>
        <ul class="space-y-1 text-sm text-gray-600">
          @forelse(($jobReferencesByType[$refType] ?? collect()) as $ref)
          <li>{{ $ref->label }}</li>
          @empty
          <li class="text-gray-400">Aucune valeur</li>
          @endforelse
        </ul>
      </div>
      @endforeach
    </div>
  </section>
</div>
@elseif($leaveSubtab === 'recent')
@php
  $recentValidatedOrRejected = collect($personnelLeaveRequests)->filter(fn($r) => in_array($r->status, ['approved', 'rejected'], true))->values();
@endphp
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Historique des demandes</h3>
      <p class="text-sm text-gray-500">Demandes validées ou rejetées par la hiérarchie.</p>
    </div>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500 border-b border-gray-100">
          <th class="py-3 pr-4">Date</th>
          <th class="py-3 pr-4">Matricule</th>
          <th class="py-3 pr-4">Nom & prénoms</th>
          <th class="py-3 pr-4">Type</th>
          <th class="py-3 pr-4">Statut</th>
          <th class="py-3">Supérieur valideur</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentValidatedOrRejected as $leaveRequest)
        <tr class="border-b border-gray-100">
          <td class="py-3 pr-4 text-gray-600">{{ optional($leaveRequest->updated_at)->format('d/m/Y H:i') }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $leaveRequest->employee?->employee_number ?: '-' }}</td>
          <td class="py-3 pr-4 text-gray-800 font-semibold">{{ $leaveRequest->employee?->full_name ?? __('personnel.ui.leave.table_deleted_employee') }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $leaveRequest->leaveType?->name ?? __('personnel.ui.leave.table_deleted_type') }}</td>
          <td class="py-3 pr-4">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $leaveRequest->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ __('personnel.ui.statuses.' . $leaveRequest->status) }}</span>
          </td>
          <td class="py-3 text-gray-600">{{ $leaveRequest->approvedBy?->name ?? 'Non renseigné' }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="py-8 text-center text-sm text-gray-400">{{ __('personnel.ui.leave.table_empty') }}</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@else
@php
  $validationRows = collect($personnelLeaveRequests)->where('status', 'pending')->values();
  $validationApproveBtnClass = 'px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition';
  $validationRejectBtnClass = 'px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition';
@endphp
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Validation hiérarchique des demandes</h3>
      <p class="text-sm text-gray-500">Le supérieur hiérarchique valide ou rejette les demandes des agents.</p>
    </div>
    <span class="text-xs rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-3 py-1">{{ $validationRows->count() }} en attente</span>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500 border-b border-gray-100">
          <th class="py-3 pr-4">Matricule</th>
          <th class="py-3 pr-4">Nom & prénoms</th>
          <th class="py-3 pr-4">Grade</th>
          <th class="py-3 pr-4">Emploi / Fonction</th>
          <th class="py-3 pr-4">Pièce justificative</th>
          <th class="py-3 pr-4">Supérieur valideur</th>
          <th class="py-3">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($validationRows as $leaveRequest)
        @php
          $wf = data_get($leaveRequest->metadata, 'approval_workflow');
          $wfSteps = collect(data_get($wf, 'steps', []))->filter(fn($s) => !empty($s['user_id']))->values();
          $wfCurrentApproverId = data_get($wf, 'current_approver_user_id');
          $wfCurrentApprover = $wfCurrentApproverId ? ($personnelLeaveApprovers[$wfCurrentApproverId] ?? null) : null;
          $employeeMeta = is_array($leaveRequest->employee?->metadata) ? $leaveRequest->employee->metadata : [];
          $agentGrade = $employeeMeta['grade'] ?? '-';
          $agentEmployment = $employeeMeta['employment'] ?? ($employeeMeta['emploi'] ?? '-');
          $agentFunction = $leaveRequest->employee?->job_title ?: ($employeeMeta['function'] ?? '-');
          $currentUser = auth()->user();
          $isSuperAdminBypass = $currentUser && $currentUser->role === 'admin' && !$currentUser->profile_id;
          $canApproveReject = $wfSteps->isEmpty()
            || $isSuperAdminBypass
            || ((string) ($currentUser?->id ?? '') !== '' && (string) ($currentUser?->id ?? '') === (string) ($wfCurrentApproverId ?? ''));
        @endphp
        <tr class="border-b border-gray-100 align-top">
          <td class="py-3 pr-4 text-gray-600">{{ $leaveRequest->employee?->employee_number ?: __('personnel.ui.leave.table_no_employee_number') }}</td>
          <td class="py-3 pr-4 text-gray-800 font-semibold">{{ $leaveRequest->employee?->full_name ?? __('personnel.ui.leave.table_deleted_employee') }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $agentGrade }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $agentEmployment }} / {{ $agentFunction }}</td>
          <td class="py-3 pr-4">
            @if($leaveRequest->attachment_path)
            <a href="{{ route('admin.personnel.leave-requests.attachment.download', $leaveRequest) }}" class="text-[#2453d6] text-xs font-semibold">ZIP / justificatif</a>
            @else
            <span class="text-xs text-gray-400">Aucun fichier</span>
            @endif
          </td>
          <td class="py-3 pr-4 text-gray-600">{{ $wfCurrentApprover?->name ?? 'Non défini' }}</td>
          <td class="py-3">
            <div class="flex flex-wrap gap-2">
              @foreach(['approved' => __('personnel.ui.leave.btn_approve'), 'rejected' => __('personnel.ui.leave.btn_reject')] as $status => $label)
              @if(!$canApproveReject)
                @continue
              @endif
              <form method="POST" action="{{ route('admin.personnel.leave-requests.status', $leaveRequest) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="personnel_tab" value="leave">
                <input type="hidden" name="leave_subtab" value="validation">
                <input type="hidden" name="status" value="{{ $status }}">
                <button type="submit" class="{{ $status === 'approved' ? $validationApproveBtnClass : $validationRejectBtnClass }}">{{ $label }}</button>
              </form>
              @endforeach
            </div>
            @if(!$canApproveReject)
            <div class="mt-2 text-[11px] text-gray-500">Action réservée au valideur courant.</div>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-8 text-center text-sm text-gray-400">Aucune demande en attente de validation.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endif
@elseif($personnelTab === 'training')
@php
  $trainingSubtab = request('training_subtab', 'management');
  $trainingSubtabs = [
    'management' => ['fas fa-sliders', 'Gestion'],
    'validation' => ['fas fa-user-check', 'Validation'],
    'history' => ['fas fa-clock-rotate-left', 'Historique'],
  ];
  if (!array_key_exists($trainingSubtab, $trainingSubtabs)) {
    $trainingSubtab = 'management';
  }
  $trainingCurrentUserId = (string) (auth()->id() ?? '');
  $trainingValidationRows = collect($personnelTrainingEnrollments ?? collect())
    ->filter(function ($enrollment) use ($trainingCurrentUserId) {
      return (string) ($enrollment->status ?? '') === 'pending'
        && (string) data_get($enrollment->metadata, 'approval_workflow.type', '') === 'training_hierarchical'
        && (string) data_get($enrollment->metadata, 'approval_workflow.current_approver_user_id', '') === $trainingCurrentUserId;
    })
    ->values();
  $trainingHistoryRows = collect($personnelTrainingEnrollments ?? collect())
    ->filter(function ($enrollment) {
      return (string) data_get($enrollment->metadata, 'approval_workflow.type', '') === 'training_hierarchical'
        && (string) ($enrollment->status ?? '') !== 'pending';
    })
    ->sortByDesc('updated_at')
    ->values();
  $trainingApproveBtnClass = 'px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition';
  $trainingRejectBtnClass = 'px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition';
@endphp

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap items-center gap-2">
    @foreach($trainingSubtabs as $key => [$icon, $label])
    <a href="{{ route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'training', 'training_subtab' => $key]) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition {{ $trainingSubtab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
      <i class="fa {{ $icon }} text-sm"></i>
      <span>{{ $label }}</span>
    </a>
    @endforeach
  </div>
</div>

@if($trainingSubtab === 'validation')
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Validation des demandes de formation</h3>
      <p class="text-sm text-gray-500">Demandes en attente d'action du valideur courant.</p>
    </div>
    <span class="text-xs rounded-full bg-violet-50 text-violet-700 border border-violet-100 px-3 py-1">{{ $trainingValidationRows->count() }} en attente</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500 border-b border-gray-100">
          <th class="py-3 pr-4">Matricule</th>
          <th class="py-3 pr-4">Nom & prénoms</th>
          <th class="py-3 pr-4">Formation</th>
          <th class="py-3 pr-4">Valideur courant</th>
          <th class="py-3">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($trainingValidationRows as $enrollment)
        @php
          $trainingCurrentApproverId = (string) data_get($enrollment->metadata, 'approval_workflow.current_approver_user_id', '');
          $trainingCurrentApproverName = $allUsers->firstWhere('id', $trainingCurrentApproverId)?->name ?? 'Non défini';
        @endphp
        <tr class="border-b border-gray-100 align-top">
          <td class="py-3 pr-4 text-gray-600">{{ $enrollment->employee?->employee_number ?: '-' }}</td>
          <td class="py-3 pr-4 text-gray-800 font-semibold">{{ $enrollment->employee?->full_name ?? 'Agent supprimé' }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $enrollment->training?->title ?? 'Formation supprimée' }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $trainingCurrentApproverName }}</td>
          <td class="py-3">
            <div class="flex flex-wrap gap-2">
              <form method="POST" action="{{ route('admin.personnel.training-enrollments.update-status', $enrollment->id) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="personnel_tab" value="training">
                <input type="hidden" name="training_subtab" value="validation">
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="{{ $trainingApproveBtnClass }}">Approuver</button>
              </form>
              <form method="POST" action="{{ route('admin.personnel.training-enrollments.update-status', $enrollment->id) }}" class="flex flex-wrap gap-2 items-center">
                @csrf
                @method('PATCH')
                <input type="hidden" name="personnel_tab" value="training">
                <input type="hidden" name="training_subtab" value="validation">
                <input type="hidden" name="status" value="rejected">
                <input type="text" name="comment" required placeholder="Motif du rejet" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs min-w-[180px]">
                <button type="submit" class="{{ $trainingRejectBtnClass }}">Rejeter</button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="py-8 text-center text-sm text-gray-400">Aucune demande de formation en attente de validation.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@elseif($trainingSubtab === 'history')
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Historique des demandes de formation</h3>
      <p class="text-sm text-gray-500">Demandes finalisées (validées/rejetées).</p>
    </div>
    <span class="text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 px-3 py-1">{{ $trainingHistoryRows->count() }} élément(s)</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500 border-b border-gray-100">
          <th class="py-3 pr-4">Date</th>
          <th class="py-3 pr-4">Matricule</th>
          <th class="py-3 pr-4">Nom & prénoms</th>
          <th class="py-3 pr-4">Formation</th>
          <th class="py-3">Statut</th>
        </tr>
      </thead>
      <tbody>
        @forelse($trainingHistoryRows as $enrollment)
        <tr class="border-b border-gray-100">
          <td class="py-3 pr-4 text-gray-600">{{ optional($enrollment->updated_at)->format('d/m/Y H:i') }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $enrollment->employee?->employee_number ?: '-' }}</td>
          <td class="py-3 pr-4 text-gray-800 font-semibold">{{ $enrollment->employee?->full_name ?? 'Agent supprimé' }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $enrollment->training?->title ?? 'Formation supprimée' }}</td>
          <td class="py-3"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $enrollment->status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">{{ ucfirst(str_replace('_', ' ', $enrollment->status)) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="5" class="py-8 text-center text-sm text-gray-400">Aucun historique disponible.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@else
<div class="grid grid-cols-1 xl:grid-cols-6 gap-5 mb-5">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800">{{ __('personnel.ui.training.catalog_title') }}</h3>
        <p class="text-sm text-gray-500">{{ __('personnel.ui.training.catalog_description') }}</p>
      </div>
      <span class="text-xs rounded-full bg-violet-50 text-violet-700 border border-violet-100 px-3 py-1">{{ number_format($personnelStats['trainings'] ?? 0) }} formation(s)</span>
    </div>

    <form method="POST" action="{{ route('admin.personnel.trainings.store') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="personnel_tab" value="training">

      @if($adminScope)
      <input type="hidden" name="administration_type" value="{{ $adminScope['type'] }}">
      <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
      <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800 font-medium">{{ __('personnel.ui.training.admin_locked') }}</div>
      @else
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.admin_type_label') }}</label>
          <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="emitter" {{ old('administration_type', 'emitter') === 'emitter' ? 'selected' : '' }}>{{ __('personnel.ui.training.admin_emitter') }}</option>
            <option value="recipient" {{ old('administration_type') === 'recipient' ? 'selected' : '' }}>{{ __('personnel.ui.training.admin_recipient') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.admin_type_label') }}</label>
          <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="">{{ __('personnel.ui.training.admin_select') }}</option>
            @foreach($emitters as $e)
            <option value="{{ $e->id }}" {{ old('administration_id') === $e->id ? 'selected' : '' }}>{{ $e->name }} {{ __('personnel.ui.training.admin_emitter_bracket') }}</option>
            @endforeach
            @foreach($recipients as $r)
            <option value="{{ $r->id }}" {{ old('administration_id') === $r->id ? 'selected' : '' }}>{{ $r->name }} {{ __('personnel.ui.training.admin_recipient_bracket') }}</option>
            @endforeach
          </select>
        </div>
      </div>
      @endif

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_title') }}</label>
          <input type="text" name="title" value="{{ old('title') }}" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_code') }}</label>
          <input type="text" name="code" value="{{ old('code') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_category') }}</label>
          <input type="text" name="category" value="{{ old('category') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_mode') }}</label>
          <select name="delivery_mode" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            @foreach(['internal' => __('personnel.ui.training.form_mode_internal'), 'external' => __('personnel.ui.training.form_mode_external'), 'elearning' => __('personnel.ui.training.form_mode_elearning'), 'hybrid' => __('personnel.ui.training.form_mode_hybrid')] as $value => $label)
            <option value="{{ $value }}" {{ old('delivery_mode', 'internal') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_provider') }}</label>
          <input type="text" name="provider_name" value="{{ old('provider_name') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_duration') }}</label>
          <input type="number" min="0" step="0.5" name="duration_hours" value="{{ old('duration_hours') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_budget') }}</label>
          <input type="number" min="0" step="0.01" name="budget_amount" value="{{ old('budget_amount') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_validity') }}</label>
          <input type="number" min="0" name="validity_months" value="{{ old('validity_months') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_skills') }}</label>
        <input type="text" name="skills" value="{{ old('skills') }}" placeholder="{{ __('personnel.ui.training.form_skills_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_objectives') }}</label>
        <textarea name="objectives" rows="2" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('objectives') }}</textarea>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.form_description') }}</label>
        <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('description') }}</textarea>
      </div>
      <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_mandatory" value="1" {{ old('is_mandatory') ? 'checked' : '' }}> {{ __('personnel.ui.training.form_is_mandatory') }}</label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}> {{ __('personnel.ui.training.form_is_active') }}</label>
      </div>
      <button type="submit" class="px-6 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.training.btn_save_training') }}</button>
    </form>
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800">{{ __('personnel.ui.training.assign_title') }}</h3>
        <p class="text-sm text-gray-500">{{ __('personnel.ui.training.assign_description') }}</p>
      </div>
      <span class="text-xs rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1">{{ number_format($personnelStats['enrollments'] ?? 0) }} affectation(s)</span>
    </div>

    @if($personnelEmployeeDirectory->isEmpty() || $personnelTrainings->isEmpty())
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800">{{ __('personnel.ui.training.need_employee_training') }}</div>
    @else
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.personnel.training-enrollments.store') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="personnel_tab" value="training">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_employee') }}</label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value="">{{ __('personnel.ui.training.select') }}</option>
          @foreach($personnelEmployeeDirectory as $employee)
          <option value="{{ $employee->id }}" {{ old('employee_id', $editingPersonnel->id ?? '') === $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_training') }}</label>
        <select name="training_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value="">{{ __('personnel.ui.training.select') }}</option>
          @foreach($personnelTrainings as $training)
          <option value="{{ $training->id }}" {{ old('training_id') === $training->id ? 'selected' : '' }}>{{ $training->title }}</option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_status') }}</label>
          <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            @foreach(['planned' => __('personnel.ui.training.assign_form_status_planned'), 'in_progress' => __('personnel.ui.training.assign_form_status_in_progress'), 'completed' => __('personnel.ui.training.assign_form_status_completed'), 'cancelled' => __('personnel.ui.training.assign_form_status_cancelled')] as $value => $label)
            <option value="{{ $value }}" {{ old('status', 'planned') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_certificate') }}</label>
          <input type="file" name="certificate" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_planned_start') }}</label>
          <input type="date" name="planned_start_date" value="{{ old('planned_start_date') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_planned_end') }}</label>
          <input type="date" name="planned_end_date" value="{{ old('planned_end_date') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_attendance') }}</label>
          <input type="number" min="0" max="100" step="0.01" name="attendance_rate" value="{{ old('attendance_rate') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_score') }}</label>
          <input type="number" min="0" max="100" step="0.01" name="score" value="{{ old('score') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_notes') }}</label>
        <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('notes') }}</textarea>
      </div>
      <button type="submit" class="px-6 py-2.5 bg-teal-600 text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.training.btn_assign') }}</button>
    </form>
    @endif
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800">{{ __('personnel.ui.training.skills_matrix_title') }}</h3>
        <p class="text-sm text-gray-500">{{ __('personnel.ui.training.skills_matrix_description') }}</p>
      </div>
      <span class="text-xs rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-3 py-1">{{ number_format($personnelStats['skills'] ?? 0) }} compétence(s)</span>
    </div>

    @if($personnelEmployeeDirectory->isEmpty())
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800">{{ __('personnel.ui.training.need_employee_skills') }}</div>
    @else
    <form method="POST" action="{{ route('admin.personnel.employees.skills.store') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="personnel_tab" value="training">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_employee') }}</label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value="">{{ __('personnel.ui.training.select') }}</option>
          @foreach($personnelEmployeeDirectory as $employee)
          <option value="{{ $employee->id }}" {{ old('employee_id', $editingPersonnel->id ?? '') === $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.skills_form_skill') }}</label>
        <input type="text" name="skill_name" value="{{ old('skill_name') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.skills_form_category') }}</label>
        <input type="text" name="category" value="{{ old('category') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.skills_form_current_level') }}</label>
          <input type="number" min="1" max="5" name="current_level" value="{{ old('current_level', 3) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.skills_form_target_level') }}</label>
          <input type="number" min="1" max="5" name="target_level" value="{{ old('target_level') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.skills_form_assessment_date') }}</label>
          <input type="date" name="assessment_date" value="{{ old('assessment_date') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.skills_form_source') }}</label>
          <input type="text" name="source" value="{{ old('source') }}" placeholder="{{ __('personnel.ui.training.skills_form_source_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.skills_form_notes') }}</label>
        <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('notes') }}</textarea>
      </div>
      <button type="submit" class="px-6 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.training.btn_add_skill') }}</button>
    </form>
    @endif
  </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.training.catalog_existing_title') }}</h3>
    <div class="space-y-3">
      @forelse($personnelTrainings as $training)
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-semibold text-gray-800">{{ $training->title }}</div>
            <div class="text-sm text-gray-500">{{ $training->category ?: __('personnel.ui.training.no_category') }} · {{ __('personnel.ui.statuses.' . $training->delivery_mode) }} · {{ $training->provider_name ?: __('personnel.ui.training.internal_provider') }}</div>
          </div>
          <span class="text-xs rounded-full bg-gray-100 text-gray-700 px-3 py-1">{{ $training->enrollments_count }} {{ __('personnel.ui.training.enrolled_count') }}</span>
        </div>
        <div class="text-xs text-gray-400 mt-2">{{ $training->skills ? implode(', ', $training->skills) : __('personnel.ui.training.no_skills_target') }}</div>
      </div>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.training.no_trainings_created') }}</div>
      @endforelse
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.training.tracking_title') }}</h3>
    <div class="space-y-3 mb-5">
      @forelse($personnelTrainingEnrollments as $enrollment)
      <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
        <div class="font-semibold text-gray-800">{{ $enrollment->employee?->full_name ?? __('personnel.ui.training.deleted_employee') }} · {{ $enrollment->training?->title ?? __('personnel.ui.training.deleted_training') }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ __('personnel.ui.training.status_prefix') }} {{ __('personnel.ui.statuses.' . $enrollment->status) }}{{ $enrollment->planned_start_date ? ' · ' . __('personnel.ui.training.start_prefix') . ' ' . $enrollment->planned_start_date->format('d/m/Y') : '' }}</div>
      </div>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.training.no_enrollments') }}</div>
      @endforelse
    </div>

    <div class="border-t border-gray-200 pt-5">
      <h4 class="font-semibold text-gray-800 mb-3">{{ __('personnel.ui.training.skills_recent_title') }}</h4>
      <div class="space-y-3">
        @forelse($personnelEmployeeSkills as $skill)
        <div class="rounded-xl border border-gray-200 p-4">
          <div class="font-semibold text-gray-800">{{ $skill->skill_name }}</div>
          <div class="text-sm text-gray-500 mt-1">{{ $skill->employee?->full_name ?? __('personnel.ui.training.deleted_employee') }} · {{ __('personnel.ui.training.level_prefix') }} {{ $skill->current_level }}{{ $skill->target_level ? ' ' . __('personnel.ui.training.target_prefix') . ' ' . $skill->target_level : '' }}</div>
        </div>
        @empty
        <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.training.no_skills_evaluated') }}</div>
        @endforelse
      </div>
    </div>
  </section>
</div>

{{-- ══ MODAL : Demandes de formations ══ --}}
@php
  $_mrUser = auth()->user();
  $_mrProfile = $_mrUser?->profile_id ? \App\Models\AdministrationProfile::find($_mrUser->profile_id) : null;
  $_mrProfileName = mb_strtoupper(trim(str_replace(['_','-'],' ',$_mrProfile?->name ?? '')), 'UTF-8');
  $_mrIsAgentRh = str_contains($_mrProfileName, 'AGENT RH');
  $_mrIsSuperAdmin = $_mrUser?->role === 'admin';
  $_mrIsFullAccess = $_mrIsAgentRh || $_mrIsSuperAdmin;
  $_mrCurrentActorId = (string) ($_mrUser?->id ?? '');

  // Toutes les demandes dans le périmètre (déjà chargées avec relations)
  $_mrAllEnrollments = $personnelTrainingEnrollments ?? collect();

  if ($_mrIsFullAccess) {
    $_mrEnrollments = $_mrAllEnrollments;
  } else {
    // Supérieur hiérarchique : seulement ses collaborateurs (user_id = auth id)
    $subordinateIds = \App\Models\PersonnelEmployee::where('user_id', $_mrUser?->id)
      ->pluck('id')
      ->map(fn($id) => (string)$id)
      ->toArray();
    $_mrEnrollments = $_mrAllEnrollments->filter(function ($e) use ($subordinateIds, $_mrUser) {
      $isDirectSubordinate = in_array((string)($e->employee_id ?? ''), $subordinateIds, true);
      $workflowApproverId = (string) data_get($e->metadata, 'approval_workflow.current_approver_user_id', '');
      $isCurrentApprover = $workflowApproverId !== '' && $workflowApproverId === (string) ($_mrUser?->id ?? '');
      return $isDirectSubordinate || $isCurrentApprover;
    });
  }
@endphp
<div id="modal-training-requests" class="hidden fixed inset-0 z-50 flex items-start justify-center p-4 pt-16 bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-4xl max-h-[80vh] flex flex-col overflow-hidden">
    {{-- Header modal --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-xl bg-violet-100 flex items-center justify-center">
          <i class="fas fa-graduation-cap text-violet-600 text-sm"></i>
        </div>
        <div>
          <h2 class="text-base font-bold text-gray-800">Demandes de formations</h2>
          <p class="text-xs text-gray-500">
            @if($_mrIsFullAccess)
              Toutes les demandes — suivi du processus de validation
            @else
              Demandes de vos collaborateurs directs
            @endif
          </p>
        </div>
      </div>
      <button type="button"
        onclick="document.getElementById('modal-training-requests').classList.add('hidden')"
        class="p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>

    {{-- Statut legend (AGENT RH / SUPER ADMIN) --}}
    @if($_mrIsFullAccess)
    <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap gap-2 text-xs">
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>En attente de validation</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>Planifiée</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>En cours</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100"><span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>Terminée</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-100"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Rejetée</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-100"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Annulée</span>
    </div>
    @endif

    {{-- Contenu --}}
    <div class="flex-1 overflow-y-auto px-6 py-4">
      @if($_mrEnrollments->isEmpty())
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
        <i class="fas fa-inbox text-gray-300 text-3xl mb-3 block"></i>
        @if($_mrIsFullAccess)
          Aucune demande de formation enregistrée.
        @else
          Aucune demande de formation de vos collaborateurs.
        @endif
      </div>
      @else
      <div class="space-y-3">
        @foreach($_mrEnrollments as $enrollment)
        @php
          $_enrStatus = $enrollment->status ?? 'planned';
          $_enrStatusColors = [
            'pending'     => 'bg-amber-50 text-amber-700 border-amber-100',
            'planned'     => 'bg-blue-50 text-blue-700 border-blue-100',
            'in_progress' => 'bg-amber-50 text-amber-700 border-amber-100',
            'completed'   => 'bg-emerald-50 text-emerald-700 border-emerald-100',
            'rejected'    => 'bg-red-50 text-red-700 border-red-100',
            'cancelled'   => 'bg-red-50 text-red-700 border-red-100',
          ];
          $_enrStatusColor = $_enrStatusColors[$_enrStatus] ?? 'bg-gray-50 text-gray-700 border-gray-100';
          $_enrStatusLabel = [
            'pending'     => 'En attente',
            'planned'     => 'Planifiée',
            'in_progress' => 'En cours',
            'completed'   => 'Terminée',
            'rejected'    => 'Rejetée',
            'cancelled'   => 'Annulée',
          ][$_enrStatus] ?? ucfirst($_enrStatus);
          $_enrWorkflow = is_array(data_get($enrollment->metadata, 'approval_workflow')) ? data_get($enrollment->metadata, 'approval_workflow') : [];
          $_enrWorkflowType = (string) ($_enrWorkflow['type'] ?? '');
          $_enrCurrentApproverId = (string) data_get($_enrWorkflow, 'current_approver_user_id', '');
          $_enrCurrentStepIndex = (int) data_get($_enrWorkflow, 'current_step_index', 0);
          $_enrSteps = collect(data_get($_enrWorkflow, 'steps', []))->values();
          $_enrCurrentStep = $_enrSteps->get($_enrCurrentStepIndex);
          $_enrCurrentApproverName = $allUsers->firstWhere('id', (string) $_enrCurrentApproverId)?->name ?? 'Non défini';
          $_enrHistory = collect(data_get($_enrWorkflow, 'history', []))
            ->sortByDesc(function ($_item) {
              return (string) ($_item['acted_at'] ?? '');
            })
            ->values();
          $_enrCanAct = $_enrWorkflowType === 'training_hierarchical'
            && $_enrStatus === 'pending'
            && $_enrCurrentApproverId !== ''
            && $_enrCurrentApproverId === $_mrCurrentActorId;
          $_enrFollowOnly = $_enrWorkflowType === 'training_hierarchical' && $_enrStatus === 'pending' && !$_enrCanAct;
        @endphp
        <div class="rounded-xl border border-gray-200 p-4 bg-white hover:shadow-sm transition">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
              <div class="font-semibold text-gray-800 truncate">{{ $enrollment->employee?->full_name ?? '—' }}</div>
              <div class="text-sm text-gray-600 mt-0.5">{{ $enrollment->training?->title ?? 'Formation supprimée' }}</div>
              @if($enrollment->training?->category)
              <div class="text-xs text-gray-400 mt-0.5">{{ $enrollment->training->category }}</div>
              @endif
            </div>
            <span class="flex-shrink-0 text-xs rounded-full border px-3 py-1 font-medium {{ $_enrStatusColor }}">{{ $_enrStatusLabel }}</span>
          </div>
          <div class="flex flex-wrap gap-4 mt-3 text-xs text-gray-500">
            @if($enrollment->planned_start_date)
            <span><i class="fas fa-calendar-day mr-1 text-gray-300"></i>Début : {{ $enrollment->planned_start_date->format('d/m/Y') }}</span>
            @endif
            @if($enrollment->planned_end_date)
            <span><i class="fas fa-calendar-check mr-1 text-gray-300"></i>Fin : {{ $enrollment->planned_end_date->format('d/m/Y') }}</span>
            @endif
            @if($enrollment->attendance_rate !== null)
            <span><i class="fas fa-user-check mr-1 text-gray-300"></i>Présence : {{ $enrollment->attendance_rate }}%</span>
            @endif
            @if($enrollment->score !== null)
            <span><i class="fas fa-star mr-1 text-gray-300"></i>Score : {{ $enrollment->score }}/100</span>
            @endif
          </div>
          @if($enrollment->notes)
          <div class="mt-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 italic">{{ $enrollment->notes }}</div>
          @endif

          @if($_enrWorkflowType === 'training_hierarchical')
          <div class="mt-2 text-xs text-gray-500">
            Valideur courant: {{ $_enrCurrentApproverName }}
            @if($_enrCurrentStep && !empty($_enrCurrentStep['profile']))
              ({{ $_enrCurrentStep['profile'] }})
            @endif
          </div>

          <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3">
            <div class="text-xs font-semibold text-gray-700 mb-2">Timeline de validation</div>
            <div class="space-y-2">
              @forelse($_enrHistory as $_h)
              @php
                $_hStatus = (string) ($_h['status'] ?? 'approved');
                $_hName = $allUsers->firstWhere('id', (string) ($_h['acted_by_user_id'] ?? ''))?->name ?? 'Utilisateur';
                $_hLabel = $_hStatus === 'rejected' ? 'Rejetée' : 'Approuvée';
                $_hDot = $_hStatus === 'rejected' ? 'bg-red-500' : 'bg-emerald-500';
                $_hText = $_hStatus === 'rejected' ? 'text-red-700' : 'text-emerald-700';
              @endphp
              <div class="flex items-start gap-2">
                <span class="mt-1 h-2 w-2 rounded-full {{ $_hDot }}"></span>
                <div class="mt-0.5 {{ $_hText }}">
                  @if($_hStatus === 'rejected')
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M12 2a10 10 0 100 20 10 10 0 000-20zm3.53 6.47a.75.75 0 010 1.06L13.06 12l2.47 2.47a.75.75 0 11-1.06 1.06L12 13.06l-2.47 2.47a.75.75 0 01-1.06-1.06L10.94 12 8.47 9.53a.75.75 0 111.06-1.06L12 10.94l2.47-2.47a.75.75 0 011.06 0z" clip-rule="evenodd" />
                  </svg>
                  @else
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm14.28-2.03a.75.75 0 10-1.06-1.06l-4.97 4.97-1.97-1.97a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.06 0l5.5-5.5z" clip-rule="evenodd" />
                  </svg>
                  @endif
                </div>
                <div class="text-xs">
                  <div class="font-semibold {{ $_hText }}">{{ $_hLabel }} par {{ $_hName }}</div>
                  <div class="text-gray-500">{{ data_get($_h, 'step_profile', 'Niveau') }} · {{ \Carbon\Carbon::parse($_h['acted_at'])->format('d/m/Y H:i') }}</div>
                  @if(!empty($_h['comment']))
                  <div class="mt-1 text-gray-600 italic">Motif: {{ $_h['comment'] }}</div>
                  @endif
                </div>
              </div>
              @empty
              <div class="text-xs text-gray-500">Aucune action enregistrée pour le moment.</div>
              @endforelse

              @if($_enrStatus === 'pending')
              <div class="flex items-start gap-2">
                <span class="mt-1 h-2 w-2 rounded-full bg-amber-500"></span>
                <div class="mt-0.5 text-amber-700">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5zM12.75 7.5a.75.75 0 00-1.5 0V12a.75.75 0 00.22.53l3 3a.75.75 0 101.06-1.06l-2.78-2.78V7.5z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="text-xs">
                  <div class="font-semibold text-amber-700">En attente de validation</div>
                  <div class="text-gray-500">Valideur courant: {{ $_enrCurrentApproverName }}</div>
                </div>
              </div>
              @endif
            </div>
          </div>

          @if($_enrStatus === 'rejected' && data_get($enrollment->metadata, 'rejection_reason'))
          <div class="mt-2 rounded-lg bg-red-50 border border-red-100 px-3 py-2 text-xs text-red-700">
            Motif du rejet: {{ data_get($enrollment->metadata, 'rejection_reason') }}
          </div>
          @endif
          @endif

          @if($_enrCanAct)
          <div class="mt-3 flex flex-col md:flex-row gap-2">
            <form method="POST" action="{{ route('admin.personnel.training-enrollments.update-status', $enrollment->id) }}" class="inline-flex">
              @csrf
              @method('PATCH')
              <input type="hidden" name="personnel_tab" value="training">
              <input type="hidden" name="status" value="approved">
              <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition">Approuver</button>
            </form>

            <form method="POST" action="{{ route('admin.personnel.training-enrollments.update-status', $enrollment->id) }}" class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-2">
              @csrf
              @method('PATCH')
              <input type="hidden" name="personnel_tab" value="training">
              <input type="hidden" name="status" value="rejected">
              <input type="text" name="comment" required placeholder="Motif du rejet" class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-1.5 text-xs bg-white">
              <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition">Rejeter</button>
            </form>
          </div>
          @elseif($_enrFollowOnly)
          <div class="mt-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-700">
            Mode suivi: cette demande est en attente de validation par le valideur courant.
          </div>
          @elseif($_mrIsFullAccess)
          {{-- Hors workflow hiérarchique: gestion statutaire manuelle legacy --}}
          <form method="POST" action="{{ route('admin.personnel.training-enrollments.update-status', $enrollment->id) }}" class="mt-3 flex items-center gap-2 flex-wrap">
            @csrf
            @method('PATCH')
            <input type="hidden" name="personnel_tab" value="training">
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs bg-white">
              @foreach(['planned' => 'Planifiée', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée'] as $sv => $sl)
              <option value="{{ $sv }}" {{ $_enrStatus === $sv ? 'selected' : '' }}>{{ $sl }}</option>
              @endforeach
            </select>
            <button type="submit" class="px-3 py-1.5 bg-violet-600 text-white rounded-lg text-xs font-semibold hover:bg-violet-700 transition">Mettre à jour</button>
          </form>
          @endif
        </div>
        @endforeach
      </div>
      @endif
    </div>

    {{-- Footer --}}
    <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between text-xs text-gray-500">
      <span>{{ $_mrEnrollments->count() }} demande(s) affichée(s)</span>
      <button type="button"
        onclick="document.getElementById('modal-training-requests').classList.add('hidden')"
        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-medium text-gray-700 transition">
        Fermer
      </button>
    </div>
  </div>
</div>

@endif
@elseif($personnelTab === 'career')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="text-xs uppercase tracking-wide text-indigo-700">{{ __('personnel.ui.career.cards.goals_title') }}</div>
    <div class="text-2xl font-black text-gray-800 mt-1">{{ number_format($personnelStats['goals'] ?? 0) }}</div>
    <p class="text-sm text-gray-500 mt-2">{{ __('personnel.ui.career.cards.goals_description') }}</p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="text-xs uppercase tracking-wide text-emerald-700">{{ __('personnel.ui.career.cards.reviews_title') }}</div>
    <div class="text-2xl font-black text-gray-800 mt-1">{{ number_format($personnelStats['reviews'] ?? 0) }}</div>
    <p class="text-sm text-gray-500 mt-2">{{ __('personnel.ui.career.cards.reviews_description') }}</p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="text-xs uppercase tracking-wide text-amber-700">{{ __('personnel.ui.career.cards.path_title') }}</div>
    <div class="text-2xl font-black text-gray-800 mt-1">{{ number_format($personnelStats['careerEvents'] ?? 0) }}</div>
    <p class="text-sm text-gray-500 mt-2">{{ __('personnel.ui.career.cards.path_description') }}</p>
  </div>
</div>

@php
  $currentActorId = (string) (auth()->id() ?? '');
  $currentActor = auth()->user();
  $currentActorProfileName = mb_strtoupper(trim(str_replace(['_', '-'], ' ', $currentActor?->profile?->name ?? '')), 'UTF-8');
  $isAgentRhFollowOnly = str_contains($currentActorProfileName, 'AGENT RH');
  $isSuperAdminFollowOnly = str_contains($currentActorProfileName, 'SUPER ADMIN')
    || (($currentActor?->role ?? '') === 'admin' && empty($currentActor?->profile_id));
  $mutationRequestsCareer = ($personnelMutationRequests ?? collect())->sortByDesc('created_at')->values();
  $mutationStatusLabelCareer = [
    'pending' => 'En attente',
    'validated' => 'Validée',
    'rejected' => 'Rejetée',
  ];
  $mutationStatusClassCareer = [
    'pending' => 'bg-amber-100 text-amber-800',
    'validated' => 'bg-emerald-100 text-emerald-700',
    'rejected' => 'bg-red-100 text-red-700',
  ];
  $careerSubtab = request('career_subtab', 'management');
  $careerSubtabs = [
    'management' => ['fas fa-sliders', 'Gestion'],
    'validation' => ['fas fa-user-check', 'Validation'],
    'history' => ['fas fa-clock-rotate-left', 'Historique'],
  ];
  if (!array_key_exists($careerSubtab, $careerSubtabs)) {
    $careerSubtab = 'management';
  }
  $careerValidationRows = $mutationRequestsCareer->filter(function ($m) use ($currentActorId) {
    return (string) ($m->status ?? '') === 'pending'
      && (string) data_get($m->metadata, 'approval_workflow.current_approver_user_id', '') === (string) $currentActorId;
  })->values();
  $careerHistoryRows = $mutationRequestsCareer->filter(fn ($m) => in_array((string) ($m->status ?? ''), ['validated', 'rejected'], true))->values();
  $careerApproveBtnClass = 'px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition';
  $careerRejectBtnClass = 'px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition';
@endphp

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap items-center gap-2">
    @foreach($careerSubtabs as $key => [$icon, $label])
    <a href="{{ route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'career', 'career_subtab' => $key]) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition {{ $careerSubtab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
      <i class="fa {{ $icon }} text-sm"></i>
      <span>{{ $label }}</span>
    </a>
    @endforeach
  </div>
</div>

@if($careerSubtab === 'validation')
<section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Validation des demandes de mutation</h3>
      <p class="text-sm text-gray-500">Demandes en attente d'action du valideur courant.</p>
    </div>
    <span class="text-xs rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-3 py-1">{{ $careerValidationRows->count() }} en attente</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500 border-b border-gray-100">
          <th class="py-3 pr-4">DATE</th>
          <th class="py-3 pr-4">MATRICULE</th>
          <th class="py-3 pr-4">NOM ET PRENOMS</th>
          <th class="py-3 pr-4">GRADE</th>
          <th class="py-3 pr-4">EMPLOI</th>
          <th class="py-3 pr-4">JUSTIF</th>
          <th class="py-3 pr-4">PJ</th>
          <th class="py-3">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($careerValidationRows as $mutation)
        @php
          $wf = is_array(data_get($mutation->metadata, 'approval_workflow')) ? data_get($mutation->metadata, 'approval_workflow') : [];
          $wfCurrentApproverId = trim((string) data_get($wf, 'current_approver_user_id', ''));
          $wfCanAct = $wfCurrentApproverId !== '' && strcasecmp($wfCurrentApproverId, $currentActorId) === 0 && !$isAgentRhFollowOnly && !$isSuperAdminFollowOnly;
          $mutationEmployeeMeta = is_array($mutation->employee?->metadata) ? $mutation->employee->metadata : [];
          $mutationGrade = $mutationEmployeeMeta['grade'] ?? '-';
          $mutationEmployment = $mutation->employee?->job_title ?: ($mutationEmployeeMeta['employment'] ?? ($mutationEmployeeMeta['emploi'] ?? '-'));
          $mutationJustif = trim((string) ($mutation->summary ?? $mutation->notes ?? ''));
          $mutationAttachments = is_array(data_get($mutation->metadata, 'mutation_request.attachments')) ? data_get($mutation->metadata, 'mutation_request.attachments') : [];
        @endphp
        <tr class="border-b border-gray-100 align-top">
          <td class="py-3 pr-4 text-gray-600">{{ optional($mutation->created_at)->format('d/m/Y H:i') }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $mutation->employee?->employee_number ?: '-' }}</td>
          <td class="py-3 pr-4 text-gray-800 font-semibold">{{ $mutation->employee?->full_name ?? 'Agent supprimé' }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $mutationGrade }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $mutationEmployment }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $mutationJustif !== '' ? $mutationJustif : '-' }}</td>
          <td class="py-3 pr-4 text-gray-600">
            @if(!empty($mutationAttachments))
              @foreach($mutationAttachments as $attachmentIndex => $attachment)
              <a href="{{ route('admin.personnel.mutation-requests.attachment.download', [$mutation, $attachmentIndex]) }}"
                class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-100">
                <i class="fas fa-file"></i> {{ $attachment['original_name'] ?? basename($attachment['path'] ?? '') }}
              </a>
              @endforeach
            @else
              -
            @endif
          </td>
          <td class="py-3">
            @if($wfCanAct)
            <div class="flex items-center gap-2 whitespace-nowrap">
              <form method="POST" action="{{ route('admin.personnel.mutation-requests.status', $mutation) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="personnel_tab" value="career">
                <input type="hidden" name="career_subtab" value="validation">
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="{{ $careerApproveBtnClass }}">Approuver</button>
              </form>
              <form method="POST" action="{{ route('admin.personnel.mutation-requests.status', $mutation) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="personnel_tab" value="career">
                <input type="hidden" name="career_subtab" value="validation">
                <input type="hidden" name="status" value="rejected">
                <input type="hidden" name="comment" value="Rejet depuis l'écran de validation mutation">
                <button type="submit" class="{{ $careerRejectBtnClass }}">Rejeter</button>
              </form>
            </div>
            @else
            <div class="text-[11px] text-gray-500">Action réservée au valideur courant.</div>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="py-8 text-center text-sm text-gray-400">Aucune demande de mutation en attente de validation.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@elseif($careerSubtab === 'history')
<section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Historique des demandes de mutation</h3>
      <p class="text-sm text-gray-500">Demandes validées ou rejetées.</p>
    </div>
    <span class="text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 px-3 py-1">{{ $careerHistoryRows->count() }} élément(s)</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500 border-b border-gray-100">
          <th class="py-3 pr-4">Date</th>
          <th class="py-3 pr-4">Agent</th>
          <th class="py-3 pr-4">Trajet</th>
          <th class="py-3">Statut</th>
        </tr>
      </thead>
      <tbody>
        @forelse($careerHistoryRows as $mutation)
        @php
          $historyTargetName = data_get($mutation->metadata, 'mutation_request.target_sub_entity_name', $mutation->new_job_title ?: '-');
          $historySourceName = data_get($mutation->metadata, 'mutation_request.source_sub_entity_name', $mutation->previous_job_title ?: '-');
        @endphp
        <tr class="border-b border-gray-100">
          <td class="py-3 pr-4 text-gray-600">{{ optional($mutation->updated_at)->format('d/m/Y H:i') }}</td>
          <td class="py-3 pr-4 text-gray-800 font-semibold">{{ $mutation->employee?->full_name ?? 'Agent supprimé' }}</td>
          <td class="py-3 pr-4 text-gray-600">{{ $historySourceName }} → {{ $historyTargetName }}</td>
          <td class="py-3">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $mutation->status === 'validated' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $mutation->status === 'validated' ? 'Validée' : 'Rejetée' }}</span>
          </td>
        </tr>
        @empty
        <tr><td colspan="4" class="py-8 text-center text-sm text-gray-400">Aucun historique disponible.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endif

<div class="grid grid-cols-1 xl:grid-cols-6 gap-5 mb-5">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.career.goals_section_title') }}</h3>
    @if($personnelEmployeeDirectory->isEmpty())
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800">{{ __('personnel.ui.career.need_employee_goals') }}</div>
    @else
    <form method="POST" action="{{ route('admin.personnel.goals.store') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="personnel_tab" value="career">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.career.form_employee') }}</label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value="">{{ __('personnel.ui.career.select') }}</option>
          @foreach($personnelEmployeeDirectory as $employee)
          <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.career.form_title') }}</label>
        <input type="text" name="title" value="{{ old('title') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.career.form_goal_type') }}</label>
          <select name="goal_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            @foreach(['individual' => __('personnel.ui.career.form_goal_type_individual'), 'team' => __('personnel.ui.career.form_goal_type_team'), 'strategic' => __('personnel.ui.career.form_goal_type_strategic')] as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.training.assign_form_status') }}</label>
          <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            @foreach(['draft' => __('personnel.ui.career.form_goal_status_draft'), 'active' => __('personnel.ui.career.form_goal_status_active'), 'completed' => __('personnel.ui.career.form_goal_status_completed'), 'on_hold' => __('personnel.ui.career.form_goal_status_on_hold'), 'cancelled' => __('personnel.ui.career.form_goal_status_cancelled')] as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="number" min="0" max="100" step="0.01" name="weight" value="{{ old('weight') }}" placeholder="{{ __('personnel.ui.career.form_goal_weight_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="number" step="0.01" name="target_value" value="{{ old('target_value') }}" placeholder="{{ __('personnel.ui.career.form_goal_target_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="number" min="0" max="100" step="0.01" name="progress_percent" value="{{ old('progress_percent', 0) }}" placeholder="{{ __('personnel.ui.career.form_goal_progress_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="date" name="start_date" value="{{ old('start_date') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="date" name="due_date" value="{{ old('due_date') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <textarea name="description" rows="3" placeholder="{{ __('personnel.ui.career.form_description_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('description') }}</textarea>
      <textarea name="notes" rows="2" placeholder="{{ __('personnel.ui.career.form_notes_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('notes') }}</textarea>
      <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.career.btn_create_goal') }}</button>
    </form>
    @endif
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.career.reviews_section_title') }}</h3>
    @if($personnelEmployeeDirectory->isEmpty())
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800">{{ __('personnel.ui.career.need_employee_review') }}</div>
    @else
    <form method="POST" action="{{ route('admin.personnel.performance-reviews.store') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="personnel_tab" value="career">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.career.form_employee') }}</label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value="">{{ __('personnel.ui.career.select') }}</option>
          @foreach($personnelEmployeeDirectory as $employee)
          <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="title" value="{{ old('title') }}" placeholder="{{ __('personnel.ui.career.review_title_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="text" name="period_label" value="{{ old('period_label') }}" placeholder="{{ __('personnel.ui.career.review_period_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <select name="review_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          @foreach(['annual' => __('personnel.ui.career.review_type_annual'), 'midyear' => __('personnel.ui.career.review_type_midyear'), 'probation' => __('personnel.ui.career.review_type_probation'), '360' => __('personnel.ui.career.review_type_360'), 'continuous' => __('personnel.ui.career.review_type_continuous')] as $value => $label)
          <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
        <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          @foreach(['scheduled' => __('personnel.ui.career.review_status_scheduled'), 'in_progress' => __('personnel.ui.career.review_status_in_progress'), 'completed' => __('personnel.ui.career.review_status_completed'), 'cancelled' => __('personnel.ui.career.review_status_cancelled')] as $value => $label)
          <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
        <input type="number" min="0" max="100" step="0.01" name="overall_score" value="{{ old('overall_score') }}" placeholder="{{ __('personnel.ui.career.overall_score_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <input type="date" name="scheduled_at" value="{{ old('scheduled_at') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      <textarea name="strengths" rows="2" placeholder="{{ __('personnel.ui.career.strengths_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('strengths') }}</textarea>
      <textarea name="improvements" rows="2" placeholder="{{ __('personnel.ui.career.improvements_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('improvements') }}</textarea>
      <textarea name="recommendations" rows="2" placeholder="{{ __('personnel.ui.career.recommendations_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('recommendations') }}</textarea>
      <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.career.btn_save_review') }}</button>
    </form>
    @endif
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.career.events_section_title') }}</h3>
    @if($personnelEmployeeDirectory->isEmpty())
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800">{{ __('personnel.ui.career.need_employee_events') }}</div>
    @else
    <form method="POST" action="{{ route('admin.personnel.career-events.store') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="personnel_tab" value="career">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('personnel.ui.career.form_employee') }}</label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value="">{{ __('personnel.ui.career.select') }}</option>
          @foreach($personnelEmployeeDirectory as $employee)
          <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="title" value="{{ old('title') }}" placeholder="{{ __('personnel.ui.career.event_title_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <select name="event_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          @foreach(['promotion' => __('personnel.ui.career.event_type_promotion'), 'mobility' => __('personnel.ui.career.event_type_mobility'), 'succession' => __('personnel.ui.career.event_type_succession'), 'job_change' => __('personnel.ui.career.event_type_job_change'), 'interview' => __('personnel.ui.career.event_type_interview')] as $value => $label)
          <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="date" name="effective_date" value="{{ old('effective_date') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="text" name="previous_job_title" value="{{ old('previous_job_title') }}" placeholder="{{ __('personnel.ui.career.prev_job_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="text" name="new_job_title" value="{{ old('new_job_title') }}" placeholder="{{ __('personnel.ui.career.new_job_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        @foreach(['planned' => __('personnel.ui.career.event_status_planned'), 'validated' => __('personnel.ui.career.event_status_validated'), 'completed' => __('personnel.ui.career.event_status_completed'), 'cancelled' => __('personnel.ui.career.event_status_cancelled')] as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
      </select>
      <textarea name="summary" rows="2" placeholder="{{ __('personnel.ui.career.summary_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('summary') }}</textarea>
      <textarea name="notes" rows="2" placeholder="{{ __('personnel.ui.career.form_notes_placeholder') }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">{{ old('notes') }}</textarea>
      <button type="submit" class="px-6 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-semibold">{{ __('personnel.ui.career.btn_trace_event') }}</button>
    </form>
    @endif
  </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.career.goals_recent_title') }}</h3>
    <div class="space-y-3">
      @forelse($personnelGoals as $goal)
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800">{{ $goal->title }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $goal->employee?->full_name ?? __('personnel.ui.career.deleted_employee') }} · {{ __('personnel.ui.statuses.' . $goal->goal_type) }} · {{ $goal->progress_percent }}%</div>
      </div>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.career.no_goals') }}</div>
      @endforelse
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.career.reviews_recent_title') }}</h3>
    <div class="space-y-3">
      @forelse($personnelPerformanceReviews as $review)
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800">{{ $review->title }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $review->employee?->full_name ?? __('personnel.ui.career.deleted_employee') }} · {{ __('personnel.ui.statuses.' . $review->review_type) }} · {{ __('personnel.ui.statuses.' . $review->status) }}</div>
      </div>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.career.no_reviews') }}</div>
      @endforelse
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">{{ __('personnel.ui.career.path_recent_title') }}</h3>
    <div class="space-y-3">
      @forelse($personnelCareerEvents as $event)
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800">{{ $event->title }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $event->employee?->full_name ?? __('personnel.ui.career.deleted_employee') }} · {{ __('personnel.ui.statuses.' . $event->event_type) }} · {{ __('personnel.ui.statuses.' . $event->status) }}</div>
      </div>
      @empty
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500">{{ __('personnel.ui.career.no_events') }}</div>
      @endforelse
    </div>
  </section>
</div>
@endif
{{-- END personnel tab --}}
@endif

{{-- ══════════════════════ TEMPLATES ══════════════════════ --}}
@if($tab === 'templates')
@php
    $selectedTplId = old('selected_template', request('selected_template', ''));
    $selectedTpl   = $templates->firstWhere('id', $selectedTplId);
    $filterEmitter = request('filter_emitter', '');
    $filteredTpls  = $filterEmitter
        ? $templates->filter(fn($t) => $t->administration_id === $filterEmitter)
        : $templates;
@endphp

{{-- Layout 2 colonnes --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

    {{-- -- Colonne gauche : formulaire template + liste -- --}}
    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">Gestion des Templates</h2>
        <p class="text-xs text-gray-500">Utilisez la syntaxe <strong>&#123;&#123;variable&#125;&#125;</strong> dans le contenu pour créer des documents dynamiques.</p>

        {{-- Filtre administration --}}
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <label class="block text-xs text-gray-500 mb-1">Administration Émettrice concern�e</label>
            <select id="tpl-filter-emitter" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-300"
                onchange="tplFilterEmitter(this.value)">
                <option value="">Toutes les administrations</option>
                @foreach($emitters as $e)
                <option value="{{ $e->id }}" {{ $filterEmitter === $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Formulaire create/edit template --}}
        @php $editTpl = session('editing_template') ? $templates->firstWhere('id', session('editing_template')) : null; @endphp
        <form id="tpl-form" method="POST"
              action="{{ $selectedTplId && request('tpl_action') === 'edit' ? route('admin.templates.update', $selectedTplId) : route('admin.templates.store') }}"
              class="flex flex-col gap-3">
            @csrf
            @if($selectedTplId && request('tpl_action') === 'edit')
                @method('PUT')
                <input type="hidden" name="selected_template" value="{{ $selectedTplId }}">
            @endif
            <input type="hidden" name="tab" value="templates">

            {{-- Ligne 1 : nom, fichier, type --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input id="tpl-name" type="text" name="name" placeholder="Nom du template *"
                       value="{{ old('name', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->name ?? '') : '') }}"
                       required class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">

                <input id="tpl-filename" type="text" name="file_name" placeholder="Nom du fichier (ex: mon-template.docx)"
                       value="{{ old('file_name', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->file_name ?? '') : '') }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">

                <select id="tpl-filetype" name="file_type" class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">
                    @foreach(['docx' => 'DOCX','xlsx' => 'XLSX','pptx' => 'PPTX','pdf' => 'PDF'] as $val => $lbl)
                    <option value="{{ $val }}" {{ old('file_type', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->file_type ?? 'docx') : 'docx') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Ligne 2 : administration + OnlyOffice --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @if(isset($adminScope) && $adminScope && $adminScope['type'] === 'emitter')
                <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
                <div class="md:col-span-2 border border-blue-100 rounded-lg px-3 py-2 text-xs bg-blue-50 text-blue-800 font-medium flex items-center gap-2">
                  <i class="fas fa-building text-blue-400"></i>
                  {{ $emitters->first()?->name ?? '--' }}
                </div>
                @else
                <select name="administration_id" class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">
                  <option value="">• Administration émettrice •</option>
                  @foreach($emitters as $e)
                  <option value="{{ $e->id }}" {{ old('administration_id', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->administration_id ?? '') : $filterEmitter) === $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                  @endforeach
                </select>
                @endif

                {{-- Bouton éditeur OnlyOffice --}}
                <button type="button" onclick="tplOpenOnlyOffice()"
                        class="bg-green-100 text-green-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-green-200 transition flex items-center justify-center gap-1">
                  <i class="fas fa-external-link-alt text-xs"></i> Ouvrir l'éditeur OnlyOffice
                </button>

                {{-- Nouveau flux: upload Word + analyse IA + génération formulaire --}}
                <button type="button" onclick="tplOpenUploadFlow()"
                        class="bg-blue-100 text-blue-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-blue-200 transition flex items-center justify-center gap-1">
                  <i class="fas fa-file-upload text-xs"></i> Uploader Word et générer le formulaire (IA)
                </button>
            </div>

            {{-- Éditeur de texte riche Quill pour le contenu du template --}}
            <div>
                {{-- Barre d'insertion de variables --}}
                <div id="tpl-var-insert-bar" class="flex flex-wrap gap-1.5 mb-1.5 p-2 bg-blue-50 border border-blue-100 rounded-lg">
                    <span class="text-[10px] font-bold text-blue-600 self-center mr-1"><i class="fas fa-code mr-1"></i>Insérer une variable :</span>
                    @php $varExamples = ['nom','prenom','date','date_naissance','adresse','numero','objet','structure','poste','lieu']; @endphp
                    @foreach($varExamples as $v)
                    @php $varTag = '[' . $v . ']'; @endphp
                    <button type="button" onclick="tplQuillInsertVar('{{ $varTag }}')"
                            class="tpl-var-pill inline-flex items-center gap-1 bg-white border border-blue-200 text-blue-700 text-[10px] font-semibold px-2 py-0.5 rounded-full hover:bg-blue-100 transition cursor-pointer">
                        <i class="fas fa-plus text-[8px]"></i>&nbsp;{{ $varTag }}
                    </button>
                    @endforeach
                    <button type="button" onclick="tplQuillInsertCustomVar()"
                            class="inline-flex items-center gap-1 bg-blue-600 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full hover:bg-blue-700 transition cursor-pointer">
                        <i class="fas fa-plus text-[8px]"></i> Personnalisée
                    </button>
                </div>
                {{-- Conteneur Quill --}}
                <div id="tpl-quill-editor" style="font-size:13px;background:#fff;"></div>
                {{-- Champ cach� qui recevra le HTML pour le form POST --}}
                <textarea id="tpl-content" name="content" class="hidden">{{ old('content', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->content ?? '') : '') }}</textarea>
            </div>

            {{-- Bouton submit DANS le form, apr�s l'�diteur --}}
            <button type="submit"
                    class="w-full bg-blue-600 text-white rounded-lg px-3 py-2.5 text-sm font-bold hover:bg-blue-700 transition flex items-center justify-center gap-2">
                <i class="fas fa-check-circle"></i>
                {{ ($selectedTplId && request('tpl_action') === 'edit') ? 'Mettre à jour le modèle' : 'Créer le modèle' }}
            </button>
        </form>

        {{-- Séparateur + liste --}}
        <div class="border-t border-gray-200 pt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-list text-gray-400 mr-1"></i> Templates créés
                <span class="ml-1 text-xs font-normal text-gray-400">({{ $filteredTpls->count() }})</span>
            </h3>

        {{-- Liste des templates --}}
        <div id="tpl-list-container" class="space-y-2">
            @forelse($filteredTpls as $tpl)
            @php $tplShared = $shareMap[$tpl->id] ?? []; @endphp
            <div class="border rounded-lg p-3 {{ $selectedTplId === $tpl->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50' }}">
                <button type="button" onclick="tplSelect('{{ $tpl->id }}')" class="w-full text-left">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-xs font-semibold text-gray-800 truncate" title="{{ $tpl->name }}">{{ $tpl->name }}</p>
                  @if(request('ia_generated') === '1' && (string) $selectedTplId === (string) $tpl->id)
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap"
                      title="Template importé avec détection IA et génération automatique des champs">
                    <i class="fas fa-robot text-[9px]"></i> Formulaire auto IA
                  </span>
                  @endif
                </div>
                    <p class="text-xs text-gray-500 truncate">{{ $tpl->file_name ?: '-' }} • {{ strtoupper($tpl->file_type) }}</p>
                    @if($tpl->administration)
                    <p class="text-xs text-gray-400">{{ $tpl->administration->name }}</p>
                    @endif
                    <p class="text-xs text-blue-600 mt-0.5">{{ count($tplShared) }} partage(s)</p>
                </button>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    {{-- Modifier dans OnlyOffice --}}
                    <button type="button" onclick="tplOoEdit('{{ $tpl->id }}', '{{ addslashes($tpl->name) }}')"
                            class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-xs hover:bg-gray-300 transition flex items-center gap-1">
                        <i class="fas fa-pen text-xs"></i> Modifier
                    </button>
                    {{-- Supprimer --}}
                    <form method="POST" action="{{ route('admin.templates.destroy', $tpl) }}"
                          onsubmit="return confirm('Supprimer ce template ?')" class="inline">
                        @csrf @method('DELETE')
                        <input type="hidden" name="tab" value="templates">
                        <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs hover:bg-red-200 transition">
                            <i class="fas fa-trash-alt text-xs"></i> Supprimer
                        </button>
                    </form>
                    {{-- Partager --}}
                    <button type="button" onclick="tplOpenShare('{{ $tpl->id }}', '{{ addslashes($tpl->name) }}')"
                            class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200 transition flex items-center gap-1">
                        <i class="fas fa-share-alt text-xs"></i> Partager
                    </button>
                    {{-- Sélectionner pour variables --}}
                    <button type="button" onclick="tplSelect('{{ $tpl->id }}')"
                            class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs hover:bg-green-200 transition flex items-center gap-1">
                        <i class="fas fa-sliders-h text-xs"></i> Variables
                    </button>
                    {{-- Auto-détecter variables depuis fichier --}}
                    @if(in_array($tpl->file_type, ['docx','xlsx','pptx']))
                    <button type="button" onclick="tplDetectVars('{{ $tpl->id }}','{{ addslashes($tpl->name) }}')" id="detect-btn-{{ $tpl->id }}"
                            class="px-2 py-1 rounded bg-yellow-100 text-yellow-700 text-xs hover:bg-yellow-200 transition flex items-center gap-1">
                        <i class="fas fa-magic text-xs"></i> <span id="detect-label-{{ $tpl->id }}">{{ $tpl->variables->count() > 0 ? $tpl->variables->count().' vars' : 'Détecter' }}</span>
                    </button>
                      <button type="button" onclick="tplAiEnrich('{{ $tpl->id }}','{{ addslashes($tpl->name) }}')" id="ai-enrich-btn-{{ $tpl->id }}"
                          class="px-2 py-1 rounded bg-purple-100 text-purple-700 text-xs hover:bg-purple-200 transition flex items-center gap-1"
                          title="Enrichir les variables avec l'IA Ollama (labels, types, obligatoire)">
                        <i class="fas fa-robot text-xs"></i> <span id="ai-enrich-label-{{ $tpl->id }}">✨ IA</span>
                      </button>
                    {{-- Récupérer les variables (urgent) si fichier existe mais 0 variables --}}
                    @if($tpl->variables->count() === 0 && $tpl->storage_path)
                    <button type="button" onclick="tplRecoverVars('{{ $tpl->id }}','{{ addslashes($tpl->name) }}')" id="recover-btn-{{ $tpl->id }}"
                            class="px-2 py-1 rounded bg-orange-500 text-white text-xs hover:bg-orange-600 transition flex items-center gap-1 animate-pulse"
                            title="Réextrait les variables d'un fichier déjà stocké">
                        <i class="fas fa-exclamation-triangle text-xs"></i> Récupérer
                    </button>
                    @endif
                    @if(!$tpl->storage_path && $tpl->variables->count() === 0)
                    {{-- Template sans fichier : demander d'ouvrir dans OO pour enregistrer --}}
                    <button type="button" onclick="tplOoEdit('{{ $tpl->id }}','{{ addslashes($tpl->name) }}')"
                            class="px-2 py-1 rounded bg-red-500 text-white text-xs hover:bg-red-600 transition flex items-center gap-1 animate-pulse"
                            title="Ce template n'a pas encore été enregistré dans OnlyOffice. Cliquez pour l'ouvrir et enregistrer.">
                        <i class="fas fa-exclamation-circle text-xs"></i> À enregistrer!
                    </button>
                    @endif
                    @endif
                </div>
            </div>
            @empty
            <div class="border border-dashed border-gray-300 rounded-lg p-6 text-xs text-gray-400 text-center">
                Aucun template configur� pour cette administration.
            </div>
            @endforelse
        </div>
        </div>{{-- end border-t wrapper --}}
    </section>

    {{-- -- Colonne droite : variables + partage -- --}}
    <section id="tpl-variables-panel" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">Balises dynamiques</h2>
        <p class="text-xs text-gray-500">
            Template sélectionné :
            <strong id="tpl-selected-name">{{ $selectedTpl ? $selectedTpl->name : 'Aucun' }}</strong>
        </p>

        {{-- Formulaire ajout variable --}}
        <form method="POST"
              action="{{ $selectedTplId ? route('admin.templates.variables.store', $selectedTplId) : '#' }}"
              class="grid grid-cols-1 md:grid-cols-3 gap-3"
              {{ $selectedTplId ? '' : 'onsubmit="return false"' }}>
            @csrf
            <input type="hidden" name="tab" value="templates">
            <input type="hidden" name="selected_template" value="{{ $selectedTplId }}">

            <input type="text" name="label" placeholder="Nom de la variable *"
                   class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none"
                   {{ $selectedTplId ? 'required' : 'disabled' }}>

            <select name="field_type" class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none" {{ $selectedTplId ? '' : 'disabled' }}>
                <option value="text">Texte</option>
                <option value="date">Date</option>
                <option value="number">Nombre</option>
                <option value="select">Liste</option>
                <option value="textarea">Zone de texte</option>
            </select>

            <button type="submit"
                    class="md:col-span-3 bg-blue-600 text-white rounded-lg px-3 py-2 text-xs font-semibold hover:bg-blue-700 transition {{ $selectedTplId ? '' : 'opacity-40 cursor-not-allowed' }}">
                Ajouter le champ
            </button>
        </form>

        {{-- Liste des variables du template s�lectionn� --}}
        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
            @if($selectedTpl)
                @forelse($selectedTpl->variables as $variable)
                <div class="border border-gray-200 bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-800">{{ $variable->label }}</p>
                    <p class="text-xs text-gray-500">Balise : <code class="bg-gray-100 px-1 rounded">[{{ $variable->key }}]</code></p>
                    <p class="text-xs text-gray-500">Type : {{ $variable->field_type }}</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button"
                                onclick="openEditVariableModal('{{ $selectedTpl->id }}', '{{ $variable->id }}', '{{ addslashes($variable->label) }}', '{{ $variable->field_type }}', '{{ $selectedTplId }}')"
                                class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200 transition">
                            <i class="fas fa-pen text-xs"></i> Modifier
                        </button>
                        <form method="POST"
                              action="{{ route('admin.templates.variables.destroy', [$selectedTpl, $variable->id]) }}"
                              onsubmit="return confirm('Supprimer cette variable ?')" class="inline">
                            @csrf @method('DELETE')
                            <input type="hidden" name="tab" value="templates">
                            <input type="hidden" name="selected_template" value="{{ $selectedTplId }}">
                            <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs hover:bg-red-200 transition">
                                <i class="fas fa-trash-alt text-xs"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-xs text-gray-400">Aucune variable pour ce template. Ajoutez des balises [variable] (ou &#123;&#123;variable&#125;&#125;) dans le contenu.</p>
                @endforelse
            @else
            <p class="text-xs text-gray-400">Sélectionnez un template pour gérer ses variables.</p>
            @endif
        </div>

        {{-- Partage : liste des utilisateurs ayant acc�s --}}
        @if($selectedTpl)
        <div class="border-t border-gray-100 pt-4 space-y-2">
            <h3 class="text-sm font-semibold text-gray-800">Acc�s partag�s</h3>
            @php $currentShared = $shareMap[$selectedTpl->id] ?? []; @endphp
            @if(count($currentShared))
            <div class="flex flex-wrap gap-2">
                @foreach($currentShared as $uid)
                @php $su = $allUsers->firstWhere('id', $uid); @endphp
                @if($su)
                <div class="flex items-center gap-1 bg-blue-50 border border-blue-200 rounded-full px-3 py-1 text-xs text-blue-700">
                    <span>{{ $su->name }}</span>
                    <form method="POST" action="{{ route('admin.templates.share', $selectedTpl) }}" class="inline">
                        @csrf
                        <input type="hidden" name="tab" value="templates">
                        <input type="hidden" name="selected_template" value="{{ $selectedTplId }}">
                        <input type="hidden" name="user_id" value="{{ $uid }}">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="ml-1 text-blue-400 hover:text-red-500 text-xs">&times;</button>
                    </form>
                </div>
                @endif
                @endforeach
            </div>
            @else
            <p class="text-xs text-gray-400">Aucun accès partagé. Utilisez le bouton "Partager" dans la liste.</p>
            @endif
        </div>
        @endif
    </section>
</div>

{{-- ═══ MODAL PARTAGE DE TEMPLATE ═══ --}}
<div id="modal-tpl-share" class="adm-modal">
    <div class="adm-modal-box max-w-md">
        <button onclick="closeModal('modal-tpl-share')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-1">Partager le template</h3>
        <p id="share-modal-tpl-name" class="text-xs text-gray-500 mb-4"></p>

        {{-- Recherche utilisateur --}}
        <input type="text" id="share-user-search" placeholder="Rechercher un utilisateur…"
               oninput="tplShareSearch(this.value)"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs mb-3 focus:ring-2 focus:ring-blue-300 outline-none">

        <div id="share-user-list" class="max-h-60 overflow-y-auto space-y-1.5">
            @foreach($allUsers as $u)
            @php $isShared = isset($shareMap['__current__']) && in_array($u->id, $shareMap['__current__'] ?? []); @endphp
            <div class="share-user-row flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2 bg-gray-50"
                 data-name="{{ strtolower($u->name) }}" data-email="{{ strtolower($u->email) }}">
                <div>
                    <p class="text-xs font-medium text-gray-800">{{ $u->name }}</p>
                    <p class="text-xs text-gray-400">{{ $u->email }}</p>
                </div>
                <form method="POST" id="share-form-{{ $u->id }}" action="" class="inline share-toggle-form">
                    @csrf
                    <input type="hidden" name="tab" value="templates">
                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="selected_template" id="share-hidden-tpl-{{ $u->id }}" value="">
                    <button type="submit"
                            class="share-toggle-btn px-3 py-1 rounded-lg text-xs font-semibold transition bg-gray-200 text-gray-700 hover:bg-blue-100 hover:text-blue-700"
                            data-user-id="{{ $u->id }}">
                        Partager
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        <div class="mt-4 flex justify-end">
            <button onclick="closeModal('modal-tpl-share')" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Fermer</button>
        </div>
    </div>
</div>

{{-- ═══ MODAL MODIFIER VARIABLE ═══ --}}
<div id="modal-edit-variable" class="adm-modal">
    <div class="adm-modal-box max-w-md">
        <button onclick="closeModal('modal-edit-variable')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-4">Modifier la variable</h3>
        <form id="form-edit-variable" method="POST" action="#">
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" value="templates">
            <input type="hidden" id="edit-var-selected-template" name="selected_template" value="">
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nom de la variable *</label>
                    <input type="text" id="edit-var-label" name="label" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
                    <select id="edit-var-field-type" name="field_type"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">
                        <option value="text">Texte</option>
                        <option value="date">Date</option>
                        <option value="number">Nombre</option>
                        <option value="select">Liste</option>
                        <option value="textarea">Zone de texte</option>
                    </select>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-edit-variable')"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Annuler</button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">Enregistrer</button>
            </div>
        </form>
    </div>
</div>


<div id="modal-tpl-oo" class="adm-modal">
    <div class="adm-modal-box" style="max-width:96vw;width:96vw;max-height:96vh;height:96vh;padding:0;display:flex;flex-direction:column;">

        {{-- Barre d'outils --}}
        <div class="flex items-center gap-2 px-4 py-2 border-b border-gray-100 bg-white flex-shrink-0 flex-wrap">
            <div class="flex items-center gap-2 mr-auto">
                <i class="fas fa-file-alt text-blue-600 text-sm"></i>
                <span class="text-sm font-bold text-gray-800">OnlyOffice &mdash; éditeur de template</span>
            </div>

            <button type="button" onclick="tplOoCreateModel()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold transition shadow-sm">
                <i class="fas fa-plus-circle text-xs"></i> Créer un modèle
            </button>

            {{-- Bouton Importer --}}
            <button type="button" onclick="tplOoOpenUpload()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold transition shadow-sm">
                <i class="fas fa-upload text-xs"></i> Importer un fichier
            </button>
            {{-- Input file cach� --}}
            <input type="file" id="tpl-oo-file-input" accept=".docx,.xlsx,.pptx,.pdf" class="hidden"
                onchange="tplOoHandleFileSelect(this)">

            <button type="button" onclick="tplOoAddZone()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold transition shadow-sm">
                <i class="fas fa-pen-nib text-xs"></i> Ajouter zone
            </button>

            <button type="button" onclick="tplOoClearZone()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold transition shadow-sm">
                <i class="fas fa-eraser text-xs"></i> Effacer zone
            </button>

            <button type="button" onclick="tplOoForceSave()" id="tpl-oo-forcesave-btn"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold transition shadow-sm">
              <i class="fas fa-save text-xs"></i> Enregistrer le modèle
            </button>

            <button type="button" onclick="tplOoSave()" id="tpl-oo-save-btn"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition shadow-sm">
                <i class="fas fa-stamp text-xs"></i> Sceller les zones
            </button>

            <button type="button" onclick="tplOoClose()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold transition">
                <i class="fas fa-times text-xs"></i> Fermer
            </button>
        </div>

        {{-- Bande de statut feedback --}}
        <div id="tpl-oo-status" class="hidden px-4 py-2 text-xs font-medium bg-blue-50 border-b border-blue-100 text-blue-700 flex-shrink-0 flex items-center gap-2">
            <i class="fas fa-info-circle"></i><span id="tpl-oo-status-text"></span>
        </div>

        <div class="px-4 py-2 text-[11px] text-amber-800 bg-amber-50 border-b border-amber-100 flex items-start gap-2">
          <i class="fas fa-triangle-exclamation mt-0.5"></i>
          <span>Utilisez OnlyOffice pour créer ou modifier votre template DOCX/XLSX/PPTX. Les balises texte [nom] ou @{{nom}} peuvent être insérées directement dans le document puis détectées dans Balises dynamiques.</span>
        </div>

        {{-- Panneau de création de template (affiché/masqué) --}}
        <div id="tpl-oo-create-panel" class="hidden flex-shrink-0 border-b border-blue-200 bg-blue-50 px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-blue-800"><i class="fas fa-plus-circle mr-1.5"></i>Créer un nouveau modèle</p>
                <button type="button" onclick="tplOoClosCreatePanel()" class="text-blue-400 hover:text-blue-700 text-lg leading-none">&times;</button>
            </div>
            <form id="tpl-oo-create-form" onsubmit="tplOoSubmitCreate(event)" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-blue-700 mb-1">Nom du template <span class="text-red-500">*</span></label>
                    <input id="tpl-oo-name" type="text" name="name" placeholder="Ex : Attestation de travail"
                        class="w-full border border-blue-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                    <p id="tpl-oo-name-err" class="hidden text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Champ obligatoire</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-blue-700 mb-1">Nom du fichier <span class="text-red-500">*</span></label>
                    <input id="tpl-oo-filename" type="text" name="file_name" placeholder="Ex : attestation.docx"
                        class="w-full border border-blue-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                    <p id="tpl-oo-filename-err" class="hidden text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Champ obligatoire</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-blue-700 mb-1">Type de fichier <span class="text-red-500">*</span></label>
                    <select id="tpl-oo-filetype" name="file_type"
                        class="w-full border border-blue-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                      <option value="docx" selected>DOCX</option>
                      <option value="xlsx">XLSX</option>
                      <option value="pptx">PPTX</option>
                    </select>
                    <p id="tpl-oo-filetype-err" class="hidden text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Champ obligatoire</p>
                </div>
                @if(isset($adminScope) && $adminScope && $adminScope['type'] === 'emitter')
                {{-- Admin d'administration scopé: administration fixée --}}
                <input type="hidden" id="tpl-oo-admin" name="administration_id" value="{{ $adminScope['id'] }}">
                <div>
                  <label class="block text-xs font-semibold text-blue-700 mb-1">Administration</label>
                  <div class="w-full border border-blue-100 rounded-lg px-3 py-2 text-xs bg-blue-50 text-blue-800 font-medium flex items-center gap-2">
                    <i class="fas fa-building text-blue-400"></i>
                    {{ $emitters->first()?->name ?? '--' }}
                  </div>
                </div>
                @elseif(auth()->user()->role === 'admin')
                <div>
                  <label class="block text-xs font-semibold text-blue-700 mb-1">Administration</label>
                  <select id="tpl-oo-admin" name="administration_id"
                    class="w-full border border-blue-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                    <option value="">-- Toutes --</option>
                    @foreach($emitters as $e)
                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                  </select>
                </div>
        @else
                {{-- Utilisateur non SUPER ADMIN : administration fixee automatiquement --}}
                <input type="hidden" id="tpl-oo-admin" name="administration_id"
                  value="{{ auth()->user()->profile->administration_id ?? '' }}">
                <div>
                  <label class="block text-xs font-semibold text-blue-700 mb-1">Administration</label>
                  <div class="w-full border border-blue-100 rounded-lg px-3 py-2 text-xs bg-blue-50 text-blue-800 font-medium flex items-center gap-2">
                    <i class="fas fa-building text-blue-400"></i>
                    {{ auth()->user()->profile->administration->name ?? '--' }}
                  </div>
                </div>
        @endif
                <div class="md:col-span-4 flex items-center gap-3">
                    <button type="submit" id="tpl-oo-submit-btn"
                        class="flex items-center gap-2 px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold rounded-lg transition shadow">
                        <i class="fas fa-check-circle"></i> Créer le modèle
                    </button>
                    <button type="button" onclick="tplOoClosCreatePanel()"
                        class="px-4 py-2 bg-white border border-blue-200 text-blue-700 text-xs font-semibold rounded-lg hover:bg-blue-100 transition">
                        Annuler
                    </button>
                    <span id="tpl-oo-create-msg" class="text-xs font-semibold"></span>
                </div>
            </form>
        </div>

        {{-- Panneau d'import de fichier --}}
        <div id="tpl-oo-upload-panel" class="hidden flex-shrink-0 border-b border-purple-200 bg-purple-50 px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-purple-800"><i class="fas fa-upload mr-1.5"></i>Importer un fichier comme modèle</p>
                <button type="button" onclick="tplOoCloseUploadPanel()" class="text-purple-400 hover:text-purple-700 text-lg leading-none">&times;</button>
            </div>
            {{-- Zone de drop --}}
            <div id="tpl-oo-drop-zone"
                 class="border-2 border-dashed border-purple-300 rounded-xl p-6 text-center cursor-pointer hover:bg-purple-100 transition mb-4"
                 onclick="document.getElementById('tpl-oo-file-input').click()"
                 ondragover="event.preventDefault();this.classList.add('bg-purple-100')"
                 ondragleave="this.classList.remove('bg-purple-100')"
                 ondrop="event.preventDefault();this.classList.remove('bg-purple-100');tplOoHandleFileDrop(event)">
                <i class="fas fa-cloud-upload-alt text-3xl text-purple-400 mb-2"></i>
                <p class="text-sm text-purple-700 font-semibold">Cliquez ou glissez un fichier ici</p>
              <p class="text-xs text-purple-400 mt-1">PDF, DOCX, XLSX, PPTX acceptés (max 20 Mo)</p>
            </div>
            {{-- Infos fichier sélectionné --}}
            <div id="tpl-oo-upload-info" class="hidden mb-4 bg-white border border-purple-200 rounded-lg px-4 py-3 flex items-center gap-3">
                <i class="fas fa-file-alt text-purple-500 text-lg"></i>
                <div class="flex-1 min-w-0">
                    <p id="tpl-oo-upload-fname" class="text-xs font-semibold text-gray-800 truncate"></p>
                    <p id="tpl-oo-upload-fsize" class="text-xs text-gray-400"></p>
                </div>
                <button onclick="tplOoClearUpload()" class="text-gray-400 hover:text-red-500 text-sm"><i class="fas fa-times"></i></button>
            </div>
            {{-- Formulaire template à remplir --}}
            <form id="tpl-oo-upload-form" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end" onsubmit="tplOoSubmitUpload(event)">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-purple-700 mb-1">Nom du modèle <span class="text-red-500">*</span></label>
                    <input id="tpl-oo-up-name" name="name" type="text" placeholder="Ex : Contrat de travail"
                        class="w-full border border-purple-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-purple-400 outline-none bg-white">
                    <p id="tpl-oo-up-name-err" class="hidden text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Champ obligatoire</p>
                </div>
                @if(auth()->user()->role === 'admin')
                <div>
                    <label class="block text-xs font-semibold text-purple-700 mb-1">Administration</label>
                    <select id="tpl-oo-up-admin" name="administration_id" class="w-full border border-purple-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-purple-400 outline-none bg-white">
                        <option value="">-- Toutes --</option>
                        @foreach($emitters as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <input type="hidden" id="tpl-oo-up-admin" name="administration_id" value="{{ auth()->user()->profile->administration_id ?? '' }}">
                <div>
                    <label class="block text-xs font-semibold text-purple-700 mb-1">Administration</label>
                    <div class="w-full border border-purple-100 rounded-lg px-3 py-2 text-xs bg-purple-50 text-purple-800 font-medium">
                        <i class="fas fa-building text-purple-400 mr-1"></i>{{ auth()->user()->profile->administration->name ?? '--' }}
                    </div>
                </div>
                @endif
                <div class="flex items-end gap-2">
                    <button type="submit" id="tpl-oo-up-submit"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold rounded-lg transition shadow disabled:opacity-50">
                    <i class="fas fa-upload"></i> Uploader &amp; ouvrir PDF
                    </button>
                </div>
                <div class="md:col-span-3">
                    <div id="tpl-oo-upload-progress" class="hidden mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="tpl-oo-up-bar" class="bg-purple-600 h-2 rounded-full transition-all" style="width:0%"></div>
                        </div>
                        <p id="tpl-oo-up-msg" class="text-xs text-purple-700 mt-1 font-semibold"></p>
                    </div>
                </div>
            </form>
        </div>

        {{-- Zones de signature enregistrées --}}
        <div id="tpl-oo-zones-bar" class="hidden px-4 py-2 bg-amber-50 border-b border-amber-100 flex-shrink-0 flex items-center gap-3 flex-wrap">
            <span class="text-xs font-semibold text-amber-700"><i class="fas fa-map-marker-alt mr-1"></i>Zones :</span>
            <div id="tpl-oo-zones-list" class="flex flex-wrap gap-2"></div>
        </div>

        {{-- Lecteur PDF natif + overlay de positionnement --}}
        <div id="oo-iframe-container" class="flex-1 relative overflow-hidden">
            <div id="oo-editor-placeholder" class="w-full h-full" style="min-height:0;position:relative;"></div>

            {{-- Les zones glissables sont injectées dynamiquement par JS --}}
            <div id="tpl-oo-zone-markers"></div>
        </div>
    </div>
</div>

    @push('scripts')
<script>
(function() {
    // --- Donn�es PHP → JS -----------------------------------------------------
    var _ooUrl    = @json($onlyofficeUrl);
    var _ooJwt    = @json($onlyofficeJwt);
    var _ooTokenUrl = "{{ route('admin.onlyoffice.token') }}";
    var _appPublicUrl = @json($appPublicUrl ?? '');
    var _shareMap = @json($shareMap);
    var _currentShareTplId = '';
    var _adminBaseServer = @json(route('admin.index', [], false));
    // Base robuste: conserve un éventuel sous-dossier (ex: /app/public/admin)
    var _path = window.location.pathname || '/admin';
    var _idxAdmin = _path.indexOf('/admin');
    var _adminBaseRuntime = _idxAdmin >= 0 ? _path.substring(0, _idxAdmin) + '/admin' : '/admin';
    var _adminBase = _adminBaseRuntime || _adminBaseServer || '/admin';
    window._adminBase = _adminBase;
    var _adminTplBase = _adminBase + '/templates';
    var _adminTplUploadUrl = @json(route('admin.admin.templates.uploadFile'));
    var _tplOoUploadTargetId = null;
    window._tplStoreUrl = @json(route('admin.templates.store'));
    var _tplOoDebug = false;
    function _tplOoLog() {
      if (!_tplOoDebug || !window.console || !console.log) return;
      console.log.apply(console, arguments);
    }

    // Garde de fermeture: pour un template nouvellement créé, empêcher la fermeture
    // du modal tant qu'un fichier n'a pas été confirmé côté serveur.
    var _tplOoCloseGuard = {
        active: false,
        templateId: null,
        fileSaved: false,
        timer: null,
        inFlight: false,
        bannerShown: false,
      lastState: 'idle',
    };

    function tplOoDisarmCloseGuard() {
        _tplOoCloseGuard.active = false;
        _tplOoCloseGuard.templateId = null;
        _tplOoCloseGuard.fileSaved = false;
        _tplOoCloseGuard.inFlight = false;
        _tplOoCloseGuard.bannerShown = false;
        _tplOoCloseGuard.lastState = 'idle';
        if (_tplOoCloseGuard.timer) {
            clearInterval(_tplOoCloseGuard.timer);
            _tplOoCloseGuard.timer = null;
        }
        // Cachet le banner d'avertissement
        var banner = document.getElementById('tpl-oo-close-guard-banner');
        if (banner) banner.style.display = 'none';
        // Réactiver le bouton Fermer
        var closeBtn = document.querySelector('#modal-tpl-oo button[onclick="tplOoClose()"]');
        if (closeBtn) closeBtn.disabled = false;
        _tplOoLog('[CloseGuard] Désarmé');
    }

    function tplOoShowCloseGuardBanner() {
        if (_tplOoCloseGuard.bannerShown) return;
        _tplOoCloseGuard.bannerShown = true;
        var banner = document.getElementById('tpl-oo-close-guard-banner');
        if (banner) {
            banner.style.display = 'flex';
            _tplOoLog('[CloseGuard] Banner visible');
        }
    }

    function tplOoCheckSavedState() {
        if (!_tplOoCloseGuard.active || !_tplOoCloseGuard.templateId || _tplOoCloseGuard.inFlight || _tplOoCloseGuard.fileSaved) {
            return;
        }
        _tplOoCloseGuard.inFlight = true;

        var csrf = document.querySelector('meta[name="csrf-token"]');
        var saveStatePath = _adminTplBase + '/' + _tplOoCloseGuard.templateId + '/save-state';
        var saveStateCandidates = [
          saveStatePath,
          window.location.origin + saveStatePath,
          (_adminBaseServer || '') + '/templates/' + _tplOoCloseGuard.templateId + '/save-state'
        ].filter(Boolean);

        function tryFetchSavedState(idx) {
          if (idx >= saveStateCandidates.length) {
            throw new Error('All save-state URLs failed: ' + saveStateCandidates.join(' | '));
          }
          var candidateUrl = saveStateCandidates[idx];
          return fetch(candidateUrl, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf ? csrf.content : ''
            },
            credentials: 'same-origin'
          }).then(function(r) {
            // If a candidate returns 404/500, try the next one.
            if (!r.ok) {
              _tplOoLog('[CloseGuard] save-state non OK', r.status, candidateUrl);
              return tryFetchSavedState(idx + 1);
            }
            return r;
          }).catch(function() {
            return tryFetchSavedState(idx + 1);
          });
        }

        tryFetchSavedState(0).then(function(r) {
          return r.json();
        })
        .then(function(data) {
            if (data && data.file_saved) {
              if (_tplOoCloseGuard.lastState !== 'saved') {
                    _tplOoLog('[CloseGuard] Fichier sauvegardé détecté!');
              }
              _tplOoCloseGuard.lastState = 'saved';
                _tplOoCloseGuard.fileSaved = true;
                tplOoShowStatus('✅ Sauvegarde du modèle confirmée. Vous pouvez maintenant fermer.', 3000);
                // Réactiver le bouton Fermer quand sauvegardé
                var closeBtn = document.querySelector('#modal-tpl-oo button[onclick="tplOoClose()"]');
                if (closeBtn) closeBtn.disabled = false;
                // Cacher le banner
                var banner = document.getElementById('tpl-oo-close-guard-banner');
                if (banner) banner.style.display = 'none';
            } else {
              if (_tplOoCloseGuard.lastState !== 'unsaved') {
                    _tplOoLog('[CloseGuard] Pas encore sauvegardé (mode informatif)');
                tplOoShowStatus('Synchronisation en cours côté serveur… vous pouvez continuer.', 2500);
                _tplOoCloseGuard.lastState = 'unsaved';
              }
            }
        })
        .catch(function(err) {
          _tplOoLog('[CloseGuard] Erreur vérification URLs=' + saveStateCandidates.join(' | ') + ':', err);
        })
        .finally(function() {
            _tplOoCloseGuard.inFlight = false;
        });
    }

    function tplOoArmCloseGuard(tplId) {
        tplOoDisarmCloseGuard();
        _tplOoCloseGuard.active = true;
        _tplOoCloseGuard.templateId = tplId || null;
        _tplOoCloseGuard.fileSaved = false;
      window._ooCurrentTemplateId = tplId || null;
        _tplOoLog('[CloseGuard] Armé pour template:', tplId);

        tplOoCheckSavedState();
        _tplOoCloseGuard.timer = setInterval(tplOoCheckSavedState, 5000);
    }

    function tplOoCanCloseModal() {
        if (!_tplOoCloseGuard.active) {
            _tplOoLog('[CloseGuard] Pas actif, autoriser fermeture');
            return true;
        }
        if (_tplOoCloseGuard.fileSaved) {
            _tplOoLog('[CloseGuard] Fichier sauvegardé, autoriser fermeture');
            return true;
        }
      // Important: ne pas bloquer la fermeture. Le callback OO status=2 part souvent à la fermeture.
      // Si on bloque ici, on peut empêcher la sauvegarde serveur et donc la détection de variables.
      _tplOoLog('[CloseGuard] Fermeture autorisée même sans confirmation immédiate serveur');
      tplOoShowStatus('Fermeture autorisée. Synchronisation en cours côté serveur…', 4000);
      return true;
    }
    window.tplOoCanCloseModal = tplOoCanCloseModal;

    // --- Filtrer par administration -------------------------------------------
    // --- Afficher/Masquer le formulaire de cr�ation de template --------------
    function tplToggleCreateForm() {
        var panel = document.getElementById('tpl-create-panel');
        var btn   = document.getElementById('tpl-create-toggle-btn');
        var label = document.getElementById('tpl-create-toggle-label');
        if (panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            btn.innerHTML = '<i class="fas fa-times text-xs"></i> Fermer';
            btn.classList.replace('bg-blue-600', 'bg-gray-500');
            btn.classList.replace('hover:bg-blue-700', 'hover:bg-gray-600');
            label.textContent = '';
        } else {
            panel.classList.add('hidden');
            btn.innerHTML = '<i class="fas fa-plus-circle text-xs"></i> Nouveau template';
            btn.classList.replace('bg-gray-500', 'bg-blue-600');
            btn.classList.replace('hover:bg-gray-600', 'hover:bg-blue-700');
            label.textContent = 'Cliquez pour cr�er un template';
        }
    }
    window.tplToggleCreateForm = tplToggleCreateForm;

    function tplNavigate(url) {
      if (window.top && window.top !== window) {
        window.top.location.href = url;
        return;
      }
      window.location.href = url;
    }
    window.tplNavigate = tplNavigate;

    function tplFilterEmitter(val) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', 'templates');
        if (val) url.searchParams.set('filter_emitter', val);
        else url.searchParams.delete('filter_emitter');
        url.searchParams.delete('selected_template');
        url.searchParams.delete('tpl_action');
      tplNavigate(url.toString());
    }
    window.tplFilterEmitter = tplFilterEmitter;

    // --- S�lectionner un template ---------------------------------------------
    function tplSelect(id) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', 'templates');
        url.searchParams.set('selected_template', id);
        url.searchParams.delete('tpl_action');
        url.hash = 'tpl-variables-panel';
        tplNavigate(url.toString());
    }
    window.tplSelect = tplSelect;

    // --- Ouvrir l'éditeur OnlyOffice directement -----------------------------
    function tplOpenOnlyOffice() {
      tplOoDisarmCloseGuard();
      // Ouvre le modal d'édition OnlyOffice
        openModal('modal-tpl-oo');

        // Si un template est d�j� s�lectionn�, le charger automatiquement
        var tplId = @json($selectedTplId ?? null) || window._ooCurrentTemplateId || null;
      if (!tplId) {
        tplOoShowStatus('Sélectionnez d\'abord un template dans la liste, ou créez-en un nouveau.', 7000);
        return;
      }
      if (!_appPublicUrl) {
        tplOoShowStatus('URL publique non configurée. Vérifiez les paramètres OnlyOffice.', 7000);
        return;
      }
      if (tplId && _appPublicUrl) {
            tplOoShowStatus('Chargement en cours\u2026', 0);
            var csrf = document.querySelector('meta[name="csrf-token"]');
            fetch(_adminTplBase + '/' + tplId + '/oo-config', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '' }
            })
            .then(function(r) { return r.json(); })
            .then(function(cfg) {
              if (cfg.error) { tplOoShowStatus('Erreur : ' + cfg.error, 5000); return; }
              if (cfg.warning) { tplOoShowStatus('Avertissement réseau : ' + cfg.warning, 8000); }
              tplOoLoadEditor(cfg);
            })
            .catch(function(err) { tplOoShowStatus('Erreur r�seau : ' + err.message, 5000); });
        }
    }
    window.tplOpenOnlyOffice = tplOpenOnlyOffice;

    // Ouvre directement le panneau d'upload pour le nouveau flux IA.
    function tplOpenUploadFlow() {
      tplOoDisarmCloseGuard();
      openModal('modal-tpl-oo');
      tplOoOpenUpload();
      tplOoShowStatus('Importez un fichier Word. Les variables seront détectées et le formulaire sera généré automatiquement.', 7000);
    }
    window.tplOpenUploadFlow = tplOpenUploadFlow;


    // --- Ouvrir le panneau d'import de fichier --------------------------------
    function tplOoOpenUpload(targetTemplateId) {
        var uploadPanel = document.getElementById('tpl-oo-upload-panel');
        var createPanel = document.getElementById('tpl-oo-create-panel');
      _tplOoUploadTargetId = targetTemplateId || null;
        if (uploadPanel) uploadPanel.classList.remove('hidden');
        if (createPanel) createPanel.classList.add('hidden');
        tplOoShowStatus('S�lectionnez un fichier DOCX, XLSX, PPTX ou PDF � importer comme mod�le.', 0);
    }
    window.tplOoOpenUpload = tplOoOpenUpload;

    // --- Stocker le fichier s�lectionn� (utilis� par tplOoSubmitUpload) -------
    window._ooSelectedFile = null;

    function tplOoHandleFileSelect(input) {
        if (!input.files || !input.files.length) return;
        window._ooSelectedFile = input.files[0];
        var info = document.getElementById('tpl-oo-upload-info');
        var fname = document.getElementById('tpl-oo-upload-fname');
        var fsize = document.getElementById('tpl-oo-upload-fsize');
        if (fname) fname.textContent = input.files[0].name;
        if (fsize) {
            var sz = input.files[0].size;
            fsize.textContent = sz < 1024*1024 ? (Math.round(sz/1024)) + ' Ko' : (Math.round(sz/1024/1024*10)/10) + ' Mo';
        }
        if (info) info.classList.remove('hidden');
        // Pr�remplir le champ nom si vide
        var nameInput = document.getElementById('tpl-oo-up-name');
        if (nameInput && !nameInput.value.trim()) {
            nameInput.value = input.files[0].name.replace(/\.[^/.]+$/, '');
        }
    }
    window.tplOoHandleFileSelect = tplOoHandleFileSelect;

    function tplOoHandleFileDrop(event) {
        var files = event.dataTransfer.files;
        if (!files || !files.length) return;
        var fakeInput = document.getElementById('tpl-oo-file-input');
        // Cr�er un DataTransfer pour affecter les fichiers � l'input
        try {
            var dt = new DataTransfer();
            dt.items.add(files[0]);
            fakeInput.files = dt.files;
        } catch(e) {}
        tplOoHandleFileSelect({ files: files });
    }
    window.tplOoHandleFileDrop = tplOoHandleFileDrop;

    function tplOoClearUpload() {
        window._ooSelectedFile = null;
        var fileInput = document.getElementById('tpl-oo-file-input');
        if (fileInput) fileInput.value = '';
        var info = document.getElementById('tpl-oo-upload-info');
        if (info) info.classList.add('hidden');
    }
    window.tplOoClearUpload = tplOoClearUpload;

    function tplOoCloseUploadPanel() {
        var panel = document.getElementById('tpl-oo-upload-panel');
        if (panel) panel.classList.add('hidden');
        tplOoClearUpload();
    }
    window.tplOoCloseUploadPanel = tplOoCloseUploadPanel;

    // --- Soumettre le formulaire d'upload -------------------------------------
    function tplOoSubmitUpload(e) {
        e.preventDefault();
        tplOoDisarmCloseGuard();
        var form = document.getElementById('tpl-oo-upload-form');
        if (!form) return;

        // Validation nom
        var upName = document.getElementById('tpl-oo-up-name');
        var upNameErr = document.getElementById('tpl-oo-up-name-err');
        if (!upName || !upName.value.trim()) {
            if (upName) upName.classList.add('border-red-400', 'ring-2', 'ring-red-300');
            if (upNameErr) upNameErr.classList.remove('hidden');
            tplOoShowStatus('Veuillez saisir un nom pour le modèle.', 4000);
            return;
        }
        if (upName) upName.classList.remove('border-red-400', 'ring-2', 'ring-red-300');
        if (upNameErr) upNameErr.classList.add('hidden');

        var fileInput = document.getElementById('tpl-oo-file-input');
        if (!fileInput || !fileInput.files.length) {
            tplOoShowStatus('Veuillez sélectionner un fichier.', 4000);
            return;
        }
        var allowed = ['pdf', 'docx', 'xlsx', 'pptx'];
        var ext = fileInput.files[0].name.split('.').pop().toLowerCase();
        if (allowed.indexOf(ext) === -1) {
          tplOoShowStatus('Format non supporté. Utilisez un fichier PDF, DOCX, XLSX ou PPTX.', 5000);
            return;
        }
        var submitBtn = form.querySelector('button[type="submit"]');
        var progressDiv = document.getElementById('tpl-oo-upload-progress');
        if (submitBtn) submitBtn.disabled = true;
        if (progressDiv) progressDiv.classList.remove('hidden');
        tplOoShowStatus('Envoi du fichier…', 0);

        var formData = new FormData(form);
        formData.set('name', upName.value.trim());
        // Evite d'envoyer deux fois le même champ fichier (append + form initial)
        formData.set('file', fileInput.files[0]);
        var upAdmin = document.getElementById('tpl-oo-up-admin');
        if (upAdmin && upAdmin.value) formData.set('administration_id', upAdmin.value);
        if (_tplOoUploadTargetId) formData.set('template_id', _tplOoUploadTargetId);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', _adminTplUploadUrl || (_adminTplBase + '/upload-file'));
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onload = function() {
            if (submitBtn) submitBtn.disabled = false;
            if (progressDiv) progressDiv.classList.add('hidden');

          var payload = null;
          try {
            payload = xhr.responseText ? JSON.parse(xhr.responseText) : null;
          } catch (ex) {
            payload = null;
          }

          if (xhr.status < 200 || xhr.status >= 300) {
            var errMsg = 'Erreur serveur (' + xhr.status + ').';
            if (payload && payload.message) {
              errMsg = payload.message;
            } else if (xhr.status === 413) {
              errMsg = 'Fichier trop volumineux pour le serveur (limite PHP/Nginx/Apache).';
            } else if (xhr.status === 419) {
              errMsg = 'Session expirée. Rechargez la page puis réessayez.';
            } else if (xhr.status === 422) {
              errMsg = 'Données invalides. Vérifiez le nom du modèle et le type de fichier.';
            }
            tplOoShowStatus(errMsg, 7000);
            return;
          }

          if (!payload) {
            tplOoShowStatus('Réponse serveur invalide après upload.', 7000);
            return;
          }

          var cfg = payload;
            if (cfg && cfg.success === false) {
              var backendMsg = cfg.message || 'Upload echoue.';
              tplOoShowStatus(backendMsg, 7000);
              return;
            }
            var upPanel = document.getElementById('tpl-oo-upload-panel');
            if (upPanel) upPanel.classList.add('hidden');
            _tplOoUploadTargetId = null;

            var tplId = cfg.template_id || null;
            if (!tplId) {
              tplOoShowStatus('Template importé mais identifiant introuvable.', 6000);
              return;
            }

            var detectedCount = Number(cfg.variables_count || 0);
            var fieldsCount = Number(cfg.form_fields_count || 0);
            var aiInfo = cfg.ai || {};
            var aiSuffix = '';
            if (aiInfo && aiInfo.applied) {
              aiSuffix = aiInfo.source === 'ollama'
                ? ' IA Ollama appliquée.'
                : ' IA indisponible, heuristique appliquée.';
            }

            if (detectedCount > 0) {
              tplOoShowStatus(
                detectedCount + ' variable(s) détectée(s), ' + fieldsCount + ' champ(s) formulaire généré(s).' + aiSuffix,
                4500
              );
            } else {
              tplOoShowStatus('Aucune variable détectée dans le modèle importé.', 6000);
            }

            // Nouveau paradigme: après upload + analyse IA, on revient sur le sous-onglet
            // templates avec le template sélectionné pour afficher directement le formulaire.
            setTimeout(function() {
              tplNavigate(window.location.pathname + '?tab=templates&selected_template=' + encodeURIComponent(String(tplId)) + '&ia_generated=1');
            }, 650);
        };

        xhr.onerror = function() {
            if (submitBtn) submitBtn.disabled = false;
            if (progressDiv) progressDiv.classList.add('hidden');
            tplOoShowStatus('Erreur réseau lors de l\'upload.', 5000);
        };

        xhr.send(formData);
    }
    window.tplOoSubmitUpload = tplOoSubmitUpload;

    // --- D�tection automatique des variables depuis le fichier DOCX -----------
    function tplDetectVars(tplId, tplName) {
        var btn = document.getElementById('detect-btn-' + tplId);
        var lbl = document.getElementById('detect-label-' + tplId);
        if (btn) btn.disabled = true;
        if (lbl) lbl.textContent = '...';
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch(_adminTplBase + '/' + tplId + '/detect-vars', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) btn.disabled = false;
            if (lbl) lbl.textContent = data.count > 0 ? data.count + ' vars' : 'D�tecter';
            if (data.success && data.count > 0) {
                var varNames = data.variables.slice(0, 5).map(function(v) { return '[' + v.key + ']'; }).join(', ');
                if (data.count > 5) varNames += ' + ' + (data.count - 5) + ' autres';
                if (confirm(data.message + '\n\n' + varNames + '\n\nRecharger pour voir les balises dynamiques ?')) {
                  tplNavigate(window.location.pathname + '?tab=templates&selected_template=' + tplId);
                }
            } else {
                alert(data.message || 'Aucune variable trouv�e. Utilisez des balises [variable] (ou @{{variable}}).');
            }
        })
        .catch(function(err) {
            if (btn) btn.disabled = false;
            if (lbl) lbl.textContent = 'D�tecter';
            alert('Erreur r�seau: ' + err.message);
        });
    }
    window.tplDetectVars = tplDetectVars;

    /**
     * Récupération d'urgence des variables depuis un fichier déjà stocké
     * (si le template a 0 variables mais un fichier physique existe)
     */
    function tplRecoverVars(tplId, tplName) {
        if (!confirm('Réextrait les variables du fichier "' + tplName + '" (qui a déjà été enregistré).\n\nContinuer ?')) {
            return;
        }
        var btn = document.getElementById('recover-btn-' + tplId);
        if (btn) btn.disabled = true;

        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch(_adminTplBase + '/' + tplId + '/recover-vars', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) btn.disabled = false;
            if (data.success) {
                alert('✅ Récupération réussie !\n\n' + data.message + '\n\nLa page va se recharger pour afficher les variables.');
                tplNavigate(window.location.pathname + '?tab=templates&selected_template=' + tplId);
            } else {
                alert('❌ Erreur: ' + (data.message || 'Impossible de récupérer les variables.'));
                if (btn) btn.classList.remove('animate-pulse');
            }
        })
        .catch(function(err) {
            if (btn) btn.disabled = false;
            alert('❌ Erreur réseau: ' + err.message);
        });
    }
    window.tplRecoverVars = tplRecoverVars;

    // --- Enrichissement IA (Ollama) des variables d'un template -------------
    function tplAiEnrich(tplId, tplName) {
      var btn  = document.getElementById('ai-enrich-btn-' + tplId);
      var lbl  = document.getElementById('ai-enrich-label-' + tplId);
      var csrf = document.querySelector('meta[name="csrf-token"]');

      if (!confirm('Analyser les variables de "' + tplName + '" avec l\'IA (Ollama) pour enrichir les libellés et types ?\n\nSi Ollama est indisponible, un enrichissement heuristique sera appliqué.')) {
        return;
      }

      if (btn) btn.disabled = true;
      if (lbl) lbl.textContent = '⏳ ...';

      function runAiEnrichRequest() {
        fetch(_adminTplBase + '/' + tplId + '/ai-enrich', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.content : '',
            'Accept': 'application/json'
          }
        })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (btn) btn.disabled = false;
        if (lbl) lbl.textContent = '✨ IA';

        if (data && data.success) {
          var detail = '\n\nVariables enrichies :';
          (data.variables || []).slice(0, 6).forEach(function(v) {
            detail += '\n• [' + v.key + '] -> ' + v.label + ' (' + v.field_type + (v.required ? ', requis' : '') + ')';
          });
          if ((data.variables || []).length > 6) {
            detail += '\n  + ' + (data.variables.length - 6) + ' autres...';
          }
          alert((data.source === 'fallback' ? '⚠️ ' : '✅ ') + (data.message || 'Enrichissement terminé.') + detail + '\n\nLa page va se recharger pour afficher les changements.');
          tplNavigate(window.location.pathname + '?tab=templates&selected_template=' + tplId);
        } else {
          alert('❌ ' + ((data && data.message) ? data.message : 'Erreur lors de l\'enrichissement IA.'));
        }
      })
      .catch(function(err) {
        if (btn) btn.disabled = false;
        if (lbl) lbl.textContent = '✨ IA';
        alert('❌ Erreur réseau : ' + err.message);
      });

      }

      // Si le template est encore la session OO courante, déclencher un force-save
      // avant enrichissement pour éviter d'analyser une ancienne version du fichier.
      var currentTplId = String(window._ooCurrentTemplateId || '');
      var currentDocKey = String(window._ooCurrentDocKey || '');
      if (currentTplId === String(tplId) && currentDocKey) {
        fetch(_adminTplBase + '/' + tplId + '/force-save', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.content : '',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ doc_key: currentDocKey })
        })
        .finally(function() {
          setTimeout(runAiEnrichRequest, 1200);
        });
        return;
      }

      runAiEnrichRequest();
    }
    window.tplAiEnrich = tplAiEnrich;

    // --- Synchronisation automatique des variables après sauvegarde OO -------
    var _tplAutoSyncState = {
      inFlight: false,
      lastRunAt: 0,
      debounceMs: 2500,
      timer: null,
      templateId: null
    };

    function tplAutoSyncVars(tplId) {
      if (!tplId) return;

      var now = Date.now();
      if (_tplAutoSyncState.inFlight) return;
      if (_tplAutoSyncState.templateId === tplId && (now - _tplAutoSyncState.lastRunAt) < _tplAutoSyncState.debounceMs) return;

      _tplAutoSyncState.inFlight = true;
      _tplAutoSyncState.lastRunAt = now;
      _tplAutoSyncState.templateId = tplId;

      var csrf = document.querySelector('meta[name="csrf-token"]');
      fetch(_adminTplBase + '/' + tplId + '/detect-vars', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf ? csrf.content : '',
          'Accept': 'application/json'
        }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var lbl = document.getElementById('detect-label-' + tplId);
        if (lbl && data && typeof data.count === 'number') {
          lbl.textContent = data.count > 0 ? data.count + ' vars' : 'D�tecter';
        }

        if (data && data.success && typeof data.count === 'number') {
          if (data.count > 0) {
            tplOoShowStatus('Variables synchronis�es automatiquement (' + data.count + ').', 3500);
          } else {
            tplOoShowStatus('Aucune variable détectée après sauvegarde.', 3500);
          }
        }
      })
      .catch(function() {
        // Silencieux pour ne pas polluer l'UX de l'éditeur.
      })
      .finally(function() {
        _tplAutoSyncState.inFlight = false;
      });
    }
    window.tplAutoSyncVars = tplAutoSyncVars;

    // Charger l'�diteur OO avec une config donn�e (fichier upload� ou nouveau)
    function tplOoLoadEditor(cfg) {
        var fileType = (cfg.fileType || '').toLowerCase();
        // Les PDF restent en prévisualisation native (OnlyOffice PDF édite mal selon la config serveur).
        if (fileType === 'pdf') {
            tplOoLoadPdfViewer(cfg);
            return;
        }

        var ooBaseRaw = cfg.ooUrl || _ooUrl || '';
        var ooBase = ooBaseRaw.replace(/\/$/, '');
        if (!ooBase) {
            tplOoShowStatus('Serveur OnlyOffice non configuré.', 7000);
            return;
        }

      var apiUrl = ooBase + '/web-apps/apps/api/documents/api.js';
        // Reinitialiser les zones
        _tplOoZones.forEach(function(z) { if (z && z.el) z.el.remove(); });
        _tplOoZones = [];
        tplOoUpdateBadge();

        var placeholder = document.getElementById('oo-editor-placeholder');
        if (placeholder) placeholder.innerHTML = '';

        tplOoShowStatus('Chargement de OnlyOffice...', 0);

        // Detruire l'instance precedente avant de reinitialiser
        if (window._ooEditorInstance) {
            try { window._ooEditorInstance.destroyEditor(); } catch(e) {}
            window._ooEditorInstance = null;
        }

        function _launchEditor() {
            if (!window.DocsAPI) {
                tplOoShowStatus('Erreur : API OnlyOffice introuvable.', 5000);
                return;
            }
            try {
                var ooConfig = cfg.ooConfig || {};
                if (cfg.token) { ooConfig.token = cfg.token; }
            var currentTplId = cfg.template_id || window._ooCurrentTemplateId || null;
            var currentDocKey = '';
            if (ooConfig && ooConfig.document && ooConfig.document.key) {
              currentDocKey = String(ooConfig.document.key);
            } else if (cfg.docKey) {
              currentDocKey = String(cfg.docKey);
            }

            // Synchroniser strictement l'éditeur avec le template courant du guard.
            window._ooCurrentTemplateId = currentTplId;
            window._ooCurrentDocKey = currentDocKey;
            if (_tplOoCloseGuard.active && _tplOoCloseGuard.templateId !== currentTplId) {
              _tplOoCloseGuard.templateId = currentTplId;
              _tplOoCloseGuard.fileSaved = false;
              console.log('[CloseGuard] Resync templateId ->', currentTplId);
            }

            var prevEvents = ooConfig.events || {};
            var previousOnDocumentStateChange = prevEvents.onDocumentStateChange;
            ooConfig.events = Object.assign({}, prevEvents, {
              onDocumentStateChange: function(event) {
                if (typeof previousOnDocumentStateChange === 'function') {
                  try { previousOnDocumentStateChange(event); } catch (e) {}
                }

                // event.data === false => document revenu "non modifié" (save terminé côté client)
                if (event && event.data === false && currentTplId) {
                  if (_tplAutoSyncState.timer) {
                    clearTimeout(_tplAutoSyncState.timer);
                  }
                  _tplAutoSyncState.timer = setTimeout(function() {
                    tplAutoSyncVars(currentTplId);
                    tplOoCheckSavedState();
                  }, 1200);
                }
              }
            });

                ooConfig.height = '100%';
                ooConfig.width  = '100%';
                ooConfig.type   = 'desktop';
                window._ooEditorInstance = new DocsAPI.DocEditor('oo-editor-placeholder', ooConfig);
                window._ooCurrentTemplateId = cfg.template_id || null;
                tplOoShowStatus('Fichier ouvert dans OnlyOffice. L\'auto-sauvegarde est active; vous pouvez aussi cliquer sur "Enregistrer le modèle".', 6000);
            } catch(err) {
                tplOoShowStatus('Erreur initialisation OnlyOffice : ' + err.message, 6000);
            }
        }

        var oldScript = document.getElementById('oo-api-script');

        // DocsAPI peut rester en mémoire alors que le script a été retiré du DOM.
        // Dans ce cas, il faut recharger api.js pour que getBasePath() pointe encore vers onlyoffice.ci.
        if (window.DocsAPI && oldScript && oldScript.src === apiUrl) {
            _launchEditor();
            return;
        }

        if (oldScript) oldScript.remove();

        var script = document.createElement('script');
        script.id  = 'oo-api-script';
        script.src = apiUrl;
        script.onload  = _launchEditor;
        script.onerror = function() {
            tplOoShowStatus('Impossible de joindre le serveur OnlyOffice.', 7000);
        };
        document.head.appendChild(script);
    }
    window.tplOoLoadEditor = tplOoLoadEditor;

    // --- Bouton Modifier : ouvre le template dans l'éditeur OnlyOffice ------
    function tplOoEdit(tplId, tplName) {
        if (!_appPublicUrl) {
            alert('URL publique non configurée. Vérifiez les paramètres.');
            return;
        }
        // Ouvrir le modal d'abord
        openModal('modal-tpl-oo');
        tplOoShowStatus('Chargement de "' + (tplName || 'template') + '" dans l\'éditeur OnlyOffice…', 0);
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch(_adminTplBase + '/' + tplId + '/oo-config', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '' }
        })
        .then(function(r) { return r.json(); })
        .then(function(cfg) {
            if (cfg.error) { tplOoShowStatus('Erreur : ' + cfg.error, 6000); return; }
            if (cfg.warning) { tplOoShowStatus('Avertissement réseau : ' + cfg.warning, 8000); }
            // Armer le close-guard si le template n'a pas encore de fichier (storage_path vide)
            if (!cfg.has_file) {
                tplOoArmCloseGuard(tplId);
            } else {
                tplOoDisarmCloseGuard();
            }
            tplOoLoadEditor(cfg);
        })
        .catch(function(err) { tplOoShowStatus('Erreur réseau : ' + err.message, 5000); });
    }
    window.tplOoEdit = tplOoEdit;
    // --- Viewer PDF natif (remplace OnlyOffice pour les PDF) -----------------
    // --- Viewer PDF natif (remplace OnlyOffice pour les PDF) -----------------
    function tplOoLoadPdfViewer(cfg) {
        // Reinitialiser les zones
        _tplOoZones.forEach(function(z) { if (z && z.el) z.el.remove(); });
        _tplOoZones = [];
        tplOoUpdateBadge();

        // Construire l'URL PDF locale (�vite l'interstitiel ngrok)
        // On utilise localDocUrl si disponible, sinon on reconstruit depuis storagePubPath
        var pdfUrl = cfg.localDocUrl || cfg.docUrl;
        if (!pdfUrl && cfg.storagePubPath) {
            pdfUrl = window.location.protocol + '//' + window.location.host + cfg.storagePubPath;
        }
        var dlUrl = cfg.docUrl || pdfUrl; // t�l�chargement via URL publique

        var placeholder = document.getElementById('oo-editor-placeholder');
        if (placeholder) {
            placeholder.innerHTML =
                '<div style="width:100%;height:100%;display:flex;flex-direction:column;background:#525659;">' +
                '<div style="flex-shrink:0;padding:8px 12px;background:#3d4043;display:flex;align-items:center;gap:10px;">' +
                '<i class="fas fa-file-pdf" style="color:#ef4444;"></i>' +
                '<span style="color:#fff;font-size:12px;font-weight:600;">' + (cfg.template_name || 'Document PDF') + '</span>' +
                '<span style="color:#aaa;font-size:11px;margin-left:4px;">� Apercu PDF</span>' +
                '<a href="' + dlUrl + '" target="_blank" style="margin-left:auto;background:#2453d6;color:#fff;font-size:11px;font-weight:600;padding:4px 12px;border-radius:6px;text-decoration:none;"><i class="fas fa-download" style="margin-right:4px;"></i>Telecharger</a>' +
                '</div>' +
                '<iframe src="' + pdfUrl + '" style="flex:1;width:100%;border:none;" allowfullscreen></iframe>' +
                '</div>';
        }
        window._ooCurrentTemplateId = cfg.template_id || null;
        tplOoShowStatus('PDF pret. Positionnez une zone de signature puis cliquez sur "Sceller les zones".', 5000);
    }
    window.tplOoLoadPdfViewer = tplOoLoadPdfViewer;

    // --- Ouvrir un template DOCX/XLSX/PPTX dans l'éditeur OnlyOffice --------
    function tplOoLoadDocxEditor(cfg) {
        var placeholder = document.getElementById('oo-editor-placeholder');
        if (!placeholder) return;
        window._ooCurrentTemplateId = cfg.template_id || null;
        var ooUrl = (cfg.ooUrl || '').replace(/\/$/, '');
        if (!ooUrl) { tplOoShowStatus('Serveur OnlyOffice non configuré.', 7000); return; }
        var sdkUrl = ooUrl + '/web-apps/apps/api/documents/api.js';
        var ooConfig = cfg.ooConfig || {};
        if (cfg.token) { ooConfig.token = cfg.token; }
        ooConfig.width  = '100%';
        ooConfig.height = '100%';
        ooConfig.events = {
            'onDocumentReady': function() { tplOoShowStatus('Éditeur prêt. Rédigez votre template avec @{{ variable }} puis sauvegardez.', 6000); },
            'onError': function(e) { tplOoShowStatus('Erreur éditeur : ' + (e && e.data ? e.data.description : ''), 8000); }
        };
        placeholder.innerHTML = '<div id="oo-docx-editor" style="width:100%;height:100%;"></div>';
        // Charger le SDK OO dynamiquement (évite les conflits si déjà chargé)
        if (typeof DocsAPI !== 'undefined') {
            try { new DocsAPI.DocEditor('oo-docx-editor', ooConfig); } catch(ex) { tplOoShowStatus('Erreur initialisation éditeur : ' + ex.message, 8000); }
            return;
        }
        var script = document.createElement('script');
        script.src = sdkUrl;
        script.onload = function() {
            if (typeof DocsAPI !== 'undefined') {
                try { new DocsAPI.DocEditor('oo-docx-editor', ooConfig); }
                catch(ex) { tplOoShowStatus('Erreur initialisation éditeur : ' + ex.message, 8000); }
            } else {
                tplOoShowStatus('SDK OnlyOffice non chargé depuis : ' + sdkUrl, 8000);
            }
        };
        script.onerror = function() { tplOoShowStatus('Impossible de joindre le serveur OnlyOffice : ' + ooUrl, 8000); };
        document.head.appendChild(script);
    }
    window.tplOoLoadDocxEditor = tplOoLoadDocxEditor;


    // ─── Variables état zones ─────────────────────────────────────────────────
    // ─── Variables état zones ─────────────────────────────────────────────────
    var _tplOoZones = [];      // [{x, y, w, h, el}]
    var _tplOoDragState = null; // {zoneIdx, startX, startY, origLeft, origTop}

    // ─── Afficher un message de statut ───────────────────────────────────────
    function tplOoShowStatus(msg, duration) {
        var bar = document.getElementById('tpl-oo-status');
        var txt = document.getElementById('tpl-oo-status-text');
        if (!bar || !txt) return;
        txt.textContent = msg;
        bar.classList.remove('hidden');
        if (duration) {
            clearTimeout(bar._timer);
            bar._timer = setTimeout(function() { bar.classList.add('hidden'); }, duration);
        }
    }
    window.tplOoShowStatus = tplOoShowStatus;

    // ─── Masquer barre statut ─────────────────────────────────────────────────
    function tplOoHideOverlay() { /* kept for compat */ }

    // ─── Mettre à jour le badge de comptage ──────────────────────────────────
    function tplOoUpdateBadge() {
        var bar = document.getElementById('tpl-oo-zones-bar');
        var list = document.getElementById('tpl-oo-zones-list');
        if (!list) return;
        list.innerHTML = '';
        if (_tplOoZones.length === 0) { if (bar) bar.classList.add('hidden'); return; }
        if (bar) bar.classList.remove('hidden');
        _tplOoZones.forEach(function(z, i) {
            var badge = document.createElement('span');
            badge.className = 'inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full';
            badge.textContent = 'Signature ' + (i + 1);
            list.appendChild(badge);
        });
    }

    // ─── Créer une boîte de zone glissable ───────────────────────────────────
    // --- Cr�er une bo�te de zone glissable -----------------------------------
    function tplOoCreateDraggableZone(idx) {
        var container = document.getElementById('oo-iframe-container');
        if (!container) return;

        var box = document.createElement('div');
        var n = idx + 1;
        var initLeft = 10 + (idx % 3) * 5;
        var initTop  = 15 + (idx % 2) * 5;
        var initW    = 22;
        var initH    = 18;

        box.id = 'tpl-zone-box-' + idx;
        box.style.cssText = [
            'position:absolute',
            'left:' + initLeft + '%',
            'top:' + initTop + '%',
            'width:' + initW + '%',
            'height:' + initH + '%',
            'border:2.5px dashed #2563eb',
            'background:rgba(37,99,235,0.10)',
            'border-radius:6px',
            'z-index:20',
            'cursor:move',
            'user-select:none',
            'box-sizing:border-box',
            'display:flex',
            'flex-direction:column',
            'align-items:center',
            'justify-content:center',
            'gap:6px'
        ].join(';');

        box.innerHTML = [
            '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;pointer-events:none;width:100%;">',
            '  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">',
            '    <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
            '  </svg>',
            '  <span class="tpl-zone-label" style="font-size:12px;font-weight:700;letter-spacing:.5px;color:#1d4ed8;">SIGNATURE ' + n + '</span>',
            '  <span class="tpl-zone-hint" style="font-size:10px;color:#3b82f6;">Cliquez en dehors pour fixer</span>',
            '</div>',
            '<div id="tpl-zone-resize-' + idx + '" style="position:absolute;bottom:4px;right:4px;width:14px;height:14px;background:#2563eb;border-radius:2px;cursor:se-resize;z-index:21;"></div>',
            '<button onclick="tplOoRemoveZone(' + idx + ')" style="position:absolute;top:3px;right:5px;background:none;border:none;cursor:pointer;color:#2563eb;font-size:15px;line-height:1;padding:0;" title="Supprimer">&#x2715;</button>'
        ].join('');

        container.appendChild(box);
        _tplOoZones[idx] = { x: initLeft, y: initTop, w: initW, h: initH, el: box, sealed: false };

        // -- Drag --------------------------------------------------------------
        box.addEventListener('mousedown', function(e) {
            if (box._sealed) return;
            if (e.target.id && e.target.id.indexOf('tpl-zone-resize-') === 0) return;
            if (e.target.tagName === 'BUTTON') return;
            e.preventDefault(); e.stopPropagation();
            var rect = container.getBoundingClientRect();
            var startX = e.clientX, startY = e.clientY;
            var origLeft = parseFloat(box.style.left);
            var origTop  = parseFloat(box.style.top);
            var moved = false;

            function onMove(ev) {
                moved = true;
                var dx = ((ev.clientX - startX) / rect.width) * 100;
                var dy = ((ev.clientY - startY) / rect.height) * 100;
                box.style.left = Math.max(0, Math.min(100 - parseFloat(box.style.width),  origLeft + dx)) + '%';
                box.style.top  = Math.max(0, Math.min(100 - parseFloat(box.style.height), origTop  + dy)) + '%';
                _tplOoZones[idx].x = parseFloat(box.style.left);
                _tplOoZones[idx].y = parseFloat(box.style.top);
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup',  onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup',  onUp);
        });

        // -- Resize ------------------------------------------------------------
        var resizeHandle = document.getElementById('tpl-zone-resize-' + idx);
        if (resizeHandle) {
            resizeHandle.addEventListener('mousedown', function(e) {
                if (box._sealed) return;
                e.preventDefault(); e.stopPropagation();
                var rect = container.getBoundingClientRect();
                var startX = e.clientX, startY = e.clientY;
                var origW = parseFloat(box.style.width);
                var origH = parseFloat(box.style.height);

                function onMove(ev) {
                    var newW = Math.max(8, Math.min(80, origW + ((ev.clientX - startX) / rect.width)  * 100));
                    var newH = Math.max(6, Math.min(80, origH + ((ev.clientY - startY) / rect.height) * 100));
                    box.style.width  = newW + '%';
                    box.style.height = newH + '%';
                    _tplOoZones[idx].w = newW;
                    _tplOoZones[idx].h = newH;
                }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup',  onUp);
                }
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup',  onUp);
            });
        }

        // -- Clic en dehors ? verrouille automatiquement la zone ---------------
        function onDocMousedown(e) {
            if (box._sealed) return;
            if (box.contains(e.target)) return; // clic dans la zone ? ignorer
            tplOoSealZone(idx);
        }
        document.addEventListener('mousedown', onDocMousedown);
        box._unsealListener = function() { document.removeEventListener('mousedown', onDocMousedown); };
    }

    // ─── Supprimer une zone par index ─────────────────────────────────────────
    function tplOoRemoveZone(idx) {
        var zone = _tplOoZones[idx];
        if (zone && zone.el) { zone.el.remove(); }
        _tplOoZones.splice(idx, 1);
        // Re-numéroter les zones restantes
        _tplOoZones.forEach(function(z, i) {
            if (!z || !z.el) return;
            z.el.id = 'tpl-zone-box-' + i;
            var spans = z.el.querySelectorAll('span');
            if (spans[0]) spans[0].textContent = 'SIGNATURE ' + (i + 1);
            var btn = z.el.querySelector('button');
            if (btn) btn.setAttribute('onclick', 'tplOoRemoveZone(' + i + ')');
            var rh = z.el.querySelector('[id^="tpl-zone-resize-"]');
            if (rh) rh.id = 'tpl-zone-resize-' + i;
        });
        tplOoUpdateBadge();
        tplOoShowStatus('Zone supprimée. ' + _tplOoZones.length + ' zone(s) restante(s).', 3000);
    }
    window.tplOoRemoveZone = tplOoRemoveZone;

    // --- Sceller une zone de signature (verrouille visuellement la position) --
    // --- Sceller une zone : verrouille d�finitivement drag ET resize ----------
    // --- Sceller : verrouille drag + resize, appliqu� automatiquement au clic hors zone
    function tplOoSealZone(idx) {
        var zone = _tplOoZones[idx];
        if (!zone || !zone.el || zone.el._sealed) return;

        // Verrouillage physique via flag DOM
        zone.el._sealed = true;
        zone.sealed      = true;

        // Style verrouill� : bordure verte pleine, curseur bloqu�
        zone.el.style.border     = '2.5px solid #16a34a';
        zone.el.style.background = 'rgba(22,163,74,0.13)';
        zone.el.style.cursor     = 'default';

        // Cacher le handle de resize
        var rh = document.getElementById('tpl-zone-resize-' + idx);
        if (rh) rh.style.display = 'none';

        // Mise � jour visuelle : label + hint verts
        var label = zone.el.querySelector('.tpl-zone-label');
        if (label) { label.style.color = '#15803d'; }
        var hint = zone.el.querySelector('.tpl-zone-hint');
        if (hint) {
            hint.textContent = '\uD83D\uDD12 Position fix�e � cliquez pour repositionner';
            hint.style.color = '#15803d';
        }

        // Permettre de cliquer sur la zone pour la d�bloquer et repositionner
        zone.el.addEventListener('click', function onReopenClick(e) {
            if (e.target.tagName === 'BUTTON') return; // bouton supprimer ? ignorer
            zone.el.removeEventListener('click', onReopenClick);
            tplOoUnsealZone(idx);
        }, { once: true });

        tplOoShowStatus('Zone "Signature ' + (idx + 1) + '" fix�e. Cliquez dessus pour repositionner, ou "Enregistrer les zones".', 5000);
    }
    window.tplOoSealZone = tplOoSealZone;

    // --- D�bloquer une zone pour la repositionner -----------------------------
    function tplOoUnsealZone(idx) {
        var zone = _tplOoZones[idx];
        if (!zone || !zone.el) return;

        // Remettre en mode libre
        zone.el._sealed = false;
        zone.sealed      = false;

        // Style libre : bordure bleue pointill�e
        zone.el.style.border     = '2.5px dashed #2563eb';
        zone.el.style.background = 'rgba(37,99,235,0.10)';
        zone.el.style.cursor     = 'move';

        // R�afficher le handle resize
        var rh = document.getElementById('tpl-zone-resize-' + idx);
        if (rh) rh.style.display = '';

        // Remettre les textes bleus
        var label = zone.el.querySelector('.tpl-zone-label');
        if (label) label.style.color = '#1d4ed8';
        var hint = zone.el.querySelector('.tpl-zone-hint');
        if (hint) { hint.textContent = 'Cliquez en dehors pour fixer'; hint.style.color = '#3b82f6'; }

        // R�-enregistrer le listener "clic en dehors"
        function onDocMousedown(e) {
            if (zone.el._sealed) return;
            if (zone.el.contains(e.target)) return;
            tplOoSealZone(idx);
        }
        document.addEventListener('mousedown', onDocMousedown);
        zone.el._unsealListener = function() { document.removeEventListener('mousedown', onDocMousedown); };

        tplOoShowStatus('Zone "Signature ' + (idx + 1) + '" d�verrouill�e. Repositionnez-la puis cliquez en dehors.', 4000);
    }
    window.tplOoUnsealZone = tplOoUnsealZone;

    // ─── Bouton : Créer un modèle ─────────────────────────────────────────────
    function tplOoCreateModel() {
      var createPanel = document.getElementById('tpl-oo-create-panel');
      if (createPanel) createPanel.classList.add('hidden');
      tplOoOpenUpload(null);
      tplOoShowStatus('Importez un fichier contenant des variables (ex: @{{ nom_client }}).', 6000);
    }
    window.tplOoCreateModel = tplOoCreateModel;

    // Fermer le panneau
    function tplOoClosCreatePanel() {
        var panel = document.getElementById('tpl-oo-create-panel');
        if (panel) panel.classList.add('hidden');
    }
    window.tplOoClosCreatePanel = tplOoClosCreatePanel;

    // Soumettre la création via AJAX
    function tplOoSubmitCreate(e) {
        e.preventDefault();
        var nameEl    = document.getElementById('tpl-oo-name');
        var fileEl    = document.getElementById('tpl-oo-filename');
        var typeEl    = document.getElementById('tpl-oo-filetype');
        var nameErr   = document.getElementById('tpl-oo-name-err');
        var fileErr   = document.getElementById('tpl-oo-filename-err');
        var typeErr   = document.getElementById('tpl-oo-filetype-err');
        var msg       = document.getElementById('tpl-oo-create-msg');
        var submitBtn = document.getElementById('tpl-oo-submit-btn');
        var valid = true;

        [nameEl, fileEl, typeEl].forEach(function(el) {
            if (el) el.classList.remove('border-red-400','ring-2','ring-red-300');
        });
        [nameErr, fileErr, typeErr].forEach(function(el) {
            if (el) el.classList.add('hidden');
        });

        if (!nameEl || !nameEl.value.trim()) {
            if (nameEl) nameEl.classList.add('border-red-400','ring-2','ring-red-300');
            if (nameErr) nameErr.classList.remove('hidden');
            valid = false;
        }
        if (!fileEl || !fileEl.value.trim()) {
            if (fileEl) fileEl.classList.add('border-red-400','ring-2','ring-red-300');
            if (fileErr) fileErr.classList.remove('hidden');
            valid = false;
        }
        if (!typeEl || !typeEl.value) {
            if (typeEl) typeEl.classList.add('border-red-400','ring-2','ring-red-300');
            if (typeErr) typeErr.classList.remove('hidden');
            valid = false;
        }

        if (!valid) {
            if (msg) { msg.textContent = 'Veuillez remplir tous les champs obligatoires.'; msg.className = 'text-xs font-semibold text-red-600'; }
            return;
        }

        if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Creation...'; }
        if (msg) { msg.textContent = ''; }

        var adminEl  = document.getElementById('tpl-oo-admin');
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var body = new URLSearchParams();
        body.append('_token', csrfMeta ? csrfMeta.content : '');
        body.append('tab', 'templates');
        body.append('name',              nameEl.value.trim());
        body.append('file_name',         fileEl.value.trim());
        body.append('file_type',         typeEl.value);
        body.append('administration_id', adminEl ? adminEl.value : '');

        fetch(window._tplStoreUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
            body: body,
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Creer le modele'; }
            if (!data || !data.success) {
                if (msg) { msg.textContent = data.message || 'Erreur lors de la creation.'; msg.className = 'text-xs font-semibold text-red-600'; }
                return;
            }
            tplOoClosCreatePanel();
            tplOoShowStatus('Modèle créé. Importez maintenant le fichier source du modèle.', 6000);
            tplOoInjectInList(data);
            if (nameEl) nameEl.value = '';
            if (fileEl) fileEl.value = '';
            if (typeEl) typeEl.value = 'docx';

            // Ouvrir le panneau d'import pour remplacer le bootstrap par le vrai fichier
            var tplId = data.id || data.template_id;
            if (tplId) {
              openModal('modal-tpl-oo');
              tplOoOpenUpload(tplId);
            }
        })
        .catch(function(err) {
            tplOoShowStatus('Erreur lors de la création du modèle.', 5000);
            if (msg) { msg.textContent = 'Erreur reseau. Reessayez.'; msg.className = 'text-xs font-semibold text-red-600'; }
        });
    }
    window.tplOoSubmitCreate = tplOoSubmitCreate;

    function tplOoInjectInList(tpl) {
        var listEl = document.getElementById('tpl-list-container');
        if (!listEl) return;
        var emptyMsg = listEl.querySelector('.border-dashed');
        if (emptyMsg) emptyMsg.remove();
        var adminUrl = '{{ route("admin.index") }}';
        var variablesUrl = adminUrl + '?tab=templates&selected_template=' + tpl.id;
        var div = document.createElement('div');
        div.id = 'tpl-row-' + tpl.id;
        div.className = 'border rounded-lg p-3 border-green-400 bg-green-50';
        div.innerHTML =
            '<div class="mb-2">' +
                '<p class="text-xs font-semibold text-gray-800 truncate">' + tpl.name + '</p>' +
                '<p class="text-xs text-gray-500">' + (tpl.file_name || '-') + ' � ' + (tpl.file_type || '').toUpperCase() + '</p>' +
                (tpl.administration ? '<p class="text-xs text-gray-400">' + tpl.administration + '</p>' : '') +
                '<p class="text-xs text-blue-600 mt-0.5">0 partage(s)</p>' +
            '</div>' +
            '<div class="flex flex-wrap gap-1.5">' +
                '<a href="' + variablesUrl + '" target="_top" class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-xs hover:bg-gray-300 transition">' +
                    '<i class="fas fa-pen text-xs"></i> Modifier</a>' +
                '<button type="button" onclick="tplOoOpenShare(\'' + tpl.id + '\', \'' + tpl.name.replace(/\x27/g, "\x27\x27") + '\')" ' +
                    'class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200 transition flex items-center gap-1">' +
                    '<i class="fas fa-share-alt text-xs"></i> Partager</button>' +
                '<a href="' + variablesUrl + '" target="_top" class="px-2 py-1 rounded bg-purple-100 text-purple-700 text-xs hover:bg-purple-200 transition flex items-center gap-1">' +
                    '<i class="fas fa-wpforms text-xs"></i> Creer formulaire</a>' +
            '</div>';
        listEl.prepend(div);
        setTimeout(function() { div.className = 'border rounded-lg p-3 border-gray-200 bg-gray-50'; }, 3000);
    }
    window.tplOoInjectInList = tplOoInjectInList;;

    // ─── Bouton : Ajouter zone ────────────────────────────────────────────────
    function tplOoAddZone() {
        var idx = _tplOoZones.length;
        tplOoCreateDraggableZone(idx);
        tplOoUpdateBadge();
        tplOoShowStatus('Zone "Signature ' + (idx + 1) + '" ajoutée. Glissez-la pour la positionner, redimensionnez avec le coin bleu.', 5000);
    }
    window.tplOoAddZone = tplOoAddZone;

    // ─── Bouton : Effacer zone ────────────────────────────────────────────────
    function tplOoClearZone() {
        if (_tplOoZones.length === 0) { tplOoShowStatus('Aucune zone à effacer.', 3000); return; }
        tplOoRemoveZone(_tplOoZones.length - 1);
    }
    window.tplOoClearZone = tplOoClearZone;

    // ─── Bouton : Enregistrer ─────────────────────────────────────────────────
    function tplOoSave() {
        if (_tplOoZones.length === 0) {
            tplOoShowStatus('Aucune zone de signature à enregistrer. Ajoutez des zones avec "Ajouter zone".', 4000);
            return;
        }
        // Sauvegarder les zones via AJAX si un template est sélectionné
        var tplId = window._ooCurrentTemplateId || new URLSearchParams(window.location.search).get('selected_template');
        if (!tplId) {
            tplOoShowStatus('Zones mémorisées (' + _tplOoZones.length + ' zone(s)). Sélectionnez un template pour les associer.', 4000);
            return;
        }
        var btn = event && event.target ? event.target : null;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Enregistrement...'; }
        fetch(_adminTplBase + '/' + tplId + '/zones', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '' },
            body: JSON.stringify({ zones: _tplOoZones.map(function(z){ return { x: z.x, y: z.y, w: z.w, h: z.h, sealed: true, label: z.label || '' }; }) })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-stamp text-xs"></i> Sceller les zones'; }
            if (data.success) {
            tplOoShowStatus((data.message || 'Enregistrement effectué avec succès !') + ' Pensez à vérifier les balises dynamiques du template.', 7000);
            } else {
                tplOoShowStatus('Erreur : ' + (data.message || 'Impossible d\'enregistrer.'), 5000);
            }
        }).catch(function() {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-stamp text-xs"></i> Sceller les zones'; }
            tplOoShowStatus(_tplOoZones.length + ' zone(s) m�moris�e(s) localement (hors-ligne).', 4000);
        });
    }
    window.tplOoSave = tplOoSave;
    // --- Bouton : Sauvegarder le modele (PDF) --------------------------------
    function tplOoForceSave() {
      var tplId = window._ooCurrentTemplateId || new URLSearchParams(window.location.search).get('selected_template');
      var docKey = window._ooCurrentDocKey || '';
      if (!tplId) {
        tplOoShowStatus('Aucun modèle actif à enregistrer.', 4000);
        return;
      }
      if (!docKey) {
        tplOoShowStatus('Clé de document OnlyOffice introuvable. Rechargez le modèle puis réessayez.', 5000);
        return;
      }

      var saveBtn = document.getElementById('tpl-oo-forcesave-btn');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Synchronisation...';
      }

      tplOoShowStatus('Synchronisation du document avec le serveur en cours...', 0);

      var csrf = document.querySelector('meta[name="csrf-token"]');
      fetch(_adminTplBase + '/' + tplId + '/force-save', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.content : ''
          },
          body: JSON.stringify({ doc_key: docKey })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data || !data.success) {
            throw new Error((data && data.message) ? data.message : 'Forcesave refusé par OnlyOffice');
          }

          tplOoShowStatus('Forcesave envoyé à OnlyOffice. Attente de la confirmation serveur…', 4500);
          tplOoPostCloseSync(tplId);

          return fetch(_adminTplBase + '/' + tplId + '/oo-config', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '' }
          });
        })
        .then(function(r) { return r ? r.json() : null; })
        .then(function(cfg) {
          if (!cfg) return;
          if (cfg.error) {
            tplOoShowStatus('Erreur : ' + cfg.error, 7000);
            return;
          }
          tplOoLoadEditor(cfg);
          tplOoShowStatus('Modèle synchronisé. Les variables sont en cours de mise à jour.', 5000);
        })
        .catch(function(err) {
          tplOoShowStatus('Erreur de synchronisation : ' + err.message, 6000);
        })
        .finally(function() {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save text-xs"></i> Enregistrer le modèle';
          }
        });
    }
    window.tplOoForceSave = tplOoForceSave;

    function tplOoPostCloseSync(tplId) {
      if (!tplId) return;

      var csrf = document.querySelector('meta[name="csrf-token"]');
      var attempts = 0;
      var maxAttempts = 12;

      function runSyncAttempt() {
        attempts += 1;
        fetch(_adminTplBase + '/' + tplId + '/detect-vars', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.content : ''
          }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data && data.success && data.count > 0) {
            var label = document.getElementById('detect-label-' + tplId);
            if (label) {
              label.textContent = data.count + ' var(s)';
            }
            _tplOoLog('[OO sync] Variables détectées après fermeture:', data.count);
            return;
          }

          if (attempts < maxAttempts) {
            setTimeout(runSyncAttempt, 1500);
          } else {
            _tplOoLog('[OO sync] Fin des tentatives sans variable détectée pour le template', tplId);
          }
        })
        .catch(function() {
          if (attempts < maxAttempts) {
            setTimeout(runSyncAttempt, 1500);
          }
        });
      }

      // Petit délai pour laisser le callback OO (status 2/6) persister le fichier.
      setTimeout(runSyncAttempt, 1200);
    }


    // ─── Bouton : Fermer ──────────────────────────────────────────────────────
    function tplOoClose(forceClose) {
        _tplOoLog('[tplOoClose] Called with forceClose=', forceClose);
        if (!forceClose && !tplOoCanCloseModal()) {
            _tplOoLog('[tplOoClose] Fermeture bloquée par close-guard');
            return;
        }

        var tplId = window._ooCurrentTemplateId;

      tplOoHideOverlay();
      if (window._ooEditorInstance) {
        try { window._ooEditorInstance.destroyEditor(); } catch(e) {}
        window._ooEditorInstance = null;
      }
      var placeholder = document.getElementById('oo-editor-placeholder');
      if (placeholder) placeholder.innerHTML = '';
      var oldScript = document.getElementById('oo-api-script');
      if (oldScript) oldScript.remove();
      tplOoDisarmCloseGuard();
      _tplOoLog('[tplOoClose] Modal fermé, close-guard désarmé');
      closeModal('modal-tpl-oo', true);

      tplOoPostCloseSync(tplId);
    }

    window.tplOoClose = tplOoClose;

    function tplOpenShare(tplId, tplName) {
        _currentShareTplId = tplId;
        var shared = _shareMap[tplId] || [];
        document.getElementById('share-modal-tpl-name').textContent = tplName;
        document.getElementById('share-user-search').value = '';

        // Mettre à jour les actions + routes pour ce template
        document.querySelectorAll('.share-toggle-form').forEach(function(form) {
            form.action = _adminTplBase + '/' + tplId + '/share';
        });
        document.querySelectorAll('.share-user-row').forEach(function(row) {
            row.style.display = '';
        });
        document.querySelectorAll('[id^="share-hidden-tpl-"]').forEach(function(inp) {
            inp.value = tplId;
        });

        // Marquer utilisateurs d�j� partag�s
        document.querySelectorAll('.share-toggle-btn').forEach(function(btn) {
            var uid = btn.getAttribute('data-user-id');
            if (shared.indexOf(uid) !== -1) {
                btn.textContent = 'Retirer';
                btn.className = 'share-toggle-btn px-3 py-1 rounded-lg text-xs font-semibold transition bg-red-100 text-red-700 hover:bg-red-200';
            } else {
                btn.textContent = 'Partager';
                btn.className = 'share-toggle-btn px-3 py-1 rounded-lg text-xs font-semibold transition bg-gray-200 text-gray-700 hover:bg-blue-100 hover:text-blue-700';
            }
        });
        openModal('modal-tpl-share');
    }
    window.tplOpenShare = tplOpenShare;

    // --- Partage : recherche --------------------------------------------------
    function tplShareSearch(q) {
        var query = q.trim().toLowerCase();
        document.querySelectorAll('.share-user-row').forEach(function(row) {
            var name = row.getAttribute('data-name') || '';
            var email = row.getAttribute('data-email') || '';
            row.style.display = (!query || name.includes(query) || email.includes(query)) ? '' : 'none';
        });
    }
    window.tplShareSearch = tplShareSearch;

    // --- Modifier variable ---------------------------------------------------
    function openEditVariableModal(templateId, variableId, label, fieldType, selectedTplId) {
        var baseUrl = '{{ url("admin/templates") }}/' + templateId + '/variables/' + variableId;
        document.getElementById('form-edit-variable').action = baseUrl;
        document.getElementById('edit-var-label').value = label;
        document.getElementById('edit-var-field-type').value = fieldType;
        document.getElementById('edit-var-selected-template').value = selectedTplId;
        openModal('modal-edit-variable');
    }
    window.openEditVariableModal = openEditVariableModal;
})();
</script>
@endpush

{{-- ══════════════════════ ÉMETTEURS ══════════════════════ --}}
@elseif($tab === 'emitters')
@php
    $emitAction    = request('emit_action');
    $selEmitId     = request('selected_emitter', '');
    $editEmit      = ($emitAction === 'edit' && $selEmitId)
                        ? $emitters->firstWhere('id', $selEmitId)
                        : null;
    $em            = $editEmit; // alias court
    $emMeta        = $em ? ($em->metadata ?? []) : [];
@endphp
<div class="grid grid-cols-1 gap-5">
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
    <h2 class="text-lg font-semibold text-gray-800">Configurer une administration émettrice</h2>
    <form method="POST"
          action="{{ $em ? route('admin.emitters.update', $em) : route('admin.emitters.store') }}"
          enctype="multipart/form-data"
          class="space-y-4">
      @csrf
      @if($em) @method('PUT') @endif

      {{-- -- Informations Générales -- --}}
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Informations Générales</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="text" name="name" value="{{ old('name', $em->name ?? '') }}"
                 placeholder="Nom de l'administration *"
                 class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="text" name="code" value="{{ old('code', $em->code ?? '') }}"
                 placeholder="Code d'identification *"
                 class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="text" name="sub_entity_code" value="{{ old('sub_entity_code', $em->sub_entity_code ?? '') }}"
                 placeholder="Code entité sous tutelle (ex: CAB MIN)"
                 class="border rounded-lg px-3 py-2 text-xs"
                 title="Code utilisé dans la numérotation des documents : CODE_ADMIN - CODE_ENTITE - 00001 - 2026">
          <select name="admin_type" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Type d'administration *</option>
            @foreach(['nationale'=>'Administration nationale','regionale'=>'Régionale','departementale'=>'Départementale','communale'=>'Communale','etablissement'=>'Établissement public'] as $v=>$l)
            <option value="{{ $v }}" {{ old('admin_type', $emMeta['adminType'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <select name="sector" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Secteur d'activité *</option>
            @foreach(['fiscalite_finance'=>'Fiscalité & Finances','protection_sociale'=>'Protection sociale','travail_emploi'=>'Travail & Emploi','urbanisme_logement'=>'Urbanisme & Logement','education_formation'=>'Éducation & Formation','sante'=>'Santé','justice'=>'Justice','securite'=>'Sécurité','environnement'=>'Environnement','autre'=>'Autre'] as $v=>$l)
            <option value="{{ $v }}" {{ old('sector', $emMeta['sector'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <textarea name="description" rows="2" placeholder="{{ __('personnel.ui.career.form_description_placeholder') }}"
                  class="w-full border rounded-lg px-3 py-2 text-xs">{{ old('description', $emMeta['description'] ?? '') }}</textarea>
      </fieldset>

      {{-- -- Coordonnées & Contacts -- --}}
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Coordonnées & Contacts</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="email" name="contact_email" value="{{ old('contact_email', $emMeta['contactEmail'] ?? '') }}"
                 placeholder="Email de contact *" class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="email" name="tech_email" value="{{ old('tech_email', $emMeta['techEmail'] ?? '') }}"
                 placeholder="Email technique" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="contact_phone" value="{{ old('contact_phone', $emMeta['contactPhone'] ?? '') }}"
                 placeholder="Téléphone" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="referent_metier" value="{{ old('referent_metier', $emMeta['referentMetier'] ?? '') }}"
                 placeholder="Référent métier" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <textarea name="postal_address" rows="2" placeholder="Adresse postale"
                  class="w-full border rounded-lg px-3 py-2 text-xs">{{ old('postal_address', $emMeta['postalAddress'] ?? '') }}</textarea>
      </fieldset>

      {{-- -- Logo -- --}}
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Logo de l'administration (affiché dans la barre latérale)</legend>
        <div class="grid grid-cols-1 md:grid-cols-[88px_1fr] gap-3 items-center">
          <div class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden" id="emit-logo-preview-wrap">
            @if($em && $em->logo)
              <img src="{{ asset($em->logo) }}" alt="Logo actuel" class="w-full h-full object-contain" id="emit-logo-preview-img">
            @else
              <span class="text-gray-400 text-xs text-center px-1" id="emit-logo-preview-txt">Aperçu logo</span>
              <img src="" alt="Aperçu" class="w-full h-full object-contain hidden" id="emit-logo-preview-img">
            @endif
          </div>
          <div class="space-y-1">
            <input type="file" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                   onchange="emitLogoPreview(this)"
                   class="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-[11px] text-gray-500">Formats acceptés : PNG, JPG, SVG, WEBP (max 2 MB).</p>
          </div>
        </div>
      </fieldset>

      {{-- -- Configuration Technique -- --}}
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Configuration Technique</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="transmission_method" class="border rounded-lg px-3 py-2 text-xs" required>
            @foreach(['api'=>'API REST','sftp'=>'SFTP','email'=>'Email sécurisé','ler'=>'LER','portal'=>'Portail Web'] as $v=>$l)
            <option value="{{ $v }}" {{ old('transmission_method', $emMeta['transmissionMethod'] ?? 'api') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <input type="text" name="endpoint_url" value="{{ old('endpoint_url', $emMeta['endpointUrl'] ?? '') }}"
                 placeholder="Endpoint URL" class="border rounded-lg px-3 py-2 text-xs">
          <select name="data_format" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['json'=>'JSON','xml'=>'XML','pdf'=>'PDF','multipart'=>'Multipart/Form-Data'] as $v=>$l)
            <option value="{{ $v }}" {{ old('data_format', $emMeta['dataFormat'] ?? 'json') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <select name="auth_method" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['api_key'=>'API Key','oauth2'=>'OAuth 2.0','mtls'=>'mTLS','basic'=>'Basic Auth'] as $v=>$l)
            <option value="{{ $v }}" {{ old('auth_method', $emMeta['authMethod'] ?? 'api_key') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <input type="password" name="api_key" value="{{ old('api_key', $emMeta['apiKey'] ?? '') }}"
                 placeholder="Clé API / Token" class="border rounded-lg px-3 py-2 text-xs">
          <input type="number" name="timeout" min="5" max="300"
                 value="{{ old('timeout', $emMeta['timeout'] ?? 30) }}"
                 placeholder="Timeout (s)" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <div class="flex flex-wrap gap-4 text-xs text-gray-700">
          <label class="flex items-center gap-2">
            <input type="hidden" name="require_tls" value="0">
            <input type="checkbox" name="require_tls" value="1"
                   {{ old('require_tls', $emMeta['requireTls'] ?? true) ? 'checked' : '' }}> TLS 1.3 requis
          </label>
          <label class="flex items-center gap-2">
            <input type="hidden" name="enable_retry" value="0">
            <input type="checkbox" name="enable_retry" value="1"
                   {{ old('enable_retry', $emMeta['enableRetry'] ?? true) ? 'checked' : '' }}> Activer retries
          </label>
        </div>
      </fieldset>

      {{-- -- Documents -- --}}
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Documents</legend>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
          @foreach(['pdf','docx','xml','zip'] as $dtype)
          <label class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2">
            <input type="checkbox" name="doc_types[]" value="{{ $dtype }}"
                   {{ in_array($dtype, old('doc_types', $emMeta['docTypes'] ?? ['pdf'])) ? 'checked' : '' }}>
            {{ strtoupper($dtype) }}
          </label>
          @endforeach
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="default_workflow" class="border rounded-lg px-3 py-2 text-xs">
            <option value="">Workflow par défaut</option>
            @foreach(['simple'=>'Validation simple (2 étapes)','standard'=>'Circuit standard (4 étapes)','urgent'=>'Circuit urgent (1 étape)'] as $v=>$l)
            <option value="{{ $v }}" {{ old('default_workflow', $emMeta['defaultWorkflow'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <input type="text" name="dossier_prefix" value="{{ old('dossier_prefix', $emMeta['dossierPrefix'] ?? '') }}"
                 placeholder="Préfixe numéro dossier" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <label class="flex items-center gap-2 text-xs text-gray-700">
          <input type="hidden" name="auto_convert_pdf" value="0">
          <input type="checkbox" name="auto_convert_pdf" value="1"
                 {{ old('auto_convert_pdf', $emMeta['autoConvertPdf'] ?? true) ? 'checked' : '' }}>
          Convertir automatiquement en PDF
        </label>
        <textarea name="required_metadata" rows="2"
                  placeholder="Métadonnées obligatoires (JSON)"
                  class="w-full border rounded-lg px-3 py-2 text-xs font-mono">{{ old('required_metadata', $emMeta['requiredMetadata'] ?? '') }}</textarea>
      </fieldset>

      {{-- -- Sécurité & Opérationnel -- --}}
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Sécurité & Opérationnel</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="signature_level" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['simple'=>'Signature Simple','avancee'=>'Signature Avancée','qualifiee'=>'Signature Qualifiée (eIDAS)'] as $v=>$l)
            <option value="{{ $v }}" {{ old('signature_level', $emMeta['signatureLevel'] ?? 'qualifiee') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <input type="number" name="log_retention" min="30" max="2555"
                 value="{{ old('log_retention', $emMeta['logRetention'] ?? 365) }}"
                 placeholder="Durée conservation logs (jours)" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="business_hours" value="{{ old('business_hours', $emMeta['businessHours'] ?? '') }}"
                 placeholder="Horaires de traitement" class="border rounded-lg px-3 py-2 text-xs">
          <select name="sla_response" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['immediat'=>'Immédiat','24h'=>'24 heures','48h'=>'48 heures','5j'=>'5 jours ouvrés'] as $v=>$l)
            <option value="{{ $v }}" {{ old('sla_response', $emMeta['slaResponse'] ?? '24h') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <select name="timezone" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['Europe/Paris'=>'Europe/Paris','UTC'=>'UTC','America/New_York'=>'America/New_York'] as $v=>$l)
            <option value="{{ $v }}" {{ old('timezone', $emMeta['timezone'] ?? 'Europe/Paris') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <select name="duplicate_handling" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['reject'=>'Rejeter le nouvel envoi','update'=>'Mettre à jour le dossier existant','version'=>'Créer une nouvelle version'] as $v=>$l)
            <option value="{{ $v }}" {{ old('duplicate_handling', $emMeta['duplicateHandling'] ?? 'update') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-700">
          <label class="flex items-center gap-2">
            <input type="hidden" name="gdpr_compliant" value="0">
            <input type="checkbox" name="gdpr_compliant" value="1"
                   {{ old('gdpr_compliant', $emMeta['gdprCompliant'] ?? true) ? 'checked' : '' }}> Conformité RGPD
          </label>
          <label class="flex items-center gap-2">
            <input type="hidden" name="enable_audit" value="0">
            <input type="checkbox" name="enable_audit" value="1"
                   {{ old('enable_audit', $emMeta['enableAudit'] ?? true) ? 'checked' : '' }}> Activer Audit Trail
          </label>
          <label class="flex items-center gap-2">
            <input type="hidden" name="file_encryption" value="0">
            <input type="checkbox" name="file_encryption" value="1"
                   {{ old('file_encryption', $emMeta['fileEncryption'] ?? false) ? 'checked' : '' }}> Chiffrement fichiers au repos
          </label>
        </div>
        <textarea name="ip_whitelist" rows="2" placeholder="IPs autorisées"
                  class="w-full border rounded-lg px-3 py-2 text-xs font-mono">{{ old('ip_whitelist', $emMeta['ipWhitelist'] ?? '') }}</textarea>
      </fieldset>

      {{-- -- Métadonnées & Tracking -- --}}
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Métadonnées & Tracking</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="text" name="external_ref_field" value="{{ old('external_ref_field', $emMeta['externalRefField'] ?? '') }}"
                 placeholder="Champ référence externe" class="border rounded-lg px-3 py-2 text-xs">
          <input type="url" name="tracking_url" value="{{ old('tracking_url', $emMeta['trackingUrl'] ?? '') }}"
                 placeholder="URL de suivi public" class="border rounded-lg px-3 py-2 text-xs">
          <input type="url" name="webhook_url" value="{{ old('webhook_url', $emMeta['webhookUrl'] ?? '') }}"
                 placeholder="Webhook URL" class="border rounded-lg px-3 py-2 text-xs">
          <input type="password" name="webhook_secret" value="{{ old('webhook_secret', $emMeta['webhookSecret'] ?? '') }}"
                 placeholder="Webhook secret" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <input type="text" name="tags" value="{{ old('tags', $emMeta['tags'] ?? '') }}"
               placeholder="Tags / Catégories" class="w-full border rounded-lg px-3 py-2 text-xs">
        <div class="flex items-center gap-3 pt-1">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" id="emit_is_active"
                 {{ old('is_active', $em->is_active ?? true) ? 'checked' : '' }}
                 class="w-4 h-4 text-violet-600 border-gray-300 rounded">
          <label for="emit_is_active" class="text-xs font-medium text-gray-700">Administration active</label>
        </div>
      </fieldset>

      <div class="flex gap-2">
        <a href="{{ route('admin.index', ['tab' => 'emitters']) }}"
           class="flex-1 border border-gray-300 text-gray-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-gray-50 text-center">
          Liste des administrations émettrices
        </a>
        <button type="submit" class="flex-1 bg-blue-600 text-white rounded-lg px-3 py-2 text-xs font-semibold">
          {{ $em ? 'Mettre à jour l\'administration émettrice' : 'Enregistrer l\'administration émettrice' }}
        </button>
      </div>
    </form>

    {{-- -- Liste des émetteurs -- --}}
    <input type="text" id="emit-search" placeholder="Rechercher une administration émettrice…"
           oninput="emitSearch(this.value)"
           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
    <div class="space-y-2 max-h-96 overflow-auto mt-2" id="emit-list">
      @forelse($emitters as $item)
      @php $iMeta = $item->metadata ?? []; @endphp
      <div data-search="{{ strtolower($item->name . ' ' . $item->code) }}"
           class="border rounded-lg p-3 {{ $selEmitId === $item->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50' }}">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-800 truncate">{{ $item->name }}</p>
            <p class="text-[11px] text-gray-500">
              Code: <code class="bg-gray-100 px-1 rounded">{{ $item->code }}</code>
              @if(!empty($iMeta['adminType'])) · {{ $iMeta['adminType'] }} @endif
              @if(!empty($iMeta['sector'])) · {{ $iMeta['sector'] }} @endif
              @if(!empty($iMeta['contactEmail'])) · {{ $iMeta['contactEmail'] }} @endif
            </p>
          </div>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $item->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
            {{ $item->is_active ? 'Actif' : 'Inactif' }}
          </span>
        </div>
        <div class="mt-2 flex gap-2">
          <a href="{{ route('admin.index', ['tab' => 'emitters', 'emit_action' => 'edit', 'selected_emitter' => $item->id]) }}"
             class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px] hover:bg-gray-300">Modifier</a>
          <form method="POST" action="{{ route('admin.emitters.destroy', $item) }}"
                onsubmit="return confirm('Supprimer cette administration émettrice ?')" class="inline">
            @csrf @method('DELETE')
            <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px] hover:bg-red-200">Supprimer</button>
          </form>
        </div>
      </div>
      @empty
      <p class="text-center text-gray-400 text-xs py-8">Aucune administration émettrice enregistrée.</p>
      @endforelse
    </div>
  </section>
</div>
@push('scripts')
<script>
function emitLogoPreview(input) {
    var img = document.getElementById('emit-logo-preview-img');
    var txt = document.getElementById('emit-logo-preview-txt');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.classList.remove('hidden');
            if (txt) txt.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function emitSearch(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#emit-list [data-search]').forEach(function(card) {
        card.style.display = (!q || card.dataset.search.includes(q)) ? '' : 'none';
    });
}
</script>
@endpush

{{-- ══════════════════════ DESTINATAIRES ══════════════════════ --}}
@elseif($tab === 'recipients')
@php
    $recipAction  = request('recip_action');
    $selRecipId   = request('selected_recipient', '');
    $editRecip    = ($recipAction === 'edit' && $selRecipId)
                       ? $recipients->firstWhere('id', $selRecipId)
                       : null;
    $re           = $editRecip;
    $reMeta       = $re ? ($re->metadata ?? []) : [];
    $reLogo       = $re ? ($re->logo ?: ($reMeta['logoPath'] ?? $reMeta['logo'] ?? null)) : null;
    $reChannel    = old('channel', $re->channel ?? 'api');
@endphp
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  {{-- -- Formulaire -- --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-5">
    <h2 class="text-lg font-semibold text-gray-800">Formulaire d'enregistrement - Administration Destinataire</h2>
    <form method="POST"
          action="{{ $re ? route('admin.recipients.update', $re) : route('admin.recipients.store') }}"
          enctype="multipart/form-data"
          class="space-y-5" id="form-recipient">
      @csrf
      @if($re) @method('PUT') @endif

      {{-- 1. Informations générales --}}
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">1. Informations générales</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="text" name="name" value="{{ old('name', $re->name ?? '') }}"
                 placeholder="Nom de l'administration" class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="text" name="code" value="{{ old('code', $re->code ?? '') }}"
                 placeholder="Code d'identification (SIRET/SIREN)" class="border rounded-lg px-3 py-2 text-xs" required>
          <select name="admin_type" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Type d'administration</option>
            @foreach(['nationale'=>'Administration Nationale','regionale'=>'Administration Régionale','departementale'=>'Administration Départementale','communale'=>'Administration Communale','etablissement'=>'Établissement Public','prive'=>'Secteur Privé','organisme'=>'Organisme Paritaire'] as $v=>$l)
            <option value="{{ $v }}" {{ old('admin_type', $reMeta['adminType'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <select name="sector" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Secteur de compétence</option>
            @foreach(['fiscalite'=>'Fiscalité & Finances','social'=>'Protection Sociale','travail'=>'Travail & Emploi','urbanisme'=>'Urbanisme & Logement','education'=>'Éducation & Formation','sante'=>'Santé','justice'=>'Justice','environnement'=>'Environnement','commerce'=>'Commerce & Industrie','banques'=>'Banques','securite'=>'Sécurité','administration'=>'Administration','agriculture'=>'Agriculture','autre'=>'Autre'] as $v=>$l)
            <option value="{{ $v }}" {{ old('sector', $reMeta['sector'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <textarea name="description" rows="3" placeholder="Description des missions"
                  class="w-full border rounded-lg px-3 py-2 text-xs">{{ old('description', $reMeta['description'] ?? '') }}</textarea>
      </div>

      {{-- 2. Coordonnées & contacts --}}
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">2. Coordonnées & contacts</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="email" name="contact_email" value="{{ old('contact_email', $reMeta['contactEmail'] ?? '') }}"
                 placeholder="Email de réception principal" class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="email" name="tech_email" value="{{ old('tech_email', $reMeta['techEmail'] ?? '') }}"
                 placeholder="Email technique" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="contact_phone" value="{{ old('contact_phone', $reMeta['contactPhone'] ?? '') }}"
                 placeholder="Téléphone" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="contact_fax" value="{{ old('contact_fax', $reMeta['contactFax'] ?? '') }}"
                 placeholder="Fax" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="referent_metier" value="{{ old('referent_metier', $reMeta['referentMetier'] ?? '') }}"
                 placeholder="Référent métier" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="referent_technique" value="{{ old('referent_technique', $reMeta['referentTechnique'] ?? '') }}"
                 placeholder="Référent technique" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <textarea name="postal_address" rows="2" placeholder="Adresse postale"
                  class="w-full border rounded-lg px-3 py-2 text-xs">{{ old('postal_address', $reMeta['postalAddress'] ?? '') }}</textarea>
      </div>

      {{-- Logo --}}
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">Logo de l'administration</p>
        <div class="grid grid-cols-1 md:grid-cols-[88px_1fr] gap-3 items-center">
          <div class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden">
            @if($re && $reLogo)
              <img src="{{ asset($reLogo) }}" alt="Logo actuel" class="w-full h-full object-contain" id="recip-logo-preview-img">
            @else
              <span class="text-gray-400 text-xs text-center px-1" id="recip-logo-preview-txt">Aperçu logo</span>
              <img src="" alt="Aperçu" class="w-full h-full object-contain hidden" id="recip-logo-preview-img">
            @endif
          </div>
          <div class="space-y-1">
            <input type="file" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                   onchange="recipLogoPreview(this)"
                   class="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
            <p class="text-[11px] text-gray-500">Formats acceptés : PNG, JPG, SVG, WEBP (max 2 MB).</p>
          </div>
        </div>
      </div>

      {{-- 3. Méthode de réception --}}
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">3. Méthode de réception</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          @foreach(['api'=>'API REST','email'=>'Email sécurisé','ler'=>'LER','application'=>'Via l\'application'] as $ch=>$chl)
          <label class="border rounded-lg p-3 text-xs cursor-pointer {{ $reChannel === $ch ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
            <input type="radio" name="channel" value="{{ $ch }}" class="mr-2"
                   {{ $reChannel === $ch ? 'checked' : '' }}
                   onchange="recipChannelToggle(this.value)">
            {{ $chl }}
          </label>
          @endforeach
        </div>

        {{-- Champs API --}}
        <div id="recip-fields-api" class="{{ $reChannel !== 'api' ? 'hidden' : '' }} grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="url" name="api_endpoint_meta" value="{{ old('api_endpoint_meta', $reMeta['apiEndpoint'] ?? '') }}"
                 placeholder="Endpoint URL de réception" class="md:col-span-2 border rounded-lg px-3 py-2 text-xs">
          <select name="api_method" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['POST'=>'POST','PUT'=>'PUT'] as $v=>$l)
            <option value="{{ $v }}" {{ old('api_method', $reMeta['apiMethod'] ?? 'POST') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <select name="api_format" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['multipart'=>'Multipart/Form-Data','json'=>'JSON','xml'=>'XML'] as $v=>$l)
            <option value="{{ $v }}" {{ old('api_format', $reMeta['apiFormat'] ?? 'multipart') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <select name="api_auth" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['api_key'=>'API Key','oauth2'=>'OAuth 2.0','basic'=>'Basic Auth','mtls'=>'mTLS','none'=>'Aucune'] as $v=>$l)
            <option value="{{ $v }}" {{ old('api_auth', $reMeta['apiAuth'] ?? 'api_key') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <input type="number" name="api_timeout" min="5" max="300"
                 value="{{ old('api_timeout', $reMeta['apiTimeout'] ?? 30) }}"
                 placeholder="Timeout (secondes)" class="border rounded-lg px-3 py-2 text-xs">
        </div>

        {{-- Champs Email --}}
        <div id="recip-fields-email" class="{{ $reChannel !== 'email' ? 'hidden' : '' }} grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="email" name="email_address_meta" value="{{ old('email_address_meta', $reMeta['emailAddress'] ?? '') }}"
                 placeholder="Email de destination" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="email_subject" value="{{ old('email_subject', $reMeta['emailSubject'] ?? '') }}"
                 placeholder="Objet du mail" class="border rounded-lg px-3 py-2 text-xs">
          <textarea name="email_body" rows="3"
                    placeholder="Corps du mail"
                    class="md:col-span-2 border rounded-lg px-3 py-2 text-xs">{{ old('email_body', $reMeta['emailBody'] ?? '') }}</textarea>
        </div>

        {{-- Champs LER --}}
        <div id="recip-fields-ler" class="{{ $reChannel !== 'ler' ? 'hidden' : '' }} grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="ler_provider" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['laposte'=>'La Poste e-Recommandé','docusign'=>'DocuSign Envelope','yousign'=>'Yousign','ar24'=>'AR24'] as $v=>$l)
            <option value="{{ $v }}" {{ old('ler_provider', $reMeta['lerProvider'] ?? 'laposte') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <input type="text" name="ler_account_id" value="{{ old('ler_account_id', $reMeta['lerAccountId'] ?? '') }}"
                 placeholder="ID de compte fournisseur" class="border rounded-lg px-3 py-2 text-xs">
        </div>

        {{-- Application --}}
        <div id="recip-fields-application" class="{{ $reChannel !== 'application' ? 'hidden' : '' }} rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700">
          Les documents seront reçus directement dans l'onglet Réception de l'application.
        </div>
      </div>

      {{-- 4. Documents acceptés --}}
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">4. Documents acceptés</p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
          @foreach(['pdf','docx','xlsx','pptx','xml','zip'] as $dtype)
          <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2">
            <input type="checkbox" name="doc_types[]" value="{{ $dtype }}"
                   {{ in_array($dtype, old('doc_types', $reMeta['docTypes'] ?? ['pdf'])) ? 'checked' : '' }}>
            {{ strtoupper($dtype) }}
          </label>
          @endforeach
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="number" name="max_file_size" min="1" max="500"
                 value="{{ old('max_file_size', $reMeta['maxFileSize'] ?? 50) }}"
                 placeholder="Taille max (MB)" class="border rounded-lg px-3 py-2 text-xs">
          <input type="number" name="max_files" min="1" max="100"
                 value="{{ old('max_files', $reMeta['maxFiles'] ?? 10) }}"
                 placeholder="Nombre max de fichiers" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-700">
          @foreach([['enable_retry','Activer retry',true],['enable_notification','Notifier l\'usager',true],['compress_files','Compresser fichiers',false],['encrypt_files','Chiffrer fichiers',false]] as [$fkey,$flabel,$fdefault])
          <label class="flex items-center gap-2">
            <input type="hidden" name="{{ $fkey }}" value="0">
            <input type="checkbox" name="{{ $fkey }}" value="1"
                   {{ old($fkey, $reMeta[lcfirst(str_replace('_',' ',$fkey))] ?? $fdefault) ? 'checked' : '' }}>
            {{ $flabel }}
          </label>
          @endforeach
        </div>
      </div>

      {{-- 5. Accusé de réception --}}
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">5. Accusé de réception</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="receipt_method" class="border rounded-lg px-3 py-2 text-xs">
            @foreach(['automatic'=>'Automatique via API','manual'=>'Manuel','email'=>'Par email automatique','none'=>'Aucun'] as $v=>$l)
            <option value="{{ $v }}" {{ old('receipt_method', $reMeta['receiptMethod'] ?? 'automatic') === $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
          <input type="number" name="receipt_timeout" min="1" max="168"
                 value="{{ old('receipt_timeout', $reMeta['receiptTimeout'] ?? 24) }}"
                 placeholder="Délai max (heures)" class="border rounded-lg px-3 py-2 text-xs">
          <input type="url" name="receipt_webhook_url" value="{{ old('receipt_webhook_url', $reMeta['receiptWebhookUrl'] ?? '') }}"
                 placeholder="URL webhook confirmation" class="md:col-span-2 border rounded-lg px-3 py-2 text-xs">
        </div>
        <div class="flex items-center gap-3">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" id="recip_is_active"
                 {{ old('is_active', $re->is_active ?? true) ? 'checked' : '' }}
                 class="w-4 h-4 text-pink-600 border-gray-300 rounded">
          <label for="recip_is_active" class="text-xs font-medium text-gray-700">Administration active</label>
        </div>
        <label class="flex items-center gap-2 text-xs text-gray-700">
          <input type="hidden" name="activate_immediately" value="0">
          <input type="checkbox" name="activate_immediately" value="1"
                 {{ old('activate_immediately', $reMeta['activateImmediately'] ?? true) ? 'checked' : '' }}>
          Activer immédiatement après création
        </label>
      </div>

      <div class="flex flex-col sm:flex-row gap-2">
        <a href="{{ route('admin.index', ['tab' => 'recipients']) }}"
           class="sm:w-auto w-full border border-gray-300 text-gray-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-gray-50 text-center">
          Annuler
        </a>
        <button type="submit" class="w-full bg-blue-600 text-white rounded-lg px-3 py-2 text-xs font-semibold">
          {{ $re ? 'Mettre à jour l\'administration destinataire' : 'Créer l\'administration destinataire' }}
        </button>
      </div>
    </form>
  </section>

  {{-- -- Liste des destinataires -- --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 max-h-[700px] overflow-auto space-y-3">
    <div class="flex items-center justify-between gap-3">
      <h3 class="text-base font-semibold text-gray-800">Administrations Destinataires enregistrées</h3>
      <span class="text-xs text-gray-400">{{ $recipients->count() }}</span>
    </div>
    <input type="text" id="recip-search" placeholder="Rechercher une administration destinataire..."
           oninput="recipSearch(this.value)"
           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="recip-list">
      @forelse($recipients as $item)
      @php
        $iMeta = $item->metadata ?? [];
        $itemLogo = $item->logo ?: ($iMeta['logoPath'] ?? $iMeta['logo'] ?? null);
      @endphp
      <div class="recip-card border border-gray-200 bg-gray-50 rounded-lg p-2.5
                  {{ $selRecipId === $item->id ? 'border-blue-500 bg-blue-50' : '' }}"
           data-name="{{ strtolower($item->name) }}" data-sector="{{ strtolower($iMeta['sector'] ?? '') }}">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-md border border-gray-200 bg-white overflow-hidden flex items-center justify-center shrink-0">
            @if($itemLogo)
              <img src="{{ asset($itemLogo) }}" alt="Logo {{ $item->name }}" class="w-full h-full object-contain">
            @else
              <span class="text-[10px] font-semibold text-gray-400">LOGO</span>
            @endif
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-gray-800 truncate">{{ $item->name }}</p>
            <p class="text-[11px] text-gray-500">Canal: {{ strtoupper($item->channel) }} · {{ $iMeta['sector'] ?? 'Secteur non défini' }}</p>
            <p class="text-[11px] text-gray-500">Contact: {{ $item->email_address ?? ($iMeta['contactEmail'] ?? '-') }}</p>
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
              <span class="w-1.5 h-1.5 rounded-full {{ $item->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
              {{ $item->is_active ? 'Actif' : 'Inactif' }}
            </span>
          </div>
        </div>
        <div class="mt-2 flex gap-2">
          <a href="{{ route('admin.index', ['tab' => 'recipients', 'recip_action' => 'edit', 'selected_recipient' => $item->id]) }}"
             class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px] hover:bg-gray-300">Modifier</a>
          <form method="POST" action="{{ route('admin.recipients.destroy', $item) }}"
                onsubmit="return confirm('Supprimer cette administration destinataire ?')" class="inline">
            @csrf @method('DELETE')
            <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px] hover:bg-red-200">Supprimer</button>
          </form>
        </div>
      </div>
      @empty
      <div class="md:col-span-2 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-xs text-gray-500">
        Aucune administration destinataire enregistrée.
      </div>
      @endforelse
    </div>
  </section>
</div>
@push('scripts')
<script>
function recipLogoPreview(input) {
    var img = document.getElementById('recip-logo-preview-img');
    var txt = document.getElementById('recip-logo-preview-txt');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.classList.remove('hidden');
            if (txt) txt.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function recipChannelToggle(ch) {
    ['api','email','ler','application'].forEach(function(c){
        var el = document.getElementById('recip-fields-' + c);
        if (el) el.classList.toggle('hidden', c !== ch);
    });
}
function recipSearch(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.recip-card').forEach(function(card){
        var name = card.dataset.name || '';
        var sector = card.dataset.sector || '';
        card.style.display = (name.includes(q) || sector.includes(q)) ? '' : 'none';
    });
}
</script>
@endpush

{{-- ══════════════════════ ENTITÉS SOUS TUTELLE ══════════════════════ --}}
@elseif($tab === 'sub-entities')
@php
    $subAction  = request('sub_action');
    $selSubId   = request('selected_sub');
    $editSub    = ($subAction === 'edit' && $selSubId) ? $subEntities->firstWhere('id', $selSubId) : null;
    $subScopeType = old('scope_type', $editSub?->scope_type ?? 'emitter');
    $subScopeId   = old('scope_id',   $editSub?->scope_id   ?? '');
@endphp

<div class="grid grid-cols-1 xl:grid-cols-[1.2fr_1fr] gap-5">

  {{-- -- Colonne gauche : Formulaire -- --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
    <div class="space-y-1">
      <h2 class="text-base font-bold text-gray-800">{{ $editSub ? 'Modifier la direction' : 'Nouvelle Direction' }}</h2>
      <p class="text-xs text-gray-500">Créez les entités sous tutelle depuis ce sous-onglet dédié.</p>
    </div>

    @if(session('success') && request('tab') === 'sub-entities')
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    @if($errors->any() && request('tab') === 'sub-entities')
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
      </ul>
    </div>
    @endif

    {{-- Sélecteurs de périmètre --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="sub-scope-selectors">
      <select id="sub-scope-type" name="scope_type_preview"
        onchange="subScopeTypeChange(this.value)"
        class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
        <option value="emitter"   {{ $subScopeType === 'emitter'   ? 'selected' : '' }}>Administration Émettrice</option>
        <option value="recipient" {{ $subScopeType === 'recipient' ? 'selected' : '' }}>Administration destinataire</option>
      </select>
      <select id="sub-scope-id" name="scope_id_preview"
        onchange="subScopeIdChange(this.value)"
        class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
        <option value="">Sélectionner une administration</option>
        @foreach($emitters as $em)
        <option class="opt-emitter" value="{{ $em->id }}" {{ $subScopeId === $em->id ? 'selected' : '' }}>{{ $em->name }}</option>
        @endforeach
        @foreach($recipients as $re)
        <option class="opt-recipient" value="{{ $re->id }}" {{ $subScopeId === $re->id ? 'selected' : '' }}>{{ $re->name }}</option>
        @endforeach
      </select>
    </div>

    {{-- Formulaire principal --}}
    @if($editSub)
    <form method="POST" action="{{ route('admin.sub-entities.update', $editSub->id) }}" class="space-y-3">
      @method('PUT')
    @else
    <form method="POST" action="{{ route('admin.sub-entities.store') }}" class="space-y-3">
    @endif
      @csrf
      <input type="hidden" name="scope_type" id="sub-scope-type-hidden" value="{{ $subScopeType }}">
      <input type="hidden" name="scope_id"   id="sub-scope-id-hidden"   value="{{ $subScopeId }}">

      <input type="text" name="name" value="{{ old('name', $editSub?->name) }}"
        placeholder="Nom de la direction" required
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="code" value="{{ old('code', $editSub?->code) }}"
          placeholder="Code (ex: DIR001)" required
          oninput="this.value = this.value.toUpperCase()"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">

        <select id="sub-parent-code" name="parent_code"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
          <option value="">Direction Parent (optionnelle)</option>
          @foreach($subEntities as $se)
            @if(!$editSub || $se->id !== $editSub->id)
            <option value="{{ $se->code }}"
              data-scope-type="{{ $se->scope_type }}"
              data-scope-id="{{ $se->scope_id }}"
              {{ old('parent_code', $editSub?->parent_code) === $se->code ? 'selected' : '' }}>
              {{ $se->code }} – {{ $se->name }}
            </option>
            @endif
          @endforeach
        </select>
      </div>

      <select name="direction_type_id"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none" required>
        <option value="">Type de Direction</option>
        @foreach($directionTypes as $dt)
        <option value="{{ $dt->id }}" {{ old('direction_type_id', $editSub?->direction_type_id) === $dt->id ? 'selected' : '' }}>
          {{ $dt->name }}
        </option>
        @endforeach
      </select>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="manager_name" value="{{ old('manager_name', $editSub?->manager_name) }}"
          placeholder="Nom du Responsable"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
        <input type="email" name="manager_email" value="{{ old('manager_email', $editSub?->manager_email) }}"
          placeholder="Email Responsable"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
      </div>

      <textarea name="description" rows="3"
        placeholder="Description (optionnelle)"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">{{ old('description', $editSub?->description) }}</textarea>

      <div class="grid grid-cols-2 gap-3 pt-2">
        <button type="submit"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gradient-to-r from-blue-500 to-violet-600 font-semibold hover:opacity-90 transition">
          <i class="fas {{ $editSub ? 'fa-save' : 'fa-plus' }} mr-1"></i>
          {{ $editSub ? 'Mettre à jour' : 'Créer' }}
        </button>
        <a href="{{ route('admin.index', ['tab' => 'sub-entities']) }}"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gray-500 font-semibold hover:bg-gray-600 transition text-center">
          Annuler
        </a>
      </div>
    </form>
  </section>

  {{-- -- Colonne droite : Tableau --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3 max-h-[780px] overflow-auto">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-800">Directions enregistrées</h3>
      <span class="text-xs text-gray-400" id="sub-count">{{ $subEntities->count() }} direction{{ $subEntities->count() > 1 ? 's' : '' }}</span>
    </div>

    <input type="text" id="sub-search"
      oninput="subSearch(this.value)"
      placeholder="Rechercher (nom, code, parent, responsable...)"
      class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">

    <div class="overflow-auto rounded-xl border border-gray-200">
      <table class="w-full min-w-[700px] text-left" id="sub-table">
        <thead class="bg-gray-100 text-gray-600 text-[11px] uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 font-semibold">Nom</th>
            <th class="px-4 py-3 font-semibold">Code</th>
            <th class="px-4 py-3 font-semibold">Code administration</th>
            <th class="px-4 py-3 font-semibold">Parent</th>
            <th class="px-4 py-3 font-semibold">Responsable</th>
            <th class="px-4 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-xs bg-white" id="sub-tbody">
          @forelse($subEntities as $se)
          <tr class="sub-row {{ $editSub && $editSub->id === $se->id ? 'bg-blue-50' : '' }}"
              data-search="{{ strtolower($se->name . ' ' . $se->code . ' ' . ($se->administration_code ?? '') . ' ' . ($se->parent_code ?? '') . ' ' . ($se->manager_name ?? '') . ' ' . ($se->manager_email ?? '')) }}">
            <td class="px-4 py-3 font-medium text-gray-800">{{ $se->name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ $se->code }}</td>
            <td class="px-4 py-3 text-gray-600">{{ $se->administration_code ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ $se->parent_code ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600">
              @if($se->manager_name || $se->manager_email)
                {{ $se->manager_name ?? '—' }}<br><span class="text-gray-400">{{ $se->manager_email ?? '' }}</span>
              @else —
              @endif
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <a href="{{ route('admin.index', ['tab' => 'sub-entities', 'sub_action' => 'edit', 'selected_sub' => $se->id]) }}"
                   class="p-1 rounded text-blue-600 hover:bg-blue-50" title="Modifier">
                  <i class="fas fa-pen w-4 h-4 text-xs"></i>
                </a>
                <form method="POST" action="{{ route('admin.sub-entities.destroy', $se->id) }}"
                  onsubmit="return confirm('Supprimer cette direction ?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="p-1 rounded text-red-600 hover:bg-red-50" title="Supprimer">
                    <i class="fas fa-trash w-4 h-4 text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr id="sub-empty-row">
            <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">
              Aucune direction enregistrée. Créez votre première entité.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>

@push('scripts')
<script>
function subScopeTypeChange(type) {
    document.getElementById('sub-scope-type-hidden').value = type;
    const select = document.getElementById('sub-scope-id');
    const opts = select.querySelectorAll('option');
    opts.forEach(opt => {
        if (!opt.value) { opt.style.display = ''; return; }
        opt.style.display = opt.classList.contains('opt-' + type) ? '' : 'none';
    });
    select.value = '';
    document.getElementById('sub-scope-id-hidden').value = '';
    filterSubParentOptions();
}
function subScopeIdChange(id) {
    document.getElementById('sub-scope-id-hidden').value = id;
    filterSubParentOptions();
}
function filterSubParentOptions() {
    const scopeType = document.getElementById('sub-scope-type-hidden')?.value || '';
    const scopeId = document.getElementById('sub-scope-id-hidden')?.value || '';
    const parentSelect = document.getElementById('sub-parent-code');
    if (!parentSelect) return;

    const currentValue = parentSelect.value;
    let currentStillVisible = currentValue === '';

    parentSelect.querySelectorAll('option').forEach(function(opt) {
        if (!opt.value) {
            opt.hidden = false;
            return;
        }

        const sameScope = scopeType && scopeId
            ? (opt.dataset.scopeType === scopeType && opt.dataset.scopeId === scopeId)
            : false;

        opt.hidden = !sameScope;
        if (opt.value === currentValue && sameScope) currentStillVisible = true;
    });

    if (!currentStillVisible) parentSelect.value = '';
}
function subSearch(q) {
    q = q.toLowerCase().trim();
    let visible = 0, total = 0;
    document.querySelectorAll('#sub-tbody .sub-row').forEach(row => {
        total++;
        const matches = !q || row.dataset.search.includes(q);
        row.style.display = matches ? '' : 'none';
        if (matches) visible++;
    });
    document.getElementById('sub-count').textContent = visible + ' / ' + total + ' direction' + (total > 1 ? 's' : '');
    const emptyRow = document.getElementById('sub-empty-fallback');
    const tbody = document.getElementById('sub-tbody');
    if (emptyRow) emptyRow.remove();
    if (visible === 0 && total > 0) {
        const tr = document.createElement('tr');
        tr.id = 'sub-empty-fallback';
        tr.innerHTML = '<td colspan="6" class="px-4 py-6 text-center text-xs text-gray-500">Aucun résultat pour cette recherche.</td>';
        tbody.appendChild(tr);
    }
}
// Init : filter par scope type actuel
document.addEventListener('DOMContentLoaded', function() {
    const st = document.getElementById('sub-scope-type');
    const sid = document.getElementById('sub-scope-id');
    if (st && sid) {
        subScopeTypeChange(st.value);
        if (sid.value) {
            document.getElementById('sub-scope-id-hidden').value = sid.value;
            filterSubParentOptions();
        }
    }
});
</script>
@endpush

{{-- ══════════════════════ ACTES DEMANDÉS ══════════════════════ --}}
@elseif($tab === 'requested-acts')
@php
    $actAction  = request('act_action');
    $selActId   = request('selected_act');
    $editAct    = ($actAction === 'edit' && $selActId) ? $requestedActs->firstWhere('id', $selActId) : null;
    $actAdminId = old('administration_id', $editAct?->administration_id ?? '');
    $actDirCode = old('direction_code',    $editAct?->direction_code    ?? '');
@endphp

<div class="grid grid-cols-1 xl:grid-cols-[1fr_1.1fr] gap-5">

  {{-- Colonne gauche : Formulaire --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
    <div class="space-y-1">
      <h2 class="text-base font-bold text-gray-800">{{ $editAct ? "Modifier l'acte" : 'Acte demande' }}</h2>
      <p class="text-xs text-gray-500">Creez un acte demande avec l'administration, la direction, les pieces a fournir et les champs usager.</p>
    </div>

    @if(session('success') && request('tab') === 'requested-acts')
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif
    @if($errors->any() && request('tab') === 'requested-acts')
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    @if($editAct)
    <form method="POST" action="{{ route('admin.requested-acts.update', $editAct->id) }}" class="space-y-4" id="act-form">
      @method('PUT')
    @else
    <form method="POST" action="{{ route('admin.requested-acts.store') }}" class="space-y-4" id="act-form">
    @endif
      @csrf

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @if(isset($adminScope) && $adminScope && $adminScope['type'] === 'emitter')
        <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
        <div class="border border-gray-200 rounded-xl px-4 py-3 text-sm bg-gray-50 text-gray-700 flex items-center gap-2">
            <i class="fas fa-building text-gray-400"></i>
            {{ $emitters->first()?->name ?? '--' }}
        </div>
        @else
        <select name="administration_id" id="act-admin-select" required
          onchange="actFilterDirections(this.value)"
          class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
          <option value="">Administration</option>
          @foreach($emitters as $em)
          <option value="{{ $em->id }}" {{ $actAdminId === $em->id ? 'selected' : '' }}>{{ $em->name }}</option>
          @endforeach
        </select>
        @endif
        <select name="direction_code" id="act-dir-select"
          class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
          <option value="">Direction</option>
          @foreach($subEntities as $se)
          <option class="opt-dir" data-admin="{{ $se->scope_id }}" value="{{ $se->code }}" {{ $actDirCode === $se->code ? 'selected' : '' }}>
            {{ $se->code }} -- {{ $se->name }}
          </option>
          @endforeach
        </select>
      </div>

      <input type="text" name="document_name" value="{{ old('document_name', $editAct?->document_name) }}"
        placeholder="Nom du document" required
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">

      <div class="space-y-2">
        <label class="text-xs font-semibold text-gray-700">Liste des documents a fournir</label>
        <div class="flex gap-2">
          <input type="text" id="act-doc-input"
            onkeydown="if(event.key==='Enter'){event.preventDefault();actAddDoc();}"
            placeholder="Ex: Copie CNI, Extrait de naissance..."
            class="flex-1 border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
          <button type="button" onclick="actAddDoc()"
            class="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition">Ajouter</button>
        </div>
        <div id="act-docs-tags" class="flex flex-wrap gap-2 min-h-[28px]"></div>
        <input type="hidden" name="required_documents" id="act-docs-json"
          value="{{ old('required_documents', $editAct ? json_encode($editAct->required_documents ?? []) : '[]') }}">
      </div>

      <div class="space-y-2">
        <label class="text-xs font-semibold text-gray-700">Champs a renseigner par l'usager</label>
        <div class="grid grid-cols-1 md:grid-cols-[1fr_160px_auto] gap-2">
          <input type="text" id="act-field-label"
            onkeydown="if(event.key==='Enter'){event.preventDefault();actAddField();}"
            placeholder="Nom du champ (ex: Date de naissance)"
            class="border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
          <select id="act-field-type"
            class="border border-gray-300 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
            <option value="text">Texte</option>
            <option value="date">Date</option>
            <option value="number">Nombre</option>
            <option value="phone">Telephone</option>
            <option value="email">Email</option>
            <option value="textarea">Texte long</option>
          </select>
          <button type="button" onclick="actAddField()"
            class="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition">Ajouter</button>
        </div>
        <div id="act-fields-tags" class="flex flex-wrap gap-2 min-h-[28px]"></div>
        <input type="hidden" name="applicant_fields" id="act-fields-json"
          value="{{ old('applicant_fields', $editAct ? json_encode($editAct->applicant_fields ?? []) : '[]') }}">
      </div>

      <div class="flex gap-2 pt-1">
        <button type="submit"
          class="flex-1 rounded-xl px-4 py-3 text-sm text-white bg-amber-500 hover:bg-amber-600 font-semibold transition">
          <i class="fas {{ $editAct ? 'fa-save' : 'fa-plus' }} mr-1"></i>
          {{ $editAct ? 'Enregistrer les modifications' : "Enregistrer l'acte demande" }}
        </button>
        @if($editAct)
        <a href="{{ route('admin.index', ['tab' => 'requested-acts']) }}"
          class="rounded-xl px-4 py-3 text-sm font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Annuler</a>
        @endif
      </div>
    </form>
  </section>

  {{-- Colonne droite : Liste des actes --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3 max-h-[780px] overflow-auto">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-800">Actes demandes</h3>
      <span class="text-xs text-gray-400" id="act-count">{{ $requestedActs->count() }} element{{ $requestedActs->count() > 1 ? 's' : '' }}</span>
    </div>

    <input type="text" id="act-search" oninput="actSearch(this.value)"
      placeholder="Rechercher un acte (nom, administration, direction)..."
      class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">

    @if($requestedActs->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-200 p-6 text-xs text-gray-500 text-center">
      Aucun acte demande enregistre pour le moment.
    </div>
    @else
    <div class="space-y-3" id="act-cards">
      @foreach($requestedActs as $act)
      @php
        $actSearch = strtolower($act->document_name . ' ' . ($act->administration?->name ?? '') . ' ' . ($act->direction_code ?? '') . ' ' . implode(' ', $act->required_documents ?? []) . ' ' . collect($act->applicant_fields ?? [])->pluck('label')->implode(' '));
      @endphp
      <div class="act-card rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2 {{ $editAct && $editAct->id === $act->id ? 'border-amber-400 bg-amber-50' : '' }}"
        data-search="{{ $actSearch }}">
        <p class="text-sm font-semibold text-gray-800">{{ $act->document_name }}</p>
        <p class="text-xs text-gray-600">Administration : {{ $act->administration?->name ?? '&mdash;' }}</p>
        <p class="text-xs text-gray-600">Direction : {{ $act->direction_code ?? '&mdash;' }}</p>
        <p class="text-xs text-gray-400">Cree le {{ $act->created_at->format('d/m/Y H:i') }}</p>
        @if(!empty($act->required_documents))
        <div class="pt-1">
          <p class="text-xs font-semibold text-gray-700 mb-1">Documents a fournir :</p>
          <ul class="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
            @foreach($act->required_documents as $doc)<li>{{ $doc }}</li>@endforeach
          </ul>
        </div>
        @endif
        @if(!empty($act->applicant_fields))
        <div class="pt-1">
          <p class="text-xs font-semibold text-gray-700 mb-1">Champs usager :</p>
          <ul class="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
            @foreach($act->applicant_fields as $f)<li>{{ $f['label'] ?? '' }} ({{ $f['inputType'] ?? '' }})</li>@endforeach
          </ul>
        </div>
        @endif
        <div class="pt-2 flex gap-2">
          <a href="{{ route('admin.index', ['tab' => 'requested-acts', 'act_action' => 'edit', 'selected_act' => $act->id]) }}"
            class="text-xs px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200 transition">
            <i class="fas fa-pen mr-1 text-[10px]"></i> Modifier
          </a>
          <a href="{{ route('public.act-requests.by-admin', $act->administration_id) }}"
             target="_blank"
             class="text-[11px] px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 transition">
            <i class="fas fa-external-link-alt mr-1 text-[9px]"></i> Lien grand public
          </a>
          <form method="POST" action="{{ route('admin.requested-acts.destroy', $act->id) }}"
            onsubmit="return confirm('Supprimer cet acte ?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
              <i class="fas fa-trash mr-1 text-[10px]"></i> Supprimer
            </button>
          </form>
        </div>
      </div>
      @endforeach
    </div>
    @endif
  </section>
</div>

@push('scripts')
<script>
var actDocs = [], actFields = [];
function actRenderDocs() {
    var c = document.getElementById('act-docs-tags'); c.innerHTML = '';
    actDocs.forEach(function(doc, i) {
        var s = document.createElement('span');
        s.className = 'inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs text-blue-700';
        s.innerHTML = doc + ' <button type="button" onclick="actRemoveDoc('+i+')" class="text-blue-600 hover:text-blue-800 font-bold">&times;</button>';
        c.appendChild(s);
    });
    document.getElementById('act-docs-json').value = JSON.stringify(actDocs);
}
function actAddDoc() {
    var inp = document.getElementById('act-doc-input'), val = inp.value.trim();
    if (!val) return; actDocs.push(val); inp.value = ''; actRenderDocs();
}
function actRemoveDoc(i) { actDocs.splice(i,1); actRenderDocs(); }

function actRenderFields() {
    var c = document.getElementById('act-fields-tags'); c.innerHTML = '';
    actFields.forEach(function(f, i) {
        var s = document.createElement('span');
        s.className = 'inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs text-indigo-700';
        s.innerHTML = f.label+' ('+f.inputType+') <button type="button" onclick="actRemoveField('+i+')" class="text-indigo-600 hover:text-indigo-800 font-bold">&times;</button>';
        c.appendChild(s);
    });
    document.getElementById('act-fields-json').value = JSON.stringify(actFields);
}
function actAddField() {
    var label = document.getElementById('act-field-label').value.trim();
    var type  = document.getElementById('act-field-type').value;
    if (!label) return;
    actFields.push({label:label, inputType:type});
    document.getElementById('act-field-label').value = '';
    actRenderFields();
}
function actRemoveField(i) { actFields.splice(i,1); actRenderFields(); }

function actFilterDirections(adminId) {
    var sel = document.getElementById('act-dir-select');
    sel.querySelectorAll('option.opt-dir').forEach(function(opt) {
        opt.style.display = (!adminId || opt.dataset.admin === adminId) ? '' : 'none';
    });
    if (sel.value && sel.selectedOptions[0] && sel.selectedOptions[0].dataset.admin !== adminId) sel.value = '';
}
function actSearch(q) {
    q = q.toLowerCase().trim();
    var vis = 0, tot = 0;
    document.querySelectorAll('#act-cards .act-card').forEach(function(card) {
        tot++; var m = !q || card.dataset.search.includes(q);
        card.style.display = m ? '' : 'none'; if (m) vis++;
    });
    document.getElementById('act-count').textContent = vis + ' element' + (vis > 1 ? 's' : '');
}
document.addEventListener('DOMContentLoaded', function() {
    try { actDocs   = JSON.parse(document.getElementById('act-docs-json').value   || '[]'); } catch(e){}
    try { actFields = JSON.parse(document.getElementById('act-fields-json').value || '[]'); } catch(e){}
    actRenderDocs(); actRenderFields();
    var a = document.getElementById('act-admin-select');
    if (a && a.value) actFilterDirections(a.value);
});
</script>
@endpush

@elseif($tab === 'direction-types')
@php
    $dtAction = request('dt_action');
    $selDtId  = request('selected_dt');
    $editDt   = ($dtAction === 'edit' && $selDtId) ? $directionTypes->firstWhere('id', $selDtId) : null;
@endphp

<div class="grid grid-cols-1 xl:grid-cols-[0.95fr_1.05fr] gap-5">

  {{-- Colonne gauche : Formulaire --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
    <div class="space-y-1">
      <h2 class="text-base font-bold text-gray-800">{{ $editDt ? 'Modifier le type' : 'Nouveau Type de Direction' }}</h2>
      <p class="text-xs text-gray-500">Créez les types utilisés dans les formulaires des entités sous tutelle.</p>
    </div>

    @if(session('success') && request('tab') === 'direction-types')
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif
    @if($errors->any() && request('tab') === 'direction-types')
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    @if($editDt)
    <form method="POST" action="{{ route('admin.direction-types.update', $editDt->id) }}" class="space-y-4">
      @method('PUT')
    @else
    <form method="POST" action="{{ route('admin.direction-types.store') }}" class="space-y-4">
    @endif
      @csrf

      <input type="text" name="name" value="{{ old('name', $editDt?->name) }}"
        placeholder="Nom du type" required
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none">

      <textarea name="description" rows="4"
        placeholder="{{ __('personnel.ui.career.form_description_placeholder') }}"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none">{{ old('description', $editDt?->description) }}</textarea>

      <div class="grid grid-cols-2 gap-3 pt-1">
        <button type="submit"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gradient-to-r from-blue-500 to-violet-600 font-semibold hover:opacity-90 transition">
          <i class="fas {{ $editDt ? 'fa-save' : 'fa-plus' }} mr-1"></i>
          {{ $editDt ? 'Modifier' : 'Créer' }}
        </button>
        <a href="{{ route('admin.index', ['tab' => 'direction-types']) }}"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gray-500 font-semibold hover:bg-gray-600 transition text-center">
          Annuler
        </a>
      </div>
    </form>
  </section>

  {{-- Colonne droite : Tableau --}}
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-800">Liste des types</h3>
      <span class="text-xs text-gray-400" id="dt-count">{{ $directionTypes->count() }} type{{ $directionTypes->count() > 1 ? 's' : '' }}</span>
    </div>

    <input type="text" id="dt-search" placeholder="Rechercher un type de direction…"
           oninput="dtSearch(this.value)"
           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none">

    <div class="overflow-auto rounded-xl border border-gray-200">
      <table class="w-full min-w-[520px] text-left">
        <thead class="bg-gray-100 text-gray-600 text-[11px] uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 font-semibold">Nom du type</th>
            <th class="px-4 py-3 font-semibold">Description</th>
            <th class="px-4 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-xs bg-white" id="dt-tbody">
          @forelse($directionTypes as $dt)
          <tr data-search="{{ strtolower($dt->name . ' ' . ($dt->description ?? '')) }}"
              class="{{ $editDt && $editDt->id === $dt->id ? 'bg-blue-50' : '' }}">
            <td class="px-4 py-3 font-medium text-gray-800">{{ $dt->name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ $dt->description ?? '�' }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <a href="{{ route('admin.index', ['tab' => 'direction-types', 'dt_action' => 'edit', 'selected_dt' => $dt->id]) }}"
                   class="p-1 rounded text-blue-600 hover:bg-blue-50" title="Modifier">
                  <i class="fas fa-pen text-xs"></i>
                </a>
                <form method="POST" action="{{ route('admin.direction-types.destroy', $dt) }}"
                  onsubmit="return confirm('Supprimer ce type ?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="p-1 rounded text-red-600 hover:bg-red-50" title="Supprimer">
                    <i class="fas fa-trash text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="px-4 py-8 text-center text-xs text-gray-500">Aucun type de direction enregistr�.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@push('scripts')
<script>
function dtSearch(q) {
    q = q.toLowerCase().trim();
    var rows = document.querySelectorAll('#dt-tbody [data-search]');
    var visible = 0;
    rows.forEach(function(row) {
        var m = !q || row.dataset.search.includes(q);
        row.style.display = m ? '' : 'none';
        if (m) visible++;
    });
    var cnt = document.getElementById('dt-count');
    if (cnt) cnt.textContent = visible + ' / ' + rows.length + ' type' + (rows.length > 1 ? 's' : '');
}
</script>
@endpush
@elseif($tab === 'routing')
<div class="flex items-center justify-between mb-5">
    <h2 class="text-lg font-bold text-gray-800">R�gles de routage</h2>
    <button onclick="openModal('modal-routing-create')"
        class="px-4 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition flex items-center gap-2">
        <i class="fas fa-plus"></i> Nouvelle r�gle
    </button>
</div>
<div class="mb-3">
    <input type="text" id="routing-search" placeholder="Rechercher (template, destinataire, condition…)"
           oninput="routingSearch(this.value)"
           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none">
</div>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Template</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Destinataire</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Condition</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Priorit�</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Statut</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50" id="routing-tbody">
            @forelse($routingRules as $rule)
            <tr data-search="{{ strtolower(($rule->template?->name ?? '') . ' ' . ($rule->recipient?->name ?? '') . ' ' . ($rule->condition_field ?? '') . ' ' . ($rule->condition_value ?? '')) }}"
                class="hover:bg-gray-50/50 transition">
                <td class="px-5 py-3.5 font-medium text-gray-800">{{ $rule->template?->name ?? '—' }}</td>
                <td class="px-5 py-3.5 text-gray-600">{{ $rule->recipient?->name ?? '—' }}</td>
                <td class="px-5 py-3.5 text-xs text-gray-500">
                    @if($rule->condition_field)
                        <code class="bg-gray-100 px-1.5 py-0.5 rounded">{{ $rule->condition_field }}</code>
                        {{ $rule->condition_operator }}
                        <code class="bg-gray-100 px-1.5 py-0.5 rounded">{{ $rule->condition_value }}</code>
                    @else
                        <span class="text-gray-400 italic">Toujours</span>
                    @endif
                </td>
                <td class="px-5 py-3.5 text-center font-bold text-gray-600">{{ $rule->priority ?? 0 }}</td>
                <td class="px-5 py-3.5">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $rule->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $rule->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                        {{ $rule->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-5 py-3.5 text-right">
                    <form method="POST" action="{{ route('admin.routing-rules.destroy', $rule) }}" onsubmit="return confirm('Supprimer cette r�gle ?')" class="inline">
                        @csrf @method('DELETE')
                        <button class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg transition">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">Aucune r�gle de routage configur�e.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($routingRules->hasPages())
    <div class="px-5 py-4 border-t border-gray-100">{{ $routingRules->appends(['tab'=>'routing'])->links() }}</div>
    @endif
</div>
@push('scripts')
<script>
function routingSearch(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#routing-tbody [data-search]').forEach(function(row) {
        row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
    });
}
</script>
@endpush
<div id="modal-routing-create" class="adm-modal">
    <div class="adm-modal-box">
        <button onclick="closeModal('modal-routing-create')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-5">Nouvelle r�gle de routage</h3>
        <form method="POST" action="{{ route('admin.routing-rules.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Template <span class="text-red-500">*</span></label>
                <select name="template_id" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">« Sélectionner »</option>
                    @foreach($templates as $tpl)
                    <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Destinataire <span class="text-red-500">*</span></label>
                <select name="recipient_id" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">« Sélectionner »</option>
                    @foreach($recipients as $recip)
                    <option value="{{ $recip->id }}">{{ $recip->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Champ</label>
                    <input type="text" name="condition_field" placeholder="status" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Op�rateur</label>
                    <select name="condition_operator" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <option value="=">=</option>
                        <option value="!=">!=</option>
                        <option value="contains">contains</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Valeur</label>
                    <input type="text" name="condition_value" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Priorit�</label>
                <input type="number" name="priority" value="0" min="0" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div class="pt-2 flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modal-routing-create')" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Annuler</button>
                <button type="submit" class="px-5 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition">Cr�er</button>
            </div>
        </form>
    </div>
</div>

{{-- ══════════════════════ ONLYOFFICE ══════════════════════ --}}
@elseif($tab === 'onlyoffice')
@php
    $ooUrl         = $settings['onlyoffice_server_url']->value  ?? '';
    $ooDisableCert = ($settings['onlyoffice_disable_cert']->value ?? '0') === '1';
    $ooSecret      = $settings['onlyoffice_secret']->value       ?? '';
    $ooViewer      = $settings['onlyoffice_doc_viewer']->value   ?? 'onlyoffice';
    $appPublicUrl  = $settings['app_public_url']->value          ?? '';
    $qrPage        = $settings['qr_image_page']->value           ?? '-1';
    $qrX           = $settings['qr_image_x']->value              ?? '390';
    $qrY           = $settings['qr_image_y']->value              ?? '710';
    $qrW           = $settings['qr_image_width']->value          ?? '150';
    $qrH           = $settings['qr_image_height']->value         ?? '80';
@endphp

<div class="max-w-2xl space-y-6">

  {{-- En-tête ONLYOFFICE --}}
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <p class="text-xs font-bold uppercase tracking-widest text-[#2453d6] mb-1">ONLYOFFICE</p>
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Bienvenue dans ONLYOFFICE&nbsp;Docs&nbsp;!</h2>
    <p class="text-sm text-gray-500 mb-4">
      Modifiez et collaborez sur des documents texte, des feuilles de calcul, des présentations
      et des fichiers PDF à l'aide de ONLYOFFICE Docs.
    </p>
    <div class="flex gap-4 text-sm font-semibold text-[#2453d6]">
      <a href="https://www.onlyoffice.com/fr/" target="_blank" rel="noreferrer" class="hover:underline">En savoir plus &#8599;</a>
      <a href="https://www.onlyoffice.com/fr/feedback.aspx" target="_blank" rel="noreferrer" class="hover:underline">Suggérer une fonctionnalité &#8599;</a>
    </div>
  </div>

  {{-- Choix du lecteur de documents --}}
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-1">Lecteur de documents</h3>
    <p class="text-sm text-gray-500 mb-4">Choisissez le lecteur à utiliser pour afficher et éditer les documents.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="oo-viewer-selector">
      {{-- Option OnlyOffice --}}
      <button type="button" onclick="selectOoViewer('onlyoffice')" id="oo-btn-onlyoffice"
        class="relative flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition cursor-pointer {{ $ooViewer === 'onlyoffice' ? 'border-[#2453d6] bg-blue-50 shadow-md' : 'border-gray-200 bg-white hover:border-gray-300' }}">
        <span id="oo-check-onlyoffice" class="{{ $ooViewer === 'onlyoffice' ? '' : 'hidden' }} absolute top-2 right-2 w-5 h-5 bg-[#2453d6] rounded-full flex items-center justify-center">
          <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
        </span>
        <svg class="w-10 h-10 text-[#2453d6]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
        <div class="text-center">
          <p class="text-sm font-semibold text-gray-900">OnlyOffice</p>
          <p class="text-xs text-gray-500 mt-1">Éditeur collaboratif complet (DOCX, XLSX, PPTX, PDF)</p>
        </div>
      </button>
      {{-- Option Lecteur natif --}}
      <button type="button" onclick="selectOoViewer('native')" id="oo-btn-native"
        class="relative flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition cursor-pointer {{ $ooViewer === 'native' ? 'border-[#2453d6] bg-blue-50 shadow-md' : 'border-gray-200 bg-white hover:border-gray-300' }}">
        <span id="oo-check-native" class="{{ $ooViewer === 'native' ? '' : 'hidden' }} absolute top-2 right-2 w-5 h-5 bg-[#2453d6] rounded-full flex items-center justify-center">
          <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
        </span>
        <svg class="w-10 h-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <div class="text-center">
          <p class="text-sm font-semibold text-gray-900">Lecteur PDF natif</p>
          <p class="text-xs text-gray-500 mt-1">Lecteur intégré du navigateur (PDF uniquement, lecture seule)</p>
        </div>
      </button>
    </div>
  </div>

  {{-- Formulaire paramètres serveur --}}
  <form method="POST" action="{{ route('admin.settings.save') }}" class="space-y-6">
    @csrf @method('PUT')
    <input type="hidden" name="tab" value="onlyoffice">
    <input type="hidden" name="onlyoffice_doc_viewer" id="oo-viewer-input" value="{{ old('onlyoffice_doc_viewer', $ooViewer) }}">

    {{-- Paramètres serveur --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
      <div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">Paramètres du serveur</h3>
        <p class="text-sm text-gray-500">
          L'emplacement du ONLYOFFICE Docs désigne l'adresse du serveur sur lequel est installé
          le service de document. Veuillez remplacer <code class="text-xs bg-gray-100 px-1 rounded">&lt;documentserver&gt;</code>
          avec l'adresse de votre serveur.
        </p>
      </div>

      @if(session('success') && request('tab') === 'onlyoffice')
      <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
      </div>
      @endif

      {{-- URL --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Adresse du ONLYOFFICE Docs</label>
        <input type="url" name="onlyoffice_server_url"
          value="{{ old('onlyoffice_server_url', $ooUrl) }}"
          placeholder="https://<documentserver>/"
          class="w-full max-w-sm border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
      </div>

      {{-- URL publique de l'application --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          URL publique de cette application
          <span class="text-red-500 font-bold ml-1">*</span>
        </label>
        <p class="text-xs text-gray-400 mb-2">
          URL accessible depuis Internet (pas localhost). Le serveur OnlyOffice télécharge le document vierge depuis cette URL.
          Ex : <code class="bg-gray-100 px-1 rounded">https://votre-app.exemple.com</code>
        </p>
        <input type="url" name="app_public_url"
          value="{{ old('app_public_url', $appPublicUrl) }}"
          placeholder="https://votre-app.exemple.com"
          class="w-full max-w-sm border {{ $appPublicUrl ? 'border-gray-300' : 'border-red-300 bg-red-50' }} rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
        @if(!$appPublicUrl)
        <p class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Non configurée : l'éditeur de templates ne peut pas charger les fichiers tant que ce champ est vide.</p>
        @endif
        @if($appPublicUrl)
        <div class="mt-2 flex items-center gap-2">
            <button type="button" onclick="testOoBlankUrl()"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-50 border border-blue-200 text-blue-700 hover:bg-blue-100">
                <i class="fas fa-vial mr-1"></i> Tester l'accès
            </button>
            <span id="oo-url-test-result" class="text-xs"></span>
        </div>
        @endif
        <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
            <p class="font-semibold mb-1"><i class="fas fa-info-circle mr-1"></i>En développement local :</p>
            <p>Utilisez <a href="https://ngrok.com" target="_blank" class="underline font-semibold">ngrok</a> pour exposer votre serveur :</p>
            <code class="block mt-1 bg-amber-100 rounded px-2 py-1 font-mono text-xs">ngrok http 80</code>
            <p class="mt-1">Copiez l'URL <code>https://xxxx.ngrok-free.app</code> ci-dessus et enregistrez.</p>
        </div>
      </div>

      {{-- Désactiver certificat --}}
      <div class="flex items-center gap-2">
        <input type="hidden" name="onlyoffice_disable_cert" value="0">
        <input id="oo-cert" type="checkbox" name="onlyoffice_disable_cert" value="1"
          {{ $ooDisableCert ? 'checked' : '' }}
          class="h-4 w-4 rounded border-gray-300 text-[#2453d6]">
        <label for="oo-cert" class="text-sm text-gray-700">
          Désactiver la vérification du certificat <span class="text-gray-400 text-xs">(non sûr)</span>
        </label>
      </div>

      {{-- Clé secrète --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Clé secrète <span class="text-gray-400 font-normal text-xs">(laisser vide pour désactiver)</span>
        </label>
        <div class="relative w-full max-w-sm">
          <input id="oo-secret-input" type="password" name="onlyoffice_secret"
            value="{{ old('onlyoffice_secret', $ooSecret) }}"
            placeholder="Saisir votre clé secrète"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm pr-9 focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
          <button type="button" onclick="toggleOoSecret()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <i id="oo-secret-eye" class="fas fa-eye text-sm"></i>
          </button>
        </div>
      </div>
    </div>

    {{-- Position du QR code --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
      <div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">Position du QR code sur les documents</h3>
        <p class="text-sm text-gray-500">Paramètre utilisé lors de la génération des fichiers dans l'onglet <strong>Templates partagés</strong>. Coordonnées en points sur une page A4.</p>
      </div>

      <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
        <p class="text-sm font-medium text-gray-700">Position visuelle du QR sur le document (A4)</p>
        <p class="text-xs text-gray-400">Valeurs par défaut : Page&nbsp;-1 (dernière page), X&nbsp;390, Y&nbsp;710, Largeur&nbsp;150, Hauteur&nbsp;80</p>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-2">
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">Page</label>
            <input type="number" name="qr_image_page" value="{{ old('qr_image_page', $qrPage) }}"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">X</label>
            <input type="number" name="qr_image_x" value="{{ old('qr_image_x', $qrX) }}"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">Y</label>
            <input type="number" name="qr_image_y" value="{{ old('qr_image_y', $qrY) }}"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">Largeur</label>
            <input type="number" name="qr_image_width" value="{{ old('qr_image_width', $qrW) }}"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">Hauteur</label>
            <input type="number" name="qr_image_height" value="{{ old('qr_image_height', $qrH) }}"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
        </div>

        {{-- Aperçu visuel A4 --}}
        <div class="mt-3">
          <p class="text-[11px] text-gray-400 mb-1">Aperçu position (proportionnel A4)</p>
          <div class="relative bg-white border border-gray-300 rounded" style="width:120px;height:170px;overflow:hidden;">
            <div id="qr-preview-box" class="absolute bg-[#2453d6]/20 border border-[#2453d6] rounded-sm text-[8px] text-[#2453d6] flex items-center justify-center font-bold" style="width:24px;height:14px;left:56px;top:116px;">QR</div>
          </div>
          <p class="text-[10px] text-gray-400 mt-1">A4&nbsp;: 595&times;842 pts</p>
        </div>
      </div>
    </div>

    {{-- Bouton sauvegarder --}}
    <div class="flex items-center gap-3">
      <button type="submit"
        class="px-6 py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
        <i class="fas fa-save"></i> Enregistrer
      </button>
    </div>
  </form>
</div>

<script>
function selectOoViewer(v) {
  document.getElementById('oo-viewer-input').value = v;
  ['onlyoffice','native'].forEach(function(k) {
    var btn   = document.getElementById('oo-btn-' + k);
    var check = document.getElementById('oo-check-' + k);
    if (k === v) {
      btn.classList.add('border-[#2453d6]','bg-blue-50','shadow-md');
      btn.classList.remove('border-gray-200','bg-white');
      check.classList.remove('hidden');
    } else {
      btn.classList.remove('border-[#2453d6]','bg-blue-50','shadow-md');
      btn.classList.add('border-gray-200','bg-white');
      check.classList.add('hidden');
    }
  });
}
function testOoBlankUrl() {
  var resultEl = document.getElementById('oo-url-test-result');
  resultEl.textContent = 'Test en cours...';
  resultEl.className = 'text-xs text-gray-500';
  var appUrl = '{{ $appPublicUrl }}';
  var testUrl = appUrl.replace(/\/$/, '') + '/oo-blank/docx';
  fetch(testUrl, { method: 'HEAD', mode: 'no-cors' })
    .then(function() {
      resultEl.textContent = '✓ URL accessible (vérifiez aussi depuis onlyoffice.ci)';
      resultEl.className = 'text-xs text-green-600 font-semibold';
    })
    .catch(function() {
      resultEl.textContent = '✗ URL inaccessible depuis ce navigateur';
      resultEl.className = 'text-xs text-red-500 font-semibold';
    });
}
function toggleOoSecret() {
  var inp = document.getElementById('oo-secret-input');
  var eye = document.getElementById('oo-secret-eye');
  if (inp.type === 'password') { inp.type = 'text';     eye.className = 'fas fa-eye-slash text-sm'; }
  else                         { inp.type = 'password'; eye.className = 'fas fa-eye text-sm'; }
}
// Aperçu QR dynamique
(function() {
  var A4w = 595, A4h = 842;
  var previewW = 120, previewH = 170;
  function updateQrPreview() {
    var box = document.getElementById('qr-preview-box');
    if (!box) return;
    var x = parseFloat(document.querySelector('[name=qr_image_x]').value) || 390;
    var y = parseFloat(document.querySelector('[name=qr_image_y]').value) || 710;
    var w = parseFloat(document.querySelector('[name=qr_image_width]').value) || 150;
    var h = parseFloat(document.querySelector('[name=qr_image_height]').value) || 80;
    box.style.left   = ((x / A4w) * previewW) + 'px';
    box.style.top    = ((y / A4h) * previewH) + 'px';
    box.style.width  = ((w / A4w) * previewW) + 'px';
    box.style.height = ((h / A4h) * previewH) + 'px';
  }
  ['qr_image_x','qr_image_y','qr_image_width','qr_image_height'].forEach(function(n) {
    var el = document.querySelector('[name=' + n + ']');
    if (el) el.addEventListener('input', updateQrPreview);
  });
  updateQrPreview();
})();
</script>
@elseif($tab === 'users')
@php
    $usersSearch    = request('u_search', '');
    $editUserId     = request('u_edit');
  $editUserData   = null;
  if (request('u_data')) {
    $decoded = base64_decode((string) request('u_data'), true);
    if ($decoded !== false) {
      $parsed = json_decode($decoded, true);
      if (is_array($parsed)) {
        $editUserData = $parsed;
      }
    }
  }
    $editUser       = $editUserId ? $users->getCollection()->firstWhere('id', $editUserId) : null;
  if (!$editUser && $editUserId) {
    $editUser = \App\Models\User::query()->whereKey($editUserId)->first();
  }
  $editDir        = $editUser ? ($dirAssignments->get($editUser->id) ?: \App\Models\UserDirectionAssignment::query()->where('user_id', $editUser->id)->first()) : null;
  $editParts      = preg_split('/\s+/', trim($editUser->full_name ?? $editUser->name ?? ''));
  $editNom        = $editUser ? (count($editParts) > 1 ? end($editParts) : ($editUser->name ?? '')) : ($editUserData['nom'] ?? '');
  $editPrenoms    = $editUser ? (count($editParts) > 1 ? implode(' ', array_slice($editParts, 0, -1)) : '') : ($editUserData['prenoms'] ?? '');
  $editScopeType  = old('administration_type', $editDir?->direction_scope_type ?? ($editUserData['scope_type'] ?? ''));
  $editScopeId    = old('administration_id', $editDir?->direction_scope_id ?? ($editUserData['scope_id'] ?? ''));
  $editSubCode    = old('sub_entity_id', $editDir?->sub_entity_code ?? ($editUserData['sub_code'] ?? ''));
  $editUserActionId = $editUser?->id ?? ($editUserData['id'] ?? $editUserId);
    $allRoles       = collect(['admin','user','signer','manager']);
    $emittersJson   = $emitters->map(fn($e) => ['id'=>$e->id,'name'=>$e->name])->values();
    $recipientsJson = $recipients->map(fn($r) => ['id'=>$r->id,'name'=>$r->name])->values();
    $subEntJson     = $subEntities->map(fn($s) => ['id'=>$s->id,'name'=>$s->name,'code'=>$s->code,'scope_id'=>$s->scope_id,'scope_type'=>$s->scope_type])->values();
    $profilesJson   = $profilesList->map(fn($p) => ['id'=>$p->id,'name'=>$p->name,'administration_id'=>$p->administration_id,'administration_type'=>$p->administration_type])->values();
    $filteredUsers  = $usersSearch
        ? $users->getCollection()->filter(fn($u) => str_contains(strtolower($u->name ?? ''), strtolower($usersSearch))
            || str_contains(strtolower($u->full_name ?? ''), strtolower($usersSearch))
            || str_contains(strtolower($u->email ?? ''), strtolower($usersSearch))
            || str_contains(strtolower($u->role ?? ''), strtolower($usersSearch))
          )
        : $users->getCollection();
@endphp

<div class="space-y-4">

  {{-- En-tête --}}
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center justify-between">
    <div>
      <h2 class="text-base font-semibold text-gray-800">Utilisateurs de l'application</h2>
      <p class="text-xs text-gray-500 mt-0.5">{{ $filteredUsers->count() }} utilisateur{{ $filteredUsers->count() !== 1 ? 's' : '' }}</p>
    </div>
    <button type="button" onclick="openUserModal('create')"
      class="px-4 py-2 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-xs font-semibold rounded-lg transition">
      + Nouvel utilisateur
    </button>
  </div>

  @if(session('success') && request('tab') === 'users')
  <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
  </div>
  @endif

  {{-- Tableau --}}
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-auto">
    <div class="p-4 border-b border-gray-100">
      <form method="GET" action="{{ route('admin.index') }}" class="flex items-center gap-2">
        <input type="hidden" name="tab" value="users">
        <input type="text" name="u_search" value="{{ $usersSearch }}"
          placeholder="Rechercher un utilisateur (nom, email, rôle)..."
          class="w-full md:w-[420px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
        @if($usersSearch)
        <a href="{{ route('admin.index', ['tab'=>'users']) }}" class="text-xs text-gray-400 hover:text-gray-600 whitespace-nowrap">✕ Effacer</a>
        @endif
      </form>
    </div>
    <table class="w-full text-xs">
      <thead>
        <tr class="border-b border-gray-100 text-gray-500 font-semibold bg-gray-50">
          <th class="px-4 py-3 text-left">Nom</th>
          <th class="px-4 py-3 text-left">Prénoms</th>
          <th class="px-4 py-3 text-left">Rôle</th>
          <th class="px-4 py-3 text-left">Direction</th>
          <th class="px-4 py-3 text-left">E-mail</th>
          <th class="px-4 py-3 text-left">Quota</th>
          <th class="px-4 py-3 text-left">Statut</th>
          <th class="px-4 py-3 text-left">Date création</th>
          <th class="px-4 py-3 text-left">Date modification</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        @forelse($filteredUsers as $u)
        @php
          $parts   = preg_split('/\s+/', trim($u->full_name ?? $u->name ?? ''));
          $nom     = count($parts) > 1 ? end($parts) : ($u->name ?? '');
          $prenoms = count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) : '';
          $dir     = $dirAssignments->get($u->id);
        @endphp
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-800 font-medium">{{ $nom ?: '-' }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $prenoms ?: '-' }}</td>
          <td class="px-4 py-3 text-gray-600">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
              {{ $u->role === 'admin' ? 'bg-purple-100 text-purple-700' : ($u->role === 'signer' ? 'bg-blue-100 text-blue-700' : ($u->role === 'manager' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600')) }}">
              {{ $u->role ?? '-' }}
            </span>
          </td>
          <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate" title="{{ $dir?->direction_label ?? '' }}">
            {{ $dir?->direction_label ?: '-' }}
          </td>
          <td class="px-4 py-3 text-gray-600">{{ $u->email }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $u->quota ?: '-' }}</td>
          <td class="px-4 py-3">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold
              {{ $u->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
              {{ $u->status === 'active' ? 'Actif' : 'Désactivé' }}
            </span>
          </td>
          <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $u->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
          <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $u->updated_at?->format('d/m/Y H:i') ?? '-' }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              {{-- Modifier --}}
              @php
                $editUserJson = json_encode([
                  'id' => $u->id,
                  'nom' => $nom,
                  'prenoms' => $prenoms,
                  'name' => $u->name,
                  'email' => $u->email,
                  'role' => $u->role,
                  'profile_id' => $u->profile_id ?? '',
                  'status' => $u->status,
                  'quota' => $u->quota ?? '',
                  'scope_type' => $dir?->direction_scope_type ?? '',
                  'scope_id' => $dir?->direction_scope_id ?? '',
                  'sub_code' => $dir?->sub_entity_code ?? '',
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $editUserPayload = base64_encode($editUserJson !== false ? $editUserJson : '{}');
              @endphp
              <a href="{{ route('admin.index', ['tab' => 'users', 'u_search' => $usersSearch, 'u_edit' => $u->id, 'u_data' => $editUserPayload, 'page' => request('page')]) }}"
                data-user="{{ $editUserPayload }}"
                class="js-user-edit-link text-blue-600 hover:bg-blue-50 rounded p-1.5 transition" title="Modifier">
                <i class="fas fa-pen text-xs"></i>
              </a>
              {{-- Notifier par email --}}
              <form method="POST" action="{{ route('admin.users-tab.notify-account', $u) }}" class="inline">
                @csrf
                <button type="submit" class="text-amber-600 hover:bg-amber-50 rounded p-1.5 transition"
                  title="Notifier la création de compte par email">
                  <i class="fas fa-envelope text-xs"></i>
                </button>
              </form>
              {{-- Toggle statut --}}
              <form method="POST" action="{{ route('admin.users-tab.toggle-status', $u) }}" class="inline">
                @csrf
                <button type="submit"
                  class="{{ $u->status === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50' }} rounded p-1.5 transition"
                  title="{{ $u->status === 'active' ? 'Désactiver' : 'Activer' }}">
                  <i class="fas {{ $u->status === 'active' ? 'fa-ban' : 'fa-check-circle' }} text-xs"></i>
                </button>
              </form>
              {{-- Supprimer --}}
              <form method="POST" action="{{ route('admin.users-tab.destroy', $u) }}" onsubmit="return confirm('Supprimer cet utilisateur ?')" class="inline">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 hover:bg-red-50 rounded p-1.5 transition" title="Supprimer">
                  <i class="fas fa-trash text-xs"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="10" class="px-4 py-8 text-center text-gray-400">Aucun utilisateur trouvé.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
    @if($users->hasPages())
    <div class="p-4 border-t border-gray-100">{{ $users->appends(['tab'=>'users','u_search'=>$usersSearch])->links() }}</div>
    @endif
  </div>
</div>

{{-- ══ MODAL CRÉER UTILISATEUR ══════════════════════════════════════════════ --}}
<div id="modal-user-create" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="text-base font-semibold text-gray-800">Créer un utilisateur</h3>
      <button type="button" onclick="closeUserModal('create')" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST" action="{{ route('admin.users-tab.store') }}" enctype="multipart/form-data"
      class="flex flex-col flex-1 overflow-y-auto px-6 py-4 space-y-4">
      @csrf

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Rôle système <span class="text-red-500">*</span></label>
        <select name="role" required
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <option value="user">Utilisateur</option>
          <option value="manager">Manager</option>
          <option value="signer">Signataire</option>
          <option value="admin">Administrateur</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Nom <span class="text-red-500">*</span></label>
          <input type="text" name="nom" placeholder="Nom de famille" required
            class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Prénoms</label>
          <input type="text" name="prenoms" placeholder="Prénoms"
            class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Nom affiché <span class="text-red-500">*</span></label>
        <input type="text" name="name" placeholder="Nom tel qu'il apparaît dans l'application" required
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Profil applicatif</label>
        <select id="c-profile" name="profile_id"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <option value="">« Aucun profil applicatif (super-admin si rôle admin) »</option>
          @foreach($profiles as $p)
          <option value="{{ $p->id }}">{{ $p->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        @if(isset($adminScope) && $adminScope && !$canManageAdministration)
        <input type="hidden" name="administration_type" value="{{ $adminScope['type'] }}">
        <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
        <div class="col-span-2 border border-blue-100 rounded-lg px-3 py-2.5 text-sm bg-blue-50 text-blue-800 flex items-center gap-2">
            <i class="fas fa-building text-blue-400"></i>
            <span>{{ $adminScope['type'] === 'emitter' ? ($emitters->first()?->name ?? '--') : ($recipients->first()?->name ?? '--') }}</span>
        </div>
        @else
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Type d'administration</label>
          <select id="c-admin-type" name="administration_type" onchange="userAdminTypeChange('c')"
            class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
            <option value="">« Sélectionner »</option>
            <option value="emitter">Émettrice</option>
            <option value="recipient">Destinataire</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Administration</label>
          <select id="c-admin-id" name="administration_id" onchange="userAdminIdChange('c')" disabled
            class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none disabled:bg-gray-50 disabled:text-gray-400">
            <option value="">Administration</option>
          </select>
        </div>
        @endif
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Entité sous tutelle</label>
        <select id="c-sub-entity" name="sub_entity_id"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none" disabled>
          <option value="">Direction sous tutelle</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">E-mail <span class="text-red-500">*</span></label>
        <input type="email" name="email" placeholder="adresse@exemple.gouv" required
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Téléphone</label>
        <input type="tel" name="phone" placeholder="+225 00 00 00 00"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Mot de passe <span class="text-red-500">*</span></label>
        <div class="relative">
          <input id="c-pwd" type="password" name="password" placeholder="Minimum 8 caractères" required
            class="border border-gray-200 rounded-lg px-3 py-2.5 pr-10 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <button type="button" onclick="togglePwd('c-pwd','c-pwd-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <i id="c-pwd-eye" class="fas fa-eye text-sm"></i>
          </button>
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Confirmer le mot de passe <span class="text-red-500">*</span></label>
        <div class="relative">
          <input id="c-pwd2" type="password" name="password_confirmation" placeholder="Répéter le mot de passe" required
            class="border border-gray-200 rounded-lg px-3 py-2.5 pr-10 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <button type="button" onclick="togglePwd('c-pwd2','c-pwd2-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <i id="c-pwd2-eye" class="fas fa-eye text-sm"></i>
          </button>
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Quota (espace de stockage)</label>
        <select name="quota"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <option value="">Par défaut</option>
          <option value="500 Mo">500 Mo</option>
          <option value="1 Go">1 Go</option>
          <option value="2 Go">2 Go</option>
          <option value="5 Go">5 Go</option>
          <option value="10 Go">10 Go</option>
          <option value="20 Go">20 Go</option>
          <option value="Illimité">Illimité</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Photo de profil</label>
        <input type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp"
          onchange="previewAvatar(this,'c-avatar-preview')"
          class="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        <p class="text-[11px] text-gray-500 mt-0.5">PNG, JPG, WEBP · max 5 Mo</p>
        <img id="c-avatar-preview" src="" alt="" class="hidden h-14 w-14 rounded-full object-cover border border-gray-200 mt-2">
      </div>

      <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
        <input type="checkbox" name="status" value="active" checked class="h-4 w-4 rounded border-gray-300 accent-[#2453d6]">
        <span>Compte actif</span>
      </label>

      <div class="grid grid-cols-2 gap-2 pt-1">
        <button type="button" onclick="closeUserModal('create')"
          class="py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition">
          Annuler
        </button>
        <button type="submit"
          class="py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition">
          Créer l'utilisateur
        </button>
      </div>
    </form>
  </div>
</div>
{{-- ══ MODAL MODIFIER UTILISATEUR ══════════════════════════════════════════ --}}
<div id="modal-user-edit" class="fixed inset-0 z-50 {{ $editUserActionId ? 'flex' : 'hidden' }} items-center justify-center bg-black/40">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="text-base font-semibold text-gray-800">Modifier l'utilisateur</h3>
      <button type="button" onclick="closeUserModal('edit')" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form id="form-user-edit" method="POST" action="{{ $editUserActionId ? route('admin.users-tab.update', $editUserActionId) : '' }}" enctype="multipart/form-data"
      class="flex flex-col flex-1 overflow-y-auto px-6 py-4 space-y-4">
      @csrf @method('PUT')

      <div class="grid grid-cols-2 gap-3">
        <input type="text" id="e-nom" name="nom" placeholder="Nom" required value="{{ old('nom', $editNom) }}"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <input type="text" id="e-prenoms" name="prenoms" placeholder="Prénoms" value="{{ old('prenoms', $editPrenoms) }}"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
      </div>

      <input type="text" id="e-name" name="name" placeholder="Nom à afficher" required value="{{ old('name', $editUser->name ?? ($editUserData['name'] ?? '')) }}"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">

      <select id="e-role" name="role" required
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">Rôle système</option>
        <option value="admin" {{ old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'admin' ? 'selected' : '' }}>Administrateur</option>
        <option value="manager" {{ old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'manager' ? 'selected' : '' }}>Manager</option>
        <option value="signer" {{ old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'signer' ? 'selected' : '' }}>Signataire</option>
        <option value="user" {{ old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'user' ? 'selected' : '' }}>Utilisateur</option>
      </select>
      <select id="e-profile" name="profile_id"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">« Aucun profil applicatif (super-admin si rôle admin) »</option>
        @foreach($profiles as $p)
        <option value="{{ $p->id }}" {{ (string) old('profile_id', $editUser->profile_id ?? ($editUserData['profile_id'] ?? '')) === (string) $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
        @endforeach
      </select>

      <div class="grid grid-cols-2 gap-3">
        @if(isset($adminScope) && $adminScope && !$canManageAdministration)
        <input type="hidden" name="administration_type" value="{{ $adminScope['type'] }}">
        <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
        <div class="col-span-2 border border-blue-100 rounded-lg px-3 py-2.5 text-sm bg-blue-50 text-blue-800 flex items-center gap-2">
            <i class="fas fa-building text-blue-400"></i>
            <span>{{ $adminScope['type'] === 'emitter' ? ($emitters->first()?->name ?? '--') : ($recipients->first()?->name ?? '--') }}</span>
        </div>
        @else
        <select id="e-admin-type" name="administration_type" onchange="userAdminTypeChange('e')"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <option value="">Type d'administration</option>
          <option value="emitter" {{ $editScopeType === 'emitter' ? 'selected' : '' }}>Émettrice</option>
          <option value="recipient" {{ $editScopeType === 'recipient' ? 'selected' : '' }}>Destinataire</option>
        </select>
        <select id="e-admin-id" name="administration_id" onchange="userAdminIdChange('e')"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <option value="">Administration</option>
          @if($editScopeType === 'recipient')
            @foreach($recipients as $r)
              <option value="{{ $r->id }}" {{ (string) $editScopeId === (string) $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
            @endforeach
          @else
            @foreach($emitters as $e)
              <option value="{{ $e->id }}" {{ (string) $editScopeId === (string) $e->id ? 'selected' : '' }}>{{ $e->name }}</option>
            @endforeach
          @endif
        </select>
        @endif
      </div>

      <select id="e-sub-entity" name="sub_entity_id"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">Direction sous tutelle</option>
        @if($editScopeType && $editScopeId)
          @foreach($subEntities->where('scope_type', $editScopeType)->where('scope_id', $editScopeId) as $se)
            <option value="{{ $se->id }}" {{ (string) $editSubCode === (string) $se->code ? 'selected' : '' }}>{{ $se->name }}</option>
          @endforeach
        @endif
      </select>

      <input type="email" id="e-email" name="email" placeholder="E-mail" required value="{{ old('email', $editUser->email ?? ($editUserData['email'] ?? '')) }}"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">

      <input type="tel" id="e-phone" name="phone" placeholder="Téléphone" value="{{ old('phone', $editUser->phone ?? ($editUserData['phone'] ?? '')) }}"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">

      <select id="e-quota" name="quota"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">Quota par défaut</option>
        <option value="1 Go" {{ old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === '1 Go' ? 'selected' : '' }}>1 Go</option>
        <option value="5 Go" {{ old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === '5 Go' ? 'selected' : '' }}>5 Go</option>
        <option value="10 Go" {{ old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === '10 Go' ? 'selected' : '' }}>10 Go</option>
        <option value="Illimité" {{ old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === 'Illimité' ? 'selected' : '' }}>Illimité</option>
      </select>

      <div class="relative">
        <input id="e-pwd" type="password" name="password" placeholder="Nouveau mot de passe (laisser vide)"
          class="border border-gray-200 rounded-lg px-3 py-2.5 pr-10 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <button type="button" onclick="togglePwd('e-pwd','e-pwd-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
          <i id="e-pwd-eye" class="fas fa-eye text-sm"></i>
        </button>
      </div>
      <div class="relative">
        <input id="e-pwd2" type="password" name="password_confirmation" placeholder="Confirmer le nouveau mot de passe"
          class="border border-gray-200 rounded-lg px-3 py-2.5 pr-10 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <button type="button" onclick="togglePwd('e-pwd2','e-pwd2-eye')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
          <i id="e-pwd2-eye" class="fas fa-eye text-sm"></i>
        </button>
      </div>

      <div class="space-y-1">
        <label class="block text-xs font-medium text-gray-600">Photo de profil</label>
        <input type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp"
          onchange="previewAvatar(this,'e-avatar-preview')"
          class="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        <p class="text-[11px] text-gray-500">PNG, JPG, WEBP (max 5 Mo)</p>
        <img id="e-avatar-preview" src="" alt="" class="hidden h-14 w-14 rounded-full object-cover border border-gray-200 mt-1">
      </div>

      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" id="e-status" name="status" value="active" class="h-4 w-4 rounded border-gray-300" {{ old('status', (($editUser->status ?? ($editUserData['status'] ?? '')) === 'active' ? 'active' : '')) === 'active' ? 'checked' : '' }}> Actif
      </label>

      <div class="grid grid-cols-2 gap-2 pt-1">
        <button type="button" onclick="closeUserModal('edit')"
          class="py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition">
          Annuler
        </button>
        <button type="submit"
          class="py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition">
          Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>

<script>
var __emitters   = @json($emittersJson);
var __recipients = @json($recipientsJson);
var __subEntities= @json($subEntJson);
var __profiles   = @json($profilesJson);
@php
  $editUserBootPayload = null;
  if ($editUser) {
    $editDir = $dirAssignments->get($editUser->id);
    $editParts = preg_split('/\s+/', trim($editUser->full_name ?? $editUser->name ?? ''));
    $editNom = count($editParts) > 1 ? end($editParts) : ($editUser->name ?? '');
    $editPrenoms = count($editParts) > 1 ? implode(' ', array_slice($editParts, 0, -1)) : '';
    $editUserBootJson = json_encode([
      'id' => $editUser->id,
      'nom' => $editNom,
      'prenoms' => $editPrenoms,
      'name' => $editUser->name,
      'email' => $editUser->email,
      'role' => $editUser->role,
      'profile_id' => $editUser->profile_id ?? '',
      'status' => $editUser->status,
      'quota' => $editUser->quota ?? '',
      'scope_type' => $editDir?->direction_scope_type ?? '',
      'scope_id' => $editDir?->direction_scope_id ?? '',
      'sub_code' => $editDir?->sub_entity_code ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $editUserBootPayload = base64_encode($editUserBootJson !== false ? $editUserBootJson : '{}');
  }
@endphp
var __editUserBootPayload = @json($editUserBootPayload);

function openUserModal(type) {
  var el = document.getElementById('modal-user-' + type);
  el.classList.remove('hidden');
  el.classList.add('flex');
}
function closeUserModal(type) {
  var el = document.getElementById('modal-user-' + type);
  el.classList.add('hidden');
  el.classList.remove('flex');
}

function userAdminTypeChange(prefix) {
  var typeEl = document.getElementById(prefix + '-admin-type');
  var adminSel = document.getElementById(prefix + '-admin-id');
  if (!typeEl || !adminSel) return;
  var type   = typeEl.value;
  var subSel = document.getElementById(prefix + '-sub-entity');
  var roleSel = document.getElementById(prefix + '-role-profile');
  adminSel.innerHTML = '<option value="">Administration</option>';
  if (subSel) { subSel.innerHTML = '<option value="">Direction sous tutelle</option>'; }
  adminSel.disabled = !type;
  if (subSel) { subSel.disabled = true; }
  if (roleSel) { roleSel.innerHTML = '<option value="">« Sélectionner un rôle »</option>'; roleSel.disabled = true; }
  if (!type) return;
  var list = type === 'emitter' ? __emitters : __recipients;
  list.forEach(function(a) {
    adminSel.innerHTML += '<option value="' + a.id + '">' + a.name + '</option>';
  });
}

function userAdminIdChange(prefix) {
  var typeEl = document.getElementById(prefix + '-admin-type');
  var adminEl = document.getElementById(prefix + '-admin-id');
  if (!typeEl || !adminEl) return;
  var type   = typeEl.value;
  var adminId = adminEl.value;
  var subSel = document.getElementById(prefix + '-sub-entity');
  var roleSel = document.getElementById(prefix + '-role-profile');
  var profileSel = document.getElementById(prefix + '-profile');
  if (subSel) {
    subSel.innerHTML = '<option value="">Direction sous tutelle</option>';
    subSel.disabled = !adminId;
  }
  if (roleSel) {
    roleSel.innerHTML = '<option value="">« Sélectionner un rôle »</option>';
    roleSel.disabled = !adminId;
  }
  if (!adminId) return;
  var filtered = __subEntities.filter(function(s) {
    return s.scope_type === type && s.scope_id === adminId;
  });
  if (subSel) {
    filtered.forEach(function(s) {
      subSel.innerHTML += '<option value="' + s.id + '">' + s.name + '</option>';
    });
  }
  if (roleSel) {
    var filteredProfiles = __profiles.filter(function(p) {
      return p.administration_id === adminId;
    });
    filteredProfiles.forEach(function(p) {
      roleSel.innerHTML += '<option value="' + p.id + '">' + p.name + '</option>';
    });
  }

  if (profileSel) {
    var prevValue = profileSel.value;
    profileSel.innerHTML = '<option value="">« Aucun profil applicatif (super-admin si rôle admin) »</option>';

    if (adminId && type) {
      var filteredProfileOptions = __profiles.filter(function(p) {
        return p.administration_id === adminId && (p.administration_type || 'emitter') === type;
      });

      filteredProfileOptions.forEach(function(p) {
        profileSel.innerHTML += '<option value="' + p.id + '">' + p.name + '</option>';
      });

      if (filteredProfileOptions.some(function(p) { return p.id === prevValue; })) {
        profileSel.value = prevValue;
      } else if (filteredProfileOptions.length === 1) {
        profileSel.value = filteredProfileOptions[0].id;
      }
    }
  }
}

function openUserEditModal(data) {
  // Construire l'URL correcte pour la route PUT
  var userUpdateUrl = _adminBase + '/users-tab/' + data.id;
  document.getElementById('form-user-edit').action = userUpdateUrl;
  document.getElementById('form-user-edit').method = 'POST'; // Laravel utilise POST avec @method('PUT')
  document.getElementById('e-nom').value     = data.nom || '';
  document.getElementById('e-prenoms').value = data.prenoms || '';
  document.getElementById('e-name').value    = data.name || '';
  document.getElementById('e-email').value   = data.email || '';
  document.getElementById('e-quota').value   = data.quota || '';
  document.getElementById('e-status').checked= data.status === 'active';
  // role
  var roleSel = document.getElementById('e-role');
  for (var i=0; i<roleSel.options.length; i++) {
    if (roleSel.options[i].value === data.role) { roleSel.selectedIndex = i; break; }
  }
  // profil métier
  var profSel = document.getElementById('e-profile');
  if (profSel) {
    for (var j=0; j<profSel.options.length; j++) {
      if (profSel.options[j].value === data.profile_id) { profSel.selectedIndex = j; break; }
    }
  }
  // administration scope: editable selects (super admin) OR hidden fixed scope inputs
  var adminTypeEl = document.getElementById('e-admin-type');
  var adminIdEl = document.getElementById('e-admin-id');
  if (adminTypeEl && adminIdEl) {
    adminTypeEl.value = data.scope_type || '';
    userAdminTypeChange('e');
    setTimeout(function() {
      if (data.scope_id) {
        adminIdEl.value = data.scope_id;
        userAdminIdChange('e');
        setTimeout(function() {
          var subSel = document.getElementById('e-sub-entity');
          if (!subSel) return;
          for (var i=0; i<subSel.options.length; i++) {
            var se = __subEntities.find(function(s){ return s.id === subSel.options[i].value; });
            if (se && se.code === data.sub_code) { subSel.selectedIndex = i; break; }
          }
        }, 50);
      }
    }, 50);
  } else {
    var editForm = document.getElementById('form-user-edit');
    if (editForm) {
      var hiddenType = editForm.querySelector('input[name="administration_type"]');
      var hiddenId = editForm.querySelector('input[name="administration_id"]');
      if (hiddenType && !hiddenType.value && data.scope_type) hiddenType.value = data.scope_type;
      if (hiddenId && !hiddenId.value && data.scope_id) hiddenId.value = data.scope_id;
    }
    // Peupler e-sub-entity pour les admins à scope fixe
    var eScopeType = (hiddenType && hiddenType.value) ? hiddenType.value : data.scope_type;
    var eScopeId   = (hiddenId   && hiddenId.value)   ? hiddenId.value   : data.scope_id;
    var eSubSel = document.getElementById('e-sub-entity');
    if (eSubSel && eScopeType && eScopeId) {
      eSubSel.innerHTML = '<option value="">Direction sous tutelle</option>';
      __subEntities.filter(function(s){ return s.scope_type === eScopeType && s.scope_id === eScopeId; })
        .forEach(function(s){ eSubSel.innerHTML += '<option value="' + s.id + '">' + s.name + '</option>'; });
      if (data.sub_code) {
        for (var si = 0; si < eSubSel.options.length; si++) {
          var ese = __subEntities.find(function(s){ return s.id === eSubSel.options[si].value; });
          if (ese && ese.code === data.sub_code) { eSubSel.selectedIndex = si; break; }
        }
      }
    }
  }
  openUserModal('edit');
}

function openUserEditModalFromButton(buttonEl) {
  try {
    var encoded = buttonEl ? buttonEl.getAttribute('data-user') : '';
    if (!encoded) {
      alert('Données utilisateur introuvables pour la modification. Rechargez la page puis réessayez.');
      return false;
    }
    var data = JSON.parse(atob(encoded));
    openUserEditModal(data);
    return true;
  } catch (e) {
    console.error('Impossible d\'ouvrir le modal de modification utilisateur:', e);
    alert('Impossible d\'ouvrir la fiche utilisateur. Rechargez la page puis réessayez.');
    return false;
  }
}

function togglePwd(inputId, eyeId) {
  var inp = document.getElementById(inputId);
  var eye = document.getElementById(eyeId);
  if (inp.type === 'password') { inp.type = 'text'; eye.className = 'fas fa-eye-slash text-sm'; }
  else { inp.type = 'password'; eye.className = 'fas fa-eye text-sm'; }
}

function previewAvatar(input, previewId) {
  var preview = document.getElementById(previewId);
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) { preview.src = e.target.result; preview.classList.remove('hidden'); };
    reader.readAsDataURL(input.files[0]);
  }
}

// Fermer modal en cliquant dehors
['modal-user-create','modal-user-edit'].forEach(function(id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('click', function(e) {
    if (e.target === el) closeUserModal(id === 'modal-user-create' ? 'create' : 'edit');
  });
});

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.js-user-edit-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      if (openUserEditModalFromButton(link)) {
        e.preventDefault();
      }
    });
  });

  if (__editUserBootPayload) {
    try {
      var bootData = JSON.parse(atob(__editUserBootPayload));
      openUserEditModal(bootData);
    } catch (e) {
      console.error('Impossible d\'ouvrir automatiquement le modal utilisateur:', e);
    }
  }

  // Peupler c-sub-entity pour les admins à scope fixe (pas de select c-admin-id)
  if (!document.getElementById('c-admin-id')) {
    var createModal = document.getElementById('modal-user-create');
    if (createModal) {
      var cHiddenType = createModal.querySelector('input[name="administration_type"]');
      var cHiddenId   = createModal.querySelector('input[name="administration_id"]');
      if (cHiddenType && cHiddenId && cHiddenType.value && cHiddenId.value) {
        var cSubSel = document.getElementById('c-sub-entity');
        if (cSubSel) {
          cSubSel.innerHTML = '<option value="">Direction sous tutelle</option>';
          var cFiltered = __subEntities.filter(function(s){
            return s.scope_type === cHiddenType.value && s.scope_id === cHiddenId.value;
          });
          cFiltered.forEach(function(s){
            cSubSel.innerHTML += '<option value="' + s.id + '">' + s.name + '</option>';
          });
          cSubSel.disabled = false;
        }
      }
    }
  }
});
</script>@elseif($tab === 'theming')
@php
    $tType  = request('t_type', 'emitter');
    $tId    = request('t_id', '');
    $tPrefix = $tId ? "theme_{$tType}_{$tId}_" : '';
    $tAppName    = $tPrefix ? ($settings[$tPrefix.'app_name']->value    ?? '') : '';
    $tWebUrl     = $tPrefix ? ($settings[$tPrefix.'web_url']->value     ?? '') : '';
    $tSlogan     = $tPrefix ? ($settings[$tPrefix.'slogan']->value      ?? '') : '';
    $tMenuColor  = $tPrefix ? ($settings[$tPrefix.'menu_color']->value  ?? '#173b9f') : '#173b9f';
    $tBgColor    = $tPrefix ? ($settings[$tPrefix.'bg_color']->value    ?? '#495F55') : '#495F55';
    $tLegalUrl   = $tPrefix ? ($settings[$tPrefix.'legal_notice_url']->value    ?? '') : '';
    $tPrivacyUrl = $tPrefix ? ($settings[$tPrefix.'privacy_policy_url']->value  ?? '') : '';
    $tLogoPath   = $tPrefix ? ($settings[$tPrefix.'logo']->value                ?? '') : '';
    $tBgImage    = $tPrefix ? ($settings[$tPrefix.'login_background_image']->value ?? '') : '';
    $tHeaderLogo = $tPrefix ? ($settings[$tPrefix.'header_logo']->value         ?? '') : '';
    $tFavicon    = $tPrefix ? ($settings[$tPrefix.'favicon']->value             ?? '') : '';
    $tDisableUser= $tPrefix ? (($settings[$tPrefix.'disable_user_theming']->value ?? 'false') === 'true') : false;
@endphp

<div class="max-w-2xl mx-auto space-y-5">
    @if(session('theming_success'))
        <div class="bg-green-50 border border-green-100 text-green-700 rounded-xl p-3 text-xs">
            {{ session('theming_success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-1">Personnaliser l'apparence</h2>
        <p class="text-xs text-gray-500 mb-6">
          Cette extension permet de personnaliser facilement l'apparence de votre instance et des clients supportés.
            La personnalisation de l'apparence sera visible par tous les utilisateurs.
        </p>

        {{-- Portée de personnalisation --}}
        <div class="mb-6 p-4 rounded-xl border border-blue-100 bg-blue-50/40 space-y-3">
          <p class="text-xs font-semibold text-gray-700">Portée de personnalisation</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select id="t_type_sel" onchange="themingScopeTypeChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="emitter" {{ $tType === 'emitter' ? 'selected' : '' }}>Administration Émettrice</option>
                    <option value="recipient" {{ $tType === 'recipient' ? 'selected' : '' }}>Administration destinataire</option>
                </select>
                <select id="t_id_sel" onchange="themingScopeIdChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">Sélectionner une administration</option>
                    @if($tType === 'emitter')
                        @foreach($emitters as $em)
                            <option value="{{ $em->id }}" {{ $tId === $em->id ? 'selected' : '' }}>{{ $em->name }} ({{ $em->code }})</option>
                        @endforeach
                    @else
                        @foreach($recipients as $re)
                            <option value="{{ $re->id }}" {{ $tId === $re->id ? 'selected' : '' }}>{{ $re->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <p class="text-[11px] text-gray-600">
              Chaque administration possède sa propre configuration d'apparence. Les modifications enregistrées ici n'impactent pas les autres administrations.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.theming.save') }}" enctype="multipart/form-data" class="space-y-6" id="theming-form">
            @csrf
            <input type="hidden" name="tab" value="theming">
            <input type="hidden" name="t_type" id="f_t_type" value="{{ $tType }}">
            <input type="hidden" name="t_id" id="f_t_id" value="{{ $tId }}">

            {{-- Informations générales --}}
            <div class="space-y-4">
                <div class="relative">
                    <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Nom</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <input type="text" name="app_name" id="t_app_name"
                            value="{{ old('app_name', $tAppName) }}"
                            class="flex-1 px-3 py-2.5 text-sm outline-none">
                        <button type="button" onclick="document.getElementById('t_app_name').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                    </div>
                </div>
                <div class="relative">
                    <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien web</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <input type="url" name="web_url" id="t_web_url"
                            value="{{ old('web_url', $tWebUrl) }}"
                            placeholder="https://"
                            class="flex-1 px-3 py-2.5 text-sm outline-none">
                        <button type="button" onclick="document.getElementById('t_web_url').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                    </div>
                </div>
                <div class="relative">
                    <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Slogan</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <input type="text" name="slogan" id="t_slogan"
                            value="{{ old('slogan', $tSlogan) }}"
                            class="flex-1 px-3 py-2.5 text-sm outline-none">
                        <button type="button" onclick="document.getElementById('t_slogan').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                    </div>
                </div>
            </div>

            {{-- Couleur principale --}}
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Couleur principale</p>
              <p class="text-xs text-gray-500 mb-3">La couleur principale est utilisée pour mettre en évidence les éléments tels que les boutons importants. Elle peut être légèrement modifiée en fonction du schéma de couleurs actuel.</p>
                <div class="flex items-center gap-2">
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <span id="t_menu_color_hex" class="px-3 py-2 text-sm font-mono bg-white">{{ $tMenuColor }}</span>
                        <input type="color" name="menu_color" id="t_menu_color"
                            value="{{ old('menu_color', $tMenuColor) }}"
                            oninput="document.getElementById('t_menu_color_hex').textContent=this.value"
                            class="w-10 h-10 border-none cursor-pointer bg-transparent p-0"
                            title="Choisir la couleur principale">
                    </div>
                </div>
            </div>

            {{-- Couleur d'arrière-plan --}}
            <div>
              <p class="text-sm font-medium text-gray-700 mb-2">Couleur d'arrière-plan</p>
              <p class="text-xs text-gray-500 mb-3">Au lieu d'une image d'arrière-plan, vous pouvez également définir une couleur unie d'arrière-plan. Si vous définissez une image d'arrière-plan, la modification de cette couleur influencera la couleur des icônes du menu de l'application.</p>
                <div class="flex items-center gap-2">
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <span id="t_bg_color_hex" class="px-3 py-2 text-sm font-mono bg-white">{{ $tBgColor }}</span>
                        <input type="color" name="bg_color" id="t_bg_color"
                            value="{{ old('bg_color', $tBgColor) }}"
                            oninput="document.getElementById('t_bg_color_hex').textContent=this.value"
                            class="w-10 h-10 border-none cursor-pointer bg-transparent p-0"
                            title="Choisir la couleur d'arrière-plan">
                    </div>
                </div>
            </div>

            {{-- Logo --}}
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Logo</p>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                      <i class="fas fa-upload"></i> Téléverser
                        <input type="file" name="logo_file" accept="image/*" class="hidden"
                            onchange="previewThemingFile(this,'t_logo_preview')">
                    </label>
                    <button type="button" onclick="clearThemingFile('logo_file','t_logo_preview')" class="text-gray-400 hover:text-gray-600 text-base">?</button>
                </div>
                <div id="t_logo_preview" class="mt-3 {{ $tLogoPath ? '' : 'hidden' }}">
                    @if($tLogoPath)
                        <img src="{{ asset('storage/' . ltrim($tLogoPath, '/')) }}" alt="Logo" class="h-16 object-contain rounded border border-gray-200 p-1">
                    @else
                        <img src="" alt="Logo preview" class="h-16 object-contain rounded border border-gray-200 p-1">
                    @endif
                </div>
            </div>

            {{-- Image d'arrière-plan et de connexion --}}
            <div>
              <p class="text-sm font-medium text-gray-700 mb-2">Image d'arrière-plan et de connexion</p>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                  <i class="fas fa-upload"></i> Téléverser
                        <input type="file" name="bg_image_file" accept="image/*" class="hidden"
                            onchange="previewThemingFile(this,'t_bg_preview')">
                    </label>
                    <button type="button" onclick="clearThemingFile('bg_image_file','t_bg_preview')" class="text-gray-400 hover:text-gray-600 text-base">?</button>
                    <button type="button" onclick="clearThemingFile('bg_image_file','t_bg_preview')" class="text-gray-400 hover:text-red-500 text-base"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div id="t_bg_preview" class="mt-3 {{ $tBgImage ? '' : 'hidden' }}">
                    @if($tBgImage)
                        <img src="{{ asset('storage/' . ltrim($tBgImage, '/')) }}" alt="Image de fond" class="max-w-sm rounded-lg border border-gray-200 object-cover">
                    @else
                        <img src="" alt="Background preview" class="max-w-sm rounded-lg border border-gray-200 object-cover">
                    @endif
                </div>
            </div>

            {{-- Options avancées --}}
            <div class="pt-4 border-t border-gray-100">
              <h3 class="text-base font-bold text-gray-800 mb-4">Options avancées</h3>
                <div class="space-y-4">
                    <div class="relative">
                  <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien vers la notice légale</label>
                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                            <input type="url" name="legal_notice_url" id="t_legal_url"
                                value="{{ old('legal_notice_url', $tLegalUrl) }}"
                                placeholder="https://"
                                class="flex-1 px-3 py-2.5 text-sm outline-none">
                            <button type="button" onclick="document.getElementById('t_legal_url').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                        </div>
                    </div>
                    <div class="relative">
                      <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien vers la politique de confidentialité</label>
                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                            <input type="url" name="privacy_policy_url" id="t_privacy_url"
                                value="{{ old('privacy_policy_url', $tPrivacyUrl) }}"
                                placeholder="https://"
                                class="flex-1 px-3 py-2.5 text-sm outline-none">
                            <button type="button" onclick="document.getElementById('t_privacy_url').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                        </div>
                    </div>

                    {{-- Logo d'en-tête --}}
                    <div>
                      <p class="text-sm font-medium text-gray-700 mb-2">Logo d'en-tête</p>
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                          <i class="fas fa-upload"></i> Téléverser
                                <input type="file" name="header_logo_file" accept="image/*" class="hidden"
                                    onchange="previewThemingFile(this,'t_header_logo_preview')">
                            </label>
                            <button type="button" onclick="clearThemingFile('header_logo_file','t_header_logo_preview')" class="text-gray-400 hover:text-gray-600 text-base">?</button>
                        </div>
                        <div id="t_header_logo_preview" class="mt-3 {{ $tHeaderLogo ? '' : 'hidden' }}">
                            @if($tHeaderLogo)
                            <img src="{{ asset('storage/' . ltrim($tHeaderLogo, '/')) }}" alt="Logo en-tête" class="h-14 object-contain rounded border border-gray-200 p-1">
                            @else
                                <img src="" alt="Header logo preview" class="h-14 object-contain rounded border border-gray-200 p-1">
                            @endif
                        </div>
                    </div>

                    {{-- Favicon --}}
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-2">Favicon</p>
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 transition text-white text-xs font-semibold px-4 py-2 rounded-lg cursor-pointer">
                          <i class="fas fa-upload"></i> Téléverser
                                <input type="file" name="favicon_file" accept="image/x-icon,image/png,image/svg+xml" class="hidden"
                                    onchange="previewThemingFile(this,'t_favicon_preview')">
                            </label>
                            <button type="button" onclick="clearThemingFile('favicon_file','t_favicon_preview')" class="text-gray-400 hover:text-gray-600 text-base">?</button>
                        </div>
                        <div id="t_favicon_preview" class="mt-3 {{ $tFavicon ? '' : 'hidden' }}">
                            @if($tFavicon)
                                <img src="{{ asset('storage/' . ltrim($tFavicon, '/')) }}" alt="Favicon" class="h-10 object-contain rounded border border-gray-200 p-1">
                            @else
                                <img src="" alt="Favicon preview" class="h-10 object-contain rounded border border-gray-200 p-1">
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Paramètres utilisateurs --}}
            <div class="pt-4 border-t border-gray-100">
              <h3 class="text-base font-bold text-gray-800 mb-2">Paramètres utilisateurs</h3>
                <label class="flex items-center gap-3 cursor-pointer" onclick="themingToggleDisable()">
                    <div id="t_disable_toggle"
                        class="relative w-11 h-6 rounded-full transition-colors {{ $tDisableUser ? 'bg-blue-600' : 'bg-gray-300' }}">
                        <span id="t_disable_knob"
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform {{ $tDisableUser ? 'translate-x-5' : 'translate-x-0' }}">
                        </span>
                    </div>
                    <span class="text-sm text-gray-700">Désactiver la gestion du thème par l'utilisateur</span>
                </label>
                <input type="hidden" name="disable_user_theming" id="t_disable_user_theming" value="{{ $tDisableUser ? 'true' : 'false' }}">
                <p class="text-xs text-gray-500 mt-2">
                    Bien que vous puissiez sélectionner et personnaliser votre instance, les utilisateurs peuvent modifier leur arrière-plan et leurs couleurs.
                    Si vous voulez imposer votre personnalisation, vous pouvez activer cette option.
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    Installez l'extension PHP ImageMagick qui prend en charge les images SVG pour générer automatiquement des favicons à partir du logo téléversé et de la couleur indiquée.
                </p>
            </div>

            {{-- Bouton enregistrer --}}
            <div class="pt-2">
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-3 rounded-lg transition">
                  Enregistrer les paramètres d'apparence
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const __themingEmitters  = @json($emitters->map(fn($e)=>['id'=>$e->id,'name'=>$e->name,'code'=>$e->code])->values());
const __themingRecipients= @json($recipients->map(fn($r)=>['id'=>$r->id,'name'=>$r->name])->values());

function themingScopeTypeChange(type) {
    document.getElementById('f_t_type').value = type;
    const sel = document.getElementById('t_id_sel');
    sel.innerHTML = '<option value="">Sélectionner une administration</option>';
    const list = type === 'emitter' ? __themingEmitters : __themingRecipients;
    list.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name + (item.code ? ' (' + item.code + ')' : '');
        sel.appendChild(opt);
    });
    document.getElementById('f_t_id').value = '';
}

function themingScopeIdChange(id) {
    document.getElementById('f_t_id').value = id;
    if (id) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', 'theming');
        url.searchParams.set('t_type', document.getElementById('f_t_type').value);
        url.searchParams.set('t_id', id);
        window.location.href = url.toString();
    }
}

function previewThemingFile(input, previewId) {
    const container = document.getElementById(previewId);
    const img = container.querySelector('img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            container.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearThemingFile(inputName, previewId) {
    const form = document.getElementById('theming-form');
    const input = form.querySelector('input[name="' + inputName + '"]');
    if (input) input.value = '';
    const container = document.getElementById(previewId);
    if (container) {
        container.classList.add('hidden');
        const img = container.querySelector('img');
        if (img) img.src = '';
    }
}

function themingToggleDisable() {
    const hidden  = document.getElementById('t_disable_user_theming');
    const toggle  = document.getElementById('t_disable_toggle');
    const knob    = document.getElementById('t_disable_knob');
    const current = hidden.value === 'true';
    hidden.value = current ? 'false' : 'true';
    if (!current) {
        toggle.classList.replace('bg-gray-300', 'bg-blue-600');
        knob.classList.replace('translate-x-0', 'translate-x-5');
    } else {
        toggle.classList.replace('bg-blue-600', 'bg-gray-300');
        knob.classList.replace('translate-x-5', 'translate-x-0');
    }
}
</script>@elseif($tab === 'email-notifications')
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3">
        <div class="h-10 w-10 bg-red-100 rounded-xl flex items-center justify-center">
            <i class="fas fa-envelope-open-text text-red-500"></i>
        </div>
        <div>
            <h2 class="text-base font-bold text-gray-800">Notifications E-mail (SMTP)</h2>
            <p class="text-xs text-gray-400">Configuration du serveur d'envoi d'e-mails par administration</p>
        </div>
    </div>
    <div class="p-6 space-y-5">
        {{-- Sélecteur d'administration --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Administration</label>
            <select id="smtpAdminSelect" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                <option value="">— Choisir une administration —</option>
                @foreach($emitters as $e)
                <option value="{{ $e->id }}" data-type="emitter">{{ $e->name }}{{ $e->code ? ' ('.$e->code.')' : '' }}</option>
                @endforeach
                @foreach($recipients as $r)
                <option value="{{ $r->id }}" data-type="recipient">{{ $r->name }}{{ $r->code ? ' ('.$r->code.')' : '' }} [dest.]</option>
                @endforeach
            </select>
        </div>

        {{-- Formulaire SMTP (caché tant qu'aucune administration n'est sélectionnée) --}}
        <div id="smtpFormWrap" class="hidden space-y-5">
            <input type="hidden" id="smtpAdminId" value="">
            <input type="hidden" id="smtpAdminType" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Hôte SMTP</label>
                    <input type="text" id="smtp_mail_host" placeholder="smtp.example.com"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Port SMTP</label>
                    <input type="number" id="smtp_mail_port" placeholder="587"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Identifiant SMTP</label>
                    <input type="text" id="smtp_mail_username" placeholder="user@example.com"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mot de passe SMTP</label>
                    <input type="password" id="smtp_mail_password" placeholder="Laisser vide pour conserver"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Adresse expéditeur</label>
                    <input type="email" id="smtp_mail_from_address" placeholder="noreply@example.com"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nom expéditeur</label>
                    <input type="text" id="smtp_mail_from_name" placeholder="E-Parapheur"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Encryption</label>
                <select id="smtp_mail_encryption" class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="">Aucune</option>
                </select>
            </div>

            <div class="pt-2 flex flex-wrap gap-3 items-center">
                <button type="button" id="smtpSaveBtn"
                    class="px-6 py-2.5 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl text-sm transition flex items-center gap-2">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
                <button type="button" id="smtpTestBtn"
                    class="px-6 py-2.5 bg-white border border-red-400 text-red-500 hover:bg-red-50 font-semibold rounded-xl text-sm transition flex items-center gap-2">
                    <i class="fas fa-paper-plane"></i> Tester la configuration SMTP
                </button>
            </div>
            <div id="smtpTestResult" class="hidden text-sm rounded-xl px-4 py-3 font-medium"></div>
        </div>
    </div>
</div>

{{-- ══════ AUTHENTIFICATION À DEUX FACTEURS (OTP) ══════ --}}
@php
    $otpScopeValue = '';
    $otpScopeLabel = null;
    if (isset($adminScope) && $adminScope) {
        $otpScopeValue = $adminScope['type'] . ':' . $adminScope['id'];
        $otpScopeLabel = $adminScope['type'] === 'emitter'
            ? \App\Models\IssuingAdministration::find($adminScope['id'])?->name
            : \App\Models\RecipientAdministration::find($adminScope['id'])?->name;
    }
    $otpChannelMap = collect($settings)
        ->filter(fn($s, $k) => $k === 'otp_channel' || str_starts_with($k, 'otp_channel:'))
        ->mapWithKeys(fn($s, $k) => [($k === 'otp_channel' ? '' : substr($k, 12)) => $s->value]);
    $otpCurrent = $otpChannelMap[$otpScopeValue] ?? ($otpChannelMap[''] ?? 'email');
@endphp
<div class="mt-6 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3">
        <div class="h-10 w-10 bg-green-100 rounded-xl flex items-center justify-center">
            <i class="fas fa-shield-alt text-green-600"></i>
        </div>
        <div>
            <h2 class="text-base font-bold text-gray-800">Authentification à deux facteurs (OTP)</h2>
            <p class="text-xs text-gray-400">Choisissez le canal d'envoi du code de vérification à la connexion, par administration</p>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.settings.save') }}" class="p-6 space-y-5">
        @csrf @method('PUT')
        <input type="hidden" name="tab" value="email-notifications">

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Administration concernée</label>
            @if($otpScopeValue !== '')
                {{-- Admin d'administration : champ grisé, verrouillé sur son administration --}}
                <select id="otpAdminScopeSelect" disabled
                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm bg-gray-100 text-gray-500 cursor-not-allowed">
                    <option selected>{{ $otpScopeLabel ?? 'Mon administration' }}</option>
                </select>
                <input type="hidden" name="otp_admin_scope" value="{{ $otpScopeValue }}">
                <p class="text-xs text-gray-400 mt-1">Le paramétrage s'applique uniquement à votre administration.</p>
            @else
                {{-- Super admin : choix de l'administration --}}
                <select name="otp_admin_scope" id="otpAdminScopeSelect"
                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                    <option value="">« Toutes les administrations (par défaut) »</option>
                    @foreach($emitters as $e)
                    <option value="emitter:{{ $e->id }}">{{ $e->name }}{{ $e->code ? ' ('.$e->code.')' : '' }}</option>
                    @endforeach
                    @foreach($recipients as $r)
                    <option value="recipient:{{ $r->id }}">{{ $r->name }}{{ $r->code ? ' ('.$r->code.')' : '' }} [dest.]</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Le choix du canal s'appliquera aux utilisateurs de l'administration sélectionnée.</p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Canal d'envoi du code OTP</label>
            <select name="otp_channel" id="otpChannelSelect" onchange="document.getElementById('whatsappOtpConfig').classList.toggle('hidden', this.value !== 'whatsapp')"
                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                <option value="email" {{ $otpCurrent === 'email' ? 'selected' : '' }}>Email</option>
                <option value="whatsapp" {{ $otpCurrent === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Si WhatsApp échoue ou si l'utilisateur n'a pas de numéro de téléphone, le code est envoyé par email.</p>
        </div>

        <div id="whatsappOtpConfig" class="{{ $otpCurrent === 'whatsapp' ? '' : 'hidden' }} space-y-4 bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-green-700"><i class="fab fa-whatsapp"></i> Configuration API WhatsApp Cloud (Meta)</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Token d'accès API</label>
                    <input type="password" name="whatsapp_api_token" placeholder="{{ ($settings['whatsapp_api_token']->value ?? '') ? 'Laisser vide pour conserver' : 'EAAxxxxxxx...' }}"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Phone Number ID</label>
                    <input type="text" name="whatsapp_phone_number_id" value="{{ $settings['whatsapp_phone_number_id']->value ?? '' }}" placeholder="Ex: 123456789012345"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                </div>
            </div>
            <p class="text-xs text-gray-500">Le code est envoyé au numéro de téléphone renseigné sur le profil de l'utilisateur (format international, ex: 225 07 01 02 03 04).</p>
        </div>

        <button type="submit"
            class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl text-sm transition flex items-center gap-2">
            <i class="fas fa-save"></i> Enregistrer
        </button>
    </form>
</div>
@if($otpScopeValue === '')
<script>
(function () {
    const OTP_CHANNEL_MAP = @json($otpChannelMap);
    const scopeSelect = document.getElementById('otpAdminScopeSelect');
    const channelSelect = document.getElementById('otpChannelSelect');
    scopeSelect.addEventListener('change', function () {
        const value = OTP_CHANNEL_MAP[this.value] || OTP_CHANNEL_MAP[''] || 'email';
        channelSelect.value = value;
        document.getElementById('whatsappOtpConfig').classList.toggle('hidden', value !== 'whatsapp');
    });
})();
</script>
@endif

{{-- ══════ PARAMÈTRES DU CHAT ══════ --}}
<div class="mt-6 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3">
        <div class="h-10 w-10 bg-blue-100 rounded-xl flex items-center justify-center">
            <i class="fas fa-comments text-blue-600"></i>
        </div>
        <div>
            <h2 class="text-base font-bold text-gray-800">Paramètres du Chat</h2>
            <p class="text-xs text-gray-400">Configurez le système de messagerie en direct entre utilisateurs</p>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.settings.save') }}" class="p-6 space-y-6">
        @csrf @method('PUT')
        <input type="hidden" name="tab" value="email-notifications">

        {{-- Sélecteur d'administration --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Administration concernée</label>
            <select name="chat_administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">« Toutes les administrations »</option>
                @foreach($emitters as $e)
                <option value="{{ $e->id }}" {{ ($settings['chat_administration_id']->value ?? '') === $e->id ? 'selected' : '' }}>
                    {{ $e->name }}{{ $e->code ? ' ('.$e->code.')' : '' }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Toggle activer le chat --}}
        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-xl px-5 py-4">
            <div>
                <p class="font-semibold text-gray-800 text-sm">Activer le chat en direct</p>
                <p class="text-xs text-gray-400 mt-0.5">Permet aux utilisateurs d'échanger des messages en temps réel.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="chat_enabled" value="0">
                <input type="checkbox" name="chat_enabled" value="1" class="sr-only peer"
                    {{ ($settings['chat_enabled']->value ?? '1') == '1' ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-gray-300 peer-checked:bg-blue-600 rounded-full peer peer-focus:ring-2 peer-focus:ring-blue-300 transition-all after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        {{-- Portée des messages directs --}}
        <div>
            <h3 class="text-sm font-bold text-gray-800 mb-1">Portée des messages directs</h3>
            <p class="text-xs text-gray-500 mb-4">Définissez avec quels utilisateurs un membre peut initier une conversation privée.</p>
            <div class="space-y-3">
                <label class="flex items-start gap-4 p-4 border-2 rounded-xl cursor-pointer transition
                    {{ ($settings['chat_scope']->value ?? 'all') === 'same_admin' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}"
                    id="scope-same-label">
                    <input type="radio" name="chat_scope" value="same_admin"
                        {{ ($settings['chat_scope']->value ?? 'all') === 'same_admin' ? 'checked' : '' }}
                        class="mt-0.5 w-4 h-4 text-blue-600 border-gray-300 accent-blue-600"
                        onchange="updateScopeStyle(this)">
                    <div>
                        <p class="font-semibold text-gray-800 text-sm">Même administration uniquement</p>
                        <p class="text-xs text-gray-500 mt-0.5">Un utilisateur ne peut chatter qu'avec les membres de son administration. Les messages directs vers d'autres administrations sont bloqués.</p>
                    </div>
                </label>
                <label class="flex items-start gap-4 p-4 border-2 rounded-xl cursor-pointer transition
                    {{ ($settings['chat_scope']->value ?? 'all') === 'all' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}"
                    id="scope-all-label">
                    <input type="radio" name="chat_scope" value="all"
                        {{ ($settings['chat_scope']->value ?? 'all') === 'all' ? 'checked' : '' }}
                        class="mt-0.5 w-4 h-4 text-blue-600 border-gray-300 accent-blue-600"
                        onchange="updateScopeStyle(this)">
                    <div>
                        <p class="font-semibold text-gray-800 text-sm">Toutes les administrations</p>
                        <p class="text-xs text-gray-500 mt-0.5">Un utilisateur peut envoyer des messages directs à n'importe quel utilisateur connecté, quelle que soit son administration.</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Note info --}}
        <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-700">
            <i class="fas fa-circle-info mt-0.5 flex-shrink-0"></i>
            <p class="text-xs leading-relaxed">Ce paramètre est pris en compte immédiatement après enregistrement et s'applique à tous les utilisateurs connectés. Le widget chat dans l'interface respectera cette configuration.</p>
        </div>

        <div>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition flex items-center gap-2">
                <i class="fas fa-save"></i> Enregistrer les paramètres du chat
            </button>
        </div>
    </form>
</div>

{{-- ══════════════════════ API SIGNATURE ══════════════════════ --}}
@elseif($tab === 'signature-provider')
@php
    $sigAdminType = request('sig_admin_type', 'emitter');
    $sigAdminId   = request('sig_admin_id', '');
    $sigCfg       = $sigAdminId ? ($sigProviders[$sigAdminId] ?? null) : null;
@endphp

<div class="max-w-3xl mx-auto space-y-5">
    @if(session('sig_success'))
        <div class="bg-green-50 border border-green-100 text-green-700 rounded-xl p-3 text-xs">
            {{ session('sig_success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        {{-- En-tete --}}
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
                <i class="fas fa-key text-blue-600 text-base"></i>
            </div>
            <div>
                <h2 class="text-base font-bold text-gray-800">Configuration API de signature</h2>
                <p class="text-xs text-gray-500">
                    Param&egrave;tres utilis&eacute;s lors du clic sur &laquo;&nbsp;Signer&nbsp;&raquo; dans l'onglet Signatures.
                    Chaque administration &eacute;mettrice peut avoir sa propre configuration.
                </p>
            </div>
        </div>

        {{-- Selecteur administration --}}
        <div class="mb-6 p-4 rounded-xl border border-blue-100 bg-blue-50/40 space-y-3">
            <p class="text-xs font-semibold text-gray-700">Administration concern&eacute;e</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select id="sig_type_sel" onchange="sigScopeTypeChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="emitter" {{ $sigAdminType === 'emitter' ? 'selected' : '' }}>Administration &eacute;mettrice</option>
                    <option value="recipient" {{ $sigAdminType === 'recipient' ? 'selected' : '' }}>Administration destinataire</option>
                </select>
                <select id="sig_id_sel" onchange="sigScopeIdChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">S&eacute;lectionner une administration</option>
                    @if($sigAdminType === 'emitter')
                        @foreach($emitters as $em)
                            <option value="{{ $em->id }}" {{ $sigAdminId === $em->id ? 'selected' : '' }}>{{ $em->name }} ({{ $em->code }})</option>
                        @endforeach
                    @else
                        @foreach($recipients as $re)
                            <option value="{{ $re->id }}" {{ $sigAdminId === $re->id ? 'selected' : '' }}>{{ $re->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        {{-- Flux d'integration --}}
        <div class="mb-5 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700 leading-relaxed">
            <p class="font-semibold mb-1">Flux d'int&eacute;gration automatique (SunnyStamp / UVCI)</p>
            <ol class="list-decimal ml-4 space-y-0.5">
                <li>Recherche du signataire par e-mail sur la plateforme</li>
                <li>Cr&eacute;ation du parapheur (workflow)</li>
                <li>Envoi du fichier PDF vers la plateforme</li>
                <li>Association du document au workflow</li>
                <li>D&eacute;marrage du workflow</li>
                <li>Envoi du lien d'invitation au signataire</li>
            </ol>
            <p class="mt-1.5 text-blue-600">L'e-mail du signataire est automatiquement r&eacute;cup&eacute;r&eacute; depuis son compte utilisateur local.</p>
        </div>

        <form method="POST" action="{{ route('admin.signature-provider.save') }}" id="sig-form" class="space-y-4">
            @csrf
            <input type="hidden" name="sig_admin_type" id="f_sig_type" value="{{ $sigAdminType }}">
            <input type="hidden" name="sig_admin_id"   id="f_sig_id"   value="{{ $sigAdminId }}">

            {{-- Toggle activer --}}
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                    <p class="text-sm font-medium text-gray-700">Activer la signature via API externe</p>
                    <p class="text-xs text-gray-400 mt-0.5">Si d&eacute;sactiv&eacute;, le syst&egrave;me utilise la signature locale interne.</p>
                </div>
                <div id="sig_active_toggle" onclick="sigToggleActive()"
                    class="relative w-11 h-6 rounded-full cursor-pointer transition-colors {{ $sigCfg?->is_active ? 'bg-blue-600' : 'bg-gray-300' }}">
                    <span id="sig_active_knob"
                        class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform {{ $sigCfg?->is_active ? 'translate-x-5' : 'translate-x-0' }}">
                    </span>
                </div>
                <input type="hidden" name="is_active" id="sig_is_active" value="{{ $sigCfg?->is_active ? '1' : '0' }}">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {{-- Endpoint --}}
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Endpoint (URL de base de l'API)</label>
                    <input type="text" name="endpoint"
                        value="{{ old('endpoint', $sigCfg?->endpoint ?? '') }}"
                        placeholder="https://sgs-demo-test01.sunnystamp.com"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Sans slash final. Ex&nbsp;: https://sgs-demo-test01.sunnystamp.com</p>
                </div>

                {{-- API Key --}}
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">API Key (Bearer token)</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-blue-300">
                        <input type="password" name="api_key" id="sig_api_key"
                            value="{{ old('api_key', $sigCfg?->api_key ?? '') }}"
                            placeholder="act_38Xcy1gjrQ9jTUfozSvpWYMi.xxxx"
                            class="flex-1 px-3 py-2 text-sm outline-none">
                        <button type="button" onclick="sigToggleApiKey()" id="sig_apikey_btn"
                            class="px-3 text-gray-400 hover:text-gray-600 transition text-xs whitespace-nowrap">
                            Afficher
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Token Bearer utilis&eacute; dans l'en-t&ecirc;te Authorization de tous les appels API.</p>
                </div>

                {{-- Provider Owner User ID (résolu par email, lecture seule) --}}
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">
                        Tester la recherche par e-mail
                        <span class="text-gray-400 font-normal">— l'User ID est résolu dynamiquement via l'e-mail de chaque utilisateur</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="email" id="sig_test_email"
                            placeholder="email@exemple.com"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                        <button type="button" onclick="sigTestConnection()"
                            class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg whitespace-nowrap transition flex items-center gap-1.5">
                            <i class="fas fa-plug"></i> Tester
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Saisissez l'e-mail d'un utilisateur de l'application pour vérifier qu'il existe sur la plateforme et récupérer son User ID.
                        L'e-mail de chaque utilisateur est utilisé automatiquement lors des actions de signature/validation.
                    </p>
                    {{-- Résultat du test --}}
                    <div id="sig_test_result" class="hidden mt-2 p-2.5 rounded-lg text-xs"></div>
                    {{-- Champ caché conservé pour compatibilité formulaire (non requis) --}}
                    <input type="hidden" name="provider_owner_user_id" value="{{ old('provider_owner_user_id', $sigCfg?->provider_owner_user_id ?? '') }}">
                </div>

                {{-- Tenant ID --}}
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Tenant ID <span class="text-amber-600">&#x2605; Requis pour certaines op&eacute;rations avancées</span></label>
                    <input type="text" name="tenant_id" id="sig_tenant_id"
                        value="{{ old('tenant_id', $sigCfg?->tenant_id ?? '') }}"
                        placeholder="ten_Guj71mvWbKxFVg8mMnZE4CAv"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Identifiant du tenant sur la plateforme. Utilis&eacute; pour les op&eacute;rations au niveau tenant (param&egrave;tres emails, m&eacute;tadonn&eacute;es, etc.). R&eacute;cup&eacute;r&eacute; automatiquement lors du test de connexion.</p>
                </div>

                {{-- Consent Page ID Signature --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Consent page ID &mdash; <span class="text-blue-600 font-medium">Signature</span></label>
                    <input type="text" name="consent_page_id"
                        value="{{ old('consent_page_id', $sigCfg?->consent_page_id ?? '') }}"
                        placeholder="cop_BgKmiR1nxZEeBiGtYhswaUUc"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Utilis&eacute; pour les &eacute;tapes de type <code class="bg-gray-100 px-1 rounded">stepType: signature</code>.</p>
                </div>

                {{-- Consent Page ID Approbation --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Consent page ID &mdash; <span class="text-purple-600 font-medium">Approbation</span></label>
                    <input type="text" name="consent_page_id_approval"
                        value="{{ old('consent_page_id_approval', $sigCfg?->consent_page_id_approval ?? '') }}"
                        placeholder="cop_Ka4BRrujjQ4VS1zE7GKg5oc9"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Utilis&eacute; pour les &eacute;tapes de type <code class="bg-gray-100 px-1 rounded">stepType: approval</code>. Peut &ecirc;tre diff&eacute;rent de celui de signature.</p>
                </div>

                {{-- Signature Profile ID --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Profil de signature (Signature Profile ID)</label>
                    <input type="text" name="signature_profile_id"
                        value="{{ old('signature_profile_id', $sigCfg?->signature_profile_id ?? '') }}"
                        placeholder="sip_KA49jsZB5kMY82cGACwYgwp8"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Utilis&eacute; lors de l'association du document au workflow.</p>
                </div>

                {{-- Sign path --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sign path</label>
                    <input type="text" name="sign_path"
                        value="{{ old('sign_path', $sigCfg?->sign_path ?? '/v1/sign') }}"
                        placeholder="/v1/sign"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                </div>

                {{-- Timeout --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Timeout (ms)</label>
                    <input type="number" name="timeout_ms" min="1000"
                        value="{{ old('timeout_ms', $sigCfg?->timeout_ms ?? 30000) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                </div>

                {{-- QR position is now managed in OnlyOffice settings only --}}
                <div class="md:col-span-2 rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <p class="text-sm font-medium text-blue-800">R&eacute;glage QR centralis&eacute; dans OnlyOffice</p>
                    <p class="text-xs text-blue-700 mt-0.5">La position du QR est d&eacute;sormais configur&eacute;e uniquement dans l'onglet <strong>OnlyOffice</strong> pour &eacute;viter les doublons.</p>
                </div>
            </div>

            {{-- Verification SSL --}}
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                    <p class="text-sm font-medium text-gray-700">V&eacute;rification SSL</p>
                    <p class="text-xs text-gray-400 mt-0.5">Conservez activ&eacute; en production.</p>
                </div>
                <div id="sig_ssl_toggle" onclick="sigToggleSsl()"
                    class="relative w-11 h-6 rounded-full cursor-pointer transition-colors {{ ($sigCfg?->verify_ssl ?? true) ? 'bg-blue-600' : 'bg-gray-300' }}">
                    <span id="sig_ssl_knob"
                        class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform {{ ($sigCfg?->verify_ssl ?? true) ? 'translate-x-5' : 'translate-x-0' }}">
                    </span>
                </div>
                <input type="hidden" name="verify_ssl" id="sig_verify_ssl" value="{{ ($sigCfg?->verify_ssl ?? true) ? '1' : '0' }}">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                    <i class="fas fa-save mr-1"></i> Enregistrer la configuration API Signature
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const __sigTestUrl   = '{{ route('admin.signature-provider.test') }}';
const __sigCsrf      = '{{ csrf_token() }}';
const __sigEmitters  = @json($emitters->map(fn($e)=>['id'=>$e->id,'name'=>$e->name,'code'=>$e->code])->values());
const __sigRecipients= @json($recipients->map(fn($r)=>['id'=>$r->id,'name'=>$r->name])->values());

async function sigTestConnection() {
    const endpoint = document.querySelector('input[name="endpoint"]')?.value?.trim();
    const apiKey   = document.getElementById('sig_api_key')?.value?.trim();
    const email    = document.getElementById('sig_test_email')?.value?.trim();
    const resultEl = document.getElementById('sig_test_result');

    if (!endpoint || !apiKey) {
        resultEl.className = 'mt-2 p-2.5 rounded-lg text-xs bg-red-50 border border-red-200 text-red-700';
        resultEl.textContent = 'Veuillez renseigner l\'Endpoint et l\'API Key avant de tester.';
        resultEl.classList.remove('hidden');
        return;
    }

    resultEl.className = 'mt-2 p-2.5 rounded-lg text-xs bg-blue-50 border border-blue-200 text-blue-700';
    resultEl.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Test en cours...';
    resultEl.classList.remove('hidden');

    try {
        const body = { endpoint, api_key: apiKey };
        if (email) body.email = email;

        const resp = await fetch(__sigTestUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': __sigCsrf },
            body: JSON.stringify(body),
        });
        const data = await resp.json();

        if (data.ok) {
            resultEl.className = 'mt-2 p-2.5 rounded-lg text-xs bg-green-50 border border-green-200 text-green-700';
            let html = '<i class="fas fa-check-circle mr-1"></i> <strong>Connexion réussie</strong>';
            if (data.tenant_id) {
                html += '<br>Tenant ID&nbsp;: <code class="bg-white px-1 rounded border">' + data.tenant_id + '</code>';
            }
            if (email) {
                if (data.platform_user_id) {
                    html += '<br>User ID trouvé pour <strong>' + email + '</strong>&nbsp;: <code class="bg-white px-1 rounded border">' + data.platform_user_id + '</code>';
                } else {
                    html += '<br><span class="text-amber-700"><i class="fas fa-exclamation-triangle mr-1"></i>Aucun utilisateur trouvé pour <strong>' + email + '</strong> sur la plateforme.</span>';
                }
            }
            // Auto-remplir Tenant ID si vide
            if (data.tenant_id && !document.getElementById('sig_tenant_id').value)
                document.getElementById('sig_tenant_id').value = data.tenant_id;
            resultEl.innerHTML = html;
        } else {
            resultEl.className = 'mt-2 p-2.5 rounded-lg text-xs bg-red-50 border border-red-200 text-red-700';
            resultEl.innerHTML = '<i class="fas fa-times-circle mr-1"></i> <strong>Échec</strong> : ' + (data.message || 'Erreur inconnue.');
        }
    } catch (e) {
        resultEl.className = 'mt-2 p-2.5 rounded-lg text-xs bg-red-50 border border-red-200 text-red-700';
        resultEl.textContent = 'Erreur réseau : ' + e.message;
    }
}

function sigScopeTypeChange(type) {
    document.getElementById('f_sig_type').value = type;
    const sel = document.getElementById('sig_id_sel');
    sel.innerHTML = '<option value="">S&eacute;lectionner une administration</option>';
    const list = type === 'emitter' ? __sigEmitters : __sigRecipients;
    list.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name + (item.code ? ' (' + item.code + ')' : '');
        sel.appendChild(opt);
    });
    document.getElementById('f_sig_id').value = '';
}

function sigScopeIdChange(id) {
    document.getElementById('f_sig_id').value = id;
    if (id) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', 'signature-provider');
        url.searchParams.set('sig_admin_type', document.getElementById('f_sig_type').value);
        url.searchParams.set('sig_admin_id', id);
        window.location.href = url.toString();
    }
}

function sigToggleActive() {
    const h = document.getElementById('sig_is_active');
    const t = document.getElementById('sig_active_toggle');
    const k = document.getElementById('sig_active_knob');
    const on = h.value === '1';
    h.value = on ? '0' : '1';
    if (!on) { t.classList.replace('bg-gray-300','bg-blue-600'); k.classList.replace('translate-x-0','translate-x-5'); }
    else      { t.classList.replace('bg-blue-600','bg-gray-300'); k.classList.replace('translate-x-5','translate-x-0'); }
}

function sigToggleSsl() {
    const h = document.getElementById('sig_verify_ssl');
    const t = document.getElementById('sig_ssl_toggle');
    const k = document.getElementById('sig_ssl_knob');
    const on = h.value === '1';
    h.value = on ? '0' : '1';
    if (!on) { t.classList.replace('bg-gray-300','bg-blue-600'); k.classList.replace('translate-x-0','translate-x-5'); }
    else      { t.classList.replace('bg-blue-600','bg-gray-300'); k.classList.replace('translate-x-5','translate-x-0'); }
}

function sigToggleApiKey() {
    const inp = document.getElementById('sig_api_key');
    const btn = document.getElementById('sig_apikey_btn');
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = 'Masquer'; }
    else { inp.type = 'password'; btn.textContent = 'Afficher'; }
}
</script>@elseif($tab === 'user-profiles')
@php
$permissionTree = [
    'dashboard'        => ['label' => 'Tableau de bord', 'children' => []],
    'courrier'         => ['label' => 'Gestion Courrier', 'children' => [
        'courrier.enregistrement'   => 'Enregistrement',
        'courrier.liste'            => 'Liste des courriers',
        'courrier.imputation'       => 'Imputation',
        'courrier.en-traitement'    => 'En traitement',
        'courrier.suivi-imputation' => 'Suivi des imputations',
        'courrier.traite'           => 'Courriers traités',
      'courrier.archives'         => 'Archives',
    ]],
    'templates-shared' => ['label' => 'Modèles partagés', 'children' => [
        'templates-shared.view' => 'Voir les modèles',
    ]],
    'documents'        => ['label' => 'Documents', 'children' => [
        'documents.view'           => 'Voir',
        'documents.upload'         => 'Déposer',
        'documents.create-folder'  => 'Créer un dossier',
        'documents.share'          => 'Partager',
        'documents.edit-onlyoffice'=> 'Éditer en ligne',
        'documents.delete'         => 'Supprimer',
    ]],
    'workflows'        => ['label' => 'Workflows', 'children' => [
        'workflows.view'     => 'Voir',
        'workflows.create'   => 'Créer',
        'workflows.validate' => 'Valider',
        'workflows.delete'   => 'Supprimer',
    ]],
    'signatures'       => ['label' => 'Signatures', 'children' => [
        'signatures.view'   => 'Voir',
        'signatures.request'=> 'Demander',
        'signatures.sign'   => 'Signer',
        'signatures.reject' => 'Rejeter',
    ]],
    'reception'        => ['label' => 'Réception', 'children' => [
        'reception.view'    => 'Voir les courriers reçus',
        'reception.process' => 'Traiter / accuser réception',
      'reception.archives' => 'Voir le sous-onglet Archives',
    ]],
    'act-requests'     => ['label' => "Demandes d'actes", 'children' => [
        'act-requests.view'    => 'Voir',
        'act-requests.process' => 'Traiter',
    ]],
    'qrcode'           => ['label' => 'Vérification QR', 'children' => [
        'qrcode.scan' => 'Scanner / vérifier un document',
    ]],
    'administration'   => ['label' => 'Administration', 'children' => [
        'administration.templates'          => 'Modèles',
        'administration.emitters'           => 'Émetteurs',
        'administration.recipients'         => 'Destinataires',
      'administration.sub-entities'       => 'Entités sous tutelle',
      'administration.direction-types'    => 'Types de direction',
        'administration.requested-acts'     => 'Actes demandés',
        'administration.routing'            => 'Règles de routage',
        'administration.onlyoffice'         => 'OnlyOffice',
        'administration.users'              => 'Utilisateurs',
        'administration.theming'            => 'Thème & Apparence',
        'administration.email-notifications'=> 'Notifications email',
        'administration.signature-provider' => 'API Signature',
      'administration.courrier-archiving' => 'Archivage courrier',
      'administration.instructions'        => 'Instructions',
        'administration.user-profiles'      => 'Profils & Rôles',
        'administration.antivirus'          => 'Journal Antivirus',
    ]],
    'personnel'        => ['label' => 'Gestion du personnel', 'children' => [
        'personnel.dashboard'  => 'Tableau de bord',
        'personnel.employees'  => 'Employés',
        'personnel.agent-space'=> 'Espace agent',
        'personnel.leave'      => 'Congés',
      'personnel.leave.validation' => 'Congés - Validation',
      'personnel.leave.parameters' => 'Congés - Paramètres',
      'personnel.leave.recent'     => 'Congés - Demandes récentes',
        'personnel.training'   => 'Formation',
        'personnel.career'     => 'Carrière',
    ]],
    'meetings'         => ['label' => 'Réunions', 'children' => [
        'meetings.view'           => 'Voir les réunions',
        'meetings.create'         => 'Créer / modifier',
        'meetings.manage-rooms'   => 'Gérer les salles',
        'meetings.attendance'     => 'Suivi des présences',
    ]],
];
$editProfile = null;
if (request('edit_profile')) {
    $editProfile = $profiles->getCollection()->firstWhere('id', request('edit_profile'));
}
@endphp

{{-- Header --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-lg font-bold text-gray-800">Profils et rôles</h2>
        <p class="text-xs text-gray-500 mt-0.5">Gérez les profils d'accès et assignez-les aux utilisateurs.</p>
    </div>
    <button onclick="openModal('modal-profile-create')"
        class="px-4 py-2.5 bg-slate-600 text-white rounded-xl text-sm font-semibold hover:bg-slate-700 transition flex items-center gap-2">
        <i class="fas fa-plus"></i> Nouveau profil
    </button>
</div>

{{-- Tableau des rôles --}}
<div class="mb-3">
    <input type="text" id="profiles-search" placeholder="Rechercher un profil ou rôle…"
           oninput="profilesSearch(this.value)"
           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-slate-300 focus:border-slate-400 outline-none">
</div>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="overflow-x-auto">
    <table class="w-full min-w-[600px] text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-1/4">Nom du rôle</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-1/4">Description</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider hidden md:table-cell w-1/4">Administration</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider hidden lg:table-cell">Perms.</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider hidden lg:table-cell">Users</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100" id="profiles-tbody">
            @forelse($profiles as $profile)
            @php
                $perms = is_array($profile->permissions) ? ($profile->permissions['menuPermissions'] ?? $profile->permissions) : [];
                $userCount = \App\Models\User::where('profile_id', $profile->id)->count();
                $profileAdministrationLabel = $profile->administration_label;
                $profileAdministrationTypeLabel = $profile->administration_type_label;
            @endphp
            <tr data-search="{{ strtolower($profile->name . ' ' . ($profile->description ?? '') . ' ' . $profileAdministrationLabel . ' ' . $profileAdministrationTypeLabel) }}"
                class="hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-semibold text-gray-800 truncate max-w-[150px]">{{ $profile->name }}</td>
                <td class="px-4 py-3 text-gray-500 truncate max-w-[160px]">{{ $profile->description ?: '—' }}</td>
                <td class="px-4 py-3 text-gray-500 max-w-[180px] hidden md:table-cell">
                    <div class="truncate">{{ $profileAdministrationLabel }}</div>
                    <div class="text-[11px] text-gray-400">{{ $profileAdministrationTypeLabel }}</div>
                </td>
                <td class="px-4 py-3 text-center hidden lg:table-cell">
                    <span class="inline-flex items-center gap-1 text-xs bg-purple-50 text-purple-700 border border-purple-100 px-2 py-0.5 rounded-full font-semibold">
                        <i class="fas fa-shield-alt"></i> {{ count($perms) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-center hidden lg:table-cell">
                    @if($userCount > 0)
                    <span class="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-full font-semibold">
                        <i class="fas fa-users"></i> {{ $userCount }}
                    </span>
                    @else
                    <span class="text-xs text-gray-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-2">
                        <button type="button"
                           onclick="openProfileEditModal({{ json_encode(['id'=>$profile->id,'name'=>$profile->name,'description'=>$profile->description ?? '','administration_type'=>$profile->effective_administration_type,'administration_id'=>$profile->administration_id,'perms'=>is_array($profile->permissions) ? ($profile->permissions['menuPermissions'] ?? $profile->permissions) : []]) }})"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition">
                            <i class="fas fa-pen"></i> Modifier
                        </button>
                        <form method="POST" action="{{ route('admin.profiles.destroy', $profile) }}" onsubmit="return confirm('Supprimer le rôle « {{ addslashes($profile->name) }} » ?')" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">
                                <i class="fas fa-trash-alt"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                    <i class="fas fa-user-shield text-4xl text-gray-200 mb-3 block"></i>
                    Aucun rôle configuré. Cliquez sur <strong>Nouveau profil</strong> pour commencer.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($profiles->hasPages())
    <div class="px-5 py-3 border-t border-gray-100">{{ $profiles->appends(['tab'=>'user-profiles'])->links() }}</div>
    @endif
</div>
@push('scripts')
<script>
function profilesSearch(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#profiles-tbody [data-search]').forEach(function(row) {
        row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
    });
}
</script>
@endpush

@php
    $profileEmitterOptions = $emitters->map(fn($emitter) => ['id' => $emitter->id, 'name' => $emitter->name])->values();
    $profileRecipientOptions = $recipients->map(fn($recipient) => ['id' => $recipient->id, 'name' => $recipient->name])->values();
@endphp

{{-- Modal: create profile --}}
<div id="modal-profile-create" class="adm-modal">
    <div class="adm-modal-box max-w-xl">
        <button onclick="closeModal('modal-profile-create')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-5">Nouveau profil</h3>
        <form method="POST" action="{{ route('admin.profiles.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="Ex: Agent de réception"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" placeholder="Rôle ou usage"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Administration associée</label>
                    @if(isset($adminScope) && $adminScope)
                    <input type="hidden" name="administration_type" value="{{ $adminScope['type'] }}">
                    <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
                    <div class="w-full border border-gray-100 rounded-xl px-4 py-2.5 text-sm bg-gray-50 text-gray-700 flex items-center justify-between gap-3">
                      <span class="flex items-center gap-2 min-w-0">
                        <i class="fas fa-building text-gray-400"></i>
                        <span class="truncate">{{ $adminScope['type'] === 'recipient' ? ($recipients->first()?->name ?? '--') : ($emitters->first()?->name ?? '--') }}</span>
                      </span>
                      <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                        {{ $adminScope['type'] === 'recipient' ? 'Destinataire' : 'Émettrice' }}
                      </span>
                    </div>
                    @else
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Type d'administration</label>
                            <select id="create-profile-admin-type" name="administration_type" onchange="profileAdministrationTypeChange('create')"
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <option value="">« Aucune (globale) »</option>
                                <option value="emitter">Émettrice</option>
                                <option value="recipient">Destinataire</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Administration</label>
                            <select id="create-profile-administration-id" name="administration_id" disabled
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="">Sélectionner une administration</option>
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Permissions initiales</label>
              <p class="text-xs text-gray-500 mb-2">Les onglets et sous-onglets sont indépendants : vous pouvez sélectionner uniquement l'onglet, ou un ou plusieurs sous-onglets.</p>
                <div class="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100 max-h-64 overflow-y-auto">
                    @foreach($permissionTree as $parentKey => $parent)
                    <div>
                        <div class="flex items-center gap-3 px-4 py-2 bg-gray-50">
                            <input type="checkbox" name="permissions[]" value="{{ $parentKey }}"
                                class="modal-parent-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                onchange="modalHandleParent(this)" data-group="{{ $parentKey }}">
                            <span class="text-sm font-semibold text-gray-700">{{ $parent['label'] }}</span>
                        </div>
                        @foreach($parent['children'] as $childKey => $childLabel)
                        <label class="flex items-center gap-3 px-4 py-1.5 pl-10 hover:bg-blue-50/30 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $childKey }}"
                                class="modal-child-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                data-parent="{{ $parentKey }}" onchange="modalHandleChild(this)">
                            <span class="text-sm text-gray-600">{{ $childLabel }}</span>
                        </label>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="pt-2 flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modal-profile-create')"
                    class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Annuler</button>
                <button type="submit"
                    class="px-5 py-2.5 bg-slate-600 text-white rounded-xl text-sm font-semibold hover:bg-slate-700 transition">Créer</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: edit profile --}}
<div id="modal-profile-edit" class="adm-modal">
    <div class="adm-modal-box max-w-xl">
        <button onclick="closeModal('modal-profile-edit')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-5">Modifier le profil</h3>
        <form id="form-profile-edit" method="POST" action="" class="space-y-4" onsubmit="console.log('Form submitting to:', this.action); return true;">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-profile-name" name="name" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                    <input type="text" id="edit-profile-description" name="description" placeholder="Rôle ou usage"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Administration associée</label>
                    @if(isset($adminScope) && $adminScope)
                    <input type="hidden" name="administration_type" value="{{ $adminScope['type'] }}">
                    <input type="hidden" name="administration_id" value="{{ $adminScope['id'] }}">
                    <div class="w-full border border-gray-100 rounded-xl px-4 py-2.5 text-sm bg-gray-50 text-gray-700 flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 min-w-0">
                            <i class="fas fa-building text-gray-400"></i>
                            <span class="truncate">{{ $adminScope['type'] === 'recipient' ? ($recipients->first()?->name ?? '--') : ($emitters->first()?->name ?? '--') }}</span>
                        </span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                            {{ $adminScope['type'] === 'recipient' ? 'Destinataire' : 'Émettrice' }}
                        </span>
                    </div>
                    @else
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Type d'administration</label>
                            <select id="edit-profile-admin-type" name="administration_type" onchange="profileAdministrationTypeChange('edit')"
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <option value="">« Aucune (globale) »</option>
                                <option value="emitter">Émettrice</option>
                                <option value="recipient">Destinataire</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Administration</label>
                            <select id="edit-profile-administration-id" name="administration_id" disabled
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-400 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="">Sélectionner une administration</option>
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-semibold text-gray-700">Permissions</label>
                    <div class="flex gap-2">
                        <button type="button" onclick="editModalCheckAll(true)" class="text-xs text-slate-600 hover:underline">Tout cocher</button>
                        <span class="text-gray-300">|</span>
                        <button type="button" onclick="editModalCheckAll(false)" class="text-xs text-gray-400 hover:underline">Tout décocher</button>
                    </div>
                </div>
              <p class="text-xs text-gray-500 mb-2">Les onglets et sous-onglets sont indépendants : vous pouvez sélectionner uniquement l'onglet, ou un ou plusieurs sous-onglets.</p>
                <div class="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100 max-h-72 overflow-y-auto">
                    @foreach($permissionTree as $parentKey => $parent)
                    <div>
                        <div class="flex items-center gap-3 px-4 py-2 bg-gray-50">
                            <input type="checkbox" name="permissions[]" value="{{ $parentKey }}"
                                class="edit-modal-parent-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                onchange="editModalHandleParent(this)" data-group="{{ $parentKey }}">
                            <span class="text-sm font-semibold text-gray-700">{{ $parent['label'] }}</span>
                        </div>
                        @foreach($parent['children'] as $childKey => $childLabel)
                        <label class="flex items-center gap-3 px-4 py-1.5 pl-10 hover:bg-blue-50/30 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $childKey }}"
                                class="edit-modal-child-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                data-parent="{{ $parentKey }}" onchange="editModalHandleChild(this)">
                            <span class="text-sm text-gray-600">{{ $childLabel }}</span>
                        </label>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="pt-2 flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modal-profile-edit')"
                    class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Annuler</button>
                <button type="submit"
                    class="px-5 py-2.5 bg-slate-600 text-white rounded-xl text-sm font-semibold hover:bg-slate-700 transition">
                    <i class="fas fa-save mr-1"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ---------------------- INSTRUCTIONS ---------------------- --}}
@elseif($tab === 'instructions')
@php $editInstruction = request('edit_instr') ? $instructions->firstWhere('id', (int) request('edit_instr')) : null; @endphp

<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

    {{-- Colonne gauche : formulaire création --}}
    <div class="lg:col-span-2 space-y-4">

        {{-- Formulaire création --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-plus text-cyan-600 text-sm"></i>
                Nouvelle instruction
            </h2>
            @if(session('success') && request('tab') === 'instructions')
            <div class="mb-3 px-4 py-2.5 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm flex items-center gap-2">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
            @endif
            <form method="POST" action="{{ route('admin.instructions.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="tab" value="instructions">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" required value="{{ old('nom') }}"
                        placeholder="Ex : Pour information, Pour visa…"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        placeholder="Description de l'instruction de traitement…"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 resize-none">{{ old('description') }}</textarea>
                </div>
                <button type="submit"
                    class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2">
                    <i class="fas fa-plus text-xs"></i> Créer l'instruction
                </button>
            </form>
        </div>

        {{-- Formulaire édition (si sélectionné) --}}
        @if($editInstruction)
        <div class="bg-white rounded-2xl border border-cyan-300 ring-2 ring-cyan-100 shadow-sm p-5">
            <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-pen text-cyan-600 text-xs"></i>
                Modifier : {{ $editInstruction->nom }}
            </h3>
            <form method="POST" action="{{ route('admin.instructions.update', $editInstruction) }}" class="space-y-3">
                @csrf @method('PUT')
                <input type="hidden" name="tab" value="instructions">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" required value="{{ $editInstruction->nom }}"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 resize-none">{{ $editInstruction->description }}</textarea>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('admin.index', ['tab' => 'instructions']) }}"
                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold transition text-center">
                        Annuler
                    </a>
                    <button type="submit"
                        class="flex-1 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-sm font-semibold transition">
                        <i class="fas fa-save mr-1"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- Colonne droite : liste --}}
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list-check text-cyan-600"></i>
                    Instructions de traitement
                </h2>
                <span class="text-xs bg-cyan-50 text-cyan-700 px-2.5 py-1 rounded-full font-semibold">
                    {{ $instructions->count() }} instruction{{ $instructions->count() !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="px-5 py-3 border-b border-gray-100">
                <input type="text" id="instr-search" placeholder="Rechercher une instruction…"
                       oninput="instrSearch(this.value)"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none">
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nom</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="instr-tbody">
                    @forelse($instructions as $instr)
                    <tr data-search="{{ strtolower($instr->nom . ' ' . ($instr->description ?? '')) }}"
                        class="hover:bg-gray-50 transition {{ request('edit_instr') == $instr->id ? 'bg-cyan-50' : '' }}">
                        <td class="px-5 py-3.5 font-semibold text-gray-800">{{ $instr->nom }}</td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $instr->description ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.index', ['tab' => 'instructions', 'edit_instr' => $instr->id]) }}"
                                    class="p-1.5 rounded-lg hover:bg-cyan-50 text-gray-400 hover:text-cyan-600 transition">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.instructions.destroy', $instr) }}"
                                    onsubmit="return confirm('Supprimer cette instruction ?')" class="inline">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="tab" value="instructions">
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-5 py-16 text-center text-gray-400">
                            <i class="fas fa-list-check text-4xl text-gray-200 mb-3 block"></i>
                            Aucune instruction configurée.<br>
                            <span class="text-xs">Créez votre première instruction à gauche.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function instrSearch(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#instr-tbody [data-search]').forEach(function(row) {
        row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
    });
}
</script>
@endpush
{{-- ══════════════════ ARCHIVAGE COURRIER ══════════════════ --}}
@elseif($tab === 'courrier-archiving')
@php
    $archivalDays = $courrierArchivalDays ?? 0;
  $receptionArchivalDaysValue = $receptionArchivalDays ?? 0;
    $archivalThresholdDate = $archivalDays > 0 ? now()->subDays($archivalDays) : null;
    $archivedCount = $archivalThresholdDate
        ? \App\Models\Courrier::where('created_at', '<', $archivalThresholdDate)->count()
        : 0;
    $totalCount = \App\Models\Courrier::count();
  $receptionArchivalThresholdDate = $receptionArchivalDaysValue > 0 ? now()->subDays($receptionArchivalDaysValue) : null;
  $receptionArchivedCount = $receptionArchivalThresholdDate
    ? \App\Models\DocumentShare::where('created_at', '<', $receptionArchivalThresholdDate)->distinct('document_id')->count('document_id')
    : 0;
  $receptionTotalCount = \App\Models\DocumentShare::query()->distinct('document_id')->count('document_id');
@endphp

<div class="max-w-3xl mx-auto space-y-5">

    {{-- Statut actuel --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start gap-4">
            <div class="h-12 w-12 rounded-xl bg-stone-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-archive text-stone-500 text-lg"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-lg font-bold text-gray-800 mb-1">Archivage automatique des courriers</h2>
                <p class="text-sm text-gray-500">
                    Configurez le délai après lequel les courriers sont considérés comme archivés et n'apparaissent plus
                    dans les sous-onglets de gestion (liste, imputation, traitement, suivi).
                    Les courriers archivés restent en base de données et ne sont pas supprimés.
                </p>
            </div>
        </div>

        @if($archivalDays > 0)
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-stone-50 rounded-xl p-4 text-center border border-stone-100">
                <div class="text-2xl font-black text-stone-700">{{ $archivalDays }}</div>
                <div class="text-xs text-stone-500 mt-1">Jours de délai</div>
            </div>
            <div class="bg-red-50 rounded-xl p-4 text-center border border-red-100">
                <div class="text-2xl font-black text-red-600">{{ number_format($archivedCount) }}</div>
                <div class="text-xs text-red-400 mt-1">Courriers archivés</div>
            </div>
            <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
                <div class="text-2xl font-black text-green-600">{{ number_format($totalCount - $archivedCount) }}</div>
                <div class="text-xs text-green-500 mt-1">Courriers actifs</div>
            </div>
        </div>
        @else
        <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-amber-500"></i>
            <span class="text-sm text-amber-700">
                L'archivage automatique est <strong>désactivé</strong>. Tous les courriers ({{ number_format($totalCount) }}) sont visibles dans les sous-onglets.
            </span>
        </div>
        @endif
    </div>

    {{-- Formulaire de configuration --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-sliders-h text-stone-500 text-sm"></i>
            Paramétrer le délai d'archivage
        </h3>

        @if(session('success') && request('tab') === 'courrier-archiving')
        <div class="mb-4 px-4 py-2.5 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.save') }}" class="space-y-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" value="courrier-archiving">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Délai d'archivage <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" name="courrier_archival_days"
                        value="{{ $archivalDays > 0 ? $archivalDays : '' }}"
                        min="1" max="3650" step="1"
                        placeholder="ex : 365"
                        class="w-40 border border-gray-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-800 focus:outline-none focus:ring-2 focus:ring-stone-400 text-center">
                    <span class="text-sm text-gray-500 font-medium">jours</span>
                </div>
                <p class="mt-2 text-xs text-gray-400">
                    Laissez vide pour désactiver l'archivage automatique (tous les courriers restent visibles).
                    Exemple : <strong>365</strong> pour archiver après 1 an, <strong>180</strong> pour 6 mois.
                </p>
            </div>

              <div class="border-t border-gray-100 pt-5">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Délai d'archivage des fichiers reçus (Réception)
                </label>
                <div class="flex items-center gap-3">
                  <input type="number" name="reception_archival_days"
                    value="{{ $receptionArchivalDaysValue > 0 ? $receptionArchivalDaysValue : '' }}"
                    min="1" max="3650" step="1"
                    placeholder="ex : 365"
                    class="w-40 border border-gray-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-800 focus:outline-none focus:ring-2 focus:ring-stone-400 text-center">
                  <span class="text-sm text-gray-500 font-medium">jours</span>
                </div>
                <p class="mt-2 text-xs text-gray-400">
                  Les documents reçus plus anciens seront masqués de l'onglet Réception et visibles dans son sous-onglet <strong>Archives</strong>.
                  Laissez vide pour désactiver l'archivage des fichiers reçus.
                </p>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="bg-red-50 rounded-xl p-3 text-center border border-red-100">
                    <div class="text-xl font-black text-red-600">{{ number_format($receptionArchivedCount) }}</div>
                    <div class="text-xs text-red-400 mt-1">Fichiers reçus archivés</div>
                  </div>
                  <div class="bg-green-50 rounded-xl p-3 text-center border border-green-100">
                    <div class="text-xl font-black text-green-600">{{ number_format(max($receptionTotalCount - $receptionArchivedCount, 0)) }}</div>
                    <div class="text-xs text-green-500 mt-1">Fichiers reçus actifs</div>
                  </div>
                </div>
              </div>

            {{-- Suggestions rapides --}}
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-2">Suggestions rapides :</p>
                <div class="flex flex-wrap gap-2">
                    @foreach([30 => '1 mois', 90 => '3 mois', 180 => '6 mois', 365 => '1 an', 730 => '2 ans'] as $days => $label)
                    <button type="button"
                        onclick="document.querySelector('[name=courrier_archival_days]').value = '{{ $days }}'"
                        class="px-3 py-1.5 text-xs rounded-lg border font-semibold transition
                            {{ $archivalDays === $days ? 'bg-stone-600 text-white border-stone-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                      @foreach([30 => 'Réception 1 mois', 90 => 'Réception 3 mois', 180 => 'Réception 6 mois', 365 => 'Réception 1 an'] as $days => $label)
                      <button type="button"
                        onclick="document.querySelector('[name=reception_archival_days]').value = '{{ $days }}'"
                        class="px-3 py-1.5 text-xs rounded-lg border font-semibold transition
                          {{ $receptionArchivalDaysValue === $days ? 'bg-stone-600 text-white border-stone-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100' }}">
                        {{ $label }}
                      </button>
                      @endforeach
                    <button type="button"
                        onclick="document.querySelector('[name=courrier_archival_days]').value = ''"
                        class="px-3 py-1.5 text-xs rounded-lg border font-semibold transition border-red-200 text-red-500 hover:bg-red-50">
                        Désactiver
                    </button>
                      <button type="button"
                        onclick="document.querySelector('[name=reception_archival_days]').value = ''"
                        class="px-3 py-1.5 text-xs rounded-lg border font-semibold transition border-red-200 text-red-500 hover:bg-red-50">
                        Désactiver Réception
                      </button>
                </div>
            </div>

            {{-- Aperçu dynamique --}}
            <div id="archival-preview" class="p-4 bg-gray-50 rounded-xl border border-gray-200 text-sm text-gray-600 hidden">
                <i class="fas fa-info-circle text-blue-400 mr-1"></i>
                <span id="archival-preview-text"></span>
            </div>
                  <div id="reception-archival-preview" class="p-4 bg-gray-50 rounded-xl border border-gray-200 text-sm text-gray-600 hidden">
                    <i class="fas fa-info-circle text-blue-400 mr-1"></i>
                    <span id="reception-archival-preview-text"></span>
                  </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                    class="px-6 py-2.5 bg-stone-600 hover:bg-stone-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-sm">
                    <i class="fas fa-save text-xs"></i> Enregistrer
                </button>
                <a href="{{ route('admin.index', ['tab' => 'courrier-archiving']) }}"
                    class="px-5 py-2.5 border border-gray-300 text-gray-600 hover:bg-gray-100 rounded-xl text-sm font-semibold transition">
                    Annuler
                </a>
            </div>
        </form>
    </div>

    {{-- Info sur ce qui est affecté --}}
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5">
        <h4 class="text-sm font-bold text-blue-800 mb-3 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-500"></i>
            Sous-onglets affectés par l'archivage
        </h4>
        <ul class="space-y-1.5 text-xs text-blue-700">
            <li class="flex items-center gap-2"><i class="fas fa-list text-blue-400 w-4"></i> <strong>Liste des courriers</strong> — les courriers archivés ne sont plus listés</li>
            <li class="flex items-center gap-2"><i class="fas fa-share text-blue-400 w-4"></i> <strong>Imputation</strong> — les courriers en attente archivés disparaissent de la file</li>
            <li class="flex items-center gap-2"><i class="fas fa-spinner text-blue-400 w-4"></i> <strong>En traitement</strong> — les courriers en cours archivés sont masqués</li>
            <li class="flex items-center gap-2"><i class="fas fa-binoculars text-blue-400 w-4"></i> <strong>Suivi imputation</strong> — les courriers traités archivés sont masqués</li>
            <li class="flex items-center gap-2"><i class="fas fa-check-circle text-blue-400 w-4"></i> <strong>Courrier traité</strong> — les courriers traités archivés ne sont plus listés</li>
          <li class="flex items-center gap-2"><i class="fas fa-inbox text-blue-400 w-4"></i> <strong>Réception</strong> — les anciens fichiers reçus sont déplacés vers le sous-onglet Archives</li>
        </ul>
        <p class="mt-3 text-xs text-blue-600">
            <i class="fas fa-database mr-1"></i>
            Les courriers archivés ne sont <strong>pas supprimés</strong> de la base de données. L'archivage est uniquement visuel (filtre par date de création).
        </p>
    </div>
</div>

@endif
@push('scripts')
<script>
if (document.querySelector('[name=courrier_archival_days]')) {
(function() {
    var input = document.querySelector('[name=courrier_archival_days]');
    var preview = document.getElementById('archival-preview');
    var previewText = document.getElementById('archival-preview-text');
  var receptionInput = document.querySelector('[name=reception_archival_days]');
  var receptionPreview = document.getElementById('reception-archival-preview');
  var receptionPreviewText = document.getElementById('reception-archival-preview-text');
  if (!input || !preview || !previewText || !receptionInput || !receptionPreview || !receptionPreviewText) return;

  function updateSinglePreview(field, container, textNode, messagePrefix) {
    var days = parseInt(field.value, 10);
    if (!days || days <= 0) { container.classList.add('hidden'); return; }
    var date = new Date();
    date.setDate(date.getDate() - days);
    var formatted = date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
    textNode.textContent = messagePrefix + formatted + '.';
    container.classList.remove('hidden');
  }

  function updatePreview() {
    updateSinglePreview(
      input,
      preview,
      previewText,
      'Les courriers créés avant le '
    );
    updateSinglePreview(
      receptionInput,
      receptionPreview,
      receptionPreviewText,
      'Les fichiers reçus partagés avant le '
    );
  }

  input.addEventListener('input', updatePreview);
  receptionInput.addEventListener('input', updatePreview);
  updatePreview();
})();
}
</script>
@endpush
@push('scripts')
<script>
var _adminBase = window._adminBase || @json(route('admin.index', [], false));

function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id, force) {
    if (!force && id === 'modal-tpl-oo' && typeof window.tplOoCanCloseModal === 'function') {
        if (!window.tplOoCanCloseModal()) {
            return;
        }
    }

    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
document.querySelectorAll('.adm-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            // Bloquer fermeture par clic sur background si close-guard actif
            if (modal.id === 'modal-tpl-oo' && typeof window.tplOoCanCloseModal === 'function' && !window.tplOoCanCloseModal()) {
                console.log('[CloseGuard] Fermeture via background bloquée');
                return;
            }
            closeModal(modal.id);
        }
    });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        var openModals = document.querySelectorAll('.adm-modal.open');
        openModals.forEach(m => {
            // Bloquer Escape si close-guard actif sur le modal OO
            if (m.id === 'modal-tpl-oo' && typeof window.tplOoCanCloseModal === 'function' && !window.tplOoCanCloseModal()) {
                console.log('[CloseGuard] Fermeture via Escape bloquée');
                e.preventDefault();
                return;
            }
            closeModal(m.id);
        });
    }
});

function openEditEmitter(id, name, code, isActive) {
    document.getElementById('edit_emit_name').value = name;
    document.getElementById('edit_emit_code').value = code;
    document.getElementById('edit_emit_active').checked = isActive;
    document.getElementById('form-emitter-edit').action = _adminBase + '/emitters/' + id;
    openModal('modal-emitter-edit');
}


function updateScopeStyle(radio) {
    const sameLabel = document.getElementById('scope-same-label');
    const allLabel  = document.getElementById('scope-all-label');
    if (!sameLabel || !allLabel) return;
    if (radio.value === 'same_admin') {
        sameLabel.className = sameLabel.className.replace('border-gray-200 hover:border-gray-300','').trim() + ' border-blue-600 bg-blue-50';
        allLabel.className  = allLabel.className.replace('border-blue-600 bg-blue-50','').trim() + ' border-gray-200 hover:border-gray-300';
    } else {
        allLabel.className  = allLabel.className.replace('border-gray-200 hover:border-gray-300','').trim() + ' border-blue-600 bg-blue-50';
        sameLabel.className = sameLabel.className.replace('border-blue-600 bg-blue-50','').trim() + ' border-gray-200 hover:border-gray-300';
    }
}

// -- Permission matrix helpers ---------------------------------------------
function checkAllPermissions(state) {
    document.querySelectorAll('.perm-group input[type=checkbox]').forEach(cb => cb.checked = state);
}
function handleParentCheck(parentCb) {
    const group = parentCb.dataset.group;
    document.querySelectorAll(`.child-perm[data-parent="${group}"]`).forEach(cb => cb.checked = parentCb.checked);
}
function handleChildCheck(childCb) {
    const group = childCb.dataset.parent;
    const allChildren = document.querySelectorAll(`.child-perm[data-parent="${group}"]`);
    const allChecked  = Array.from(allChildren).every(cb => cb.checked);
    const parentCb    = document.querySelector(`.parent-perm[data-group="${group}"]`);
    if (parentCb) parentCb.checked = allChecked;
}
function togglePermGroup(headerEl) {
    const group    = headerEl.closest('.perm-group');
    const children = group.querySelector('.perm-children');
    const chevron  = headerEl.querySelector('.group-chevron');
    if (!children) return;
    const hidden = children.style.display === 'none';
    children.style.display = hidden ? '' : 'none';
    if (chevron) chevron.style.transform = hidden ? '' : 'rotate(-90deg)';
}
// modal permission helpers (create)
function modalHandleParent(parentCb) {
  // Parent and child permissions are intentionally independent.
}
function modalHandleChild(childCb) {
  // Parent and child permissions are intentionally independent.
}
var profileAdministrationOptions = {
  emitter: @json($profileEmitterOptions ?? []),
  recipient: @json($profileRecipientOptions ?? [])
};

function fillProfileAdministrationOptions(prefix, selectedValue) {
    var typeEl = document.getElementById(prefix + '-profile-admin-type');
    var adminEl = document.getElementById(prefix + '-profile-administration-id');
    if (!typeEl || !adminEl) return;

    var type = typeEl.value || '';
    var options = profileAdministrationOptions[type] || [];
    adminEl.innerHTML = '<option value="">Sélectionner une administration</option>';

    if (!type) {
        adminEl.disabled = true;
        return;
    }

    options.forEach(function(option) {
        var opt = document.createElement('option');
        opt.value = option.id;
        opt.textContent = option.name;
        if (selectedValue && selectedValue === option.id) {
            opt.selected = true;
        }
        adminEl.appendChild(opt);
    });

    adminEl.disabled = false;
}

function profileAdministrationTypeChange(prefix, selectedValue) {
    fillProfileAdministrationOptions(prefix, selectedValue || '');
}

document.addEventListener('DOMContentLoaded', function() {
    profileAdministrationTypeChange('create');
});

// modal permission helpers (edit)
function openProfileEditModal(data) {
    document.getElementById('edit-profile-name').value        = data.name        || '';
    document.getElementById('edit-profile-description').value = data.description || '';
    var adminTypeEl = document.getElementById('edit-profile-admin-type');
    if (adminTypeEl) {
        adminTypeEl.value = data.administration_type || '';
        profileAdministrationTypeChange('edit', data.administration_id || '');
    }
    // set form action (without query params - use method spoofing with @method('PUT'))
    document.getElementById('form-profile-edit').action = _adminBase + '/profiles/' + data.id;
    // reset all checkboxes then check the active ones
    document.querySelectorAll('#modal-profile-edit input[type=checkbox]').forEach(cb => cb.checked = false);
    var activeSet = {};
    (data.perms || []).forEach(function(k){ activeSet[k] = true; });
    document.querySelectorAll('#modal-profile-edit input[type=checkbox]').forEach(cb => {
        if (activeSet[cb.value]) cb.checked = true;
    });
    openModal('modal-profile-edit');
}
function editModalCheckAll(state) {
    document.querySelectorAll('#modal-profile-edit input[type=checkbox]').forEach(cb => cb.checked = state);
}
function editModalHandleParent(parentCb) {
  // Parent and child permissions are intentionally independent.
}
function editModalHandleChild(childCb) {
  // Parent and child permissions are intentionally independent.
}
</script>
@endpush

{{-- -----------------------------------------------------------
     �DITEUR QUILL � Contenu du template (avec insertion de {{variables}})
----------------------------------------------------------- --}}
@push('scripts')
<link href="{{ asset('vendor/quill/quill.snow.css') }}" rel="stylesheet"
  onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css';">
<script src="{{ asset('vendor/quill/quill.min.js') }}"
    onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js';"></script>
<style>
/* -- Conteneur �diteur ----------------------------------------------- */
#tpl-quill-editor .ql-editor {
    min-height: 280px;
    font-size: 13px;
    font-family: 'Times New Roman', serif;
    line-height: 1.8;
    padding: 16px 20px;
    background: #fff;
}
#tpl-quill-editor .ql-toolbar {
    border-radius: 8px 8px 0 0;
    background: #f1f5f9;
    border-color: #cbd5e1;
    padding: 6px 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
}
#tpl-quill-editor .ql-container {
    border-radius: 0 0 8px 8px;
    border-color: #cbd5e1;
    font-size: 13px;
}
/* -- S�lecteurs police / taille -------------------------------------- */
#tpl-quill-editor .ql-font .ql-picker-label,
#tpl-quill-editor .ql-size .ql-picker-label {
    font-size: 11px;
    font-weight: 600;
}
#tpl-quill-editor .ql-font { width: 130px; }
#tpl-quill-editor .ql-size { width: 80px; }
/* -- Polices dans le dropdown ---------------------------------------- */
.ql-font-arial       { font-family: Arial, sans-serif; }
.ql-font-times       { font-family: 'Times New Roman', serif; }
.ql-font-courier     { font-family: 'Courier New', monospace; }
.ql-font-georgia     { font-family: Georgia, serif; }
.ql-font-verdana     { font-family: Verdana, sans-serif; }
.ql-font-tahoma      { font-family: Tahoma, sans-serif; }
/* -- Rendu des polices dans l'�diteur -------------------------------- */
.ql-editor .ql-font-arial       { font-family: Arial, sans-serif !important; }
.ql-editor .ql-font-times       { font-family: 'Times New Roman', serif !important; }
.ql-editor .ql-font-courier     { font-family: 'Courier New', monospace !important; }
.ql-editor .ql-font-georgia     { font-family: Georgia, serif !important; }
.ql-editor .ql-font-verdana     { font-family: Verdana, sans-serif !important; }
.ql-editor .ql-font-tahoma      { font-family: Tahoma, sans-serif !important; }
/* -- Tailles dans le dropdown ---------------------------------------- */
.ql-size-8px  { font-size: 8px; }
.ql-size-10px { font-size: 10px; }
.ql-size-12px { font-size: 12px; }
.ql-size-14px { font-size: 14px; }
.ql-size-16px { font-size: 16px; }
.ql-size-18px { font-size: 18px; }
.ql-size-20px { font-size: 20px; }
.ql-size-24px { font-size: 24px; }
.ql-size-36px { font-size: 36px; }
.ql-size-48px { font-size: 48px; }
/* -- Variable pill --------------------------------------------------- */
.tpl-var-pill { transition: all .15s; }
.tpl-var-pill:hover { transform: scale(1.05); }
/* -- Redimensionnement manuel --------------------------------------- */
#tpl-quill-editor .ql-editor { resize: vertical; overflow-y: auto; }
</style>
@verbatim
<script>
(function() {
    var _quill = null;

  if (typeof Quill === 'undefined') {
    console.warn('Quill non charge: editeur des templates indisponible.');
    return;
  }

    /* -- Enregistrement des polices -------------------------------- */
    var FontClass = Quill.import('attributors/class/font');
    FontClass.whitelist = ['arial','times','courier','georgia','verdana','tahoma'];
    Quill.register(FontClass, true);

    /* -- Enregistrement des tailles ------------------------------- */
    var SizeClass = Quill.import('attributors/class/size');
    SizeClass.whitelist = ['8px','10px','12px','14px','16px','18px','20px','24px','36px','48px'];
    Quill.register(SizeClass, true);

    function initQuill() {
        var editorEl = document.getElementById('tpl-quill-editor');
        var hiddenTa = document.getElementById('tpl-content');
        if (!editorEl || typeof Quill === 'undefined') return;

        _quill = new Quill('#tpl-quill-editor', {
            theme: 'snow',
            placeholder: 'R�digez votre template ici� Ex: Je soussign� {{nom}}, n� le {{date_naissance}}, demande�',
            modules: {
                toolbar: [
                    /* Ligne 1 � Police, taille, titres */
                    [
                        { 'font': ['arial','times','courier','georgia','verdana','tahoma'] },
                        { 'size': ['8px','10px','12px','14px','16px','18px','20px','24px','36px','48px'] },
                        { 'header': [1, 2, 3, 4, false] }
                    ],
                    /* Ligne 2 � Style texte */
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    /* Ligne 3 � Alignement, listes */
                    [{ 'align': [] }],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }, { 'list': 'check' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                    /* Ligne 4 � Divers */
                    ['blockquote', 'code-block'],
                    [{ 'script': 'sub' }, { 'script': 'super' }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        var existing = hiddenTa ? hiddenTa.value.trim() : '';
        if (existing) {
            if (existing.startsWith('<')) {
                _quill.root.innerHTML = existing;
            } else {
                _quill.setText(existing);
            }
        }

        var form = document.getElementById('tpl-form');
        if (form) {
            form.addEventListener('submit', function() {
                if (hiddenTa) hiddenTa.value = _quill.root.innerHTML;
            });
        }
        _quill.on('text-change', function() {
            if (hiddenTa) hiddenTa.value = _quill.root.innerHTML;
        });
    }

    /* -- Recharger le contenu quand un template est s�lectionn� -- */
    var _origTplSelect = window.tplSelect;
    window.tplSelect = function(id) {
        if (_origTplSelect) _origTplSelect(id);
        setTimeout(function() {
            var hiddenTa = document.getElementById('tpl-content');
            if (_quill && hiddenTa) {
                var val = hiddenTa.value.trim();
                if (val && val.startsWith('<')) { _quill.root.innerHTML = val; }
                else if (val) { _quill.setText(val); }
                else { _quill.setText(''); }
            }
        }, 400);
    };

    /* -- Ins�rer une variable {{}} � la position du curseur ------ */
    window.tplQuillInsertVar = function(varText) {
        if (!_quill) return;
        var range = _quill.getSelection(true);
        var pos = range ? range.index : _quill.getLength() - 1;
        _quill.insertText(pos, varText, { color: '#1d4ed8', bold: true, background: '#dbeafe' });
        _quill.insertText(pos + varText.length, ' ', { color: false, bold: false, background: false });
        _quill.setSelection(pos + varText.length + 1);
        _quill.focus();
    };

    /* -- Ins�rer une variable personnalis�e ----------------------- */
    window.tplQuillInsertCustomVar = function() {
        var name = prompt('Nom de la variable (ex: nom_directeur, date_signature) :');
        if (!name) return;
        name = name.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
        if (!name) return;
        tplQuillInsertVar('[' + name + ']');
    };

    /* -- Compteur de mots/caract�res ------------------------------- */
    window.tplQuillGetStats = function() {
        if (!_quill) return { words: 0, chars: 0 };
        var text = _quill.getText().trim();
        var words = text ? text.split(/\s+/).filter(Boolean).length : 0;
        return { words: words, chars: text.length };
    };

    if (typeof Quill !== 'undefined') { initQuill(); }
    else { document.addEventListener('DOMContentLoaded', initQuill); }
})();
</script>
@endverbatim
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const adminSelect = document.getElementById('smtpAdminSelect');
  const formWrap    = document.getElementById('smtpFormWrap');
  const saveBtn     = document.getElementById('smtpSaveBtn');
  const testBtn     = document.getElementById('smtpTestBtn');
  const result      = document.getElementById('smtpTestResult');
  if (!adminSelect) return;

  const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
  const fields = ['mail_host','mail_port','mail_username','mail_password','mail_from_address','mail_from_name','mail_encryption'];

  function showResult(msg, ok) {
    result.textContent = msg;
    result.className = 'text-sm rounded-xl px-4 py-3 font-medium ' +
      (ok ? 'bg-green-50 border border-green-300 text-green-700'
        : 'bg-red-50 border border-red-300 text-red-700');
  }

  function getPayload() {
    const p = {
      administration_id:   document.getElementById('smtpAdminId').value,
      administration_type: document.getElementById('smtpAdminType').value,
    };
    fields.forEach(f => {
      const el = document.getElementById('smtp_' + f);
      if (el) p[f] = el.value;
    });
    return p;
  }

  adminSelect.addEventListener('change', function () {
    const opt  = this.options[this.selectedIndex];
    const id   = opt.value;
    const type = opt.dataset.type || 'emitter';
    formWrap.classList.add('hidden');
    result.className = 'hidden';
    fields.forEach(f => {
      const el = document.getElementById('smtp_' + f);
      if (!el) return;
      el.value = (f === 'mail_port') ? '587' : '';
    });
    if (!id) return;
    document.getElementById('smtpAdminId').value   = id;
    document.getElementById('smtpAdminType').value = type;
    fetch(`{{ url('admin/smtp-settings') }}/${type}/${id}`, {
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }
    })
    .then(r => r.json())
    .then(data => {
      fields.forEach(f => {
        const el = document.getElementById('smtp_' + f);
        if (el && f !== 'mail_password') el.value = data[f] ?? '';
      });
      const enc = document.getElementById('smtp_mail_encryption');
      if (enc) enc.value = data['mail_encryption'] ?? 'tls';
      const port = document.getElementById('smtp_mail_port');
      if (port && !port.value) port.value = '587';
      formWrap.classList.remove('hidden');
    })
    .catch(() => { formWrap.classList.remove('hidden'); });
  });

  if (saveBtn) saveBtn.addEventListener('click', function () {
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement…';
    fetch('{{ route('admin.smtp.settings.save') }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(getPayload()),
    })
    .then(r => r.json())
    .then(data => showResult(data.message, data.success))
    .catch(err => showResult('Erreur réseau : ' + err.message, false))
    .finally(() => { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer'; });
  });

  if (testBtn) testBtn.addEventListener('click', function () {
    testBtn.disabled = true;
    testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours…';
    result.className = 'hidden';
    fetch('{{ route('admin.smtp.test') }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        administration_id:   document.getElementById('smtpAdminId').value,
        administration_type: document.getElementById('smtpAdminType').value,
      }),
    })
    .then(r => r.json())
    .then(data => showResult(data.message, data.success))
    .catch(err => showResult('Erreur réseau : ' + err.message, false))
    .finally(() => { testBtn.disabled = false; testBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Tester la configuration SMTP'; });
  });
});
</script>
@endpush

{{-- ══════════════════════ ANTIVIRUS ══════════════════════ --}}
@if($tab === 'antivirus')
@php
$avEnabled = config('services.clamav.enabled', false);
$avResultFilter = request('av_result', '');
$avContextFilter = request('av_context', '');
$avContextLabels = [
    'documents'    => 'Documents',
    'courriers'    => 'Courriers',
    'signatures'   => 'Signatures',
    'reunions'     => 'Réunions',
    'profil'       => 'Profil',
    'actes-publics'=> 'Actes publics',
    'rh-mutations' => 'RH — Mutations',
    'rh-employes'  => 'RH — Employés',
    'rh-documents' => 'RH — Documents',
    'rh-conges'    => 'RH — Congés',
    'rh-formations'=> 'RH — Formations',
    'templates'    => 'Templates',
    'apparence'    => 'Apparence',
    'emetteurs'    => 'Émetteurs',
    'destinataires'=> 'Destinataires',
    'utilisateurs' => 'Utilisateurs',
];
@endphp

{{-- Statut ClamAV --}}
<div class="mb-6 flex items-center gap-3 px-5 py-4 rounded-2xl border
    {{ $avEnabled ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }}">
    <i class="fas fa-shield-virus text-xl {{ $avEnabled ? 'text-green-600' : 'text-amber-500' }}"></i>
    <div>
        <p class="font-semibold text-sm {{ $avEnabled ? 'text-green-800' : 'text-amber-800' }}">
            Antivirus ClamAV — {{ $avEnabled ? 'Activé' : 'Désactivé (mode développement)' }}
        </p>
        <p class="text-xs {{ $avEnabled ? 'text-green-600' : 'text-amber-600' }} mt-0.5">
            @if($avEnabled)
                Chaque fichier uploadé est scanné avant enregistrement. Les résultats sont consignés ci-dessous.
            @else
                Activez <code class="bg-amber-100 px-1 rounded font-mono">CLAMAV_ENABLED=true</code> dans <code class="bg-amber-100 px-1 rounded font-mono">.env</code> sur le serveur Ubuntu de production pour activer les scans.
            @endif
        </p>
    </div>
</div>

{{-- Compteurs rapides --}}
@if((method_exists($scanLogs, 'total') ? $scanLogs->total() : $scanLogs->count()) > 0 || $avResultFilter || $avContextFilter)
@php
    $hasAvTable   = \Illuminate\Support\Facades\Schema::hasTable('clamav_scan_logs');
    $totalScans   = $hasAvTable ? \App\Models\ClamAvScanLog::count() : 0;
    $cleanCount   = $hasAvTable ? \App\Models\ClamAvScanLog::where('result', 'clean')->count() : 0;
    $infectedCount= $hasAvTable ? \App\Models\ClamAvScanLog::where('result', 'infected')->count() : 0;
    $errorCount   = $hasAvTable ? \App\Models\ClamAvScanLog::where('result', 'error')->count() : 0;
@endphp
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-gray-800">{{ number_format($totalScans) }}</p>
        <p class="text-xs text-gray-500 mt-1">Fichiers scannés</p>
    </div>
    <div class="bg-white rounded-2xl border border-green-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-green-600">{{ number_format($cleanCount) }}</p>
        <p class="text-xs text-gray-500 mt-1">Propres</p>
    </div>
    <div class="bg-white rounded-2xl border border-red-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-red-600">{{ number_format($infectedCount) }}</p>
        <p class="text-xs text-gray-500 mt-1">Infectés</p>
    </div>
    <div class="bg-white rounded-2xl border border-amber-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-amber-600">{{ number_format($errorCount) }}</p>
        <p class="text-xs text-gray-500 mt-1">Erreurs</p>
    </div>
</div>
@endif

{{-- Filtres --}}
<form method="GET" action="{{ route('admin.index') }}" class="flex flex-wrap gap-3 mb-5 items-end">
    <input type="hidden" name="tab" value="antivirus">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Résultat</label>
        <select name="av_result" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
            <option value="">Tous les résultats</option>
            <option value="clean"    {{ $avResultFilter === 'clean'    ? 'selected' : '' }}>✓ Propre</option>
            <option value="infected" {{ $avResultFilter === 'infected' ? 'selected' : '' }}>✗ Infecté</option>
            <option value="error"    {{ $avResultFilter === 'error'    ? 'selected' : '' }}>⚠ Erreur</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Module</label>
        <select name="av_context" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
            <option value="">Tous les modules</option>
            @foreach($avContextLabels as $val => $lbl)
            <option value="{{ $val }}" {{ $avContextFilter === $val ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
    @if($avResultFilter || $avContextFilter)
    <a href="{{ route('admin.index', ['tab' => 'antivirus']) }}" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-200 transition">
        <i class="fas fa-times mr-1"></i> Réinitialiser
    </a>
    @endif
</form>

{{-- Tableau des résultats --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    @if($scanLogs->isEmpty())
    <div class="py-16 text-center text-gray-400">
        <i class="fas fa-shield-virus text-4xl mb-3"></i>
        <p class="text-sm font-medium">Aucun scan enregistré{{ $avResultFilter || $avContextFilter ? ' pour ces filtres' : '' }}.</p>
        @if(!$avEnabled)
        <p class="text-xs mt-1">Activez ClamAV sur le serveur pour commencer à scanner les fichiers.</p>
        @endif
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fichier</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Taille</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Module</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Résultat</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Menace</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Utilisateur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @foreach($scanLogs as $log)
            @php
                $resultConfig = match($log->result) {
                    'infected' => ['bg-red-100 text-red-700',   'fas fa-bug',          'Infecté'],
                    'error'    => ['bg-amber-100 text-amber-700','fas fa-exclamation-triangle', 'Erreur'],
                    default    => ['bg-green-100 text-green-700','fas fa-check-circle', 'Propre'],
                };
                [$resultClass, $resultIcon, $resultLabel] = $resultConfig;
                $sizeFormatted = $log->file_size
                    ? ($log->file_size >= 1048576
                        ? number_format($log->file_size / 1048576, 1) . ' Mo'
                        : number_format($log->file_size / 1024, 0) . ' Ko')
                    : '—';
                $contextLabel = $avContextLabels[$log->context] ?? ($log->context ?? '—');
                $userName = $log->user?->full_name ?: ($log->user?->name ?: ($log->user?->email ?? '—'));
            @endphp
            <tr class="{{ $log->result === 'infected' ? 'bg-red-50' : '' }} hover:bg-gray-50 transition">
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                    {{ $log->scanned_at?->format('d/m/Y H:i:s') ?? '—' }}
                </td>
                <td class="px-4 py-3 max-w-[200px]">
                    <span class="block truncate font-medium text-gray-800" title="{{ $log->file_name }}">
                        {{ $log->file_name }}
                    </span>
                    @if($log->mime_type)
                    <span class="text-xs text-gray-400">{{ $log->mime_type }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ $sizeFormatted }}</td>
                <td class="px-4 py-3 text-xs">
                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-medium">{{ $contextLabel }}</span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold {{ $resultClass }}">
                        <i class="{{ $resultIcon }}"></i> {{ $resultLabel }}
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-red-700 font-mono">
                    {{ $log->threat ?? '—' }}
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ $userName }}</td>
                <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $log->ip_address ?? '—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(method_exists($scanLogs, 'hasPages') && $scanLogs->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">
        {{ $scanLogs->appends(request()->except('av_page'))->links() }}
    </div>
    @endif
    @endif
</div>
@endif

@endsection
