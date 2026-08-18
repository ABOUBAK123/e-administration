<?php $__env->startSection('title', 'Administration'); ?>
<?php $__env->startSection('page-title', 'Administration'); ?>
<?php $__env->startSection('page-subtitle', 'Configurez l\'ensemble de votre application'); ?>
<?php $__env->startSection('content'); ?>


<style>
.adm-modal { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.45); align-items:center; justify-content:center; }
.adm-modal.open { display:flex; }
.adm-modal-box { background:#fff; border-radius:1.25rem; padding:2rem; width:100%; max-width:520px; box-shadow:0 20px 60px rgba(0,0,0,.2); position:relative; max-height:90vh; overflow-y:auto; }
.adm-tab-label { display:none; }
@media (min-width: 640px) { .adm-tab-label { display:inline; } }
</style>


<?php
$tab = request('tab', 'overview');
$personnelTab = request('personnel_tab', 'dashboard');
$allTabs = [
  'overview'           => ['fas fa-th-large',          'Aperçu',                '#6366f1', null],
  'templates'          => ['fas fa-file-code',         'Templates',             '#0ea5e9', 'administration.templates'],
  'emitters'           => ['fas fa-building',          'Émetteurs',             '#8b5cf6', 'administration.emitters'],
  'recipients'         => ['fas fa-paper-plane',       'Destinataires',         '#ec4899', 'administration.recipients'],
  'sub-entities'       => ['fas fa-sitemap',           'Entités sous tutelle',  '#14b8a6', 'administration.sub-entities'],
  'requested-acts'     => ['fas fa-clipboard-list',    'Actes demandés',        '#f59e0b', 'administration.requested-acts'],
  'civil-status-types' => ['fas fa-id-card-clip',      'Types État civil',      '#0d9488', 'administration.civil-status-types'],
    'direction-types'    => ['fas fa-tags',               'Types de direction',    '#10b981', 'administration.direction-types'],
    'routing'            => ['fas fa-route',              'Routage',               '#f97316', 'administration.routing'],
    'onlyoffice'         => ['fas fa-edit',               'OnlyOffice',            '#06b6d4', 'administration.onlyoffice'],
    'ai-integration'     => ['fas fa-robot',              'Intelligence Artificielle', '#7c3aed', 'administration.ai-integration'],
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
$tabs = array_filter($allTabs, function($v) use ($permSvcAdmin, $permSetAdmin) {
    $perm = $v[3];
    if ($perm === null) return true;
    if ($permSetAdmin['isElevated']) return true;
    // can() gère aussi bien "un parent accordé donne accès à ses enfants" (ex: profil admin
    // avec la seule permission 'administration' voit tous ses sous-onglets) que l'inverse
    // (ex: personnel.dashboard → affiche l'onglet parent 'personnel').
    return $permSvcAdmin->can(auth()->user(), $perm);
});

if ($tab !== 'personnel' && !array_key_exists($tab, $tabs)) {
  $tab = 'overview';
}
?>


<?php
  $currentProfileName = auth()->user()?->profile?->name ?? '';
  $normalizedProfileName = mb_strtoupper(trim(str_replace(['_', '-'], ' ', $currentProfileName)), 'UTF-8');
  $isAgentRhProfile = str_contains($normalizedProfileName, 'AGENT RH');
?>
<?php if(isset($adminScope) && $adminScope && $tab === 'personnel' && $isAgentRhProfile): ?>
<?php
    $scopedAdminLabel = null;
    if ($adminScope['type'] === 'emitter') {
        $scopedAdminLabel = \App\Models\IssuingAdministration::find($adminScope['id'])?->name;
    } else {
        $scopedAdminLabel = \App\Models\RecipientAdministration::find($adminScope['id'])?->name;
    }
?>
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
    <i class="fas fa-building text-blue-500 flex-shrink-0"></i>
    <span>Vous gérez l'administration <strong><?php echo e($scopedAdminLabel ?? $adminScope['id']); ?></strong>. Seules les données de cette administration sont visibles et modifiables.</span>
</div>
<?php endif; ?>


<?php if($tab === 'personnel'): ?>

<?php
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
?>
<div class="flex flex-wrap gap-1.5 mb-6 bg-white rounded-2xl border border-gray-200 p-2 shadow-sm">
    <?php $__currentLoopData = $_personnelNavTabsFiltered; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.index', ['tab' => 'personnel', 'personnel_tab' => $key])); ?>"
       title="<?php echo e($label); ?>"
       class="flex items-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition
              <?php echo e($personnelTab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'); ?>">
        <i class="fa <?php echo e($icon); ?> text-sm flex-shrink-0"></i>
        <span class="adm-tab-label"><?php echo e($label); ?></span>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php else: ?>
<div class="flex flex-wrap gap-1.5 mb-6 bg-white rounded-2xl border border-gray-200 p-2 shadow-sm">
    <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$icon, $label, $color, $perm]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.index', ['tab' => $key])); ?>"
       title="<?php echo e($label); ?>"
       class="flex items-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition
              <?php echo e($tab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'); ?>">
        <i class="fa <?php echo e($icon); ?> text-sm flex-shrink-0"></i>
        <span class="adm-tab-label"><?php echo e($label); ?></span>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php endif; ?>


<?php if($tab === 'overview'): ?>
<?php
    $overviewAdminScope = $adminScope ?? null;
    $overviewAdminName  = null;
    if ($overviewAdminScope) {
        if ($overviewAdminScope['type'] === 'emitter') {
            $overviewAdminName = \App\Models\IssuingAdministration::find($overviewAdminScope['id'])?->name;
        } else {
            $overviewAdminName = \App\Models\RecipientAdministration::find($overviewAdminScope['id'])?->name;
        }
    }
?>
<?php if($overviewAdminScope): ?>
<div class="mb-5 flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3">
    <i class="fas fa-building text-blue-500"></i>
    <div>
        <span class="text-sm font-semibold text-blue-800">Périmètre : </span>
        <span class="text-sm text-blue-700"><?php echo e($overviewAdminName ?? $overviewAdminScope['id']); ?></span>
        <span class="ml-2 text-xs text-blue-500 bg-blue-100 rounded-full px-2 py-0.5"><?php echo e($overviewAdminScope['type'] === 'emitter' ? 'Émetteur' : 'Destinataire'); ?></span>
    </div>
</div>
<?php else: ?>
<div class="mb-5 flex items-center gap-3 bg-violet-50 border border-violet-200 rounded-xl px-4 py-3">
    <i class="fas fa-globe text-violet-500"></i>
    <span class="text-sm font-semibold text-violet-800">Super Admin — toutes les administrations</span>
</div>
<?php endif; ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php $__currentLoopData = [
        ['fas fa-users',      $stats['users'],      'Utilisateurs',  'blue'],
        ['fas fa-file-alt',   $stats['documents'],  'Documents',      'green'],
        ['fas fa-pen-nib',    $stats['signatures'], 'Signatures',     'purple'],
        ['fas fa-code-branch',$stats['workflows'],  'Workflows',      'orange'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $count, $label, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="h-10 w-10 rounded-xl bg-<?php echo e($color); ?>-100 flex items-center justify-center">
                <i class="fa <?php echo e($icon); ?> text-<?php echo e($color); ?>-500"></i>
            </div>
            <span class="text-2xl font-black text-gray-800"><?php echo e(number_format($count)); ?></span>
        </div>
        <p class="text-sm font-semibold text-gray-600"><?php echo e($label); ?></p>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php
$_oc = [
    'templates'          => method_exists($templates, 'total') ? $templates->total() : $templates->count(),
    'emitters'           => $emitters->count(),
    'recipients'         => $recipients->count(),
    'sub-entities'       => $subEntities->count(),
    'requested-acts'     => $requestedActs->count(),
    'civil-status-types' => $civilStatusRecordTypes->count(),
    'direction-types'    => $directionTypes->count(),
    'routing'            => method_exists($routingRules, 'total') ? $routingRules->total() : $routingRules->count(),
    'users'              => method_exists($users, 'total') ? $users->total() : $users->count(),
    'user-profiles'      => method_exists($profiles, 'total') ? $profiles->total() : $profiles->count(),
    'instructions'       => $instructions->count(),
    'signature-provider' => $sigProviders->count(),
];
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php $__currentLoopData = [
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
        ['civil-status-types', 'fas fa-id-card-clip',       'Types État civil',      'Naissance, mariage, décès et pièces requises.', 'teal'],
        ['onlyoffice',         'fas fa-edit',               'OnlyOffice',            'Serveur d\'édition collaborative.',        'cyan'],
        ['ai-integration',     'fas fa-robot',              'Intelligence Artificielle', 'Clé API IA (Gemini) pour les comptes rendus.', 'violet'],
        ['courrier-archiving', 'fas fa-archive',            'Archivage courrier',    'Délai d\'archivage automatique des courriers.','stone'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$t, $icon, $title, $desc, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $cnt = $_oc[$t] ?? null; ?>
    <a href="<?php echo e(route('admin.index', ['tab' => $t])); ?>"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition group">
        <div class="flex items-start justify-between mb-3">
            <div class="h-11 w-11 rounded-xl bg-<?php echo e($color); ?>-100 flex items-center justify-center">
                <i class="fa <?php echo e($icon); ?> text-<?php echo e($color); ?>-600"></i>
            </div>
            <?php if($cnt !== null): ?>
            <span class="text-2xl font-black text-gray-800"><?php echo e(number_format($cnt)); ?></span>
            <?php endif; ?>
        </div>
        <h3 class="font-bold text-gray-800 mb-1 text-sm"><?php echo e($title); ?></h3>
        <p class="text-xs text-gray-400"><?php echo e($desc); ?></p>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<?php elseif($tab === 'personnel'): ?>
<?php
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
?>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-6">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h2 class="text-xl font-bold text-gray-800"><?php echo e(__('personnel.ui.module.title')); ?></h2>
      <p class="text-sm text-gray-500 mt-1"><?php echo e(__('personnel.ui.module.description')); ?></p>
    </div>
    <?php if($personnelTab === 'training'): ?>
    <?php
      $_tpCurrentUser = auth()->user();
      $_tpProfile = $_tpCurrentUser?->profile_id ? \App\Models\AdministrationProfile::find($_tpCurrentUser->profile_id) : null;
      $_tpProfileName = mb_strtoupper(trim(str_replace(['_','-'],' ',$_tpProfile?->name ?? '')), 'UTF-8');
      $_tpIsAgentRh = str_contains($_tpProfileName, 'AGENT RH');
      $_tpIsSuperAdmin = $_tpCurrentUser?->role === 'admin';
      // Supérieur hiérarchique : a des collaborateurs dont il est user_id
      $_tpIsSuperior = \App\Models\PersonnelEmployee::where('user_id', $_tpCurrentUser?->id)->exists();
      $_tpCanSeeRequests = $_tpIsAgentRh || $_tpIsSuperAdmin || $_tpIsSuperior;
    ?>
    <?php if($_tpCanSeeRequests): ?>
    <button type="button"
      onclick="document.getElementById('modal-training-requests').classList.remove('hidden')"
      class="ml-auto flex-shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold shadow transition">
      <i class="fas fa-graduation-cap text-xs"></i>
      Voir demandes de formations
    </button>
    <?php endif; ?>
    <?php elseif($personnelTab === 'dashboard'): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 min-w-0">
      <div class="rounded-xl bg-teal-50 border border-teal-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-teal-700"><?php echo e(__('personnel.ui.stats.employees')); ?></div>
        <div class="text-2xl font-black text-gray-800"><?php echo e(number_format($personnelStats['employees'] ?? 0)); ?></div>
      </div>
      <div class="rounded-xl bg-cyan-50 border border-cyan-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-cyan-700"><?php echo e(__('personnel.ui.stats.documents')); ?></div>
        <div class="text-2xl font-black text-gray-800"><?php echo e(number_format($personnelStats['documents'] ?? 0)); ?></div>
      </div>
      <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-emerald-700"><?php echo e(__('personnel.ui.stats.active')); ?></div>
        <div class="text-2xl font-black text-gray-800"><?php echo e(number_format($personnelStats['active'] ?? 0)); ?></div>
      </div>
      <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3">
        <div class="text-xs uppercase tracking-wide text-amber-700"><?php echo e(__('personnel.ui.stats.new_hires_year', ['year' => now()->year])); ?></div>
        <div class="text-2xl font-black text-gray-800"><?php echo e(number_format($personnelStats['newThisYear'] ?? 0)); ?></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if($personnelTab === 'dashboard'): ?>
<?php
  $pd = $personnelDashboard ?? null;
  $pdGender = $pd['byGender'] ?? [];
  $pdGrade = $pd['byGrade'] ?? [];
  $pdLeaveByType = $pd['leaveByType'] ?? [];
  $pdRetirement = $pd['retirement'] ?? ['within1Year' => 0, 'within3Years' => 0, 'within5Years' => 0, 'upcoming' => []];
  $pdStaffing = $pd['staffingNeeds'] ?? ['total' => 0, 'gap' => 0, 'urgent' => 0, 'items' => []];
  $pdOnLeaveToday = $pd['onLeaveToday'] ?? 0;
  $pdInTrainingToday = $pd['inTrainingToday'] ?? 0;
?>
<div class="mb-6">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('personnel.ui.dashboard.decisional.title')); ?></h3>
      <p class="text-sm text-gray-500"><?php echo e(__('personnel.ui.dashboard.decisional.subtitle')); ?></p>
    </div>
  </div>

  
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4">
      <div class="h-10 w-10 rounded-xl bg-sky-100 flex items-center justify-center mb-2">
        <i class="fa fa-calendar-check text-sky-600"></i>
      </div>
      <div class="text-2xl font-bold text-gray-800"><?php echo e($pdOnLeaveToday); ?></div>
      <div class="text-sm text-gray-500"><?php echo e(__('personnel.ui.dashboard.decisional.kpi.on_leave_today')); ?></div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4">
      <div class="h-10 w-10 rounded-xl bg-violet-100 flex items-center justify-center mb-2">
        <i class="fa fa-graduation-cap text-violet-600"></i>
      </div>
      <div class="text-2xl font-bold text-gray-800"><?php echo e($pdInTrainingToday); ?></div>
      <div class="text-sm text-gray-500"><?php echo e(__('personnel.ui.dashboard.decisional.kpi.in_training_today')); ?></div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4">
      <div class="h-10 w-10 rounded-xl bg-amber-100 flex items-center justify-center mb-2">
        <i class="fa fa-person-walking-arrow-right text-amber-600"></i>
      </div>
      <div class="text-2xl font-bold text-gray-800"><?php echo e($pdRetirement['within1Year'] ?? 0); ?></div>
      <div class="text-sm text-gray-500"><?php echo e(__('personnel.ui.dashboard.decisional.kpi.retirement_1y')); ?></div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4">
      <div class="h-10 w-10 rounded-xl bg-rose-100 flex items-center justify-center mb-2">
        <i class="fa fa-user-plus text-rose-600"></i>
      </div>
      <div class="text-2xl font-bold text-gray-800"><?php echo e($pdStaffing['gap'] ?? 0); ?></div>
      <div class="text-sm text-gray-500"><?php echo e(__('personnel.ui.dashboard.decisional.kpi.staffing_gap')); ?></div>
    </div>
  </div>

  
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
      <h4 class="font-semibold text-gray-800 mb-3"><?php echo e(__('personnel.ui.dashboard.decisional.charts.gender_title')); ?></h4>
      <?php if(count($pdGender) > 0): ?>
        <canvas id="pdGenderChart" height="180"></canvas>
      <?php else: ?>
        <p class="text-sm text-gray-400"><?php echo e(__('personnel.ui.dashboard.decisional.charts.no_data')); ?></p>
      <?php endif; ?>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
      <h4 class="font-semibold text-gray-800 mb-3"><?php echo e(__('personnel.ui.dashboard.decisional.charts.grade_title')); ?></h4>
      <?php if(count($pdGrade) > 0): ?>
        <canvas id="pdGradeChart" height="180"></canvas>
      <?php else: ?>
        <p class="text-sm text-gray-400"><?php echo e(__('personnel.ui.dashboard.decisional.charts.no_data')); ?></p>
      <?php endif; ?>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
      <h4 class="font-semibold text-gray-800 mb-3"><?php echo e(__('personnel.ui.dashboard.decisional.charts.retirement_title')); ?></h4>
      <canvas id="pdRetirementChart" height="180"></canvas>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
      <h4 class="font-semibold text-gray-800 mb-3"><?php echo e(__('personnel.ui.dashboard.decisional.charts.leave_type_title')); ?></h4>
      <?php if(count($pdLeaveByType) > 0): ?>
        <canvas id="pdLeaveTypeChart" height="180"></canvas>
      <?php else: ?>
        <p class="text-sm text-gray-400"><?php echo e(__('personnel.ui.dashboard.decisional.charts.no_data')); ?></p>
      <?php endif; ?>
    </div>
  </div>

  
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
      <h4 class="font-semibold text-gray-800 mb-3"><?php echo e(__('personnel.ui.dashboard.decisional.retirement_table.title')); ?></h4>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-200">
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.retirement_table.name')); ?></th>
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.retirement_table.job_title')); ?></th>
              <th class="py-2"><?php echo e(__('personnel.ui.dashboard.decisional.retirement_table.date')); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = ($pdRetirement['upcoming'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $upcomingItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr class="border-b border-gray-100">
              <td class="py-2 pr-3 font-medium text-gray-800"><?php echo e($upcomingItem['full_name']); ?></td>
              <td class="py-2 pr-3 text-gray-500"><?php echo e($upcomingItem['job_title']); ?></td>
              <td class="py-2 text-gray-500"><?php echo e(\Illuminate\Support\Carbon::parse($upcomingItem['retirement_date'])->format('d/m/Y')); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="3" class="py-3 text-gray-400"><?php echo e(__('personnel.ui.dashboard.decisional.retirement_table.empty')); ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
      <div class="flex items-center justify-between gap-3 mb-1">
        <h4 class="font-semibold text-gray-800"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.title')); ?></h4>
        <button type="button" onclick="document.getElementById('pdStaffingCreateModal').classList.add('open')" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#2453d6] text-white hover:bg-[#1c3fa8]">
          <i class="fa fa-plus mr-1"></i><?php echo e(__('personnel.ui.dashboard.decisional.staffing.add_button')); ?>

        </button>
      </div>
      <p class="text-sm text-gray-500 mb-3"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.subtitle')); ?></p>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-200">
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.job_title')); ?></th>
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.required')); ?></th>
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.current')); ?></th>
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.gap')); ?></th>
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.priority')); ?></th>
              <th class="py-2 pr-3"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.status')); ?></th>
              <th class="py-2"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.actions')); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = ($pdStaffing['items'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $need): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $priorityColors = ['urgent' => 'bg-red-100 text-red-700', 'high' => 'bg-orange-100 text-orange-700', 'normal' => 'bg-sky-100 text-sky-700', 'low' => 'bg-gray-100 text-gray-600'];
              $statusColors = ['open' => 'bg-emerald-100 text-emerald-700', 'filled' => 'bg-gray-100 text-gray-500', 'cancelled' => 'bg-gray-100 text-gray-400 line-through'];
            ?>
            <tr class="border-b border-gray-100">
              <td class="py-2 pr-3 font-medium text-gray-800"><?php echo e($need['job_title']); ?></td>
              <td class="py-2 pr-3 text-gray-500"><?php echo e($need['required_count']); ?></td>
              <td class="py-2 pr-3 text-gray-500"><?php echo e($need['current_count']); ?></td>
              <td class="py-2 pr-3 font-semibold <?php echo e($need['gap'] > 0 ? 'text-rose-600' : 'text-emerald-600'); ?>"><?php echo e($need['gap']); ?></td>
              <td class="py-2 pr-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo e($priorityColors[$need['priority']] ?? 'bg-gray-100 text-gray-600'); ?>"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.priorities.' . $need['priority'])); ?></span></td>
              <td class="py-2 pr-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo e($statusColors[$need['status']] ?? 'bg-gray-100 text-gray-600'); ?>"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.statuses.' . $need['status'])); ?></span></td>
              <td class="py-2">
                <div class="flex items-center gap-2">
                  <button type="button"
                    onclick='pdOpenStaffingEditModal(<?php echo json_encode($need, 15, 512) ?>)'
                    class="text-xs text-[#2453d6] hover:underline"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.edit_title')); ?></button>
                  <form method="POST" action="<?php echo e(route('admin.personnel.staffing-needs.destroy', $need['id'])); ?>" onsubmit="return confirm('<?php echo e(__('personnel.ui.dashboard.decisional.staffing.confirm_delete')); ?>')">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <input type="hidden" name="personnel_tab" value="dashboard">
                    <button type="submit" class="text-xs text-rose-600 hover:underline">
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="7" class="py-3 text-gray-400"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.empty')); ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


<div id="pdStaffingCreateModal" class="adm-modal">
  <div class="adm-modal-box">
    <button type="button" onclick="document.getElementById('pdStaffingCreateModal').classList.remove('open')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa fa-times"></i></button>
    <h3 id="pdStaffingModalTitle" class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.create_title')); ?></h3>
    <form id="pdStaffingForm" method="POST" action="<?php echo e(route('admin.personnel.staffing-needs.store')); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="_method" id="pdStaffingMethod" value="POST">
      <input type="hidden" name="personnel_tab" value="dashboard">
      <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type'] ?? 'emitter'); ?>">
      <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id'] ?? ''); ?>">
      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.job_title')); ?></label>
          <input type="text" name="job_title" id="pdStaffingJobTitle" required class="w-full rounded-lg border-gray-300">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.required')); ?></label>
            <input type="number" min="0" name="required_count" id="pdStaffingRequired" required class="w-full rounded-lg border-gray-300">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.current')); ?></label>
            <input type="number" min="0" name="current_count" id="pdStaffingCurrent" class="w-full rounded-lg border-gray-300">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.priority')); ?></label>
            <select name="priority" id="pdStaffingPriority" class="w-full rounded-lg border-gray-300">
              <?php $__currentLoopData = ['urgent','high','normal','low']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($p); ?>"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.priorities.' . $p)); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.status')); ?></label>
            <select name="status" id="pdStaffingStatus" class="w-full rounded-lg border-gray-300">
              <?php $__currentLoopData = ['open','filled','cancelled']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s); ?>"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.statuses.' . $s)); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.target_date')); ?></label>
          <input type="date" name="target_date" id="pdStaffingTargetDate" class="w-full rounded-lg border-gray-300">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.notes')); ?></label>
          <textarea name="notes" id="pdStaffingNotes" rows="2" class="w-full rounded-lg border-gray-300"></textarea>
        </div>
      </div>
      <div class="flex items-center justify-end gap-3 mt-5">
        <button type="button" onclick="document.getElementById('pdStaffingCreateModal').classList.remove('open')" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.cancel')); ?></button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-[#2453d6] text-white font-semibold"><?php echo e(__('personnel.ui.dashboard.decisional.staffing.save')); ?></button>
      </div>
    </form>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function () {
  const pdGenderData = <?php echo json_encode($pdGender, 15, 512) ?>;
  const pdGradeData = <?php echo json_encode($pdGrade, 15, 512) ?>;
  const pdLeaveTypeData = <?php echo json_encode($pdLeaveByType, 15, 512) ?>;
  const pdRetirementData = {
    within1Year: <?php echo e($pdRetirement['within1Year'] ?? 0); ?>,
    within3Years: <?php echo e($pdRetirement['within3Years'] ?? 0); ?>,
    within5Years: <?php echo e($pdRetirement['within5Years'] ?? 0); ?>,
  };

  function buildDoughnut(canvasId, dataObj) {
    const el = document.getElementById(canvasId);
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
      type: 'doughnut',
      data: {
        labels: Object.keys(dataObj),
        datasets: [{ data: Object.values(dataObj), backgroundColor: ['#2453d6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7', '#06b6d4'] }],
      },
      options: { plugins: { legend: { position: 'bottom' } } },
    });
  }

  function buildBar(canvasId, dataObj) {
    const el = document.getElementById(canvasId);
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
      type: 'bar',
      data: {
        labels: Object.keys(dataObj),
        datasets: [{ label: '<?php echo e(__('personnel.ui.dashboard.decisional.charts.grade_title')); ?>', data: Object.values(dataObj), backgroundColor: '#2453d6' }],
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
  }

  buildDoughnut('pdGenderChart', pdGenderData);
  buildBar('pdGradeChart', pdGradeData);
  buildDoughnut('pdLeaveTypeChart', pdLeaveTypeData);

  const retirementEl = document.getElementById('pdRetirementChart');
  if (retirementEl && typeof Chart !== 'undefined') {
    new Chart(retirementEl, {
      type: 'bar',
      data: {
        labels: [
          '<?php echo e(__('personnel.ui.dashboard.decisional.charts.retirement_1y')); ?>',
          '<?php echo e(__('personnel.ui.dashboard.decisional.charts.retirement_3y')); ?>',
          '<?php echo e(__('personnel.ui.dashboard.decisional.charts.retirement_5y')); ?>',
        ],
        datasets: [{
          label: '<?php echo e(__('personnel.ui.dashboard.decisional.charts.retirement_title')); ?>',
          data: [pdRetirementData.within1Year, pdRetirementData.within3Years, pdRetirementData.within5Years],
          backgroundColor: ['#ef4444', '#f59e0b', '#22c55e'],
        }],
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
  }

  window.pdOpenStaffingEditModal = function (need) {
    document.getElementById('pdStaffingModalTitle').textContent = <?php echo json_encode(__('personnel.ui.dashboard.decisional.staffing.edit_title'), 15, 512) ?>;
    document.getElementById('pdStaffingForm').action = '/admin/personnel/staffing-needs/' + need.id;
    document.getElementById('pdStaffingMethod').value = 'PUT';
    document.getElementById('pdStaffingJobTitle').value = need.job_title || '';
    document.getElementById('pdStaffingRequired').value = need.required_count || 0;
    document.getElementById('pdStaffingCurrent').value = need.current_count || 0;
    document.getElementById('pdStaffingPriority').value = need.priority || 'normal';
    document.getElementById('pdStaffingStatus').value = need.status || 'open';
    document.getElementById('pdStaffingTargetDate').value = need.target_date || '';
    document.getElementById('pdStaffingNotes').value = need.notes || '';
    document.getElementById('pdStaffingCreateModal').classList.add('open');
  };
})();
</script>
<?php $__env->stopPush(); ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-6">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo e(__('personnel.ui.dashboard.vision_title')); ?></h3>
    <p class="text-sm text-gray-500 mb-4"><?php echo e(__('personnel.ui.dashboard.vision_description')); ?></p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php $__currentLoopData = [
        [__('personnel.ui.dashboard.cards.employees_title'), __('personnel.ui.dashboard.cards.employees_desc'), 'fas fa-id-card', 'teal'],
        [__('personnel.ui.dashboard.cards.leave_title'), __('personnel.ui.dashboard.cards.leave_desc'), 'fas fa-calendar-check', 'sky'],
        [__('personnel.ui.dashboard.cards.training_title'), __('personnel.ui.dashboard.cards.training_desc'), 'fas fa-graduation-cap', 'violet'],
        [__('personnel.ui.dashboard.cards.career_title'), __('personnel.ui.dashboard.cards.career_desc'), 'fas fa-ranking-star', 'amber'],
      ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$title, $desc, $icon, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="rounded-2xl border border-gray-200 p-4 bg-gray-50">
        <div class="h-10 w-10 rounded-xl bg-<?php echo e($color); ?>-100 flex items-center justify-center mb-3">
          <i class="fa <?php echo e($icon); ?> text-<?php echo e($color); ?>-600"></i>
        </div>
        <h4 class="font-semibold text-gray-800 mb-1"><?php echo e($title); ?></h4>
        <p class="text-sm text-gray-500"><?php echo e($desc); ?></p>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.dashboard.workflow_title')); ?></h3>
    <div class="space-y-3">
      <?php $__currentLoopData = [
        [__('personnel.ui.dashboard.workflow.approvals_title'), __('personnel.ui.dashboard.workflow.approvals_desc')],
        [__('personnel.ui.dashboard.workflow.notifications_title'), __('personnel.ui.dashboard.workflow.notifications_desc')],
        [__('personnel.ui.dashboard.workflow.reminders_title'), __('personnel.ui.dashboard.workflow.reminders_desc')],
        [__('personnel.ui.dashboard.workflow.steering_title'), __('personnel.ui.dashboard.workflow.steering_desc')],
        [__('personnel.ui.dashboard.workflow.attendance_title'), __('personnel.ui.dashboard.workflow.attendance_desc')],
      ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800"><?php echo e($title); ?></div>
        <div class="text-sm text-gray-500 mt-1"><?php echo e($desc); ?></div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="mt-5 pt-5 border-t border-gray-200">
      <div class="flex items-center justify-between gap-3 mb-3">
        <h4 class="font-semibold text-gray-800"><?php echo e(__('personnel.audit.title')); ?></h4>
        <span class="text-xs text-gray-500"><?php echo e(__('personnel.audit.recent_actions', ['count' => $personnelRecentActivity->count()])); ?></span>
      </div>
      <div class="space-y-3">
        <?php $__empty_1 = true; $__currentLoopData = $personnelRecentActivity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
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
        ?>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
          <div class="font-semibold text-gray-800"><?php echo e($eventLabel); ?> <?php echo e($subjectLabel); ?></div>
          <div class="text-sm text-gray-500 mt-1"><?php echo e($descriptionLabel); ?></div>
          <div class="text-xs text-gray-400 mt-2"><?php echo e($activity->causer?->name ?? __('personnel.audit.system')); ?> · <?php echo e(optional($activity->created_at)->diffForHumans()); ?></div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="rounded-xl border border-gray-200 p-4 text-sm text-gray-500 bg-gray-50"><?php echo e(__('personnel.audit.empty')); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
<?php elseif($personnelTab === 'employees'): ?>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h3 class="text-base font-bold text-gray-800"><?php echo e(__('personnel.ui.employees.import_title')); ?></h3>
      <p class="text-sm text-gray-500 mt-1"><?php echo e(__('personnel.ui.employees.import_description')); ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <a href="<?php echo e(route('admin.personnel.employees.template')); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
        <i class="fas fa-file-excel text-emerald-600"></i>
        <?php echo e(__('personnel.ui.employees.download_template')); ?>

      </a>
      <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('admin.personnel.employees.import')); ?>" class="flex items-center gap-2">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="personnel_tab" value="employees">
        <input type="file" name="employees_file" accept=".xlsx,.xls,.csv" required class="border border-gray-300 rounded-xl px-3 py-2 text-sm">
        <button type="submit" class="px-4 py-2 bg-[#2453d6] text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.employees.import_button')); ?></button>
      </form>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('personnel.ui.employees.sheet_title')); ?></h3>
        <p class="text-sm text-gray-500"><?php echo e(__('personnel.ui.employees.sheet_description')); ?></p>
      </div>
      <?php if($editingPersonnel): ?>
      <a href="<?php echo e(route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'employees'])); ?>" class="text-xs font-semibold text-[#2453d6]"><?php echo e(__('personnel.ui.employees.form_btn_new')); ?></a>
      <?php endif; ?>
    </div>

    <?php $empMeta = $editingPersonnel?->metadata ?? []; ?>
    <?php if($editingPersonnel): ?>
    <form id="create-linked-user-form" method="POST" action="<?php echo e(route('admin.personnel.employees.create-user', $editingPersonnel)); ?>" class="hidden">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="employees">
    </form>
    <?php endif; ?>
    <form id="employee-registration-form" method="POST" enctype="multipart/form-data" action="<?php echo e($editingPersonnel ? route('admin.personnel.employees.update', $editingPersonnel) : route('admin.personnel.employees.store')); ?>" class="space-y-6">
      <?php echo csrf_field(); ?>
      <?php if($editingPersonnel): ?>
        <?php echo method_field('PUT'); ?>
      <?php endif; ?>
      <input type="hidden" name="personnel_tab" value="employees">

      
      <div>
        <h5 class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3 pb-2 border-b border-gray-100">Informations Personnelles</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input type="text" name="last_name" required value="<?php echo e(old('last_name', $editingPersonnel->last_name ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" required value="<?php echo e(old('first_name', $editingPersonnel->first_name ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Date de naissance</label>
            <input type="date" name="birth_date" value="<?php echo e(old('birth_date', optional($editingPersonnel?->birth_date)->format('Y-m-d'))); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Lieu de naissance</label>
            <input type="text" name="birth_place" value="<?php echo e(old('birth_place', $editingPersonnel->birth_place ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">NNI</label>
            <input type="text" name="meta_nni" value="<?php echo e(old('meta_nni', $empMeta['nni'] ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400" placeholder="Numéro National d'Identité">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Téléphone</label>
            <input type="text" name="phone" value="<?php echo e(old('phone', $editingPersonnel->phone ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="<?php echo e(old('email', $editingPersonnel->email ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Adresse</label>
            <textarea name="address" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400"><?php echo e(old('address', $editingPersonnel->address ?? '')); ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Situation familiale</label>
            <select name="marital_status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <?php $__currentLoopData = ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf/Veuve', 'Union libre']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ms): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($ms); ?>" <?php echo e(old('marital_status', $editingPersonnel->marital_status ?? '') === $ms ? 'selected' : ''); ?>><?php echo e($ms); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre d'enfants</label>
            <input type="number" name="meta_children_count" min="0" value="<?php echo e(old('meta_children_count', $empMeta['children_count'] ?? 0)); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Personne à contacter en urgence</label>
            <input type="text" name="emergency_contact_name" value="<?php echo e(old('emergency_contact_name', $editingPersonnel->emergency_contact_name ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Téléphone urgence</label>
            <input type="text" name="emergency_contact_phone" value="<?php echo e(old('emergency_contact_phone', $editingPersonnel->emergency_contact_phone ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Photo de l'agent (carte virtuelle)</label>
            <input type="file" id="employee-photo-input" name="employee_photo" accept="image/*" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-orange-100 file:px-3 file:py-1.5 file:text-orange-700 hover:file:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-teal-400">
            <?php if(!empty($empMeta['photo_path'])): ?>
            <p class="mt-1 text-xs text-gray-500">Photo actuelle enregistrée: <?php echo e(basename($empMeta['photo_path'])); ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      
      <div>
        <h5 class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3 pb-2 border-b border-gray-100">Informations Professionnelles</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.employees.form_admin_type')); ?></label>
            <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="emitter" <?php echo e(old('administration_type', $editingPersonnel->administration_type ?? ($adminScope['type'] ?? 'emitter')) === 'emitter' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.employees.admin_emitter')); ?></option>
              <option value="recipient" <?php echo e(old('administration_type', $editingPersonnel->administration_type ?? ($adminScope['type'] ?? 'emitter')) === 'recipient' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.employees.admin_recipient')); ?></option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.employees.form_administration')); ?></label>
            <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value=""><?php echo e(__('personnel.ui.employees.form_select_admin')); ?></option>
              <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($e->id); ?>" <?php echo e(old('administration_id', $editingPersonnel->administration_id ?? ($adminScope['id'] ?? '')) === $e->id ? 'selected' : ''); ?>><?php echo e($e->name); ?> <?php echo e(__('personnel.ui.employees.admin_emitter_bracket')); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($r->id); ?>" <?php echo e(old('administration_id', $editingPersonnel->administration_id ?? ($adminScope['id'] ?? '')) === $r->id ? 'selected' : ''); ?>><?php echo e($r->name); ?> <?php echo e(__('personnel.ui.employees.admin_recipient_bracket')); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Matricule <span class="text-red-500">*</span></label>
            <input type="text" name="employee_number" value="<?php echo e(old('employee_number', $editingPersonnel->employee_number ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Direction Générale</label>
            <?php
              $dirGenEntities = $subEntities->filter(function($se) {
                  $typeName = mb_strtoupper(trim($se->directionType?->name ?? ''));
                  return in_array($typeName, ['DIRECTION GENERALE', 'DIRECTION GÉNÉRALE'], true);
              })->values();
            ?>
            <select name="meta_direction_generale" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              <?php $__currentLoopData = $dirGenEntities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($dge->name); ?>" <?php echo e(old('meta_direction_generale', $empMeta['direction_generale'] ?? '') === $dge->name ? 'selected' : ''); ?>><?php echo e($dge->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Direction Centrale</label>
            <?php
              $dirCentraleEntities = $subEntities->filter(function($se) {
                  $typeName = mb_strtoupper(trim($se->directionType?->name ?? ''));
                  return in_array($typeName, ['DIRECTION CENTRALE', 'DIRECTION CENTRALE'], true);
              })->values();
            ?>
            <select id="select_direction_centrale" name="meta_direction_centrale" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              <?php $__currentLoopData = $dirCentraleEntities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dce): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($dce->name); ?>" data-code="<?php echo e($dce->code); ?>" <?php echo e(old('meta_direction_centrale', $empMeta['direction_centrale'] ?? '') === $dce->name ? 'selected' : ''); ?>><?php echo e($dce->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Sous-Direction</label>
            <?php
              $allSubEntitiesJson = $subEntities->map(fn($se) => [
                  'name'        => $se->name,
                  'code'        => $se->code,
                  'parent_code' => $se->parent_code,
              ])->values()->toJson();
              $currentSousDir = old('meta_sous_direction', $empMeta['sous_direction'] ?? '');
            ?>
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
              var allEntities = <?php echo $allSubEntitiesJson; ?>;
              var currentVal  = <?php echo json_encode($currentSousDir, 15, 512) ?>;
              var selDC  = document.getElementById('select_direction_centrale');
              var selSD  = document.getElementById('select_sous_direction');
              var selSVC = document.getElementById('select_service');
              var currentSVC = <?php echo json_encode(old('meta_service', $empMeta['service'] ?? ''), 512) ?>;

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
              <?php $__currentLoopData = ['A','B','C','D']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($cat); ?>" <?php echo e(old('meta_categorie', $empMeta['categorie'] ?? '') === $cat ? 'selected' : ''); ?>><?php echo e($cat); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Grade</label>
            <select name="meta_grade" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              <?php $__currentLoopData = collect($personnelJobReferences)->where('reference_type', 'grade'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gradeRef): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($gradeRef->label); ?>" <?php echo e(old('meta_grade', $empMeta['grade'] ?? '') === $gradeRef->label ? 'selected' : ''); ?>><?php echo e($gradeRef->label); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Emploi</label>
            <select name="job_title" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Sélectionner</option>
              <?php $__currentLoopData = collect($personnelJobReferences)->where('reference_type', 'employment'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employmentRef): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($employmentRef->label); ?>" <?php echo e(old('job_title', $editingPersonnel->job_title ?? '') === $employmentRef->label ? 'selected' : ''); ?>><?php echo e($employmentRef->label); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Lieu de Travail</label>
            <input type="text" name="meta_lieu_travail" value="<?php echo e(old('meta_lieu_travail', $empMeta['lieu_travail'] ?? '')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Date d'embauche <span class="text-red-500">*</span></label>
            <input type="date" name="hire_date" value="<?php echo e(old('hire_date', optional($editingPersonnel?->hire_date)->format('Y-m-d'))); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.employees.form_employment_status')); ?></label>
            <select name="employment_status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <?php $__currentLoopData = ['active', 'probation', 'suspended', 'inactive']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $empStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($empStatus); ?>" <?php echo e(old('employment_status', $editingPersonnel->employment_status ?? 'active') === $empStatus ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.employees.status_' . $empStatus)); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.employees.form_superior')); ?></label>
            <select name="user_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value=""><?php echo e(__('personnel.ui.employees.form_no_superior')); ?></option>
              <?php $__currentLoopData = $allUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($u->id); ?>" <?php echo e(old('user_id', $editingPersonnel->user_id ?? '') === $u->id ? 'selected' : ''); ?>><?php echo e($u->name); ?> - <?php echo e($u->email); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Compte utilisateur</label>
            <select name="linked_user_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="">Aucun compte lié</option>
              <?php $__currentLoopData = $allUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($u->id); ?>" <?php echo e(old('linked_user_id', $editingPersonnel->linked_user_id ?? '') === $u->id ? 'selected' : ''); ?>><?php echo e($u->name); ?> - <?php echo e($u->email); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php if($editingPersonnel): ?>
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <?php if($editingPersonnel->linked_user_id): ?>
              <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1 text-xs font-semibold">Compte utilisateur déjà lié</span>
              <?php elseif(!empty($editingPersonnel->email)): ?>
              <button type="submit" form="create-linked-user-form" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-[#2453d6] text-white text-xs font-semibold hover:bg-[#1d43ad]">
                <i class="fas fa-user-plus"></i>
                Créer le compte utilisateur
              </button>
              <span class="text-xs text-gray-500">Le compte sera créé à partir du nom, de l'e-mail et de l'administration de l'employé.</span>
              <?php else: ?>
              <span class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2">Renseignez d'abord l'e-mail de l'employé pour créer son compte utilisateur.</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.employees.form_sub_entity')); ?></label>
            <select name="sub_entity_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value=""><?php echo e(__('personnel.ui.employees.form_no_entity')); ?></option>
              <?php $__currentLoopData = $subEntities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subEntity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($subEntity->id); ?>" <?php echo e(old('sub_entity_id', $editingPersonnel->sub_entity_id ?? '') === $subEntity->id ? 'selected' : ''); ?>><?php echo e($subEntity->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.employees.form_notes')); ?></label>
            <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400"><?php echo e(old('notes', $editingPersonnel->notes ?? '')); ?></textarea>
          </div>
        </div>
      </div>

      <div class="pt-2 flex flex-wrap gap-3">
        <button type="button" id="btn-open-virtual-card" class="px-6 py-2.5 bg-[#2453d6] hover:bg-[#1d43ad] text-white font-semibold rounded-xl text-sm inline-flex items-center gap-2">
          <i class="fas fa-id-badge"></i>
          Éditer la carte virtuelle de l'Agent
        </button>
        <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl text-sm"><?php echo e($editingPersonnel ? __('personnel.ui.employees.form_btn_update') : __('personnel.ui.employees.form_btn_create')); ?></button>
      </div>
    </form>

    <div id="virtual-agent-card-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm p-4 sm:p-6 lg:p-8">
      <div class="w-full max-w-[88vw] min-w-[320px] mx-auto bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h4 class="text-base font-bold text-gray-800">Carte virtuelle de l'Agent</h4>
          <button type="button" id="btn-close-virtual-card" class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm">Fermer</button>
        </div>

        <div class="p-5 bg-gray-50">
          <?php
            $_armoiriePath = public_path('images/armoirie_ci.png');
            $_armoirieSrc = file_exists($_armoiriePath)
              ? 'data:image/png;base64,' . base64_encode(file_get_contents($_armoiriePath))
              : asset('images/armoirie_ci.png');
          ?>
          <div id="virtual-agent-card-preview" style="background-color:#f0fff4;border:3px solid #f97316;border-radius:16px;padding:16px;width:100%;max-width:450px;min-height:320px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,0.08);position:relative;overflow:hidden;">
            <div aria-hidden="true" style="position:absolute;inset:0;background-image:url('<?php echo e($_armoirieSrc); ?>');background-repeat:no-repeat;background-position:center;background-size:72% auto;opacity:0.08;pointer-events:none;"></div>
            <div style="position:relative;z-index:1;">

            
            <div style="text-align:center;margin-bottom:10px;">
              <div style="font-size:15px;font-weight:900;letter-spacing:0.05em;color:#111;">REPUBLIQUE DE CÔTE D'IVOIRE</div>
              <div style="margin-top:8px;display:flex;justify-content:center;">
                <img src="<?php echo e($_armoirieSrc); ?>" alt="Armoirie de la Côte d'Ivoire" style="height:70px;width:auto;display:block;margin:0 auto;">
              </div>
              <div style="font-size:12px;font-weight:700;color:#c2410c;margin-top:6px;">Union - Discipline - Travail</div>
            </div>

            
            <div style="border:2px solid #f97316;border-radius:8px;padding:6px 12px;text-align:center;margin-bottom:10px;">
              <div id="vac-admin-name" style="font-size:15px;font-weight:800;color:#111;">ADMINISTRATION</div>
            </div>

            
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
          <?php if($editingPersonnel): ?>
          <form id="virtual-card-transmit-form" method="POST" action="<?php echo e(route('admin.personnel.employees.virtual-card.transmit', $editingPersonnel)); ?>" class="hidden">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="personnel_tab" value="employees">
            <input type="hidden" name="card_html" id="virtual-card-html-input" value="">
            <input type="hidden" name="signature_zone_page" value="1">
            <input type="hidden" name="signature_zone_x" value="70">
            <input type="hidden" name="signature_zone_y" value="84">
            <input type="hidden" name="signature_zone_width" value="26">
            <input type="hidden" name="signature_zone_height" value="10">
          </form>
          <button type="button" id="btn-transmit-virtual-card" class="px-4 py-2 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold">Transmettre pour signature</button>
          <?php endif; ?>
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
        setText('vac-admin-name', adminText && adminText !== '<?php echo e(__('personnel.ui.employees.form_select_admin')); ?>' ? adminText : 'ADMINISTRATION');
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
      var existingPhotoUrl = <?php echo json_encode(!empty($empMeta['photo_path']) ? asset('storage/' . $empMeta['photo_path']) : '', 15, 512) ?>;

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
      <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.employees.table_title')); ?></h3>
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
              <th class="py-3 pr-4"><?php echo e(__('personnel.ui.employees.table_col_agent')); ?></th>
              <th class="py-3 pr-4"><?php echo e(__('personnel.ui.employees.table_col_admin')); ?></th>
              <th class="py-3 pr-4">Emploi</th>
              <th class="py-3 pr-4"><?php echo e(__('personnel.ui.employees.table_col_status')); ?></th>
              <th class="py-3 pr-4"><?php echo e(__('personnel.ui.employees.table_col_docs')); ?></th>
              <th class="py-3"><?php echo e(__('personnel.ui.employees.table_col_action')); ?></th>
            </tr>
          </thead>
          <?php
            $emitterCodesMap = collect($emitters ?? [])->mapWithKeys(fn($e) => [$e->id => ($e->code ?? $e->name)]);
            $recipientCodesMap = collect($recipients ?? [])->mapWithKeys(fn($r) => [$r->id => ($r->code ?? $r->name)]);
          ?>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $personnelEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $adminCode = $employee->administration_type === 'recipient'
                ? ($recipientCodesMap[$employee->administration_id] ?? null)
                : ($emitterCodesMap[$employee->administration_id] ?? null);
            ?>
            <tr class="border-b border-gray-100">
              <td class="py-3 pr-4">
                <div class="font-semibold text-gray-800"><?php echo e($employee->full_name); ?></div>
                <div class="text-xs text-gray-500"><?php echo e($employee->employee_number ?: __('personnel.ui.employees.table_no_employee_number')); ?></div>
              </td>
              <td class="py-3 pr-4 text-gray-600"><?php echo e($adminCode ?: __('personnel.ui.employees.table_not_assigned')); ?></td>
              <td class="py-3 pr-4 text-gray-600"><?php echo e($employee->job_title ?: __('personnel.ui.employees.table_not_defined')); ?></td>
              <td class="py-3 pr-4">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700"><?php echo e(__('personnel.ui.statuses.' . $employee->employment_status)); ?></span>
              </td>
              <td class="py-3 pr-4 text-gray-600"><?php echo e($employee->documents->count()); ?></td>
              <td class="py-3">
                <a href="<?php echo e(route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'employees', 'selected_employee' => $employee->id])); ?>" class="text-sm font-semibold text-[#2453d6]"><?php echo e(__('personnel.ui.employees.table_btn_open')); ?></a>
              </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="6" class="py-8 text-center text-sm text-gray-400"><?php echo e(__('personnel.ui.employees.table_empty')); ?></td>
            </tr>
            <?php endif; ?>
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
      <?php if(method_exists($personnelEmployees, 'links')): ?>
      <div class="mt-4"><?php echo e($personnelEmployees->appends(['tab' => 'personnel', 'personnel_tab' => 'employees', 'selected_employee' => request('selected_employee')])->links()); ?></div>
      <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
      <div class="flex items-center justify-between gap-3 mb-4">
        <div>
          <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('personnel.ui.employees.documents_title')); ?></h3>
          <p class="text-sm text-gray-500"><?php echo e(__('personnel.ui.employees.documents_description')); ?></p>
        </div>
        <?php if(!$editingPersonnel): ?>
        <span class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-full px-3 py-1"><?php echo e(__('personnel.ui.employees.doc_select_hint')); ?></span>
        <?php endif; ?>
      </div>

      <?php if($editingPersonnel): ?>
      <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('admin.personnel.employees.documents.store', $editingPersonnel)); ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="personnel_tab" value="employees">
        <input type="text" name="label" placeholder="<?php echo e(__('personnel.ui.employees.doc_placeholder_label')); ?>" class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
        <select name="category" class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
          <option value="job_description"><?php echo e(__('personnel.ui.employees.doc_cat_job_desc')); ?></option>
          <option value="cv"><?php echo e(__('personnel.ui.employees.doc_cat_cv')); ?></option>
          <option value="certificate"><?php echo e(__('personnel.ui.employees.doc_cat_certificate')); ?></option>
          <option value="visa"><?php echo e(__('personnel.ui.employees.doc_cat_visa')); ?></option>
          <option value="badge"><?php echo e(__('personnel.ui.employees.doc_cat_badge')); ?></option>
          <option value="other"><?php echo e(__('personnel.ui.employees.doc_cat_other')); ?></option>
        </select>
        <input type="file" name="document" class="border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
        <button type="submit" class="px-5 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.employees.doc_btn_add')); ?></button>
      </form>

      <div class="space-y-3">
        <?php $__empty_1 = true; $__currentLoopData = $editingPersonnel->documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 rounded-xl border border-gray-200 p-4">
          <div>
            <div class="font-semibold text-gray-800"><?php echo e($doc->label); ?></div>
            <div class="text-xs text-gray-500"><?php echo e($doc->category); ?> | <?php echo e($doc->original_name); ?> | <?php echo e($doc->mime_type ?: 'type inconnu'); ?></div>
          </div>
          <a href="<?php echo e(route('admin.personnel.documents.download', $doc)); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            <i class="fas fa-download"></i>
            <?php echo e(__('personnel.ui.employees.doc_btn_download')); ?>

          </a>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.employees.doc_empty')); ?></div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.employees.doc_private_hint')); ?></div>
      <?php endif; ?>
    </div>
  </section>
<?php elseif($personnelTab === 'agent-space'): ?>
<?php
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
?>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('personnel.ui.agent_space.title')); ?></h3>
      <p class="text-sm text-gray-500 mt-1"><?php echo e(__('personnel.ui.agent_space.description')); ?></p>
    </div>
    <?php if($agentSpaceCanSearchAll): ?>
    <form method="GET" action="<?php echo e(route('admin.index')); ?>" class="flex items-center gap-2">
      <input type="hidden" name="tab" value="personnel">
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="<?php echo e($agentSpaceTab); ?>">
      <label class="text-sm font-semibold text-gray-700"><?php echo e(__('personnel.ui.agent_space.employee_number_label')); ?></label>
      <input
        type="text"
        name="selected_employee_number"
        value="<?php echo e($agentSpaceEmployeeNumberInput); ?>"
        placeholder="<?php echo e(__('personnel.ui.agent_space.employee_number_placeholder')); ?>"
        class="border border-gray-300 rounded-xl px-3 py-2 text-sm"
      >
      <button type="submit" class="px-3 py-2 rounded-xl bg-[#2453d6] text-white text-sm font-semibold"><?php echo e(__('personnel.ui.agent_space.search_btn')); ?></button>
    </form>
    <?php else: ?>
    <span class="inline-flex items-center rounded-full bg-gray-100 border border-gray-200 text-gray-700 text-xs font-semibold px-3 py-1">
      Espace personnel: vos propres informations et demandes
    </span>
    <?php endif; ?>
  </div>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap gap-2">
    <?php $__currentLoopData = $agentSpaceTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.index', array_merge(['tab' => 'personnel', 'personnel_tab' => 'agent-space', 'agent_space_tab' => $key], $agentSpaceCanSearchAll ? ($agentSpaceEmployee ? ['selected_employee' => $agentSpaceEmployee->id, 'selected_employee_number' => $agentSpaceEmployee->employee_number] : ($agentSpaceEmployeeNumberInput !== '' ? ['selected_employee_number' => $agentSpaceEmployeeNumberInput] : [])) : []))); ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition <?php echo e($agentSpaceTab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'); ?>">
      <i class="fa <?php echo e($icon); ?> text-sm"></i>
      <span><?php echo e($label); ?></span>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>

<?php if(!$agentSpaceEmployee): ?>
<div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 mb-5">
  <?php if($agentSpaceEmployeeNumberInput !== ''): ?>
    <?php echo e(__('personnel.ui.agent_space.employee_number_not_found')); ?>

  <?php else: ?>
    Votre compte utilisateur n'est associé à aucun dossier agent. Contactez les RH pour rattacher votre profil.
  <?php endif; ?>
</div>
<?php else: ?>

<?php if($agentSpaceTab === 'profile'): ?>
<?php
  $agentAdminLabel = $agentSpaceEmployee->administration_type === 'recipient'
    ? ($recipients->firstWhere('id', $agentSpaceEmployee->administration_id)?->name ?? '-')
    : ($emitters->firstWhere('id', $agentSpaceEmployee->administration_id)?->name ?? '-');
  $agentMeta = $agentSpaceEmployee->metadata ?? [];
  $agentMaritalLabels = [
    'Célibataire' => 'Célibataire', 'Marié(e)' => 'Marié(e)',
    'Divorcé(e)' => 'Divorcé(e)', 'Veuf/Veuve' => 'Veuf/Veuve', 'Union libre' => 'Union libre',
  ];
?>


<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-5">
  <h4 class="text-base font-bold text-gray-800 mb-5">Informations Personnelles</h4>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <?php
      $profileFields = [
        ['Nom', $agentSpaceEmployee->last_name ?: '-'],
        ['Prénom', $agentSpaceEmployee->first_name ?: '-'],
        ['Date de naissance', optional($agentSpaceEmployee->birth_date)->format('d/m/Y') ?: '-'],
        ['Lieu de naissance', $agentSpaceEmployee->birth_place ?: '-'],
        ['NNI', $agentMeta['nni'] ?? '-'],
        ['Téléphone', $agentSpaceEmployee->phone ?: '-'],
        ['Email', $agentSpaceEmployee->email ?: '-'],
      ];
    ?>
    <?php $__currentLoopData = $profileFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $val]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div>
      <label class="block text-sm text-gray-500 mb-1"><?php echo e($label); ?></label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($val); ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <div class="md:col-span-2">
      <label class="block text-sm text-gray-500 mb-1">Adresse</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 text-sm font-medium text-gray-800 min-h-[4rem]"><?php echo e($agentSpaceEmployee->address ?: '-'); ?></div>
    </div>

    <div>
      <label class="block text-sm text-gray-500 mb-1">Situation familiale</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentSpaceEmployee->marital_status ?: 'Célibataire'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Nombre d'enfants</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['children_count'] ?? '0'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Personne à contacter en urgence</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentSpaceEmployee->emergency_contact_name ?: '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Téléphone urgence</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentSpaceEmployee->emergency_contact_phone ?: '-'); ?></div>
    </div>
  </div>
</div>


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
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentSpaceEmployee->employee_number ?: 'N/A'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Direction Générale</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['direction_generale'] ?? '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Direction Centrale</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['direction_centrale'] ?? '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Sous-Direction</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['sous_direction'] ?? '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Service</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['service'] ?? '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Catégorie</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['categorie'] ?? '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Grade</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['grade'] ?? '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Emploi</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentSpaceEmployee->job_title ?: '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Lieu de Travail</label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e($agentMeta['lieu_travail'] ?? '-'); ?></div>
    </div>
    <div>
      <label class="block text-sm text-gray-500 mb-1">Date d'embauche <span class="text-red-400">*</span></label>
      <div class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-800"><?php echo e(optional($agentSpaceEmployee->hire_date)->format('d/m/Y') ?: '-'); ?></div>
    </div>
  </div>
</div>


<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
  <h4 class="text-base font-bold text-gray-800 mb-3"><?php echo e(__('personnel.ui.agent_space.mobility_history_title')); ?></h4>
  <div class="space-y-2">
    <?php $__empty_1 = true; $__currentLoopData = $agentSpaceCareerHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="rounded-xl border border-gray-200 px-3 py-2">
      <div class="text-sm font-semibold text-gray-800"><?php echo e($event->title); ?></div>
      <div class="text-xs text-gray-500"><?php echo e(optional($event->effective_date)->format('d/m/Y') ?: '-'); ?> · <?php echo e($event->event_type === 'mobility' ? __('personnel.ui.agent_space.event_mutation') : __('personnel.ui.agent_space.event_assignment')); ?> · <?php echo e(__('personnel.ui.statuses.' . $event->status)); ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500"><?php echo e(__('personnel.ui.agent_space.no_mobility')); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php elseif($agentSpaceTab === 'leave'): ?>
<?php
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
?>


<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
  <h3 class="text-xl font-bold text-gray-800">Mes Congés</h3>
  <div class="flex flex-wrap gap-2">
    <?php if($annualLeaveType): ?>
    <button type="button"
      onclick="document.getElementById('modal-annual-leave').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#2453d6] hover:bg-blue-700 text-white text-sm font-semibold shadow transition">
      <i class="fas fa-calendar-check text-xs"></i> Congé Annuel
    </button>
    <?php endif; ?>
    <button type="button"
      onclick="document.getElementById('modal-other-leave').classList.remove('hidden')"
      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow transition">
      <i class="fas fa-plus text-xs"></i> Autre congé
    </button>
  </div>
</div>


<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="rounded-2xl bg-[#2453d6] text-white p-5 flex items-center justify-between shadow">
    <div>
      <div class="text-4xl font-black"><?php echo e($annualRemaining); ?></div>
      <div class="text-sm font-semibold mt-1 opacity-90">Jours Restants</div>
      <div class="text-xs mt-0.5 opacity-70">Sur <?php echo e($annualQuota); ?> jours acquis</div>
    </div>
    <div class="opacity-40"><i class="fas fa-calendar-check text-4xl"></i></div>
  </div>
  <div class="rounded-2xl bg-emerald-500 text-white p-5 flex items-center justify-between shadow">
    <div>
      <div class="text-4xl font-black"><?php echo e((int) $annualApproved); ?></div>
      <div class="text-sm font-semibold mt-1 opacity-90">Jours Pris (Validés)</div>
      <div class="text-xs mt-0.5 opacity-70">Congés annuels confirmés</div>
    </div>
    <div class="opacity-40"><i class="fas fa-calendar-minus text-4xl"></i></div>
  </div>
  <div class="rounded-2xl bg-amber-500 text-white p-5 flex items-center justify-between shadow">
    <div>
      <div class="text-4xl font-black"><?php echo e($annualPending); ?></div>
      <div class="text-sm font-semibold mt-1 opacity-90">En Attente</div>
      <div class="text-xs mt-0.5 opacity-70">Demandes à valider</div>
    </div>
    <div class="opacity-40"><i class="fas fa-hourglass-half text-4xl"></i></div>
  </div>
</div>


<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <h4 class="text-base font-bold text-gray-800 mb-4">Historique des Demandes</h4>
  <div class="space-y-3">
    <?php $__empty_1 = true; $__currentLoopData = $myLeaveRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php
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
    ?>
    <div class="rounded-xl border border-gray-200 px-4 py-3">
      
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
        <div>
          <div class="text-sm font-bold text-gray-800"><?php echo e($lr->leaveType?->name ?? 'Type supprimé'); ?></div>
          <?php if($lr->start_date && $lr->end_date): ?>
          <div class="text-xs text-gray-500 mt-0.5">
            Du <?php echo e($lr->start_date->format('d/m/Y')); ?> au <?php echo e($lr->end_date->format('d/m/Y')); ?>

          </div>
          <?php endif; ?>
          <div class="text-xs text-gray-400 mt-0.5">
            Durée : <?php echo e($lrDays ? number_format((float) $lrDays, 0) . ' jour(s)' : '-'); ?>

            &nbsp;·&nbsp; Demandé le : <?php echo e($lr->created_at->format('d/m/Y')); ?>

          </div>
          <?php if($lr->reason): ?>
          <div class="text-xs text-gray-400 mt-0.5 italic">"<?php echo e(Str::limit($lr->reason, 80)); ?>"</div>
          <?php endif; ?>
        </div>
        <span class="inline-block flex-shrink-0 text-xs font-semibold px-3 py-1 rounded-full <?php echo e($badge); ?>"><?php echo e($label); ?></span>
      </div>

      
      <?php if(!empty($wfSteps)): ?>
      <div class="mt-3 pt-3 border-t border-gray-100">
        <div class="text-[11px] text-gray-400 font-medium mb-2 flex items-center gap-1">
          <i class="fas fa-project-diagram text-[9px]"></i> Circuit de validation
        </div>
        <div class="flex items-start overflow-x-auto pb-1 gap-0">
          <?php $__currentLoopData = $wfSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $si => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
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
          ?>

          
          <div class="flex flex-col items-center flex-shrink-0" style="min-width:68px">
            <div class="w-9 h-9 rounded-full border-2 <?php echo e($circleClass); ?> flex items-center justify-center text-xs shadow-sm">
              <i class="<?php echo e($iconClass); ?>"></i>
            </div>
            <div class="text-[10px] text-center mt-1 font-medium text-gray-600 leading-tight" style="max-width:64px">
              <?php echo e(Str::limit($profileLabel, 22)); ?>

            </div>
            <?php if($isDrh): ?>
            <div class="text-[9px] text-indigo-500 font-semibold -mt-0.5">DRH</div>
            <?php endif; ?>
            <?php if($hasMotif): ?>
            <button type="button"
              onclick="openMotifPopup(<?php echo e(json_encode($info['comment'])); ?>, <?php echo e(json_encode($profileLabel)); ?>, <?php echo e(json_encode($info['at'])); ?>)"
              class="mt-1 text-[10px] px-2 py-0.5 rounded-full bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 transition font-semibold whitespace-nowrap">
              <i class="fas fa-comment-alt text-[8px]"></i> Motif
            </button>
            <?php endif; ?>
          </div>

          
          <?php if(!$loop->last): ?>
          <div class="flex-shrink-0 self-start" style="margin-top:16px; width:28px">
            <div class="h-0.5 w-full <?php echo e($connColor); ?> rounded"></div>
          </div>
          <?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="rounded-xl bg-gray-50 border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
      <i class="fas fa-calendar-times text-gray-300 text-2xl mb-2 block"></i>
      Aucune demande de congé pour le moment.
    </div>
    <?php endif; ?>
  </div>
</div>


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
<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>


<?php
  $specialRequests = $myLeaveRequests->whereNotIn('leave_type_id', $annualLeaveType ? [$annualLeaveType->id] : []);
?>
<?php if($specialRequests->isNotEmpty()): ?>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
  <h4 class="text-base font-bold text-gray-800 mb-4">Permissions spéciales d'absence</h4>
  <div class="space-y-3">
    <?php $__currentLoopData = $specialRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $badge = $leaveBadge[$lr->status] ?? 'bg-gray-100 text-gray-500';
      $label = $leaveStatusLabels[$lr->status] ?? $lr->status;
    ?>
    <div class="rounded-xl border border-gray-200 px-4 py-3 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
      <div>
        <div class="text-sm font-bold text-gray-800"><?php echo e($lr->leaveType?->name ?? 'Type supprimé'); ?></div>
        <?php if($lr->start_date && $lr->end_date): ?>
        <div class="text-xs text-gray-500 mt-0.5">Du <?php echo e($lr->start_date->format('d/m/Y')); ?> au <?php echo e($lr->end_date->format('d/m/Y')); ?></div>
        <?php endif; ?>
        <div class="text-xs text-gray-400 mt-0.5">Demandé le : <?php echo e($lr->created_at->format('d/m/Y')); ?></div>
      </div>
      <span class="inline-block flex-shrink-0 text-xs font-semibold px-3 py-1 rounded-full <?php echo e($badge); ?>"><?php echo e($label); ?></span>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php endif; ?>


<?php if($annualLeaveType): ?>
<div id="modal-annual-leave"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
    <div class="p-6">
      
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

      
      <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 mb-5">
        <div class="text-sm font-bold text-blue-800 mb-3 flex items-center gap-2">
          <i class="fas fa-calendar-check text-blue-500"></i> Votre Solde Congés Annuels
        </div>
        <div class="grid grid-cols-3 gap-3 text-center">
          <div class="bg-white rounded-xl border border-blue-100 py-3">
            <div class="text-2xl font-black text-blue-600"><?php echo e($annualQuota); ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Quota annuel</div>
          </div>
          <div class="bg-white rounded-xl border border-amber-100 py-3">
            <div class="text-2xl font-black text-amber-500"><?php echo e((int) $annualApproved); ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Jours utilisés</div>
          </div>
          <div class="bg-white rounded-xl border border-green-100 py-3">
            <div class="text-2xl font-black text-green-600"><?php echo e($annualRemaining); ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Jours restants</div>
          </div>
        </div>
      </div>

      
      <form method="POST" action="<?php echo e(route('admin.personnel.leave-requests.store')); ?>" enctype="multipart/form-data" class="space-y-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="personnel_tab" value="agent-space">
        <input type="hidden" name="agent_space_tab" value="leave">
        <input type="hidden" name="employee_id" value="<?php echo e($agentSpaceEmployee->id); ?>">
        <input type="hidden" name="selected_employee" value="<?php echo e($agentSpaceEmployee->id); ?>">
        <input type="hidden" name="leave_type_id" value="<?php echo e($annualLeaveType->id); ?>">
        <input type="hidden" name="annual_segments_json" id="annual-segments-json" value="">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            <i class="fas fa-hashtag mr-1 text-blue-400"></i> Nombre de jours demandés <span class="text-red-500">*</span>
          </label>
          <input type="number" id="annual-days-count" name="requested_days"
            value="<?php echo e(max(1, (int) $annualRemaining)); ?>"
            min="1" max="<?php echo e($annualRemaining); ?>" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
          <p class="text-xs text-gray-400 mt-1">Vous pouvez fractionner vos 30 jours en plusieurs demandes. Solde disponible : <strong class="text-blue-700"><?php echo e($annualRemaining); ?> jour(s)</strong>.</p>
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
          <input type="hidden" name="start_date" id="annual-start" value="<?php echo e(old('start_date')); ?>">
          <input type="hidden" name="end_date" id="annual-end" value="<?php echo e(old('end_date')); ?>">
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
                <div class="text-lg font-black text-green-600" id="annual-preview-remaining"><?php echo e($annualRemaining); ?></div>
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
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"><?php echo e(old('reason')); ?></textarea>
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
<?php endif; ?>


<div id="modal-other-leave"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     onclick="if(event.target===this)this.classList.add('hidden')">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
    <div class="p-6">
      
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold text-gray-800">Nouvelle demande de congé</h2>
        <button type="button" onclick="document.getElementById('modal-other-leave').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 transition">
          <i class="fas fa-times text-lg"></i>
        </button>
      </div>

      <?php if($otherLeaveTypes->isEmpty()): ?>
      <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-4 text-sm text-amber-800">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Aucun autre type de congé configuré pour cette administration.
      </div>
      <?php else: ?>
      <form method="POST" action="<?php echo e(route('admin.personnel.leave-requests.store')); ?>" enctype="multipart/form-data" class="space-y-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="personnel_tab" value="agent-space">
        <input type="hidden" name="agent_space_tab" value="leave">
        <input type="hidden" name="employee_id" value="<?php echo e($agentSpaceEmployee->id); ?>">
        <input type="hidden" name="selected_employee" value="<?php echo e($agentSpaceEmployee->id); ?>">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Type de congé <span class="text-red-500">*</span>
          </label>
          <select name="leave_type_id" id="other-leave-type" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
            <option value="">Sélectionner un type</option>
            <?php $__currentLoopData = $otherLeaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($lt->id); ?>"
              data-requires-attachment="<?php echo e($lt->requires_attachment ? '1' : '0'); ?>"
              data-default-days="<?php echo e((int) $lt->default_days); ?>">
              <?php echo e($lt->name); ?>

            </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
              value="<?php echo e(old('start_date')); ?>"
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
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-400"><?php echo e(old('reason')); ?></textarea>
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
      <?php endif; ?>
    </div>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
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

  var annualQuota = <?php echo e($annualQuota); ?>;
  var annualAlreadyApproved = <?php echo e((int) $annualApproved); ?>;
  var annualMaxAllowed = <?php echo e($annualRemaining); ?>;
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
<?php $__env->stopPush(); ?>
<?php elseif($agentSpaceTab === 'training'): ?>
<?php
  $agentSpaceTrainings = $personnelTrainings
    ->where('administration_type', $agentSpaceEmployee->administration_type)
    ->where('administration_id', $agentSpaceEmployee->administration_id)
    ->values();
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3"><?php echo e(__('personnel.ui.agent_space.training_request_title')); ?></h4>
    <?php if($agentSpaceTrainings->isEmpty()): ?>
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-4 text-sm text-amber-800"><?php echo e(__('personnel.ui.agent_space.no_trainings')); ?></div>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.personnel.training-enrollments.store')); ?>" class="space-y-3">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="training">
      <input type="hidden" name="employee_id" value="<?php echo e($agentSpaceEmployee->id); ?>">
      <input type="hidden" name="status" value="planned">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.agent_space.training_label')); ?></label>
        <select name="training_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
          <option value=""><?php echo e(__('personnel.ui.agent_space.select')); ?></option>
          <?php $__currentLoopData = $agentSpaceTrainings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $training): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($training->id); ?>"><?php echo e($training->title); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <input type="date" name="planned_start_date" value="<?php echo e(old('planned_start_date')); ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
        <input type="date" name="planned_end_date" value="<?php echo e(old('planned_end_date')); ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
      </div>
      <textarea name="notes" rows="2" placeholder="<?php echo e(__('personnel.ui.agent_space.training_goal_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm"><?php echo e(old('notes')); ?></textarea>
      <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.agent_space.btn_submit_request')); ?></button>
    </form>
    <?php endif; ?>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3"><?php echo e(__('personnel.ui.agent_space.requested_training_title')); ?></h4>
    <div class="space-y-2">
      <?php $__empty_1 = true; $__currentLoopData = $agentSpaceTrainingEnrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
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
      ?>

      <div class="rounded-xl border border-gray-200 px-4 py-3">

        
        <div class="flex items-center justify-between gap-2">
          <div class="text-sm font-semibold text-gray-800 truncate"><?php echo e($enrollment->training?->title ?? __('personnel.ui.agent_space.deleted_training')); ?></div>
          <span class="flex-shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e($_enrBadge['bg']); ?>"><?php echo e($_enrBadge['label']); ?></span>
        </div>

        
        <div class="text-xs text-gray-400 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
          <?php if($enrollment->planned_start_date || $enrollment->planned_end_date): ?>
          <span><i class="far fa-calendar mr-1"></i><?php echo e(optional($enrollment->planned_start_date)->format('d/m/Y') ?: '-'); ?><?php if($enrollment->planned_end_date): ?> → <?php echo e(optional($enrollment->planned_end_date)->format('d/m/Y')); ?><?php endif; ?></span>
          <?php endif; ?>
          <span><i class="far fa-clock mr-1"></i>Soumise le <?php echo e(optional($enrollment->created_at)->format('d/m/Y H:i') ?: '-'); ?></span>
        </div>

        <?php if($enrollment->notes): ?>
        <div class="text-xs text-gray-500 mt-1.5 italic"><?php echo e(Str::limit($enrollment->notes, 100)); ?></div>
        <?php endif; ?>

        
        <?php if($_enrHasWorkflow): ?>
        <div class="mt-3 rounded-xl overflow-hidden border
          <?php echo e($_enrCircStatus === 'validated' ? 'border-emerald-200' : ($_enrCircStatus === 'rejected' ? 'border-red-200' : 'border-blue-200')); ?>">

          
          <button type="button"
            onclick="(function(el){ el.classList.toggle('hidden') })(document.getElementById('<?php echo e($_enrCircuitId); ?>'))"
            class="w-full flex items-center justify-between px-3 py-2.5 text-left gap-2
              <?php echo e($_enrCircStatus === 'validated' ? 'bg-emerald-50' : ($_enrCircStatus === 'rejected' ? 'bg-red-50' : 'bg-blue-50')); ?>">
            <div class="flex items-center gap-2">
              <?php if($_enrCircStatus === 'validated'): ?>
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white shadow-sm">
                  <i class="fas fa-check-double text-xs"></i>
                </span>
                <span class="text-xs font-bold text-emerald-700">Demande approuvée</span>
              <?php elseif($_enrCircStatus === 'rejected'): ?>
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white shadow-sm">
                  <i class="fas fa-times text-xs"></i>
                </span>
                <span class="text-xs font-bold text-red-700">Demande rejetée</span>
              <?php else: ?>
                <span class="relative flex items-center justify-center w-6 h-6">
                  <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                  <span class="relative flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white shadow-sm">
                    <i class="fas fa-hourglass-half text-xs"></i>
                  </span>
                </span>
                <span class="text-xs font-bold text-blue-700">Circuit de validation en cours</span>
              <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs font-semibold
                <?php echo e($_enrCircStatus === 'validated' ? 'text-emerald-600' : ($_enrCircStatus === 'rejected' ? 'text-red-600' : 'text-blue-600')); ?>">
                <?php echo e($_enrDone); ?>/<?php echo e($_enrTotalSteps); ?>

              </span>
              <div class="w-14 h-1.5 rounded-full bg-white/60 overflow-hidden border
                <?php echo e($_enrCircStatus === 'validated' ? 'border-emerald-200' : ($_enrCircStatus === 'rejected' ? 'border-red-200' : 'border-blue-200')); ?>">
                <div class="h-full rounded-full
                  <?php echo e($_enrCircStatus === 'rejected' ? 'bg-red-500' : ($_enrCircStatus === 'validated' ? 'bg-emerald-500' : 'bg-blue-500')); ?>"
                  style="width: <?php echo e($_enrPct); ?>%"></div>
              </div>
              <i class="fas fa-chevron-down text-xs opacity-40"></i>
            </div>
          </button>

          
          <div id="<?php echo e($_enrCircuitId); ?>" class="<?php echo e($_enrCircuitOpen ? '' : 'hidden'); ?> bg-white px-3 py-4">

            
            <div class="mb-4 h-2 w-full rounded-full bg-gray-100 overflow-hidden border border-gray-200">
              <div class="h-full rounded-full transition-all duration-700
                <?php echo e($_enrCircStatus === 'rejected' ? 'bg-red-400' : ($_enrCircStatus === 'validated' ? 'bg-emerald-500' : 'bg-blue-500')); ?>"
                style="width: <?php echo e($_enrPct); ?>%"></div>
            </div>

            
            <div class="relative">
              <?php if($_enrSteps->count() > 1): ?>
              <div class="absolute left-[19px] top-5 bottom-5 w-0.5 bg-gray-200"></div>
              <?php endif; ?>
              <div class="space-y-4">
                <?php $__currentLoopData = $_enrSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
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
                ?>
                <div class="relative flex items-start gap-3">

                  
                  <div class="relative z-10 flex-shrink-0">
                    <?php if($sState === 'done'): ?>
                      <div class="w-10 h-10 rounded-full bg-emerald-500 border-2 border-emerald-200 flex items-center justify-center shadow">
                        <i class="fas fa-check text-white text-xs"></i>
                      </div>
                    <?php elseif($sState === 'rejected'): ?>
                      <div class="w-10 h-10 rounded-full bg-red-500 border-2 border-red-200 flex items-center justify-center shadow">
                        <i class="fas fa-times text-white text-xs"></i>
                      </div>
                    <?php elseif($sState === 'current'): ?>
                      <div class="relative w-10 h-10">
                        <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                        <div class="relative w-10 h-10 rounded-full bg-blue-500 border-2 border-blue-200 flex items-center justify-center shadow">
                          <i class="fas fa-hourglass-half text-white text-xs"></i>
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-300 flex items-center justify-center">
                        <span class="text-xs font-bold text-gray-400"><?php echo e($idx + 1); ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  
                  <div class="flex-1 min-w-0 pb-1">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                      <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                          <span class="text-xs font-bold
                            <?php echo e($sState === 'done' ? 'text-emerald-700' : ($sState === 'rejected' ? 'text-red-700' : ($sState === 'current' ? 'text-blue-700' : 'text-gray-400'))); ?>">
                            <?php echo e($sKindLbl); ?>

                          </span>
                          <?php if($sState === 'done'): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                              <i class="fas fa-check text-xs"></i> Validé
                            </span>
                          <?php elseif($sState === 'rejected'): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                              <i class="fas fa-times text-xs"></i> Rejeté
                            </span>
                          <?php elseif($sState === 'current'): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 animate-pulse">
                              <i class="fas fa-clock text-xs"></i> En attente
                            </span>
                          <?php else: ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs text-gray-400 bg-gray-100">À venir</span>
                          <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-1.5 mt-1">
                          <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                            <?php echo e($sState === 'done' ? 'bg-emerald-100 text-emerald-700' : ($sState === 'rejected' ? 'bg-red-100 text-red-700' : ($sState === 'current' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400'))); ?>">
                            <?php echo e($sInits ?: '?'); ?>

                          </div>
                          <span class="text-xs font-medium <?php echo e($sState === 'todo' ? 'text-gray-400' : 'text-gray-700'); ?> truncate"><?php echo e($sName); ?></span>
                        </div>
                      </div>
                      <?php if($sAt): ?>
                      <div class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                        <i class="far fa-clock mr-0.5"></i><?php echo e(\Carbon\Carbon::parse($sAt)->format('d/m/Y à H:i')); ?>

                      </div>
                      <?php endif; ?>
                    </div>
                    <?php if($sCmt): ?>
                    <div class="mt-2 rounded-lg px-3 py-2 text-xs flex items-start gap-2
                      <?php echo e($sState === 'rejected' ? 'bg-red-50 border border-red-100 text-red-700' : 'bg-gray-50 border border-gray-100 text-gray-600'); ?>">
                      <i class="fas fa-comment-alt mt-0.5 opacity-60 flex-shrink-0"></i>
                      <span><?php echo e($sCmt); ?></span>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>
            </div>

            
            <?php if($_enrCircStatus === 'pending' && $_enrNextStep): ?>
            <div class="mt-4 rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-user-check text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-blue-900">Prochain valideur</div>
                <div class="text-xs text-blue-700 truncate"><?php echo e($_enrNextName); ?> · <?php echo e($_enrNextKind); ?></div>
              </div>
            </div>
            <?php endif; ?>

            
            <?php if($_enrCircStatus === 'validated'): ?>
            <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-emerald-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-check-double text-white text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-bold text-emerald-800">Formation approuvée par tous les responsables</div>
                <div class="text-xs text-emerald-600">Votre demande a été validée. La formation est planifiée.</div>
              </div>
            </div>
            <?php endif; ?>

            
            <?php if($_enrCircStatus === 'rejected' && data_get($enrollment->metadata, 'rejection_reason')): ?>
            <div class="mt-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-red-500 flex items-center justify-center shadow-sm mt-0.5">
                <i class="fas fa-ban text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-red-800">Motif de rejet</div>
                <div class="text-xs text-red-700"><?php echo e(data_get($enrollment->metadata, 'rejection_reason')); ?></div>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endif; ?>

        
        <?php if($_enrStatus === 'rejected' && !$_enrHasWorkflow && data_get($enrollment->metadata, 'rejection_reason')): ?>
        <div class="mt-2 rounded-lg bg-red-50 border border-red-100 px-3 py-2 text-xs text-red-700">
          <i class="fas fa-ban mr-1 opacity-60"></i><?php echo e(data_get($enrollment->metadata, 'rejection_reason')); ?>

        </div>
        <?php endif; ?>

      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500"><?php echo e(__('personnel.ui.agent_space.no_training_requests')); ?></div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php elseif($agentSpaceTab === 'mutation'): ?>
<?php
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
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
      <h4 class="text-base font-bold text-gray-800">Nouvelle demande de mutation</h4>
      <span class="text-xs rounded-full bg-blue-50 text-blue-700 px-3 py-1 border border-blue-100">Circuit hiérarchique automatique</span>
    </div>

    <?php if($agentSpaceMutationTargets->isEmpty()): ?>
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-4 text-sm text-amber-800">
      Aucune entité de destination disponible pour votre administration.
    </div>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.personnel.mutation-requests.store')); ?>" enctype="multipart/form-data" class="space-y-3">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="mutation">
      <input type="hidden" name="employee_id" value="<?php echo e($agentSpaceEmployee->id); ?>">

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Entité de destination</label>
        <select name="target_sub_entity_code" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm" required>
          <option value="">Sélectionner une entité</option>
          <?php $__currentLoopData = $agentSpaceMutationTargets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $target): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($target->code); ?>" <?php echo e(old('target_sub_entity_code') === $target->code ? 'selected' : ''); ?>>
            <?php echo e($target->name); ?> (<?php echo e($target->code); ?>)
          </option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Objet de la demande</label>
        <input type="text" name="summary" value="<?php echo e(old('summary')); ?>" placeholder="Ex: Mutation pour rapprochement de service"
          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Justification</label>
        <textarea name="notes" rows="4" placeholder="Précisez les raisons de votre demande..."
          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm"><?php echo e(old('notes')); ?></textarea>
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

          const form = document.querySelector('form[action="<?php echo e(route('admin.personnel.mutation-requests.store')); ?>"]');
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
      <?php $__empty_1 = true; $__currentLoopData = $agentSpaceMutationRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
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
      ?>
      <div class="rounded-xl border border-gray-200 px-4 py-3">
        <div class="flex items-center justify-between gap-3">
          <div class="text-sm font-semibold text-gray-800"><?php echo e($request->title); ?></div>
          <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e($badgeClass); ?>"><?php echo e($label); ?></span>
        </div>
        <div class="text-xs text-gray-500 mt-1"><?php echo e($sourceName); ?> <i class="fas fa-arrow-right mx-1"></i> <?php echo e($targetName); ?></div>
        <div class="text-xs text-gray-400 mt-1">Soumise le <?php echo e(optional($request->created_at)->format('d/m/Y H:i') ?: '-'); ?></div>
        <?php if(!empty($request->summary)): ?>
        <div class="text-xs text-gray-500 mt-2"><?php echo e($request->summary); ?></div>
        <?php endif; ?>

        <?php if(!empty($attachments)): ?>
        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
          <div class="text-xs font-semibold text-slate-700">Pièce justificative</div>
          <div class="mt-2 flex flex-wrap gap-2">
            <?php $__currentLoopData = $attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachmentIndex => $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('admin.personnel.mutation-requests.attachment.download', [$request, $attachmentIndex])); ?>"
              class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-100">
              <i class="fas fa-file-upload"></i> <?php echo e($attachment['original_name'] ?? basename($attachment['path'] ?? '')); ?>

            </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if($steps->isNotEmpty()): ?>
        <?php
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
        ?>

        
        <div class="mt-3 rounded-xl overflow-hidden border
          <?php echo e($status === 'validated' ? 'border-emerald-200' : ($status === 'rejected' ? 'border-red-200' : 'border-blue-200')); ?>">

          
          <button type="button"
            onclick="(function(el){ el.classList.toggle('hidden') })(document.getElementById('<?php echo e($circuitId); ?>'))"
            class="w-full flex items-center justify-between px-3 py-2.5 text-left gap-2
              <?php echo e($status === 'validated' ? 'bg-emerald-50' : ($status === 'rejected' ? 'bg-red-50' : 'bg-blue-50')); ?>">

            <div class="flex items-center gap-2">
              <?php if($status === 'validated'): ?>
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white shadow-sm">
                  <i class="fas fa-check-double text-xs"></i>
                </span>
                <span class="text-xs font-bold text-emerald-700">Circuit terminé — Mutation approuvée</span>
              <?php elseif($status === 'rejected'): ?>
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white shadow-sm">
                  <i class="fas fa-times text-xs"></i>
                </span>
                <span class="text-xs font-bold text-red-700">Demande rejetée</span>
              <?php else: ?>
                <span class="relative flex items-center justify-center w-6 h-6">
                  <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                  <span class="relative flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white shadow-sm">
                    <i class="fas fa-hourglass-half text-xs"></i>
                  </span>
                </span>
                <span class="text-xs font-bold text-blue-700">Circuit de validation en cours</span>
              <?php endif; ?>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs font-semibold
                <?php echo e($status === 'validated' ? 'text-emerald-600' : ($status === 'rejected' ? 'text-red-600' : 'text-blue-600')); ?>">
                <?php echo e($completedSteps); ?>/<?php echo e($totalSteps); ?>

              </span>
              <div class="w-14 h-1.5 rounded-full bg-white/60 overflow-hidden border
                <?php echo e($status === 'validated' ? 'border-emerald-200' : ($status === 'rejected' ? 'border-red-200' : 'border-blue-200')); ?>">
                <div class="h-full rounded-full
                  <?php echo e($status === 'rejected' ? 'bg-red-500' : ($status === 'validated' ? 'bg-emerald-500' : 'bg-blue-500')); ?>"
                  style="width: <?php echo e($progressPct); ?>%"></div>
              </div>
              <i class="fas fa-chevron-down text-xs opacity-40"></i>
            </div>
          </button>

          
          <div id="<?php echo e($circuitId); ?>" class="<?php echo e($circuitOpen ? '' : 'hidden'); ?> bg-white px-3 py-4">

            
            <div class="mb-4 h-2 w-full rounded-full bg-gray-100 overflow-hidden border border-gray-200">
              <div class="h-full rounded-full transition-all duration-700
                <?php echo e($status === 'rejected' ? 'bg-red-400' : ($status === 'validated' ? 'bg-emerald-500' : 'bg-blue-500')); ?>"
                style="width: <?php echo e($progressPct); ?>%"></div>
            </div>

            
            <div class="relative">
              
              <?php if($steps->count() > 1): ?>
              <div class="absolute left-[19px] top-5 bottom-5 w-0.5 bg-gray-200"></div>
              <?php endif; ?>

              <div class="space-y-4">
                <?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
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
                ?>

                <div class="relative flex items-start gap-3">
                  
                  <div class="relative z-10 flex-shrink-0">
                    <?php if($sState === 'done'): ?>
                      <div class="w-10 h-10 rounded-full bg-emerald-500 border-2 border-emerald-200 flex items-center justify-center shadow">
                        <i class="fas fa-check text-white text-xs"></i>
                      </div>
                    <?php elseif($sState === 'rejected'): ?>
                      <div class="w-10 h-10 rounded-full bg-red-500 border-2 border-red-200 flex items-center justify-center shadow">
                        <i class="fas fa-times text-white text-xs"></i>
                      </div>
                    <?php elseif($sState === 'current'): ?>
                      <div class="relative w-10 h-10">
                        <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-40"></span>
                        <div class="relative w-10 h-10 rounded-full bg-blue-500 border-2 border-blue-200 flex items-center justify-center shadow">
                          <i class="fas fa-hourglass-half text-white text-xs"></i>
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-300 flex items-center justify-center">
                        <span class="text-xs font-bold text-gray-400"><?php echo e($idx + 1); ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  
                  <div class="flex-1 min-w-0 pb-1">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                      <div class="min-w-0">
                        
                        <div class="flex items-center gap-1.5 flex-wrap">
                          <span class="text-xs font-bold
                            <?php echo e($sState === 'done' ? 'text-emerald-700' : ($sState === 'rejected' ? 'text-red-700' : ($sState === 'current' ? 'text-blue-700' : 'text-gray-400'))); ?>">
                            <?php echo e($sKindLabel); ?>

                          </span>
                          <?php if($sState === 'done'): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                              <i class="fas fa-check text-xs"></i> Validé
                            </span>
                          <?php elseif($sState === 'rejected'): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                              <i class="fas fa-times text-xs"></i> Rejeté
                            </span>
                          <?php elseif($sState === 'current'): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 animate-pulse">
                              <i class="fas fa-clock text-xs"></i> En attente
                            </span>
                          <?php else: ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs text-gray-400 bg-gray-100">
                              À venir
                            </span>
                          <?php endif; ?>
                        </div>

                        
                        <div class="flex items-center gap-1.5 mt-1">
                          <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                            <?php echo e($sState === 'done' ? 'bg-emerald-100 text-emerald-700' : ($sState === 'rejected' ? 'bg-red-100 text-red-700' : ($sState === 'current' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400'))); ?>">
                            <?php echo e($sInitials ?: '?'); ?>

                          </div>
                          <span class="text-xs font-medium <?php echo e($sState === 'todo' ? 'text-gray-400' : 'text-gray-700'); ?> truncate">
                            <?php echo e($sApproverName); ?>

                          </span>
                        </div>
                      </div>

                      
                      <?php if($sActedAt): ?>
                      <div class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                        <i class="far fa-clock mr-0.5"></i>
                        <?php echo e(\Carbon\Carbon::parse($sActedAt)->format('d/m/Y à H:i')); ?>

                      </div>
                      <?php endif; ?>
                    </div>

                    
                    <?php if($sComment): ?>
                    <div class="mt-2 rounded-lg px-3 py-2 text-xs flex items-start gap-2
                      <?php echo e($sState === 'rejected' ? 'bg-red-50 border border-red-100 text-red-700' : 'bg-gray-50 border border-gray-100 text-gray-600'); ?>">
                      <i class="fas fa-comment-alt mt-0.5 opacity-60 flex-shrink-0"></i>
                      <span><?php echo e($sComment); ?></span>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>
            </div>

            
            <?php if($status === 'pending' && $nextStep): ?>
            <div class="mt-4 rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-user-check text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-blue-900">Prochain valideur</div>
                <div class="text-xs text-blue-700 truncate">
                  <?php echo e($nextApproverName); ?>

                  <span class="mx-1 opacity-40">·</span>
                  <?php echo e($nextKindLabel); ?>

                </div>
              </div>
            </div>
            <?php endif; ?>

            
            <?php if($status === 'validated'): ?>
            <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-center gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-emerald-500 flex items-center justify-center shadow-sm">
                <i class="fas fa-check-double text-white text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-bold text-emerald-800">Mutation approuvée par tous les responsables</div>
                <div class="text-xs text-emerald-600">Décision finale le <?php echo e(optional($request->updated_at)->format('d/m/Y')); ?></div>
              </div>
            </div>
            <?php endif; ?>

            
            <?php if($status === 'rejected' && data_get($request->metadata, 'rejection_reason')): ?>
            <div class="mt-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
              <div class="flex-shrink-0 w-9 h-9 rounded-full bg-red-500 flex items-center justify-center shadow-sm mt-0.5">
                <i class="fas fa-ban text-white text-sm"></i>
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-red-800">Motif de rejet</div>
                <div class="text-xs text-red-700"><?php echo e(data_get($request->metadata, 'rejection_reason')); ?></div>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endif; ?>

      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">Aucune demande de mutation pour le moment.</div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php endif; ?>
<?php else: ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-base font-bold text-gray-800 mb-3">CV Agent</h4>
    <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('admin.personnel.employees.documents.store', $agentSpaceEmployee)); ?>" class="space-y-3">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="agent-space">
      <input type="hidden" name="agent_space_tab" value="documents">
      <input type="hidden" name="category" value="cv">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.agent_space.label_label')); ?></label>
        <input type="text" name="label" value="<?php echo e(old('label', 'CV Agent')); ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.agent_space.document_label')); ?></label>
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
        <?php $__empty_1 = true; $__currentLoopData = $agentSpaceEmployee->documents->where('category', 'virtual_card_signed')->sortByDesc('created_at')->take(8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <a href="<?php echo e(route('admin.personnel.documents.download', $doc)); ?>" class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
          <span><?php echo e($doc->label); ?></span>
          <i class="fas fa-download text-gray-400"></i>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">Aucune carte signée disponible.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="pt-3 border-t border-gray-100">
      <h5 class="text-sm font-semibold text-gray-700 mb-2">CV Agent</h5>
      <div class="space-y-2">
      <?php $__empty_1 = true; $__currentLoopData = $agentSpaceEmployee->documents->where('category', 'cv')->sortByDesc('created_at')->take(8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <a href="<?php echo e(route('admin.personnel.documents.download', $doc)); ?>" class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
        <span><?php echo e($doc->label); ?></span>
        <i class="fas fa-download text-gray-400"></i>
      </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 text-sm text-gray-500">Aucun CV disponible.</div>
      <?php endif; ?>
      </div>
    </div>
  </section>
</div>
<?php endif; ?>
<?php endif; ?>

<?php elseif($personnelTab === 'leave'): ?>
<?php
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
?>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap items-center gap-2">
    <?php if(empty($leaveSubtabs)): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      Aucun sous-onglet Congés & permissions n'est autorisé pour ce rôle.
    </div>
    <?php endif; ?>
    <?php $__currentLoopData = $leaveSubtabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'leave', 'leave_subtab' => $key])); ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition <?php echo e($leaveSubtab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'); ?>">
      <i class="fa <?php echo e($icon); ?> text-sm"></i>
      <span><?php echo e($label); ?></span>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if($leaveGlobalVisibility): ?>
    <span class="ml-auto inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700 text-xs font-semibold px-3 py-1">Visibilité globale AGENT RH / SUPER ADMIN</span>
    <?php endif; ?>
  </div>
</div>

<?php if(empty($leaveSubtabs)): ?>
<?php elseif($leaveSubtab === 'parameters'): ?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800">Catalogue des congés & permissions</h3>
        <p class="text-sm text-gray-500">Configuration des types et ZIP des pièces justificatives.</p>
      </div>
      <span class="text-xs rounded-full bg-sky-50 text-sky-700 border border-sky-100 px-3 py-1"><?php echo e($personnelLeaveTypes->count()); ?> type(s)</span>
    </div>

    <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('admin.personnel.leave-types.store')); ?>" class="space-y-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="leave">
      <input type="hidden" name="leave_subtab" value="parameters">

      <?php if($adminScope): ?>
      <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type']); ?>">
      <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
      <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800 font-medium"><?php echo e(__('personnel.ui.leave.admin_locked')); ?></div>
      <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.leave.admin_type_label')); ?></label>
          <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="emitter" <?php echo e(old('administration_type', 'emitter') === 'emitter' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.leave.admin_emitter')); ?></option>
            <option value="recipient" <?php echo e(old('administration_type') === 'recipient' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.leave.admin_recipient')); ?></option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.leave.admin_type_label')); ?></label>
          <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value=""><?php echo e(__('personnel.ui.leave.admin_select')); ?></option>
            <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($e->id); ?>" <?php echo e(old('administration_id') === $e->id ? 'selected' : ''); ?>><?php echo e($e->name); ?> <?php echo e(__('personnel.ui.leave.admin_emitter_bracket')); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($r->id); ?>" <?php echo e(old('administration_id') === $r->id ? 'selected' : ''); ?>><?php echo e($r->name); ?> <?php echo e(__('personnel.ui.leave.admin_recipient_bracket')); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.leave.form_name')); ?></label>
          <input type="text" name="name" value="<?php echo e(old('name')); ?>" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.leave.form_code')); ?></label>
          <input type="text" name="code" value="<?php echo e(old('code')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.leave.form_unit')); ?></label>
          <select name="unit" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="day" <?php echo e(old('unit', 'day') === 'day' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.leave.form_unit_day')); ?></option>
            <option value="hour" <?php echo e(old('unit') === 'hour' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.leave.form_unit_hour')); ?></option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.leave.form_default_days')); ?></label>
          <input type="number" step="0.5" min="0" name="default_days" value="<?php echo e(old('default_days')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">ZIP justificatifs</label>
        <input type="file" name="justification_zip" accept=".zip" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>

      <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="requires_attachment" value="1" <?php echo e(old('requires_attachment') ? 'checked' : ''); ?>> <?php echo e(__('personnel.ui.leave.form_requires_attachment')); ?></label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_paid" value="1" <?php echo e(old('is_paid') ? 'checked' : ''); ?>> <?php echo e(__('personnel.ui.leave.form_is_paid')); ?></label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" <?php echo e(old('is_active') ? 'checked' : ''); ?>> <?php echo e(__('personnel.ui.leave.form_is_active')); ?></label>
      </div>

      <button type="submit" class="px-6 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.leave.btn_save_type')); ?></button>
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
          <?php $__empty_1 = true; $__currentLoopData = $personnelLeaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leaveType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="border-b border-gray-100">
            <td class="py-2 pr-4 text-gray-700 font-semibold"><?php echo e($leaveType->name); ?></td>
            <td class="py-2 pr-4 text-gray-500"><?php echo e($leaveType->code); ?></td>
            <td class="py-2">
              <?php if($leaveType->justification_zip_path): ?>
              <a href="<?php echo e(route('admin.personnel.leave-types.justification-zip.download', $leaveType)); ?>" class="text-[#2453d6] text-xs font-semibold">Télécharger ZIP</a>
              <?php else: ?>
              <span class="text-xs text-gray-400"></span>
              <?php endif; ?>
            </td>
            <td class="py-2 text-right whitespace-nowrap">
              <button type="button"
                      onclick="document.getElementById('leave-type-edit-<?php echo e($leaveType->id); ?>').classList.toggle('hidden')"
                      class="inline-flex items-center px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">
                Modifier
              </button>
              <form method="POST" action="<?php echo e(route('admin.personnel.leave-types.destroy', $leaveType)); ?>" class="inline-block ml-2" onsubmit="return confirm('Supprimer ce type de congé ?');">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <input type="hidden" name="personnel_tab" value="leave">
                <input type="hidden" name="leave_subtab" value="parameters">
                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-red-200 text-red-700 text-xs font-semibold hover:bg-red-50 transition">
                  Supprimer
                </button>
              </form>
            </td>
          </tr>
          <tr id="leave-type-edit-<?php echo e($leaveType->id); ?>" class="hidden border-b border-gray-100 bg-gray-50">
            <td colspan="4" class="py-3 pr-2">
              <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('admin.personnel.leave-types.update', $leaveType)); ?>" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <input type="hidden" name="personnel_tab" value="leave">
                <input type="hidden" name="leave_subtab" value="parameters">

                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Nom</label>
                  <input type="text" name="name" value="<?php echo e($leaveType->name); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Code</label>
                  <input type="text" name="code" value="<?php echo e($leaveType->code); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Unité</label>
                  <select name="unit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="day" <?php echo e($leaveType->unit === 'day' ? 'selected' : ''); ?>>Jour</option>
                    <option value="hour" <?php echo e($leaveType->unit === 'hour' ? 'selected' : ''); ?>>Heure</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Jours par défaut</label>
                  <input type="number" step="0.5" min="0" name="default_days" value="<?php echo e($leaveType->default_days); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Report max</label>
                  <input type="number" step="0.5" min="0" name="carry_over_days" value="<?php echo e($leaveType->carry_over_days); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">ZIP justificatifs</label>
                  <input type="file" name="justification_zip" accept=".zip" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                </div>
                <div class="md:col-span-3">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
                  <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><?php echo e($leaveType->description); ?></textarea>
                </div>
                <div class="md:col-span-3 flex flex-wrap items-center gap-4">
                  <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="requires_attachment" value="1" <?php echo e($leaveType->requires_attachment ? 'checked' : ''); ?>> Pièce obligatoire</label>
                  <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_paid" value="1" <?php echo e($leaveType->is_paid ? 'checked' : ''); ?>> Congé payé</label>
                  <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" <?php echo e($leaveType->is_active ? 'checked' : ''); ?>> Actif</label>
                  <button type="submit" class="ml-auto inline-flex items-center px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-blue-700 transition">Enregistrer</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="4" class="py-3 text-center text-gray-400">Aucun type configuré.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="mb-4">
      <h3 class="text-lg font-bold text-gray-800">Référentiel Grades / Emplois / Fonctions</h3>
      <p class="text-sm text-gray-500">Formulaire de création des grades, emplois et fonctions.</p>
    </div>

    <form method="POST" action="<?php echo e(route('admin.personnel.job-references.store')); ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="leave">
      <input type="hidden" name="leave_subtab" value="parameters">
      <?php if($adminScope): ?>
      <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type']); ?>">
      <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
      <?php else: ?>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.leave.admin_type_label')); ?></label>
        <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
          <option value="emitter"><?php echo e(__('personnel.ui.leave.admin_emitter')); ?></option>
          <option value="recipient"><?php echo e(__('personnel.ui.leave.admin_recipient')); ?></option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Administration</label>
        <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm" required>
          <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($e->id); ?>"><?php echo e($e->name); ?> <?php echo e(__('personnel.ui.leave.admin_emitter_bracket')); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($r->id); ?>"><?php echo e($r->name); ?> <?php echo e(__('personnel.ui.leave.admin_recipient_bracket')); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <?php endif; ?>
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
      <?php $__currentLoopData = ['grade' => 'Grades', 'employment' => 'Emplois', 'function' => 'Fonctions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refType => $refLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="rounded-xl border border-gray-200 p-3">
        <h4 class="text-sm font-bold text-gray-800 mb-2"><?php echo e($refLabel); ?></h4>
        <ul class="space-y-1 text-sm text-gray-600">
          <?php $__empty_1 = true; $__currentLoopData = ($jobReferencesByType[$refType] ?? collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ref): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <li><?php echo e($ref->label); ?></li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <li class="text-gray-400">Aucune valeur</li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </section>
</div>
<?php elseif($leaveSubtab === 'recent'): ?>
<?php
  $recentValidatedOrRejected = collect($personnelLeaveRequests)->filter(fn($r) => in_array($r->status, ['approved', 'rejected'], true))->values();
?>
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
        <?php $__empty_1 = true; $__currentLoopData = $recentValidatedOrRejected; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leaveRequest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="border-b border-gray-100">
          <td class="py-3 pr-4 text-gray-600"><?php echo e(optional($leaveRequest->updated_at)->format('d/m/Y H:i')); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($leaveRequest->employee?->employee_number ?: '-'); ?></td>
          <td class="py-3 pr-4 text-gray-800 font-semibold"><?php echo e($leaveRequest->employee?->full_name ?? __('personnel.ui.leave.table_deleted_employee')); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($leaveRequest->leaveType?->name ?? __('personnel.ui.leave.table_deleted_type')); ?></td>
          <td class="py-3 pr-4">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?php echo e($leaveRequest->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'); ?>"><?php echo e(__('personnel.ui.statuses.' . $leaveRequest->status)); ?></span>
          </td>
          <td class="py-3 text-gray-600"><?php echo e($leaveRequest->approvedBy?->name ?? 'Non renseigné'); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td colspan="6" class="py-8 text-center text-sm text-gray-400"><?php echo e(__('personnel.ui.leave.table_empty')); ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<?php
  $validationRows = collect($personnelLeaveRequests)->where('status', 'pending')->values();
  $validationApproveBtnClass = 'px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition';
  $validationRejectBtnClass = 'px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition';
?>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Validation hiérarchique des demandes</h3>
      <p class="text-sm text-gray-500">Le supérieur hiérarchique valide ou rejette les demandes des agents.</p>
    </div>
    <span class="text-xs rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-3 py-1"><?php echo e($validationRows->count()); ?> en attente</span>
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
        <?php $__empty_1 = true; $__currentLoopData = $validationRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leaveRequest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
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
        ?>
        <tr class="border-b border-gray-100 align-top">
          <td class="py-3 pr-4 text-gray-600"><?php echo e($leaveRequest->employee?->employee_number ?: __('personnel.ui.leave.table_no_employee_number')); ?></td>
          <td class="py-3 pr-4 text-gray-800 font-semibold"><?php echo e($leaveRequest->employee?->full_name ?? __('personnel.ui.leave.table_deleted_employee')); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($agentGrade); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($agentEmployment); ?> / <?php echo e($agentFunction); ?></td>
          <td class="py-3 pr-4">
            <?php if($leaveRequest->attachment_path): ?>
            <a href="<?php echo e(route('admin.personnel.leave-requests.attachment.download', $leaveRequest)); ?>" class="text-[#2453d6] text-xs font-semibold">ZIP / justificatif</a>
            <?php else: ?>
            <span class="text-xs text-gray-400">Aucun fichier</span>
            <?php endif; ?>
          </td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($wfCurrentApprover?->name ?? 'Non défini'); ?></td>
          <td class="py-3">
            <div class="flex flex-wrap gap-2">
              <?php $__currentLoopData = ['approved' => __('personnel.ui.leave.btn_approve'), 'rejected' => __('personnel.ui.leave.btn_reject')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if(!$canApproveReject): ?>
                <?php continue; ?>
              <?php endif; ?>
              <form method="POST" action="<?php echo e(route('admin.personnel.leave-requests.status', $leaveRequest)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>
                <input type="hidden" name="personnel_tab" value="leave">
                <input type="hidden" name="leave_subtab" value="validation">
                <input type="hidden" name="status" value="<?php echo e($status); ?>">
                <button type="submit" class="<?php echo e($status === 'approved' ? $validationApproveBtnClass : $validationRejectBtnClass); ?>"><?php echo e($label); ?></button>
              </form>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php if(!$canApproveReject): ?>
            <div class="mt-2 text-[11px] text-gray-500">Action réservée au valideur courant.</div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td colspan="7" class="py-8 text-center text-sm text-gray-400">Aucune demande en attente de validation.</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php elseif($personnelTab === 'training'): ?>
<?php
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
?>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap items-center gap-2">
    <?php $__currentLoopData = $trainingSubtabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'training', 'training_subtab' => $key])); ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition <?php echo e($trainingSubtab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'); ?>">
      <i class="fa <?php echo e($icon); ?> text-sm"></i>
      <span><?php echo e($label); ?></span>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>

<?php if($trainingSubtab === 'validation'): ?>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Validation des demandes de formation</h3>
      <p class="text-sm text-gray-500">Demandes en attente d'action du valideur courant.</p>
    </div>
    <span class="text-xs rounded-full bg-violet-50 text-violet-700 border border-violet-100 px-3 py-1"><?php echo e($trainingValidationRows->count()); ?> en attente</span>
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
        <?php $__empty_1 = true; $__currentLoopData = $trainingValidationRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
          $trainingCurrentApproverId = (string) data_get($enrollment->metadata, 'approval_workflow.current_approver_user_id', '');
          $trainingCurrentApproverName = $allUsers->firstWhere('id', $trainingCurrentApproverId)?->name ?? 'Non défini';
        ?>
        <tr class="border-b border-gray-100 align-top">
          <td class="py-3 pr-4 text-gray-600"><?php echo e($enrollment->employee?->employee_number ?: '-'); ?></td>
          <td class="py-3 pr-4 text-gray-800 font-semibold"><?php echo e($enrollment->employee?->full_name ?? 'Agent supprimé'); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($enrollment->training?->title ?? 'Formation supprimée'); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($trainingCurrentApproverName); ?></td>
          <td class="py-3">
            <div class="flex flex-wrap gap-2">
              <form method="POST" action="<?php echo e(route('admin.personnel.training-enrollments.update-status', $enrollment->id)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>
                <input type="hidden" name="personnel_tab" value="training">
                <input type="hidden" name="training_subtab" value="validation">
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="<?php echo e($trainingApproveBtnClass); ?>">Approuver</button>
              </form>
              <form method="POST" action="<?php echo e(route('admin.personnel.training-enrollments.update-status', $enrollment->id)); ?>" class="flex flex-wrap gap-2 items-center">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>
                <input type="hidden" name="personnel_tab" value="training">
                <input type="hidden" name="training_subtab" value="validation">
                <input type="hidden" name="status" value="rejected">
                <input type="text" name="comment" required placeholder="Motif du rejet" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs min-w-[180px]">
                <button type="submit" class="<?php echo e($trainingRejectBtnClass); ?>">Rejeter</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="5" class="py-8 text-center text-sm text-gray-400">Aucune demande de formation en attente de validation.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif($trainingSubtab === 'history'): ?>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Historique des demandes de formation</h3>
      <p class="text-sm text-gray-500">Demandes finalisées (validées/rejetées).</p>
    </div>
    <span class="text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 px-3 py-1"><?php echo e($trainingHistoryRows->count()); ?> élément(s)</span>
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
        <?php $__empty_1 = true; $__currentLoopData = $trainingHistoryRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="border-b border-gray-100">
          <td class="py-3 pr-4 text-gray-600"><?php echo e(optional($enrollment->updated_at)->format('d/m/Y H:i')); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($enrollment->employee?->employee_number ?: '-'); ?></td>
          <td class="py-3 pr-4 text-gray-800 font-semibold"><?php echo e($enrollment->employee?->full_name ?? 'Agent supprimé'); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($enrollment->training?->title ?? 'Formation supprimée'); ?></td>
          <td class="py-3"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?php echo e($enrollment->status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'); ?>"><?php echo e(ucfirst(str_replace('_', ' ', $enrollment->status))); ?></span></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="5" class="py-8 text-center text-sm text-gray-400">Aucun historique disponible.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="grid grid-cols-1 xl:grid-cols-6 gap-5 mb-5">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('personnel.ui.training.catalog_title')); ?></h3>
        <p class="text-sm text-gray-500"><?php echo e(__('personnel.ui.training.catalog_description')); ?></p>
      </div>
      <span class="text-xs rounded-full bg-violet-50 text-violet-700 border border-violet-100 px-3 py-1"><?php echo e(number_format($personnelStats['trainings'] ?? 0)); ?> formation(s)</span>
    </div>

    <form method="POST" action="<?php echo e(route('admin.personnel.trainings.store')); ?>" class="space-y-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="training">

      <?php if($adminScope): ?>
      <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type']); ?>">
      <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
      <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800 font-medium"><?php echo e(__('personnel.ui.training.admin_locked')); ?></div>
      <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.admin_type_label')); ?></label>
          <select name="administration_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value="emitter" <?php echo e(old('administration_type', 'emitter') === 'emitter' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.training.admin_emitter')); ?></option>
            <option value="recipient" <?php echo e(old('administration_type') === 'recipient' ? 'selected' : ''); ?>><?php echo e(__('personnel.ui.training.admin_recipient')); ?></option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.admin_type_label')); ?></label>
          <select name="administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <option value=""><?php echo e(__('personnel.ui.training.admin_select')); ?></option>
            <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($e->id); ?>" <?php echo e(old('administration_id') === $e->id ? 'selected' : ''); ?>><?php echo e($e->name); ?> <?php echo e(__('personnel.ui.training.admin_emitter_bracket')); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($r->id); ?>" <?php echo e(old('administration_id') === $r->id ? 'selected' : ''); ?>><?php echo e($r->name); ?> <?php echo e(__('personnel.ui.training.admin_recipient_bracket')); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_title')); ?></label>
          <input type="text" name="title" value="<?php echo e(old('title')); ?>" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_code')); ?></label>
          <input type="text" name="code" value="<?php echo e(old('code')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_category')); ?></label>
          <input type="text" name="category" value="<?php echo e(old('category')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_mode')); ?></label>
          <select name="delivery_mode" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <?php $__currentLoopData = ['internal' => __('personnel.ui.training.form_mode_internal'), 'external' => __('personnel.ui.training.form_mode_external'), 'elearning' => __('personnel.ui.training.form_mode_elearning'), 'hybrid' => __('personnel.ui.training.form_mode_hybrid')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($value); ?>" <?php echo e(old('delivery_mode', 'internal') === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_provider')); ?></label>
          <input type="text" name="provider_name" value="<?php echo e(old('provider_name')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_duration')); ?></label>
          <input type="number" min="0" step="0.5" name="duration_hours" value="<?php echo e(old('duration_hours')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_budget')); ?></label>
          <input type="number" min="0" step="0.01" name="budget_amount" value="<?php echo e(old('budget_amount')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_validity')); ?></label>
          <input type="number" min="0" name="validity_months" value="<?php echo e(old('validity_months')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_skills')); ?></label>
        <input type="text" name="skills" value="<?php echo e(old('skills')); ?>" placeholder="<?php echo e(__('personnel.ui.training.form_skills_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_objectives')); ?></label>
        <textarea name="objectives" rows="2" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('objectives')); ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.form_description')); ?></label>
        <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('description')); ?></textarea>
      </div>
      <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_mandatory" value="1" <?php echo e(old('is_mandatory') ? 'checked' : ''); ?>> <?php echo e(__('personnel.ui.training.form_is_mandatory')); ?></label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" <?php echo e(old('is_active', '1') ? 'checked' : ''); ?>> <?php echo e(__('personnel.ui.training.form_is_active')); ?></label>
      </div>
      <button type="submit" class="px-6 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.training.btn_save_training')); ?></button>
    </form>
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('personnel.ui.training.assign_title')); ?></h3>
        <p class="text-sm text-gray-500"><?php echo e(__('personnel.ui.training.assign_description')); ?></p>
      </div>
      <span class="text-xs rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1"><?php echo e(number_format($personnelStats['enrollments'] ?? 0)); ?> affectation(s)</span>
    </div>

    <?php if($personnelEmployeeDirectory->isEmpty() || $personnelTrainings->isEmpty()): ?>
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800"><?php echo e(__('personnel.ui.training.need_employee_training')); ?></div>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('admin.personnel.training-enrollments.store')); ?>" class="space-y-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="training">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_employee')); ?></label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value=""><?php echo e(__('personnel.ui.training.select')); ?></option>
          <?php $__currentLoopData = $personnelEmployeeDirectory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($employee->id); ?>" <?php echo e(old('employee_id', $editingPersonnel->id ?? '') === $employee->id ? 'selected' : ''); ?>><?php echo e($employee->full_name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_training')); ?></label>
        <select name="training_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value=""><?php echo e(__('personnel.ui.training.select')); ?></option>
          <?php $__currentLoopData = $personnelTrainings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $training): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($training->id); ?>" <?php echo e(old('training_id') === $training->id ? 'selected' : ''); ?>><?php echo e($training->title); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_status')); ?></label>
          <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <?php $__currentLoopData = ['planned' => __('personnel.ui.training.assign_form_status_planned'), 'in_progress' => __('personnel.ui.training.assign_form_status_in_progress'), 'completed' => __('personnel.ui.training.assign_form_status_completed'), 'cancelled' => __('personnel.ui.training.assign_form_status_cancelled')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($value); ?>" <?php echo e(old('status', 'planned') === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_certificate')); ?></label>
          <input type="file" name="certificate" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_planned_start')); ?></label>
          <input type="date" name="planned_start_date" value="<?php echo e(old('planned_start_date')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_planned_end')); ?></label>
          <input type="date" name="planned_end_date" value="<?php echo e(old('planned_end_date')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_attendance')); ?></label>
          <input type="number" min="0" max="100" step="0.01" name="attendance_rate" value="<?php echo e(old('attendance_rate')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_score')); ?></label>
          <input type="number" min="0" max="100" step="0.01" name="score" value="<?php echo e(old('score')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_notes')); ?></label>
        <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('notes')); ?></textarea>
      </div>
      <button type="submit" class="px-6 py-2.5 bg-teal-600 text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.training.btn_assign')); ?></button>
    </form>
    <?php endif; ?>
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('personnel.ui.training.skills_matrix_title')); ?></h3>
        <p class="text-sm text-gray-500"><?php echo e(__('personnel.ui.training.skills_matrix_description')); ?></p>
      </div>
      <span class="text-xs rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-3 py-1"><?php echo e(number_format($personnelStats['skills'] ?? 0)); ?> compétence(s)</span>
    </div>

    <?php if($personnelEmployeeDirectory->isEmpty()): ?>
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800"><?php echo e(__('personnel.ui.training.need_employee_skills')); ?></div>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.personnel.employees.skills.store')); ?>" class="space-y-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="training">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_employee')); ?></label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value=""><?php echo e(__('personnel.ui.training.select')); ?></option>
          <?php $__currentLoopData = $personnelEmployeeDirectory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($employee->id); ?>" <?php echo e(old('employee_id', $editingPersonnel->id ?? '') === $employee->id ? 'selected' : ''); ?>><?php echo e($employee->full_name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.skills_form_skill')); ?></label>
        <input type="text" name="skill_name" value="<?php echo e(old('skill_name')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.skills_form_category')); ?></label>
        <input type="text" name="category" value="<?php echo e(old('category')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.skills_form_current_level')); ?></label>
          <input type="number" min="1" max="5" name="current_level" value="<?php echo e(old('current_level', 3)); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.skills_form_target_level')); ?></label>
          <input type="number" min="1" max="5" name="target_level" value="<?php echo e(old('target_level')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.skills_form_assessment_date')); ?></label>
          <input type="date" name="assessment_date" value="<?php echo e(old('assessment_date')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.skills_form_source')); ?></label>
          <input type="text" name="source" value="<?php echo e(old('source')); ?>" placeholder="<?php echo e(__('personnel.ui.training.skills_form_source_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.skills_form_notes')); ?></label>
        <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('notes')); ?></textarea>
      </div>
      <button type="submit" class="px-6 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.training.btn_add_skill')); ?></button>
    </form>
    <?php endif; ?>
  </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.training.catalog_existing_title')); ?></h3>
    <div class="space-y-3">
      <?php $__empty_1 = true; $__currentLoopData = $personnelTrainings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $training): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-semibold text-gray-800"><?php echo e($training->title); ?></div>
            <div class="text-sm text-gray-500"><?php echo e($training->category ?: __('personnel.ui.training.no_category')); ?> · <?php echo e(__('personnel.ui.statuses.' . $training->delivery_mode)); ?> · <?php echo e($training->provider_name ?: __('personnel.ui.training.internal_provider')); ?></div>
          </div>
          <span class="text-xs rounded-full bg-gray-100 text-gray-700 px-3 py-1"><?php echo e($training->enrollments_count); ?> <?php echo e(__('personnel.ui.training.enrolled_count')); ?></span>
        </div>
        <div class="text-xs text-gray-400 mt-2"><?php echo e($training->skills ? implode(', ', $training->skills) : __('personnel.ui.training.no_skills_target')); ?></div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.training.no_trainings_created')); ?></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.training.tracking_title')); ?></h3>
    <div class="space-y-3 mb-5">
      <?php $__empty_1 = true; $__currentLoopData = $personnelTrainingEnrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
        <div class="font-semibold text-gray-800"><?php echo e($enrollment->employee?->full_name ?? __('personnel.ui.training.deleted_employee')); ?> · <?php echo e($enrollment->training?->title ?? __('personnel.ui.training.deleted_training')); ?></div>
        <div class="text-sm text-gray-500 mt-1"><?php echo e(__('personnel.ui.training.status_prefix')); ?> <?php echo e(__('personnel.ui.statuses.' . $enrollment->status)); ?><?php echo e($enrollment->planned_start_date ? ' · ' . __('personnel.ui.training.start_prefix') . ' ' . $enrollment->planned_start_date->format('d/m/Y') : ''); ?></div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.training.no_enrollments')); ?></div>
      <?php endif; ?>
    </div>

    <div class="border-t border-gray-200 pt-5">
      <h4 class="font-semibold text-gray-800 mb-3"><?php echo e(__('personnel.ui.training.skills_recent_title')); ?></h4>
      <div class="space-y-3">
        <?php $__empty_1 = true; $__currentLoopData = $personnelEmployeeSkills; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $skill): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="rounded-xl border border-gray-200 p-4">
          <div class="font-semibold text-gray-800"><?php echo e($skill->skill_name); ?></div>
          <div class="text-sm text-gray-500 mt-1"><?php echo e($skill->employee?->full_name ?? __('personnel.ui.training.deleted_employee')); ?> · <?php echo e(__('personnel.ui.training.level_prefix')); ?> <?php echo e($skill->current_level); ?><?php echo e($skill->target_level ? ' ' . __('personnel.ui.training.target_prefix') . ' ' . $skill->target_level : ''); ?></div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.training.no_skills_evaluated')); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>


<?php
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
?>
<div id="modal-training-requests" class="hidden fixed inset-0 z-50 flex items-start justify-center p-4 pt-16 bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-4xl max-h-[80vh] flex flex-col overflow-hidden">
    
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-xl bg-violet-100 flex items-center justify-center">
          <i class="fas fa-graduation-cap text-violet-600 text-sm"></i>
        </div>
        <div>
          <h2 class="text-base font-bold text-gray-800">Demandes de formations</h2>
          <p class="text-xs text-gray-500">
            <?php if($_mrIsFullAccess): ?>
              Toutes les demandes — suivi du processus de validation
            <?php else: ?>
              Demandes de vos collaborateurs directs
            <?php endif; ?>
          </p>
        </div>
      </div>
      <button type="button"
        onclick="document.getElementById('modal-training-requests').classList.add('hidden')"
        class="p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>

    
    <?php if($_mrIsFullAccess): ?>
    <div class="px-6 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap gap-2 text-xs">
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>En attente de validation</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>Planifiée</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>En cours</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100"><span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>Terminée</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-100"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Rejetée</span>
      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-100"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>Annulée</span>
    </div>
    <?php endif; ?>

    
    <div class="flex-1 overflow-y-auto px-6 py-4">
      <?php if($_mrEnrollments->isEmpty()): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
        <i class="fas fa-inbox text-gray-300 text-3xl mb-3 block"></i>
        <?php if($_mrIsFullAccess): ?>
          Aucune demande de formation enregistrée.
        <?php else: ?>
          Aucune demande de formation de vos collaborateurs.
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="space-y-3">
        <?php $__currentLoopData = $_mrEnrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
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
        ?>
        <div class="rounded-xl border border-gray-200 p-4 bg-white hover:shadow-sm transition">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
              <div class="font-semibold text-gray-800 truncate"><?php echo e($enrollment->employee?->full_name ?? '—'); ?></div>
              <div class="text-sm text-gray-600 mt-0.5"><?php echo e($enrollment->training?->title ?? 'Formation supprimée'); ?></div>
              <?php if($enrollment->training?->category): ?>
              <div class="text-xs text-gray-400 mt-0.5"><?php echo e($enrollment->training->category); ?></div>
              <?php endif; ?>
            </div>
            <span class="flex-shrink-0 text-xs rounded-full border px-3 py-1 font-medium <?php echo e($_enrStatusColor); ?>"><?php echo e($_enrStatusLabel); ?></span>
          </div>
          <div class="flex flex-wrap gap-4 mt-3 text-xs text-gray-500">
            <?php if($enrollment->planned_start_date): ?>
            <span><i class="fas fa-calendar-day mr-1 text-gray-300"></i>Début : <?php echo e($enrollment->planned_start_date->format('d/m/Y')); ?></span>
            <?php endif; ?>
            <?php if($enrollment->planned_end_date): ?>
            <span><i class="fas fa-calendar-check mr-1 text-gray-300"></i>Fin : <?php echo e($enrollment->planned_end_date->format('d/m/Y')); ?></span>
            <?php endif; ?>
            <?php if($enrollment->attendance_rate !== null): ?>
            <span><i class="fas fa-user-check mr-1 text-gray-300"></i>Présence : <?php echo e($enrollment->attendance_rate); ?>%</span>
            <?php endif; ?>
            <?php if($enrollment->score !== null): ?>
            <span><i class="fas fa-star mr-1 text-gray-300"></i>Score : <?php echo e($enrollment->score); ?>/100</span>
            <?php endif; ?>
          </div>
          <?php if($enrollment->notes): ?>
          <div class="mt-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 italic"><?php echo e($enrollment->notes); ?></div>
          <?php endif; ?>

          <?php if($_enrWorkflowType === 'training_hierarchical'): ?>
          <div class="mt-2 text-xs text-gray-500">
            Valideur courant: <?php echo e($_enrCurrentApproverName); ?>

            <?php if($_enrCurrentStep && !empty($_enrCurrentStep['profile'])): ?>
              (<?php echo e($_enrCurrentStep['profile']); ?>)
            <?php endif; ?>
          </div>

          <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3">
            <div class="text-xs font-semibold text-gray-700 mb-2">Timeline de validation</div>
            <div class="space-y-2">
              <?php $__empty_1 = true; $__currentLoopData = $_enrHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $_h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
              <?php
                $_hStatus = (string) ($_h['status'] ?? 'approved');
                $_hName = $allUsers->firstWhere('id', (string) ($_h['acted_by_user_id'] ?? ''))?->name ?? 'Utilisateur';
                $_hLabel = $_hStatus === 'rejected' ? 'Rejetée' : 'Approuvée';
                $_hDot = $_hStatus === 'rejected' ? 'bg-red-500' : 'bg-emerald-500';
                $_hText = $_hStatus === 'rejected' ? 'text-red-700' : 'text-emerald-700';
              ?>
              <div class="flex items-start gap-2">
                <span class="mt-1 h-2 w-2 rounded-full <?php echo e($_hDot); ?>"></span>
                <div class="mt-0.5 <?php echo e($_hText); ?>">
                  <?php if($_hStatus === 'rejected'): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M12 2a10 10 0 100 20 10 10 0 000-20zm3.53 6.47a.75.75 0 010 1.06L13.06 12l2.47 2.47a.75.75 0 11-1.06 1.06L12 13.06l-2.47 2.47a.75.75 0 01-1.06-1.06L10.94 12 8.47 9.53a.75.75 0 111.06-1.06L12 10.94l2.47-2.47a.75.75 0 011.06 0z" clip-rule="evenodd" />
                  </svg>
                  <?php else: ?>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm14.28-2.03a.75.75 0 10-1.06-1.06l-4.97 4.97-1.97-1.97a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.06 0l5.5-5.5z" clip-rule="evenodd" />
                  </svg>
                  <?php endif; ?>
                </div>
                <div class="text-xs">
                  <div class="font-semibold <?php echo e($_hText); ?>"><?php echo e($_hLabel); ?> par <?php echo e($_hName); ?></div>
                  <div class="text-gray-500"><?php echo e(data_get($_h, 'step_profile', 'Niveau')); ?> · <?php echo e(\Carbon\Carbon::parse($_h['acted_at'])->format('d/m/Y H:i')); ?></div>
                  <?php if(!empty($_h['comment'])): ?>
                  <div class="mt-1 text-gray-600 italic">Motif: <?php echo e($_h['comment']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <div class="text-xs text-gray-500">Aucune action enregistrée pour le moment.</div>
              <?php endif; ?>

              <?php if($_enrStatus === 'pending'): ?>
              <div class="flex items-start gap-2">
                <span class="mt-1 h-2 w-2 rounded-full bg-amber-500"></span>
                <div class="mt-0.5 text-amber-700">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5zM12.75 7.5a.75.75 0 00-1.5 0V12a.75.75 0 00.22.53l3 3a.75.75 0 101.06-1.06l-2.78-2.78V7.5z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="text-xs">
                  <div class="font-semibold text-amber-700">En attente de validation</div>
                  <div class="text-gray-500">Valideur courant: <?php echo e($_enrCurrentApproverName); ?></div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if($_enrStatus === 'rejected' && data_get($enrollment->metadata, 'rejection_reason')): ?>
          <div class="mt-2 rounded-lg bg-red-50 border border-red-100 px-3 py-2 text-xs text-red-700">
            Motif du rejet: <?php echo e(data_get($enrollment->metadata, 'rejection_reason')); ?>

          </div>
          <?php endif; ?>
          <?php endif; ?>

          <?php if($_enrCanAct): ?>
          <div class="mt-3 flex flex-col md:flex-row gap-2">
            <form method="POST" action="<?php echo e(route('admin.personnel.training-enrollments.update-status', $enrollment->id)); ?>" class="inline-flex">
              <?php echo csrf_field(); ?>
              <?php echo method_field('PATCH'); ?>
              <input type="hidden" name="personnel_tab" value="training">
              <input type="hidden" name="status" value="approved">
              <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition">Approuver</button>
            </form>

            <form method="POST" action="<?php echo e(route('admin.personnel.training-enrollments.update-status', $enrollment->id)); ?>" class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-2">
              <?php echo csrf_field(); ?>
              <?php echo method_field('PATCH'); ?>
              <input type="hidden" name="personnel_tab" value="training">
              <input type="hidden" name="status" value="rejected">
              <input type="text" name="comment" required placeholder="Motif du rejet" class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-1.5 text-xs bg-white">
              <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition">Rejeter</button>
            </form>
          </div>
          <?php elseif($_enrFollowOnly): ?>
          <div class="mt-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-700">
            Mode suivi: cette demande est en attente de validation par le valideur courant.
          </div>
          <?php elseif($_mrIsFullAccess): ?>
          
          <form method="POST" action="<?php echo e(route('admin.personnel.training-enrollments.update-status', $enrollment->id)); ?>" class="mt-3 flex items-center gap-2 flex-wrap">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PATCH'); ?>
            <input type="hidden" name="personnel_tab" value="training">
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs bg-white">
              <?php $__currentLoopData = ['planned' => 'Planifiée', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sv => $sl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($sv); ?>" <?php echo e($_enrStatus === $sv ? 'selected' : ''); ?>><?php echo e($sl); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <button type="submit" class="px-3 py-1.5 bg-violet-600 text-white rounded-lg text-xs font-semibold hover:bg-violet-700 transition">Mettre à jour</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endif; ?>
    </div>

    
    <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between text-xs text-gray-500">
      <span><?php echo e($_mrEnrollments->count()); ?> demande(s) affichée(s)</span>
      <button type="button"
        onclick="document.getElementById('modal-training-requests').classList.add('hidden')"
        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-medium text-gray-700 transition">
        Fermer
      </button>
    </div>
  </div>
</div>

<?php endif; ?>
<?php elseif($personnelTab === 'career'): ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="text-xs uppercase tracking-wide text-indigo-700"><?php echo e(__('personnel.ui.career.cards.goals_title')); ?></div>
    <div class="text-2xl font-black text-gray-800 mt-1"><?php echo e(number_format($personnelStats['goals'] ?? 0)); ?></div>
    <p class="text-sm text-gray-500 mt-2"><?php echo e(__('personnel.ui.career.cards.goals_description')); ?></p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="text-xs uppercase tracking-wide text-emerald-700"><?php echo e(__('personnel.ui.career.cards.reviews_title')); ?></div>
    <div class="text-2xl font-black text-gray-800 mt-1"><?php echo e(number_format($personnelStats['reviews'] ?? 0)); ?></div>
    <p class="text-sm text-gray-500 mt-2"><?php echo e(__('personnel.ui.career.cards.reviews_description')); ?></p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="text-xs uppercase tracking-wide text-amber-700"><?php echo e(__('personnel.ui.career.cards.path_title')); ?></div>
    <div class="text-2xl font-black text-gray-800 mt-1"><?php echo e(number_format($personnelStats['careerEvents'] ?? 0)); ?></div>
    <p class="text-sm text-gray-500 mt-2"><?php echo e(__('personnel.ui.career.cards.path_description')); ?></p>
  </div>
</div>

<?php
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
?>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-3 mb-5">
  <div class="flex flex-wrap items-center gap-2">
    <?php $__currentLoopData = $careerSubtabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.index', ['tab' => 'personnel', 'personnel_tab' => 'career', 'career_subtab' => $key])); ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition <?php echo e($careerSubtab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'); ?>">
      <i class="fa <?php echo e($icon); ?> text-sm"></i>
      <span><?php echo e($label); ?></span>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>

<?php if($careerSubtab === 'validation'): ?>
<section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Validation des demandes de mutation</h3>
      <p class="text-sm text-gray-500">Demandes en attente d'action du valideur courant.</p>
    </div>
    <span class="text-xs rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-3 py-1"><?php echo e($careerValidationRows->count()); ?> en attente</span>
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
        <?php $__empty_1 = true; $__currentLoopData = $careerValidationRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mutation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
          $wf = is_array(data_get($mutation->metadata, 'approval_workflow')) ? data_get($mutation->metadata, 'approval_workflow') : [];
          $wfCurrentApproverId = trim((string) data_get($wf, 'current_approver_user_id', ''));
          $wfCanAct = $wfCurrentApproverId !== '' && strcasecmp($wfCurrentApproverId, $currentActorId) === 0 && !$isAgentRhFollowOnly && !$isSuperAdminFollowOnly;
          $mutationEmployeeMeta = is_array($mutation->employee?->metadata) ? $mutation->employee->metadata : [];
          $mutationGrade = $mutationEmployeeMeta['grade'] ?? '-';
          $mutationEmployment = $mutation->employee?->job_title ?: ($mutationEmployeeMeta['employment'] ?? ($mutationEmployeeMeta['emploi'] ?? '-'));
          $mutationJustif = trim((string) ($mutation->summary ?? $mutation->notes ?? ''));
          $mutationAttachments = is_array(data_get($mutation->metadata, 'mutation_request.attachments')) ? data_get($mutation->metadata, 'mutation_request.attachments') : [];
        ?>
        <tr class="border-b border-gray-100 align-top">
          <td class="py-3 pr-4 text-gray-600"><?php echo e(optional($mutation->created_at)->format('d/m/Y H:i')); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($mutation->employee?->employee_number ?: '-'); ?></td>
          <td class="py-3 pr-4 text-gray-800 font-semibold"><?php echo e($mutation->employee?->full_name ?? 'Agent supprimé'); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($mutationGrade); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($mutationEmployment); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($mutationJustif !== '' ? $mutationJustif : '-'); ?></td>
          <td class="py-3 pr-4 text-gray-600">
            <?php if(!empty($mutationAttachments)): ?>
              <?php $__currentLoopData = $mutationAttachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachmentIndex => $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <a href="<?php echo e(route('admin.personnel.mutation-requests.attachment.download', [$mutation, $attachmentIndex])); ?>"
                class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-100">
                <i class="fas fa-file"></i> <?php echo e($attachment['original_name'] ?? basename($attachment['path'] ?? '')); ?>

              </a>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
          <td class="py-3">
            <?php if($wfCanAct): ?>
            <div class="flex items-center gap-2 whitespace-nowrap">
              <form method="POST" action="<?php echo e(route('admin.personnel.mutation-requests.status', $mutation)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>
                <input type="hidden" name="personnel_tab" value="career">
                <input type="hidden" name="career_subtab" value="validation">
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="<?php echo e($careerApproveBtnClass); ?>">Approuver</button>
              </form>
              <form method="POST" action="<?php echo e(route('admin.personnel.mutation-requests.status', $mutation)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>
                <input type="hidden" name="personnel_tab" value="career">
                <input type="hidden" name="career_subtab" value="validation">
                <input type="hidden" name="status" value="rejected">
                <input type="hidden" name="comment" value="Rejet depuis l'écran de validation mutation">
                <button type="submit" class="<?php echo e($careerRejectBtnClass); ?>">Rejeter</button>
              </form>
            </div>
            <?php else: ?>
            <div class="text-[11px] text-gray-500">Action réservée au valideur courant.</div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="7" class="py-8 text-center text-sm text-gray-400">Aucune demande de mutation en attente de validation.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php elseif($careerSubtab === 'history'): ?>
<section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Historique des demandes de mutation</h3>
      <p class="text-sm text-gray-500">Demandes validées ou rejetées.</p>
    </div>
    <span class="text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 px-3 py-1"><?php echo e($careerHistoryRows->count()); ?> élément(s)</span>
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
        <?php $__empty_1 = true; $__currentLoopData = $careerHistoryRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mutation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
          $historyTargetName = data_get($mutation->metadata, 'mutation_request.target_sub_entity_name', $mutation->new_job_title ?: '-');
          $historySourceName = data_get($mutation->metadata, 'mutation_request.source_sub_entity_name', $mutation->previous_job_title ?: '-');
        ?>
        <tr class="border-b border-gray-100">
          <td class="py-3 pr-4 text-gray-600"><?php echo e(optional($mutation->updated_at)->format('d/m/Y H:i')); ?></td>
          <td class="py-3 pr-4 text-gray-800 font-semibold"><?php echo e($mutation->employee?->full_name ?? 'Agent supprimé'); ?></td>
          <td class="py-3 pr-4 text-gray-600"><?php echo e($historySourceName); ?> → <?php echo e($historyTargetName); ?></td>
          <td class="py-3">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?php echo e($mutation->status === 'validated' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'); ?>"><?php echo e($mutation->status === 'validated' ? 'Validée' : 'Rejetée'); ?></span>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="4" class="py-8 text-center text-sm text-gray-400">Aucun historique disponible.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-6 gap-5 mb-5">
  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.career.goals_section_title')); ?></h3>
    <?php if($personnelEmployeeDirectory->isEmpty()): ?>
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800"><?php echo e(__('personnel.ui.career.need_employee_goals')); ?></div>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.personnel.goals.store')); ?>" class="space-y-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="career">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.career.form_employee')); ?></label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value=""><?php echo e(__('personnel.ui.career.select')); ?></option>
          <?php $__currentLoopData = $personnelEmployeeDirectory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($employee->id); ?>"><?php echo e($employee->full_name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.career.form_title')); ?></label>
        <input type="text" name="title" value="<?php echo e(old('title')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.career.form_goal_type')); ?></label>
          <select name="goal_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <?php $__currentLoopData = ['individual' => __('personnel.ui.career.form_goal_type_individual'), 'team' => __('personnel.ui.career.form_goal_type_team'), 'strategic' => __('personnel.ui.career.form_goal_type_strategic')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.training.assign_form_status')); ?></label>
          <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
            <?php $__currentLoopData = ['draft' => __('personnel.ui.career.form_goal_status_draft'), 'active' => __('personnel.ui.career.form_goal_status_active'), 'completed' => __('personnel.ui.career.form_goal_status_completed'), 'on_hold' => __('personnel.ui.career.form_goal_status_on_hold'), 'cancelled' => __('personnel.ui.career.form_goal_status_cancelled')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="number" min="0" max="100" step="0.01" name="weight" value="<?php echo e(old('weight')); ?>" placeholder="<?php echo e(__('personnel.ui.career.form_goal_weight_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="number" step="0.01" name="target_value" value="<?php echo e(old('target_value')); ?>" placeholder="<?php echo e(__('personnel.ui.career.form_goal_target_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="number" min="0" max="100" step="0.01" name="progress_percent" value="<?php echo e(old('progress_percent', 0)); ?>" placeholder="<?php echo e(__('personnel.ui.career.form_goal_progress_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="date" name="start_date" value="<?php echo e(old('start_date')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="date" name="due_date" value="<?php echo e(old('due_date')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <textarea name="description" rows="3" placeholder="<?php echo e(__('personnel.ui.career.form_description_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('description')); ?></textarea>
      <textarea name="notes" rows="2" placeholder="<?php echo e(__('personnel.ui.career.form_notes_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('notes')); ?></textarea>
      <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.career.btn_create_goal')); ?></button>
    </form>
    <?php endif; ?>
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.career.reviews_section_title')); ?></h3>
    <?php if($personnelEmployeeDirectory->isEmpty()): ?>
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800"><?php echo e(__('personnel.ui.career.need_employee_review')); ?></div>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.personnel.performance-reviews.store')); ?>" class="space-y-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="career">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.career.form_employee')); ?></label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value=""><?php echo e(__('personnel.ui.career.select')); ?></option>
          <?php $__currentLoopData = $personnelEmployeeDirectory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($employee->id); ?>"><?php echo e($employee->full_name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="title" value="<?php echo e(old('title')); ?>" placeholder="<?php echo e(__('personnel.ui.career.review_title_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="text" name="period_label" value="<?php echo e(old('period_label')); ?>" placeholder="<?php echo e(__('personnel.ui.career.review_period_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <select name="review_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <?php $__currentLoopData = ['annual' => __('personnel.ui.career.review_type_annual'), 'midyear' => __('personnel.ui.career.review_type_midyear'), 'probation' => __('personnel.ui.career.review_type_probation'), '360' => __('personnel.ui.career.review_type_360'), 'continuous' => __('personnel.ui.career.review_type_continuous')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <?php $__currentLoopData = ['scheduled' => __('personnel.ui.career.review_status_scheduled'), 'in_progress' => __('personnel.ui.career.review_status_in_progress'), 'completed' => __('personnel.ui.career.review_status_completed'), 'cancelled' => __('personnel.ui.career.review_status_cancelled')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <input type="number" min="0" max="100" step="0.01" name="overall_score" value="<?php echo e(old('overall_score')); ?>" placeholder="<?php echo e(__('personnel.ui.career.overall_score_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <input type="date" name="scheduled_at" value="<?php echo e(old('scheduled_at')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      <textarea name="strengths" rows="2" placeholder="<?php echo e(__('personnel.ui.career.strengths_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('strengths')); ?></textarea>
      <textarea name="improvements" rows="2" placeholder="<?php echo e(__('personnel.ui.career.improvements_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('improvements')); ?></textarea>
      <textarea name="recommendations" rows="2" placeholder="<?php echo e(__('personnel.ui.career.recommendations_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('recommendations')); ?></textarea>
      <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.career.btn_save_review')); ?></button>
    </form>
    <?php endif; ?>
  </section>

  <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.career.events_section_title')); ?></h3>
    <?php if($personnelEmployeeDirectory->isEmpty()): ?>
    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-5 text-sm text-amber-800"><?php echo e(__('personnel.ui.career.need_employee_events')); ?></div>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.personnel.career-events.store')); ?>" class="space-y-4">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="personnel_tab" value="career">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1"><?php echo e(__('personnel.ui.career.form_employee')); ?></label>
        <select name="employee_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <option value=""><?php echo e(__('personnel.ui.career.select')); ?></option>
          <?php $__currentLoopData = $personnelEmployeeDirectory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($employee->id); ?>"><?php echo e($employee->full_name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="title" value="<?php echo e(old('title')); ?>" placeholder="<?php echo e(__('personnel.ui.career.event_title_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <select name="event_type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
          <?php $__currentLoopData = ['promotion' => __('personnel.ui.career.event_type_promotion'), 'mobility' => __('personnel.ui.career.event_type_mobility'), 'succession' => __('personnel.ui.career.event_type_succession'), 'job_change' => __('personnel.ui.career.event_type_job_change'), 'interview' => __('personnel.ui.career.event_type_interview')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="date" name="effective_date" value="<?php echo e(old('effective_date')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="text" name="previous_job_title" value="<?php echo e(old('previous_job_title')); ?>" placeholder="<?php echo e(__('personnel.ui.career.prev_job_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <input type="text" name="new_job_title" value="<?php echo e(old('new_job_title')); ?>" placeholder="<?php echo e(__('personnel.ui.career.new_job_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
      </div>
      <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
        <?php $__currentLoopData = ['planned' => __('personnel.ui.career.event_status_planned'), 'validated' => __('personnel.ui.career.event_status_validated'), 'completed' => __('personnel.ui.career.event_status_completed'), 'cancelled' => __('personnel.ui.career.event_status_cancelled')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <textarea name="summary" rows="2" placeholder="<?php echo e(__('personnel.ui.career.summary_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('summary')); ?></textarea>
      <textarea name="notes" rows="2" placeholder="<?php echo e(__('personnel.ui.career.form_notes_placeholder')); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm"><?php echo e(old('notes')); ?></textarea>
      <button type="submit" class="px-6 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-semibold"><?php echo e(__('personnel.ui.career.btn_trace_event')); ?></button>
    </form>
    <?php endif; ?>
  </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.career.goals_recent_title')); ?></h3>
    <div class="space-y-3">
      <?php $__empty_1 = true; $__currentLoopData = $personnelGoals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $goal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800"><?php echo e($goal->title); ?></div>
        <div class="text-sm text-gray-500 mt-1"><?php echo e($goal->employee?->full_name ?? __('personnel.ui.career.deleted_employee')); ?> · <?php echo e(__('personnel.ui.statuses.' . $goal->goal_type)); ?> · <?php echo e($goal->progress_percent); ?>%</div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.career.no_goals')); ?></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.career.reviews_recent_title')); ?></h3>
    <div class="space-y-3">
      <?php $__empty_1 = true; $__currentLoopData = $personnelPerformanceReviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800"><?php echo e($review->title); ?></div>
        <div class="text-sm text-gray-500 mt-1"><?php echo e($review->employee?->full_name ?? __('personnel.ui.career.deleted_employee')); ?> · <?php echo e(__('personnel.ui.statuses.' . $review->review_type)); ?> · <?php echo e(__('personnel.ui.statuses.' . $review->status)); ?></div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.career.no_reviews')); ?></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(__('personnel.ui.career.path_recent_title')); ?></h3>
    <div class="space-y-3">
      <?php $__empty_1 = true; $__currentLoopData = $personnelCareerEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="rounded-xl border border-gray-200 p-4">
        <div class="font-semibold text-gray-800"><?php echo e($event->title); ?></div>
        <div class="text-sm text-gray-500 mt-1"><?php echo e($event->employee?->full_name ?? __('personnel.ui.career.deleted_employee')); ?> · <?php echo e(__('personnel.ui.statuses.' . $event->event_type)); ?> · <?php echo e(__('personnel.ui.statuses.' . $event->status)); ?></div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-5 text-sm text-gray-500"><?php echo e(__('personnel.ui.career.no_events')); ?></div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php endif; ?>

<?php endif; ?>


<?php if($tab === 'templates'): ?>
<?php
    $selectedTplId = old('selected_template', request('selected_template', ''));
    $selectedTpl   = $templates->firstWhere('id', $selectedTplId);
    $filterEmitter = request('filter_emitter', '');
    $filteredTpls  = $filterEmitter
        ? $templates->filter(fn($t) => $t->administration_id === $filterEmitter)
        : $templates;
?>


<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

    
    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">Gestion des Templates</h2>
        <p class="text-xs text-gray-500">Utilisez la syntaxe <strong>&#123;&#123;variable&#125;&#125;</strong> dans le contenu pour créer des documents dynamiques.</p>

        
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
          <label class="block text-xs text-gray-500 mb-1">Administration Émettrice concernée</label>
            <select id="tpl-filter-emitter" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-300"
                onchange="tplFilterEmitter(this.value)">
                <option value="">Toutes les administrations</option>
                <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($e->id); ?>" <?php echo e($filterEmitter === $e->id ? 'selected' : ''); ?>><?php echo e($e->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        
        <?php $editTpl = session('editing_template') ? $templates->firstWhere('id', session('editing_template')) : null; ?>
        <form id="tpl-form" method="POST"
              action="<?php echo e($selectedTplId && request('tpl_action') === 'edit' ? route('admin.templates.update', $selectedTplId) : route('admin.templates.store')); ?>"
              class="flex flex-col gap-3">
            <?php echo csrf_field(); ?>
            <?php if($selectedTplId && request('tpl_action') === 'edit'): ?>
                <?php echo method_field('PUT'); ?>
                <input type="hidden" name="selected_template" value="<?php echo e($selectedTplId); ?>">
            <?php endif; ?>
            <input type="hidden" name="tab" value="templates">

            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input id="tpl-name" type="text" name="name" placeholder="Nom du template *"
                       value="<?php echo e(old('name', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->name ?? '') : '')); ?>"
                       required class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">

                <input id="tpl-filename" type="text" name="file_name" placeholder="Nom du fichier (ex: mon-template.docx)"
                       value="<?php echo e(old('file_name', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->file_name ?? '') : '')); ?>"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">

                <select id="tpl-filetype" name="file_type" class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">
                    <?php $__currentLoopData = ['docx' => 'DOCX','xlsx' => 'XLSX','pptx' => 'PPTX','pdf' => 'PDF']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($val); ?>" <?php echo e(old('file_type', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->file_type ?? 'docx') : 'docx') === $val ? 'selected' : ''); ?>><?php echo e($lbl); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php if(isset($adminScope) && $adminScope && $adminScope['type'] === 'emitter'): ?>
                <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
                <div class="md:col-span-2 border border-blue-100 rounded-lg px-3 py-2 text-xs bg-blue-50 text-blue-800 font-medium flex items-center gap-2">
                  <i class="fas fa-building text-blue-400"></i>
                  <?php echo e($emitters->first()?->name ?? '--'); ?>

                </div>
                <?php else: ?>
                <select name="administration_id" class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none">
                  <option value="">• Administration émettrice •</option>
                  <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($e->id); ?>" <?php echo e(old('administration_id', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->administration_id ?? '') : $filterEmitter) === $e->id ? 'selected' : ''); ?>><?php echo e($e->name); ?></option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php endif; ?>

                
                <button type="button" onclick="tplOpenOnlyOffice()"
                        class="bg-green-100 text-green-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-green-200 transition flex items-center justify-center gap-1">
                  <i class="fas fa-external-link-alt text-xs"></i> Ouvrir l'éditeur OnlyOffice
                </button>

                
                <button type="button" onclick="tplOpenUploadFlow()"
                        class="bg-blue-100 text-blue-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-blue-200 transition flex items-center justify-center gap-1">
                  <i class="fas fa-file-upload text-xs"></i> Uploader Word et générer le formulaire (IA)
                </button>
            </div>

            
            <div>
                
                <div id="tpl-var-insert-bar" class="flex flex-wrap gap-1.5 mb-1.5 p-2 bg-blue-50 border border-blue-100 rounded-lg">
                    <span class="text-[10px] font-bold text-blue-600 self-center mr-1"><i class="fas fa-code mr-1"></i>Insérer une variable :</span>
                    <?php $varExamples = ['nom','prenom','date','date_naissance','adresse','numero','objet','structure','poste','lieu']; ?>
                    <?php $__currentLoopData = $varExamples; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $varTag = '[' . $v . ']'; ?>
                    <button type="button" onclick="tplQuillInsertVar('<?php echo e($varTag); ?>')"
                            class="tpl-var-pill inline-flex items-center gap-1 bg-white border border-blue-200 text-blue-700 text-[10px] font-semibold px-2 py-0.5 rounded-full hover:bg-blue-100 transition cursor-pointer">
                        <i class="fas fa-plus text-[8px]"></i>&nbsp;<?php echo e($varTag); ?>

                    </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <button type="button" onclick="tplQuillInsertCustomVar()"
                            class="inline-flex items-center gap-1 bg-blue-600 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full hover:bg-blue-700 transition cursor-pointer">
                        <i class="fas fa-plus text-[8px]"></i> Personnalisée
                    </button>
                </div>
                
                <div id="tpl-quill-editor" style="font-size:13px;background:#fff;"></div>
                
                <textarea id="tpl-content" name="content" class="hidden"><?php echo e(old('content', $selectedTplId && request('tpl_action') === 'edit' ? ($selectedTpl->content ?? '') : '')); ?></textarea>
            </div>

              
            <button type="submit"
                    class="w-full bg-blue-600 text-white rounded-lg px-3 py-2.5 text-sm font-bold hover:bg-blue-700 transition flex items-center justify-center gap-2">
                <i class="fas fa-check-circle"></i>
                <?php echo e(($selectedTplId && request('tpl_action') === 'edit') ? 'Mettre à jour le modèle' : 'Créer le modèle'); ?>

            </button>
        </form>

        
        <div class="border-t border-gray-200 pt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-list text-gray-400 mr-1"></i> Templates créés
                <span class="ml-1 text-xs font-normal text-gray-400">(<?php echo e($filteredTpls->count()); ?>)</span>
            </h3>

        
        <div id="tpl-list-container" class="space-y-2">
            <?php $__empty_1 = true; $__currentLoopData = $filteredTpls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $tplShared = $shareMap[$tpl->id] ?? []; ?>
            <div class="border rounded-lg p-3 <?php echo e($selectedTplId === $tpl->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50'); ?>">
                <button type="button" onclick="tplSelect('<?php echo e($tpl->id); ?>')" class="w-full text-left">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-xs font-semibold text-gray-800 truncate" title="<?php echo e($tpl->name); ?>"><?php echo e($tpl->name); ?></p>
                  <?php if(request('ia_generated') === '1' && (string) $selectedTplId === (string) $tpl->id): ?>
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap"
                      title="Template importé avec détection IA et génération automatique des champs">
                    <i class="fas fa-robot text-[9px]"></i> Formulaire auto IA
                  </span>
                  <?php endif; ?>
                </div>
                    <p class="text-xs text-gray-500 truncate"><?php echo e($tpl->file_name ?: '-'); ?> • <?php echo e(strtoupper($tpl->file_type)); ?></p>
                    <?php if($tpl->administration): ?>
                    <p class="text-xs text-gray-400"><?php echo e($tpl->administration->name); ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-blue-600 mt-0.5"><?php echo e(count($tplShared)); ?> partage(s)</p>
                </button>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    
                    <button type="button" onclick="tplOoEdit('<?php echo e($tpl->id); ?>', '<?php echo e(addslashes($tpl->name)); ?>')"
                            class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-xs hover:bg-gray-300 transition flex items-center gap-1">
                        <i class="fas fa-pen text-xs"></i> Modifier
                    </button>
                    
                    <form method="POST" action="<?php echo e(route('admin.templates.destroy', $tpl)); ?>"
                          onsubmit="return confirm('Supprimer ce template ?')" class="inline">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <input type="hidden" name="tab" value="templates">
                        <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs hover:bg-red-200 transition">
                            <i class="fas fa-trash-alt text-xs"></i> Supprimer
                        </button>
                    </form>
                    
                    <button type="button" onclick="tplOpenShare('<?php echo e($tpl->id); ?>', '<?php echo e(addslashes($tpl->name)); ?>')"
                            class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200 transition flex items-center gap-1">
                        <i class="fas fa-share-alt text-xs"></i> Partager
                    </button>
                    
                    <button type="button" onclick="tplSelect('<?php echo e($tpl->id); ?>')"
                            class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs hover:bg-green-200 transition flex items-center gap-1">
                        <i class="fas fa-sliders-h text-xs"></i> Variables
                    </button>
                    
                    <?php if(in_array($tpl->file_type, ['docx','xlsx','pptx'])): ?>
                    <button type="button" onclick="tplDetectVars('<?php echo e($tpl->id); ?>','<?php echo e(addslashes($tpl->name)); ?>')" id="detect-btn-<?php echo e($tpl->id); ?>"
                            class="px-2 py-1 rounded bg-yellow-100 text-yellow-700 text-xs hover:bg-yellow-200 transition flex items-center gap-1">
                        <i class="fas fa-magic text-xs"></i> <span id="detect-label-<?php echo e($tpl->id); ?>"><?php echo e($tpl->variables->count() > 0 ? $tpl->variables->count().' vars' : 'Détecter'); ?></span>
                    </button>
                      <button type="button" onclick="tplAiEnrich('<?php echo e($tpl->id); ?>','<?php echo e(addslashes($tpl->name)); ?>')" id="ai-enrich-btn-<?php echo e($tpl->id); ?>"
                          class="px-2 py-1 rounded bg-purple-100 text-purple-700 text-xs hover:bg-purple-200 transition flex items-center gap-1"
                          title="Enrichir les variables avec l'IA Ollama (labels, types, obligatoire)">
                        <i class="fas fa-robot text-xs"></i> <span id="ai-enrich-label-<?php echo e($tpl->id); ?>">✨ IA</span>
                      </button>
                    
                    <?php if($tpl->variables->count() === 0 && $tpl->storage_path): ?>
                    <button type="button" onclick="tplRecoverVars('<?php echo e($tpl->id); ?>','<?php echo e(addslashes($tpl->name)); ?>')" id="recover-btn-<?php echo e($tpl->id); ?>"
                            class="px-2 py-1 rounded bg-orange-500 text-white text-xs hover:bg-orange-600 transition flex items-center gap-1 animate-pulse"
                            title="Réextrait les variables d'un fichier déjà stocké">
                        <i class="fas fa-exclamation-triangle text-xs"></i> Récupérer
                    </button>
                    <?php endif; ?>
                    <?php if(!$tpl->storage_path && $tpl->variables->count() === 0): ?>
                    
                    <button type="button" onclick="tplOoEdit('<?php echo e($tpl->id); ?>','<?php echo e(addslashes($tpl->name)); ?>')"
                            class="px-2 py-1 rounded bg-red-500 text-white text-xs hover:bg-red-600 transition flex items-center gap-1 animate-pulse"
                            title="Ce template n'a pas encore été enregistré dans OnlyOffice. Cliquez pour l'ouvrir et enregistrer.">
                        <i class="fas fa-exclamation-circle text-xs"></i> À enregistrer!
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="border border-dashed border-gray-300 rounded-lg p-6 text-xs text-gray-400 text-center">
              Aucun template configuré pour cette administration.
            </div>
            <?php endif; ?>
        </div>
        </div>
    </section>

    
    <section id="tpl-variables-panel" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">Balises dynamiques</h2>
        <p class="text-xs text-gray-500">
            Template sélectionné :
            <strong id="tpl-selected-name"><?php echo e($selectedTpl ? $selectedTpl->name : 'Aucun'); ?></strong>
        </p>

        
        <form method="POST"
              action="<?php echo e($selectedTplId ? route('admin.templates.variables.store', $selectedTplId) : '#'); ?>"
              class="grid grid-cols-1 md:grid-cols-3 gap-3"
              <?php echo e($selectedTplId ? '' : 'onsubmit="return false"'); ?>>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="tab" value="templates">
            <input type="hidden" name="selected_template" value="<?php echo e($selectedTplId); ?>">

            <input type="text" name="label" placeholder="Nom de la variable *"
                   class="md:col-span-2 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none"
                   <?php echo e($selectedTplId ? 'required' : 'disabled'); ?>>

            <select name="field_type" class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-300 outline-none" <?php echo e($selectedTplId ? '' : 'disabled'); ?>>
                <option value="text">Texte</option>
                <option value="date">Date</option>
                <option value="number">Nombre</option>
                <option value="select">Liste</option>
                <option value="textarea">Zone de texte</option>
            </select>

            <button type="submit"
                    class="md:col-span-3 bg-blue-600 text-white rounded-lg px-3 py-2 text-xs font-semibold hover:bg-blue-700 transition <?php echo e($selectedTplId ? '' : 'opacity-40 cursor-not-allowed'); ?>">
                Ajouter le champ
            </button>
        </form>

        
        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
            <?php if($selectedTpl): ?>
                <?php $__empty_1 = true; $__currentLoopData = $selectedTpl->variables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variable): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="border border-gray-200 bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-800"><?php echo e($variable->label); ?></p>
                    <p class="text-xs text-gray-500">Balise : <code class="bg-gray-100 px-1 rounded">[<?php echo e($variable->key); ?>]</code></p>
                    <p class="text-xs text-gray-500">Type : <?php echo e($variable->field_type); ?></p>
                    <div class="mt-2 flex gap-2">
                        <button type="button"
                                onclick="openEditVariableModal('<?php echo e($selectedTpl->id); ?>', '<?php echo e($variable->id); ?>', '<?php echo e(addslashes($variable->label)); ?>', '<?php echo e($variable->field_type); ?>', '<?php echo e($selectedTplId); ?>')"
                                class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200 transition">
                            <i class="fas fa-pen text-xs"></i> Modifier
                        </button>
                        <form method="POST"
                              action="<?php echo e(route('admin.templates.variables.destroy', [$selectedTpl, $variable->id])); ?>"
                              onsubmit="return confirm('Supprimer cette variable ?')" class="inline">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <input type="hidden" name="tab" value="templates">
                            <input type="hidden" name="selected_template" value="<?php echo e($selectedTplId); ?>">
                            <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs hover:bg-red-200 transition">
                                <i class="fas fa-trash-alt text-xs"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-xs text-gray-400">Aucune variable pour ce template. Ajoutez des balises [variable] (ou &#123;&#123;variable&#125;&#125;) dans le contenu.</p>
                <?php endif; ?>
            <?php else: ?>
            <p class="text-xs text-gray-400">Sélectionnez un template pour gérer ses variables.</p>
            <?php endif; ?>
        </div>

        
        <?php if($selectedTpl): ?>
        <div class="border-t border-gray-100 pt-4 space-y-2">
          <h3 class="text-sm font-semibold text-gray-800">Accès partagés</h3>
            <?php $currentShared = $shareMap[$selectedTpl->id] ?? []; ?>
            <?php if(count($currentShared)): ?>
            <div class="flex flex-wrap gap-2">
                <?php $__currentLoopData = $currentShared; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $uid): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $su = $allUsers->firstWhere('id', $uid); ?>
                <?php if($su): ?>
                <div class="flex items-center gap-1 bg-blue-50 border border-blue-200 rounded-full px-3 py-1 text-xs text-blue-700">
                    <span><?php echo e($su->name); ?></span>
                    <form method="POST" action="<?php echo e(route('admin.templates.share', $selectedTpl)); ?>" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="tab" value="templates">
                        <input type="hidden" name="selected_template" value="<?php echo e($selectedTplId); ?>">
                        <input type="hidden" name="user_id" value="<?php echo e($uid); ?>">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="ml-1 text-blue-400 hover:text-red-500 text-xs">&times;</button>
                    </form>
                </div>
                <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php else: ?>
            <p class="text-xs text-gray-400">Aucun accès partagé. Utilisez le bouton "Partager" dans la liste.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</div>


<div id="modal-tpl-share" class="adm-modal">
    <div class="adm-modal-box max-w-md">
        <button onclick="closeModal('modal-tpl-share')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-1">Partager le template</h3>
        <p id="share-modal-tpl-name" class="text-xs text-gray-500 mb-4"></p>

        
        <input type="text" id="share-user-search" placeholder="Rechercher un utilisateur…"
               oninput="tplShareSearch(this.value)"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs mb-3 focus:ring-2 focus:ring-blue-300 outline-none">

        <div id="share-user-list" class="max-h-60 overflow-y-auto space-y-1.5">
            <?php $__currentLoopData = $allUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $isShared = isset($shareMap['__current__']) && in_array($u->id, $shareMap['__current__'] ?? []); ?>
            <div class="share-user-row flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2 bg-gray-50"
                 data-name="<?php echo e(strtolower($u->name)); ?>" data-email="<?php echo e(strtolower($u->email)); ?>">
                <div>
                    <p class="text-xs font-medium text-gray-800"><?php echo e($u->name); ?></p>
                    <p class="text-xs text-gray-400"><?php echo e($u->email); ?></p>
                </div>
                <form method="POST" id="share-form-<?php echo e($u->id); ?>" action="" class="inline share-toggle-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="tab" value="templates">
                    <input type="hidden" name="user_id" value="<?php echo e($u->id); ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="selected_template" id="share-hidden-tpl-<?php echo e($u->id); ?>" value="">
                    <button type="submit"
                            class="share-toggle-btn px-3 py-1 rounded-lg text-xs font-semibold transition bg-gray-200 text-gray-700 hover:bg-blue-100 hover:text-blue-700"
                            data-user-id="<?php echo e($u->id); ?>">
                        Partager
                    </button>
                </form>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div class="mt-4 flex justify-end">
            <button onclick="closeModal('modal-tpl-share')" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Fermer</button>
        </div>
    </div>
</div>


<div id="modal-edit-variable" class="adm-modal">
    <div class="adm-modal-box max-w-md">
        <button onclick="closeModal('modal-edit-variable')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-4">Modifier la variable</h3>
        <form id="form-edit-variable" method="POST" action="#">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
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

        
        <div class="flex items-center gap-2 px-4 py-2 border-b border-gray-100 bg-white flex-shrink-0 flex-wrap">
            <div class="flex items-center gap-2 mr-auto">
                <i class="fas fa-file-alt text-blue-600 text-sm"></i>
                <span class="text-sm font-bold text-gray-800">OnlyOffice &mdash; éditeur de template</span>
            </div>

            <button type="button" onclick="tplOoCreateModel()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold transition shadow-sm">
                <i class="fas fa-plus-circle text-xs"></i> Créer un modèle
            </button>

            
            <button type="button" onclick="tplOoOpenUpload()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold transition shadow-sm">
                <i class="fas fa-upload text-xs"></i> Importer un fichier
            </button>
            
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

        
        <div id="tpl-oo-status" class="hidden px-4 py-2 text-xs font-medium bg-blue-50 border-b border-blue-100 text-blue-700 flex-shrink-0 flex items-center gap-2">
            <i class="fas fa-info-circle"></i><span id="tpl-oo-status-text"></span>
        </div>

        <div class="px-4 py-2 text-[11px] text-amber-800 bg-amber-50 border-b border-amber-100 flex items-start gap-2">
          <i class="fas fa-triangle-exclamation mt-0.5"></i>
          <span>Utilisez OnlyOffice pour créer ou modifier votre template DOCX/XLSX/PPTX. Les balises texte [nom] ou {{nom}} peuvent être insérées directement dans le document puis détectées dans Balises dynamiques.</span>
        </div>

        
        <div id="tpl-oo-create-panel" class="hidden flex-shrink-0 border-b border-blue-200 bg-blue-50 px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-blue-800"><i class="fas fa-plus-circle mr-1.5"></i>Créer un nouveau modèle</p>
                <button type="button" onclick="tplOoClosCreatePanel()" class="text-blue-400 hover:text-blue-700 text-lg leading-none">&times;</button>
            </div>
            <form id="tpl-oo-create-form" onsubmit="tplOoSubmitCreate(event)" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <?php echo csrf_field(); ?>
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
                <?php if(isset($adminScope) && $adminScope && $adminScope['type'] === 'emitter'): ?>
                
                <input type="hidden" id="tpl-oo-admin" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
                <div>
                  <label class="block text-xs font-semibold text-blue-700 mb-1">Administration</label>
                  <div class="w-full border border-blue-100 rounded-lg px-3 py-2 text-xs bg-blue-50 text-blue-800 font-medium flex items-center gap-2">
                    <i class="fas fa-building text-blue-400"></i>
                    <?php echo e($emitters->first()?->name ?? '--'); ?>

                  </div>
                </div>
                <?php elseif(auth()->user()->role === 'admin'): ?>
                <div>
                  <label class="block text-xs font-semibold text-blue-700 mb-1">Administration</label>
                  <select id="tpl-oo-admin" name="administration_id"
                    class="w-full border border-blue-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                    <option value="">-- Toutes --</option>
                    <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($e->id); ?>"><?php echo e($e->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                </div>
        <?php else: ?>
                
                <input type="hidden" id="tpl-oo-admin" name="administration_id"
                  value="<?php echo e(auth()->user()->profile->administration_id ?? ''); ?>">
                <div>
                  <label class="block text-xs font-semibold text-blue-700 mb-1">Administration</label>
                  <div class="w-full border border-blue-100 rounded-lg px-3 py-2 text-xs bg-blue-50 text-blue-800 font-medium flex items-center gap-2">
                    <i class="fas fa-building text-blue-400"></i>
                    <?php echo e(auth()->user()->profile->administration->name ?? '--'); ?>

                  </div>
                </div>
        <?php endif; ?>
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

        
        <div id="tpl-oo-upload-panel" class="hidden flex-shrink-0 border-b border-purple-200 bg-purple-50 px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold text-purple-800"><i class="fas fa-upload mr-1.5"></i>Importer un fichier comme modèle</p>
                <button type="button" onclick="tplOoCloseUploadPanel()" class="text-purple-400 hover:text-purple-700 text-lg leading-none">&times;</button>
            </div>
            
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
            
            <div id="tpl-oo-upload-info" class="hidden mb-4 bg-white border border-purple-200 rounded-lg px-4 py-3 flex items-center gap-3">
                <i class="fas fa-file-alt text-purple-500 text-lg"></i>
                <div class="flex-1 min-w-0">
                    <p id="tpl-oo-upload-fname" class="text-xs font-semibold text-gray-800 truncate"></p>
                    <p id="tpl-oo-upload-fsize" class="text-xs text-gray-400"></p>
                </div>
                <button onclick="tplOoClearUpload()" class="text-gray-400 hover:text-red-500 text-sm"><i class="fas fa-times"></i></button>
            </div>
            
            <form id="tpl-oo-upload-form" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end" onsubmit="tplOoSubmitUpload(event)">
                <?php echo csrf_field(); ?>
                <div>
                    <label class="block text-xs font-semibold text-purple-700 mb-1">Nom du modèle <span class="text-red-500">*</span></label>
                    <input id="tpl-oo-up-name" name="name" type="text" placeholder="Ex : Contrat de travail"
                        class="w-full border border-purple-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-purple-400 outline-none bg-white">
                    <p id="tpl-oo-up-name-err" class="hidden text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Champ obligatoire</p>
                </div>
                <?php if(auth()->user()->role === 'admin'): ?>
                <div>
                    <label class="block text-xs font-semibold text-purple-700 mb-1">Administration</label>
                    <select id="tpl-oo-up-admin" name="administration_id" class="w-full border border-purple-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-purple-400 outline-none bg-white">
                        <option value="">-- Toutes --</option>
                        <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($e->id); ?>"><?php echo e($e->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" id="tpl-oo-up-admin" name="administration_id" value="<?php echo e(auth()->user()->profile->administration_id ?? ''); ?>">
                <div>
                    <label class="block text-xs font-semibold text-purple-700 mb-1">Administration</label>
                    <div class="w-full border border-purple-100 rounded-lg px-3 py-2 text-xs bg-purple-50 text-purple-800 font-medium">
                        <i class="fas fa-building text-purple-400 mr-1"></i><?php echo e(auth()->user()->profile->administration->name ?? '--'); ?>

                    </div>
                </div>
                <?php endif; ?>
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

        
        <div id="tpl-oo-zones-bar" class="hidden px-4 py-2 bg-amber-50 border-b border-amber-100 flex-shrink-0 flex items-center gap-3 flex-wrap">
            <span class="text-xs font-semibold text-amber-700"><i class="fas fa-map-marker-alt mr-1"></i>Zones :</span>
            <div id="tpl-oo-zones-list" class="flex flex-wrap gap-2"></div>
        </div>

        
        <div id="oo-iframe-container" class="flex-1 relative overflow-hidden">
            <div id="oo-editor-placeholder" class="w-full h-full" style="min-height:0;position:relative;"></div>

            
            <div id="tpl-oo-zone-markers"></div>
        </div>
    </div>
</div>

    <?php $__env->startPush('scripts'); ?>
<script>
(function() {
  // --- Données PHP → JS -----------------------------------------------------
    var _ooUrl    = <?php echo json_encode($onlyofficeUrl, 15, 512) ?>;
    var _ooJwt    = <?php echo json_encode($onlyofficeJwt, 15, 512) ?>;
    var _ooTokenUrl = "<?php echo e(route('admin.onlyoffice.token')); ?>";
    var _appPublicUrl = <?php echo json_encode($appPublicUrl ?? '', 15, 512) ?>;
    var _shareMap = <?php echo json_encode($shareMap, 15, 512) ?>;
    var _currentShareTplId = '';
    var _adminBaseServer = <?php echo json_encode(route('admin.index', [], false)) ?>;
    // Base robuste: conserve un éventuel sous-dossier (ex: /app/public/admin)
    var _path = window.location.pathname || '/admin';
    var _idxAdmin = _path.indexOf('/admin');
    var _adminBaseRuntime = _idxAdmin >= 0 ? _path.substring(0, _idxAdmin) + '/admin' : '/admin';
    var _adminBase = _adminBaseRuntime || _adminBaseServer || '/admin';
    window._adminBase = _adminBase;
    var _adminTplBase = _adminBase + '/templates';
    var _adminTplUploadUrl = <?php echo json_encode(route('admin.admin.templates.uploadFile'), 15, 512) ?>;
    var _tplOoUploadTargetId = null;
    window._tplStoreUrl = <?php echo json_encode(route('admin.templates.store'), 15, 512) ?>;
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
    // --- Afficher/Masquer le formulaire de création de template --------------
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
            label.textContent = 'Cliquez pour créer un template';
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

    // --- Sélectionner un template ---------------------------------------------
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

        // Si un template est déjà sélectionné, le charger automatiquement
        var tplId = <?php echo json_encode($selectedTplId ?? null, 15, 512) ?> || window._ooCurrentTemplateId || null;
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
            .catch(function(err) { tplOoShowStatus('Erreur réseau : ' + err.message, 5000); });
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
        tplOoShowStatus('Sélectionnez un fichier DOCX, XLSX, PPTX ou PDF à importer comme modèle.', 0);
    }
    window.tplOoOpenUpload = tplOoOpenUpload;

    // --- Stocker le fichier sélectionné (utilisé par tplOoSubmitUpload) -------
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
        // Préremplir le champ nom si vide
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
        // Créer un DataTransfer pour affecter les fichiers à l'input
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

    // --- Détection automatique des variables depuis le fichier DOCX -----------
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
            if (lbl) lbl.textContent = data.count > 0 ? data.count + ' vars' : 'Détecter';
            if (data.success && data.count > 0) {
                var varNames = data.variables.slice(0, 5).map(function(v) { return '[' + v.key + ']'; }).join(', ');
                if (data.count > 5) varNames += ' + ' + (data.count - 5) + ' autres';
                if (confirm(data.message + '\n\n' + varNames + '\n\nRecharger pour voir les balises dynamiques ?')) {
                  tplNavigate(window.location.pathname + '?tab=templates&selected_template=' + tplId);
                }
            } else {
                alert(data.message || 'Aucune variable trouvée. Utilisez des balises [variable] (ou {{variable}}).');
            }
        })
        .catch(function(err) {
            if (btn) btn.disabled = false;
            if (lbl) lbl.textContent = 'Détecter';
            alert('Erreur réseau: ' + err.message);
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
          lbl.textContent = data.count > 0 ? data.count + ' vars' : 'Détecter';
        }

        if (data && data.success && typeof data.count === 'number') {
          if (data.count > 0) {
            tplOoShowStatus('Variables synchronisées automatiquement (' + data.count + ').', 3500);
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

    // Charger l'éditeur OO avec une config donnée (fichier uploadé ou nouveau)
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

        // Construire l'URL PDF locale (évite l'interstitiel ngrok)
        // On utilise localDocUrl si disponible, sinon on reconstruit depuis storagePubPath
        var pdfUrl = cfg.localDocUrl || cfg.docUrl;
        if (!pdfUrl && cfg.storagePubPath) {
            pdfUrl = window.location.protocol + '//' + window.location.host + cfg.storagePubPath;
        }
        var dlUrl = cfg.docUrl || pdfUrl; // téléchargement via URL publique

        var placeholder = document.getElementById('oo-editor-placeholder');
        if (placeholder) {
            placeholder.innerHTML =
                '<div style="width:100%;height:100%;display:flex;flex-direction:column;background:#525659;">' +
                '<div style="flex-shrink:0;padding:8px 12px;background:#3d4043;display:flex;align-items:center;gap:10px;">' +
                '<i class="fas fa-file-pdf" style="color:#ef4444;"></i>' +
                '<span style="color:#fff;font-size:12px;font-weight:600;">' + (cfg.template_name || 'Document PDF') + '</span>' +
                '<span style="color:#aaa;font-size:11px;margin-left:4px;">• Aperçu PDF</span>' +
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
            'onDocumentReady': function() { tplOoShowStatus('Éditeur prêt. Rédigez votre template avec {{ variable }} puis sauvegardez.', 6000); },
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
    // --- Créer une boîte de zone glissable -----------------------------------
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
    // --- Sceller une zone : verrouille définitivement drag ET resize ----------
    // --- Sceller : verrouille drag + resize, appliqué automatiquement au clic hors zone
    function tplOoSealZone(idx) {
        var zone = _tplOoZones[idx];
        if (!zone || !zone.el || zone.el._sealed) return;

        // Verrouillage physique via flag DOM
        zone.el._sealed = true;
        zone.sealed      = true;

        // Style verrouillé : bordure verte pleine, curseur bloqué
        zone.el.style.border     = '2.5px solid #16a34a';
        zone.el.style.background = 'rgba(22,163,74,0.13)';
        zone.el.style.cursor     = 'default';

        // Cacher le handle de resize
        var rh = document.getElementById('tpl-zone-resize-' + idx);
        if (rh) rh.style.display = 'none';

        // Mise à jour visuelle : label + hint verts
        var label = zone.el.querySelector('.tpl-zone-label');
        if (label) { label.style.color = '#15803d'; }
        var hint = zone.el.querySelector('.tpl-zone-hint');
        if (hint) {
            hint.textContent = '\uD83D\uDD12 Position fixée • cliquez pour repositionner';
            hint.style.color = '#15803d';
        }

        // Permettre de cliquer sur la zone pour la débloquer et repositionner
        zone.el.addEventListener('click', function onReopenClick(e) {
            if (e.target.tagName === 'BUTTON') return; // bouton supprimer ? ignorer
            zone.el.removeEventListener('click', onReopenClick);
            tplOoUnsealZone(idx);
        }, { once: true });

        tplOoShowStatus('Zone "Signature ' + (idx + 1) + '" fixée. Cliquez dessus pour repositionner, ou "Enregistrer les zones".', 5000);
    }
    window.tplOoSealZone = tplOoSealZone;

    // --- Débloquer une zone pour la repositionner -----------------------------
    function tplOoUnsealZone(idx) {
        var zone = _tplOoZones[idx];
        if (!zone || !zone.el) return;

        // Remettre en mode libre
        zone.el._sealed = false;
        zone.sealed      = false;

        // Style libre : bordure bleue pointillée
        zone.el.style.border     = '2.5px dashed #2563eb';
        zone.el.style.background = 'rgba(37,99,235,0.10)';
        zone.el.style.cursor     = 'move';

        // Réafficher le handle resize
        var rh = document.getElementById('tpl-zone-resize-' + idx);
        if (rh) rh.style.display = '';

        // Remettre les textes bleus
        var label = zone.el.querySelector('.tpl-zone-label');
        if (label) label.style.color = '#1d4ed8';
        var hint = zone.el.querySelector('.tpl-zone-hint');
        if (hint) { hint.textContent = 'Cliquez en dehors pour fixer'; hint.style.color = '#3b82f6'; }

        // Ré-enregistrer le listener "clic en dehors"
        function onDocMousedown(e) {
            if (zone.el._sealed) return;
            if (zone.el.contains(e.target)) return;
            tplOoSealZone(idx);
        }
        document.addEventListener('mousedown', onDocMousedown);
        zone.el._unsealListener = function() { document.removeEventListener('mousedown', onDocMousedown); };

        tplOoShowStatus('Zone "Signature ' + (idx + 1) + '" déverrouillée. Repositionnez-la puis cliquez en dehors.', 4000);
    }
    window.tplOoUnsealZone = tplOoUnsealZone;

    // ─── Bouton : Créer un modèle ─────────────────────────────────────────────
    function tplOoCreateModel() {
      var createPanel = document.getElementById('tpl-oo-create-panel');
      if (createPanel) createPanel.classList.add('hidden');
      tplOoOpenUpload(null);
      tplOoShowStatus('Importez un fichier contenant des variables (ex: {{ nom_client }}).', 6000);
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
        var adminUrl = '<?php echo e(route("admin.index")); ?>';
        var variablesUrl = adminUrl + '?tab=templates&selected_template=' + tpl.id;
        var div = document.createElement('div');
        div.id = 'tpl-row-' + tpl.id;
        div.className = 'border rounded-lg p-3 border-green-400 bg-green-50';
        div.innerHTML =
            '<div class="mb-2">' +
                '<p class="text-xs font-semibold text-gray-800 truncate">' + tpl.name + '</p>' +
            '<p class="text-xs text-gray-500">' + (tpl.file_name || '-') + ' • ' + (tpl.file_type || '').toUpperCase() + '</p>' +
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
          tplOoShowStatus(_tplOoZones.length + ' zone(s) mémorisée(s) localement (hors-ligne).', 4000);
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

        // Marquer utilisateurs déjà partagés
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
        var baseUrl = '<?php echo e(url("admin/templates")); ?>/' + templateId + '/variables/' + variableId;
        document.getElementById('form-edit-variable').action = baseUrl;
        document.getElementById('edit-var-label').value = label;
        document.getElementById('edit-var-field-type').value = fieldType;
        document.getElementById('edit-var-selected-template').value = selectedTplId;
        openModal('modal-edit-variable');
    }
    window.openEditVariableModal = openEditVariableModal;
})();
</script>
<?php $__env->stopPush(); ?>


<?php elseif($tab === 'emitters'): ?>
<?php
    $emitAction    = request('emit_action');
    $selEmitId     = request('selected_emitter', '');
    $editEmit      = ($emitAction === 'edit' && $selEmitId)
                        ? $emitters->firstWhere('id', $selEmitId)
                        : null;
    $em            = $editEmit; // alias court
    $emMeta        = $em ? ($em->metadata ?? []) : [];
?>
<div class="grid grid-cols-1 gap-5">
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
    <h2 class="text-lg font-semibold text-gray-800">Configurer une administration émettrice</h2>
    <form method="POST"
          action="<?php echo e($em ? route('admin.emitters.update', $em) : route('admin.emitters.store')); ?>"
          enctype="multipart/form-data"
          class="space-y-4">
      <?php echo csrf_field(); ?>
      <?php if($em): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>

      
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Informations Générales</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="text" name="name" value="<?php echo e(old('name', $em->name ?? '')); ?>"
                 placeholder="Nom de l'administration *"
                 class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="text" name="code" value="<?php echo e(old('code', $em->code ?? '')); ?>"
                 placeholder="Code d'identification *"
                 class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="text" name="sub_entity_code" value="<?php echo e(old('sub_entity_code', $em->sub_entity_code ?? '')); ?>"
                 placeholder="Code entité sous tutelle (ex: CAB MIN)"
                 class="border rounded-lg px-3 py-2 text-xs"
                 title="Code utilisé dans la numérotation des documents : CODE_ADMIN - CODE_ENTITE - 00001 - 2026">
          <select name="admin_type" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Type d'administration *</option>
            <?php $__currentLoopData = ['nationale'=>'Administration nationale','regionale'=>'Régionale','departementale'=>'Départementale','communale'=>'Communale','etablissement'=>'Établissement public']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('admin_type', $emMeta['adminType'] ?? '') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <select name="sector" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Secteur d'activité *</option>
            <?php $__currentLoopData = ['fiscalite_finance'=>'Fiscalité & Finances','protection_sociale'=>'Protection sociale','travail_emploi'=>'Travail & Emploi','urbanisme_logement'=>'Urbanisme & Logement','education_formation'=>'Éducation & Formation','sante'=>'Santé','justice'=>'Justice','securite'=>'Sécurité','environnement'=>'Environnement','autre'=>'Autre']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('sector', $emMeta['sector'] ?? '') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <textarea name="description" rows="2" placeholder="<?php echo e(__('personnel.ui.career.form_description_placeholder')); ?>"
                  class="w-full border rounded-lg px-3 py-2 text-xs"><?php echo e(old('description', $emMeta['description'] ?? '')); ?></textarea>
      </fieldset>

      
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Coordonnées & Contacts</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="email" name="contact_email" value="<?php echo e(old('contact_email', $emMeta['contactEmail'] ?? '')); ?>"
                 placeholder="Email de contact *" class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="email" name="tech_email" value="<?php echo e(old('tech_email', $emMeta['techEmail'] ?? '')); ?>"
                 placeholder="Email technique" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="contact_phone" value="<?php echo e(old('contact_phone', $emMeta['contactPhone'] ?? '')); ?>"
                 placeholder="Téléphone" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="referent_metier" value="<?php echo e(old('referent_metier', $emMeta['referentMetier'] ?? '')); ?>"
                 placeholder="Référent métier" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <textarea name="postal_address" rows="2" placeholder="Adresse postale"
                  class="w-full border rounded-lg px-3 py-2 text-xs"><?php echo e(old('postal_address', $emMeta['postalAddress'] ?? '')); ?></textarea>
      </fieldset>

      
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Logo de l'administration (affiché dans la barre latérale)</legend>
        <div class="grid grid-cols-1 md:grid-cols-[88px_1fr] gap-3 items-center">
          <div class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden" id="emit-logo-preview-wrap">
            <?php if($em && $em->logo): ?>
              <img src="<?php echo e(asset($em->logo)); ?>" alt="Logo actuel" class="w-full h-full object-contain" id="emit-logo-preview-img">
            <?php else: ?>
              <span class="text-gray-400 text-xs text-center px-1" id="emit-logo-preview-txt">Aperçu logo</span>
              <img src="" alt="Aperçu" class="w-full h-full object-contain hidden" id="emit-logo-preview-img">
            <?php endif; ?>
          </div>
          <div class="space-y-1">
            <input type="file" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                   onchange="emitLogoPreview(this)"
                   class="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-[11px] text-gray-500">Formats acceptés : PNG, JPG, SVG, WEBP (max 2 MB).</p>
          </div>
        </div>
      </fieldset>

      
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Configuration Technique</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="transmission_method" class="border rounded-lg px-3 py-2 text-xs" required>
            <?php $__currentLoopData = ['api'=>'API REST','sftp'=>'SFTP','email'=>'Email sécurisé','ler'=>'LER','portal'=>'Portail Web']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('transmission_method', $emMeta['transmissionMethod'] ?? 'api') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="text" name="endpoint_url" value="<?php echo e(old('endpoint_url', $emMeta['endpointUrl'] ?? '')); ?>"
                 placeholder="Endpoint URL" class="border rounded-lg px-3 py-2 text-xs">
          <select name="data_format" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['json'=>'JSON','xml'=>'XML','pdf'=>'PDF','multipart'=>'Multipart/Form-Data']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('data_format', $emMeta['dataFormat'] ?? 'json') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <select name="auth_method" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['api_key'=>'API Key','oauth2'=>'OAuth 2.0','mtls'=>'mTLS','basic'=>'Basic Auth']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('auth_method', $emMeta['authMethod'] ?? 'api_key') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="password" name="api_key" value="<?php echo e(old('api_key', $emMeta['apiKey'] ?? '')); ?>"
                 placeholder="Clé API / Token" class="border rounded-lg px-3 py-2 text-xs">
          <input type="number" name="timeout" min="5" max="300"
                 value="<?php echo e(old('timeout', $emMeta['timeout'] ?? 30)); ?>"
                 placeholder="Timeout (s)" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <div class="flex flex-wrap gap-4 text-xs text-gray-700">
          <label class="flex items-center gap-2">
            <input type="hidden" name="require_tls" value="0">
            <input type="checkbox" name="require_tls" value="1"
                   <?php echo e(old('require_tls', $emMeta['requireTls'] ?? true) ? 'checked' : ''); ?>> TLS 1.3 requis
          </label>
          <label class="flex items-center gap-2">
            <input type="hidden" name="enable_retry" value="0">
            <input type="checkbox" name="enable_retry" value="1"
                   <?php echo e(old('enable_retry', $emMeta['enableRetry'] ?? true) ? 'checked' : ''); ?>> Activer retries
          </label>
        </div>
      </fieldset>

      
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Documents</legend>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
          <?php $__currentLoopData = ['pdf','docx','xml','zip']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dtype): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2">
            <input type="checkbox" name="doc_types[]" value="<?php echo e($dtype); ?>"
                   <?php echo e(in_array($dtype, old('doc_types', $emMeta['docTypes'] ?? ['pdf'])) ? 'checked' : ''); ?>>
            <?php echo e(strtoupper($dtype)); ?>

          </label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="default_workflow" class="border rounded-lg px-3 py-2 text-xs">
            <option value="">Workflow par défaut</option>
            <?php $__currentLoopData = ['simple'=>'Validation simple (2 étapes)','standard'=>'Circuit standard (4 étapes)','urgent'=>'Circuit urgent (1 étape)']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('default_workflow', $emMeta['defaultWorkflow'] ?? '') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="text" name="dossier_prefix" value="<?php echo e(old('dossier_prefix', $emMeta['dossierPrefix'] ?? '')); ?>"
                 placeholder="Préfixe numéro dossier" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <label class="flex items-center gap-2 text-xs text-gray-700">
          <input type="hidden" name="auto_convert_pdf" value="0">
          <input type="checkbox" name="auto_convert_pdf" value="1"
                 <?php echo e(old('auto_convert_pdf', $emMeta['autoConvertPdf'] ?? true) ? 'checked' : ''); ?>>
          Convertir automatiquement en PDF
        </label>
        <textarea name="required_metadata" rows="2"
                  placeholder="Métadonnées obligatoires (JSON)"
                  class="w-full border rounded-lg px-3 py-2 text-xs font-mono"><?php echo e(old('required_metadata', $emMeta['requiredMetadata'] ?? '')); ?></textarea>
      </fieldset>

      
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Sécurité & Opérationnel</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="signature_level" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['simple'=>'Signature Simple','avancee'=>'Signature Avancée','qualifiee'=>'Signature Qualifiée (eIDAS)']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('signature_level', $emMeta['signatureLevel'] ?? 'qualifiee') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="number" name="log_retention" min="30" max="2555"
                 value="<?php echo e(old('log_retention', $emMeta['logRetention'] ?? 365)); ?>"
                 placeholder="Durée conservation logs (jours)" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="business_hours" value="<?php echo e(old('business_hours', $emMeta['businessHours'] ?? '')); ?>"
                 placeholder="Horaires de traitement" class="border rounded-lg px-3 py-2 text-xs">
          <select name="sla_response" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['immediat'=>'Immédiat','24h'=>'24 heures','48h'=>'48 heures','5j'=>'5 jours ouvrés']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('sla_response', $emMeta['slaResponse'] ?? '24h') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <select name="timezone" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['Europe/Paris'=>'Europe/Paris','UTC'=>'UTC','America/New_York'=>'America/New_York']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('timezone', $emMeta['timezone'] ?? 'Europe/Paris') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <select name="duplicate_handling" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['reject'=>'Rejeter le nouvel envoi','update'=>'Mettre à jour le dossier existant','version'=>'Créer une nouvelle version']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('duplicate_handling', $emMeta['duplicateHandling'] ?? 'update') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-700">
          <label class="flex items-center gap-2">
            <input type="hidden" name="gdpr_compliant" value="0">
            <input type="checkbox" name="gdpr_compliant" value="1"
                   <?php echo e(old('gdpr_compliant', $emMeta['gdprCompliant'] ?? true) ? 'checked' : ''); ?>> Conformité RGPD
          </label>
          <label class="flex items-center gap-2">
            <input type="hidden" name="enable_audit" value="0">
            <input type="checkbox" name="enable_audit" value="1"
                   <?php echo e(old('enable_audit', $emMeta['enableAudit'] ?? true) ? 'checked' : ''); ?>> Activer Audit Trail
          </label>
          <label class="flex items-center gap-2">
            <input type="hidden" name="file_encryption" value="0">
            <input type="checkbox" name="file_encryption" value="1"
                   <?php echo e(old('file_encryption', $emMeta['fileEncryption'] ?? false) ? 'checked' : ''); ?>> Chiffrement fichiers au repos
          </label>
        </div>
        <textarea name="ip_whitelist" rows="2" placeholder="IPs autorisées"
                  class="w-full border rounded-lg px-3 py-2 text-xs font-mono"><?php echo e(old('ip_whitelist', $emMeta['ipWhitelist'] ?? '')); ?></textarea>
      </fieldset>

      
      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="px-2 text-xs font-semibold text-gray-700">Métadonnées & Tracking</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="text" name="external_ref_field" value="<?php echo e(old('external_ref_field', $emMeta['externalRefField'] ?? '')); ?>"
                 placeholder="Champ référence externe" class="border rounded-lg px-3 py-2 text-xs">
          <input type="url" name="tracking_url" value="<?php echo e(old('tracking_url', $emMeta['trackingUrl'] ?? '')); ?>"
                 placeholder="URL de suivi public" class="border rounded-lg px-3 py-2 text-xs">
          <input type="url" name="webhook_url" value="<?php echo e(old('webhook_url', $emMeta['webhookUrl'] ?? '')); ?>"
                 placeholder="Webhook URL" class="border rounded-lg px-3 py-2 text-xs">
          <input type="password" name="webhook_secret" value="<?php echo e(old('webhook_secret', $emMeta['webhookSecret'] ?? '')); ?>"
                 placeholder="Webhook secret" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <input type="text" name="tags" value="<?php echo e(old('tags', $emMeta['tags'] ?? '')); ?>"
               placeholder="Tags / Catégories" class="w-full border rounded-lg px-3 py-2 text-xs">
        <div class="flex items-center gap-3 pt-1">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" id="emit_is_active"
                 <?php echo e(old('is_active', $em->is_active ?? true) ? 'checked' : ''); ?>

                 class="w-4 h-4 text-violet-600 border-gray-300 rounded">
          <label for="emit_is_active" class="text-xs font-medium text-gray-700">Administration active</label>
        </div>
      </fieldset>

      <div class="flex gap-2">
        <a href="<?php echo e(route('admin.index', ['tab' => 'emitters'])); ?>"
           class="flex-1 border border-gray-300 text-gray-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-gray-50 text-center">
          Liste des administrations émettrices
        </a>
        <button type="submit" class="flex-1 bg-blue-600 text-white rounded-lg px-3 py-2 text-xs font-semibold">
          <?php echo e($em ? 'Mettre à jour l\'administration émettrice' : 'Enregistrer l\'administration émettrice'); ?>

        </button>
      </div>
    </form>

    
    <input type="text" id="emit-search" placeholder="Rechercher une administration émettrice…"
           oninput="emitSearch(this.value)"
           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
    <div class="space-y-2 max-h-96 overflow-auto mt-2" id="emit-list">
      <?php $__empty_1 = true; $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php $iMeta = $item->metadata ?? []; ?>
      <div data-search="<?php echo e(strtolower($item->name . ' ' . $item->code)); ?>"
           class="border rounded-lg p-3 <?php echo e($selEmitId === $item->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50'); ?>">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-800 truncate"><?php echo e($item->name); ?></p>
            <p class="text-[11px] text-gray-500">
              Code: <code class="bg-gray-100 px-1 rounded"><?php echo e($item->code); ?></code>
              <?php if(!empty($iMeta['adminType'])): ?> · <?php echo e($iMeta['adminType']); ?> <?php endif; ?>
              <?php if(!empty($iMeta['sector'])): ?> · <?php echo e($iMeta['sector']); ?> <?php endif; ?>
              <?php if(!empty($iMeta['contactEmail'])): ?> · <?php echo e($iMeta['contactEmail']); ?> <?php endif; ?>
            </p>
          </div>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 <?php echo e($item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'); ?>">
            <span class="w-1.5 h-1.5 rounded-full <?php echo e($item->is_active ? 'bg-green-500' : 'bg-gray-400'); ?>"></span>
            <?php echo e($item->is_active ? 'Actif' : 'Inactif'); ?>

          </span>
        </div>
        <div class="mt-2 flex gap-2">
          <a href="<?php echo e(route('admin.index', ['tab' => 'emitters', 'emit_action' => 'edit', 'selected_emitter' => $item->id])); ?>"
             class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px] hover:bg-gray-300">Modifier</a>
          <form method="POST" action="<?php echo e(route('admin.emitters.destroy', $item)); ?>"
                onsubmit="return confirm('Supprimer cette administration émettrice ?')" class="inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px] hover:bg-red-200">Supprimer</button>
          </form>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p class="text-center text-gray-400 text-xs py-8">Aucune administration émettrice enregistrée.</p>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>


<?php elseif($tab === 'recipients'): ?>
<?php
    $recipAction  = request('recip_action');
    $selRecipId   = request('selected_recipient', '');
    $editRecip    = ($recipAction === 'edit' && $selRecipId)
                       ? $recipients->firstWhere('id', $selRecipId)
                       : null;
    $re           = $editRecip;
    $reMeta       = $re ? ($re->metadata ?? []) : [];
    $reLogo       = $re ? ($re->logo ?: ($reMeta['logoPath'] ?? $reMeta['logo'] ?? null)) : null;
    $reChannel    = old('channel', $re->channel ?? 'api');
?>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-5">
    <h2 class="text-lg font-semibold text-gray-800">Formulaire d'enregistrement - Administration Destinataire</h2>
    <form method="POST"
          action="<?php echo e($re ? route('admin.recipients.update', $re) : route('admin.recipients.store')); ?>"
          enctype="multipart/form-data"
          class="space-y-5" id="form-recipient">
      <?php echo csrf_field(); ?>
      <?php if($re): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>

      
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">1. Informations générales</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="text" name="name" value="<?php echo e(old('name', $re->name ?? '')); ?>"
                 placeholder="Nom de l'administration" class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="text" name="code" value="<?php echo e(old('code', $re->code ?? '')); ?>"
                 placeholder="Code d'identification (SIRET/SIREN)" class="border rounded-lg px-3 py-2 text-xs" required>
          <select name="admin_type" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Type d'administration</option>
            <?php $__currentLoopData = ['nationale'=>'Administration Nationale','regionale'=>'Administration Régionale','departementale'=>'Administration Départementale','communale'=>'Administration Communale','etablissement'=>'Établissement Public','prive'=>'Secteur Privé','organisme'=>'Organisme Paritaire']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('admin_type', $reMeta['adminType'] ?? '') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <select name="sector" class="border rounded-lg px-3 py-2 text-xs" required>
            <option value="">Secteur de compétence</option>
            <?php $__currentLoopData = ['fiscalite'=>'Fiscalité & Finances','social'=>'Protection Sociale','travail'=>'Travail & Emploi','urbanisme'=>'Urbanisme & Logement','education'=>'Éducation & Formation','sante'=>'Santé','justice'=>'Justice','environnement'=>'Environnement','commerce'=>'Commerce & Industrie','banques'=>'Banques','securite'=>'Sécurité','administration'=>'Administration','agriculture'=>'Agriculture','autre'=>'Autre']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('sector', $reMeta['sector'] ?? '') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <textarea name="description" rows="3" placeholder="Description des missions"
                  class="w-full border rounded-lg px-3 py-2 text-xs"><?php echo e(old('description', $reMeta['description'] ?? '')); ?></textarea>
      </div>

      
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">2. Coordonnées & contacts</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="email" name="contact_email" value="<?php echo e(old('contact_email', $reMeta['contactEmail'] ?? '')); ?>"
                 placeholder="Email de réception principal" class="border rounded-lg px-3 py-2 text-xs" required>
          <input type="email" name="tech_email" value="<?php echo e(old('tech_email', $reMeta['techEmail'] ?? '')); ?>"
                 placeholder="Email technique" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="contact_phone" value="<?php echo e(old('contact_phone', $reMeta['contactPhone'] ?? '')); ?>"
                 placeholder="Téléphone" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="contact_fax" value="<?php echo e(old('contact_fax', $reMeta['contactFax'] ?? '')); ?>"
                 placeholder="Fax" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="referent_metier" value="<?php echo e(old('referent_metier', $reMeta['referentMetier'] ?? '')); ?>"
                 placeholder="Référent métier" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="referent_technique" value="<?php echo e(old('referent_technique', $reMeta['referentTechnique'] ?? '')); ?>"
                 placeholder="Référent technique" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <textarea name="postal_address" rows="2" placeholder="Adresse postale"
                  class="w-full border rounded-lg px-3 py-2 text-xs"><?php echo e(old('postal_address', $reMeta['postalAddress'] ?? '')); ?></textarea>
      </div>

      
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">Logo de l'administration</p>
        <div class="grid grid-cols-1 md:grid-cols-[88px_1fr] gap-3 items-center">
          <div class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden">
            <?php if($re && $reLogo): ?>
              <img src="<?php echo e(asset($reLogo)); ?>" alt="Logo actuel" class="w-full h-full object-contain" id="recip-logo-preview-img">
            <?php else: ?>
              <span class="text-gray-400 text-xs text-center px-1" id="recip-logo-preview-txt">Aperçu logo</span>
              <img src="" alt="Aperçu" class="w-full h-full object-contain hidden" id="recip-logo-preview-img">
            <?php endif; ?>
          </div>
          <div class="space-y-1">
            <input type="file" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                   onchange="recipLogoPreview(this)"
                   class="w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
            <p class="text-[11px] text-gray-500">Formats acceptés : PNG, JPG, SVG, WEBP (max 2 MB).</p>
          </div>
        </div>
      </div>

      
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">3. Méthode de réception</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <?php $__currentLoopData = ['api'=>'API REST','email'=>'Email sécurisé','ler'=>'LER','application'=>'Via l\'application']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ch=>$chl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="border rounded-lg p-3 text-xs cursor-pointer <?php echo e($reChannel === $ch ? 'border-blue-500 bg-blue-50' : 'border-gray-200'); ?>">
            <input type="radio" name="channel" value="<?php echo e($ch); ?>" class="mr-2"
                   <?php echo e($reChannel === $ch ? 'checked' : ''); ?>

                   onchange="recipChannelToggle(this.value)">
            <?php echo e($chl); ?>

          </label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        
        <div id="recip-fields-api" class="<?php echo e($reChannel !== 'api' ? 'hidden' : ''); ?> grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="url" name="api_endpoint_meta" value="<?php echo e(old('api_endpoint_meta', $reMeta['apiEndpoint'] ?? '')); ?>"
                 placeholder="Endpoint URL de réception" class="md:col-span-2 border rounded-lg px-3 py-2 text-xs">
          <select name="api_method" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['POST'=>'POST','PUT'=>'PUT']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('api_method', $reMeta['apiMethod'] ?? 'POST') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <select name="api_format" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['multipart'=>'Multipart/Form-Data','json'=>'JSON','xml'=>'XML']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('api_format', $reMeta['apiFormat'] ?? 'multipart') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <select name="api_auth" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['api_key'=>'API Key','oauth2'=>'OAuth 2.0','basic'=>'Basic Auth','mtls'=>'mTLS','none'=>'Aucune']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('api_auth', $reMeta['apiAuth'] ?? 'api_key') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="number" name="api_timeout" min="5" max="300"
                 value="<?php echo e(old('api_timeout', $reMeta['apiTimeout'] ?? 30)); ?>"
                 placeholder="Timeout (secondes)" class="border rounded-lg px-3 py-2 text-xs">
        </div>

        
        <div id="recip-fields-email" class="<?php echo e($reChannel !== 'email' ? 'hidden' : ''); ?> grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="email" name="email_address_meta" value="<?php echo e(old('email_address_meta', $reMeta['emailAddress'] ?? '')); ?>"
                 placeholder="Email de destination" class="border rounded-lg px-3 py-2 text-xs">
          <input type="text" name="email_subject" value="<?php echo e(old('email_subject', $reMeta['emailSubject'] ?? '')); ?>"
                 placeholder="Objet du mail" class="border rounded-lg px-3 py-2 text-xs">
          <textarea name="email_body" rows="3"
                    placeholder="Corps du mail"
                    class="md:col-span-2 border rounded-lg px-3 py-2 text-xs"><?php echo e(old('email_body', $reMeta['emailBody'] ?? '')); ?></textarea>
        </div>

        
        <div id="recip-fields-ler" class="<?php echo e($reChannel !== 'ler' ? 'hidden' : ''); ?> grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="ler_provider" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['laposte'=>'La Poste e-Recommandé','docusign'=>'DocuSign Envelope','yousign'=>'Yousign','ar24'=>'AR24']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('ler_provider', $reMeta['lerProvider'] ?? 'laposte') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="text" name="ler_account_id" value="<?php echo e(old('ler_account_id', $reMeta['lerAccountId'] ?? '')); ?>"
                 placeholder="ID de compte fournisseur" class="border rounded-lg px-3 py-2 text-xs">
        </div>

        
        <div id="recip-fields-application" class="<?php echo e($reChannel !== 'application' ? 'hidden' : ''); ?> rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700">
          Les documents seront reçus directement dans l'onglet Réception de l'application.
        </div>
      </div>

      
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">4. Documents acceptés</p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
          <?php $__currentLoopData = ['pdf','docx','xlsx','pptx','xml','zip']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dtype): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2">
            <input type="checkbox" name="doc_types[]" value="<?php echo e($dtype); ?>"
                   <?php echo e(in_array($dtype, old('doc_types', $reMeta['docTypes'] ?? ['pdf'])) ? 'checked' : ''); ?>>
            <?php echo e(strtoupper($dtype)); ?>

          </label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="number" name="max_file_size" min="1" max="500"
                 value="<?php echo e(old('max_file_size', $reMeta['maxFileSize'] ?? 50)); ?>"
                 placeholder="Taille max (MB)" class="border rounded-lg px-3 py-2 text-xs">
          <input type="number" name="max_files" min="1" max="100"
                 value="<?php echo e(old('max_files', $reMeta['maxFiles'] ?? 10)); ?>"
                 placeholder="Nombre max de fichiers" class="border rounded-lg px-3 py-2 text-xs">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-700">
          <?php $__currentLoopData = [['enable_retry','Activer retry',true],['enable_notification','Notifier l\'usager',true],['compress_files','Compresser fichiers',false],['encrypt_files','Chiffrer fichiers',false]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$fkey,$flabel,$fdefault]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="flex items-center gap-2">
            <input type="hidden" name="<?php echo e($fkey); ?>" value="0">
            <input type="checkbox" name="<?php echo e($fkey); ?>" value="1"
                   <?php echo e(old($fkey, $reMeta[lcfirst(str_replace('_',' ',$fkey))] ?? $fdefault) ? 'checked' : ''); ?>>
            <?php echo e($flabel); ?>

          </label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>

      
      <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <p class="text-sm font-semibold text-gray-800">5. Accusé de réception</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <select name="receipt_method" class="border rounded-lg px-3 py-2 text-xs">
            <?php $__currentLoopData = ['automatic'=>'Automatique via API','manual'=>'Manuel','email'=>'Par email automatique','none'=>'Aucun']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v=>$l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($v); ?>" <?php echo e(old('receipt_method', $reMeta['receiptMethod'] ?? 'automatic') === $v ? 'selected' : ''); ?>><?php echo e($l); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="number" name="receipt_timeout" min="1" max="168"
                 value="<?php echo e(old('receipt_timeout', $reMeta['receiptTimeout'] ?? 24)); ?>"
                 placeholder="Délai max (heures)" class="border rounded-lg px-3 py-2 text-xs">
          <input type="url" name="receipt_webhook_url" value="<?php echo e(old('receipt_webhook_url', $reMeta['receiptWebhookUrl'] ?? '')); ?>"
                 placeholder="URL webhook confirmation" class="md:col-span-2 border rounded-lg px-3 py-2 text-xs">
        </div>
        <div class="flex items-center gap-3">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" id="recip_is_active"
                 <?php echo e(old('is_active', $re->is_active ?? true) ? 'checked' : ''); ?>

                 class="w-4 h-4 text-pink-600 border-gray-300 rounded">
          <label for="recip_is_active" class="text-xs font-medium text-gray-700">Administration active</label>
        </div>
        <label class="flex items-center gap-2 text-xs text-gray-700">
          <input type="hidden" name="activate_immediately" value="0">
          <input type="checkbox" name="activate_immediately" value="1"
                 <?php echo e(old('activate_immediately', $reMeta['activateImmediately'] ?? true) ? 'checked' : ''); ?>>
          Activer immédiatement après création
        </label>
      </div>

      <div class="flex flex-col sm:flex-row gap-2">
        <a href="<?php echo e(route('admin.index', ['tab' => 'recipients'])); ?>"
           class="sm:w-auto w-full border border-gray-300 text-gray-700 rounded-lg px-3 py-2 text-xs font-semibold hover:bg-gray-50 text-center">
          Annuler
        </a>
        <button type="submit" class="w-full bg-blue-600 text-white rounded-lg px-3 py-2 text-xs font-semibold">
          <?php echo e($re ? 'Mettre à jour l\'administration destinataire' : 'Créer l\'administration destinataire'); ?>

        </button>
      </div>
    </form>
  </section>

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 max-h-[700px] overflow-auto space-y-3">
    <div class="flex items-center justify-between gap-3">
      <h3 class="text-base font-semibold text-gray-800">Administrations Destinataires enregistrées</h3>
      <span class="text-xs text-gray-400"><?php echo e($recipients->count()); ?></span>
    </div>
    <input type="text" id="recip-search" placeholder="Rechercher une administration destinataire..."
           oninput="recipSearch(this.value)"
           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="recip-list">
      <?php $__empty_1 = true; $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
        $iMeta = $item->metadata ?? [];
        $itemLogo = $item->logo ?: ($iMeta['logoPath'] ?? $iMeta['logo'] ?? null);
      ?>
      <div class="recip-card border border-gray-200 bg-gray-50 rounded-lg p-2.5
                  <?php echo e($selRecipId === $item->id ? 'border-blue-500 bg-blue-50' : ''); ?>"
           data-name="<?php echo e(strtolower($item->name)); ?>" data-sector="<?php echo e(strtolower($iMeta['sector'] ?? '')); ?>">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-md border border-gray-200 bg-white overflow-hidden flex items-center justify-center shrink-0">
            <?php if($itemLogo): ?>
              <img src="<?php echo e(asset($itemLogo)); ?>" alt="Logo <?php echo e($item->name); ?>" class="w-full h-full object-contain">
            <?php else: ?>
              <span class="text-[10px] font-semibold text-gray-400">LOGO</span>
            <?php endif; ?>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-gray-800 truncate"><?php echo e($item->name); ?></p>
            <p class="text-[11px] text-gray-500">Canal: <?php echo e(strtoupper($item->channel)); ?> · <?php echo e($iMeta['sector'] ?? 'Secteur non défini'); ?></p>
            <p class="text-[11px] text-gray-500">Contact: <?php echo e($item->email_address ?? ($iMeta['contactEmail'] ?? '-')); ?></p>
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold <?php echo e($item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'); ?>">
              <span class="w-1.5 h-1.5 rounded-full <?php echo e($item->is_active ? 'bg-green-500' : 'bg-gray-400'); ?>"></span>
              <?php echo e($item->is_active ? 'Actif' : 'Inactif'); ?>

            </span>
          </div>
        </div>
        <div class="mt-2 flex gap-2">
          <a href="<?php echo e(route('admin.index', ['tab' => 'recipients', 'recip_action' => 'edit', 'selected_recipient' => $item->id])); ?>"
             class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[11px] hover:bg-gray-300">Modifier</a>
          <form method="POST" action="<?php echo e(route('admin.recipients.destroy', $item)); ?>"
                onsubmit="return confirm('Supprimer cette administration destinataire ?')" class="inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button class="px-2 py-1 rounded bg-red-100 text-red-700 text-[11px] hover:bg-red-200">Supprimer</button>
          </form>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="md:col-span-2 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-xs text-gray-500">
        Aucune administration destinataire enregistrée.
      </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>


<?php elseif($tab === 'sub-entities'): ?>
<?php
    $subAction  = request('sub_action');
    $selSubId   = request('selected_sub');
    $editSub    = ($subAction === 'edit' && $selSubId) ? $subEntities->firstWhere('id', $selSubId) : null;
    $subScopeType = old('scope_type', $editSub?->scope_type ?? 'emitter');
    $subScopeId   = old('scope_id',   $editSub?->scope_id   ?? '');
?>

<div class="grid grid-cols-1 xl:grid-cols-[1.2fr_1fr] gap-5">

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
    <div class="space-y-1">
      <h2 class="text-base font-bold text-gray-800"><?php echo e($editSub ? 'Modifier la direction' : 'Nouvelle Direction'); ?></h2>
      <p class="text-xs text-gray-500">Créez les entités sous tutelle depuis ce sous-onglet dédié.</p>
    </div>

    <?php if(session('success') && request('tab') === 'sub-entities'): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

    </div>
    <?php endif; ?>

    <?php if($errors->any() && request('tab') === 'sub-entities'): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-1">
        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($err); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>
    <?php endif; ?>

    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="sub-scope-selectors">
      <select id="sub-scope-type" name="scope_type_preview"
        onchange="subScopeTypeChange(this.value)"
        class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
        <option value="emitter"   <?php echo e($subScopeType === 'emitter'   ? 'selected' : ''); ?>>Administration Émettrice</option>
        <option value="recipient" <?php echo e($subScopeType === 'recipient' ? 'selected' : ''); ?>>Administration destinataire</option>
      </select>
      <select id="sub-scope-id" name="scope_id_preview"
        onchange="subScopeIdChange(this.value)"
        class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
        <option value="">Sélectionner une administration</option>
        <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $em): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option class="opt-emitter" value="<?php echo e($em->id); ?>" <?php echo e($subScopeId === $em->id ? 'selected' : ''); ?>><?php echo e($em->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $re): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option class="opt-recipient" value="<?php echo e($re->id); ?>" <?php echo e($subScopeId === $re->id ? 'selected' : ''); ?>><?php echo e($re->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>

    
    <?php if($editSub): ?>
    <form method="POST" action="<?php echo e(route('admin.sub-entities.update', $editSub->id)); ?>" class="space-y-3">
      <?php echo method_field('PUT'); ?>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.sub-entities.store')); ?>" class="space-y-3">
    <?php endif; ?>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="scope_type" id="sub-scope-type-hidden" value="<?php echo e($subScopeType); ?>">
      <input type="hidden" name="scope_id"   id="sub-scope-id-hidden"   value="<?php echo e($subScopeId); ?>">

      <input type="text" name="name" value="<?php echo e(old('name', $editSub?->name)); ?>"
        placeholder="Nom de la direction" required
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="code" value="<?php echo e(old('code', $editSub?->code)); ?>"
          placeholder="Code (ex: DIR001)" required
          oninput="this.value = this.value.toUpperCase()"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">

        <select id="sub-parent-code" name="parent_code"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
          <option value="">Direction Parent (optionnelle)</option>
          <?php $__currentLoopData = $subEntities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $se): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(!$editSub || $se->id !== $editSub->id): ?>
            <option value="<?php echo e($se->code); ?>"
              data-scope-type="<?php echo e($se->scope_type); ?>"
              data-scope-id="<?php echo e($se->scope_id); ?>"
              <?php echo e(old('parent_code', $editSub?->parent_code) === $se->code ? 'selected' : ''); ?>>
              <?php echo e($se->code); ?> – <?php echo e($se->name); ?>

            </option>
            <?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>

      <select name="direction_type_id"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none" required>
        <option value="">Type de Direction</option>
        <?php $__currentLoopData = $directionTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($dt->id); ?>" <?php echo e(old('direction_type_id', $editSub?->direction_type_id) === $dt->id ? 'selected' : ''); ?>>
          <?php echo e($dt->name); ?>

        </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="manager_name" value="<?php echo e(old('manager_name', $editSub?->manager_name)); ?>"
          placeholder="Nom du Responsable"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
        <input type="email" name="manager_email" value="<?php echo e(old('manager_email', $editSub?->manager_email)); ?>"
          placeholder="Email Responsable"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none">
      </div>

      <textarea name="description" rows="3"
        placeholder="Description (optionnelle)"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-violet-300 focus:border-violet-400 outline-none"><?php echo e(old('description', $editSub?->description)); ?></textarea>

      <div class="grid grid-cols-2 gap-3 pt-2">
        <button type="submit"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gradient-to-r from-blue-500 to-violet-600 font-semibold hover:opacity-90 transition">
          <i class="fas <?php echo e($editSub ? 'fa-save' : 'fa-plus'); ?> mr-1"></i>
          <?php echo e($editSub ? 'Mettre à jour' : 'Créer'); ?>

        </button>
        <a href="<?php echo e(route('admin.index', ['tab' => 'sub-entities'])); ?>"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gray-500 font-semibold hover:bg-gray-600 transition text-center">
          Annuler
        </a>
      </div>
    </form>
  </section>

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3 max-h-[780px] overflow-auto">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-800">Directions enregistrées</h3>
      <span class="text-xs text-gray-400" id="sub-count"><?php echo e($subEntities->count()); ?> direction<?php echo e($subEntities->count() > 1 ? 's' : ''); ?></span>
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
          <?php $__empty_1 = true; $__currentLoopData = $subEntities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $se): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="sub-row <?php echo e($editSub && $editSub->id === $se->id ? 'bg-blue-50' : ''); ?>"
              data-search="<?php echo e(strtolower($se->name . ' ' . $se->code . ' ' . ($se->administration_code ?? '') . ' ' . ($se->parent_code ?? '') . ' ' . ($se->manager_name ?? '') . ' ' . ($se->manager_email ?? ''))); ?>">
            <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($se->name); ?></td>
            <td class="px-4 py-3 text-gray-600"><?php echo e($se->code); ?></td>
            <td class="px-4 py-3 text-gray-600"><?php echo e($se->administration_code ?? '—'); ?></td>
            <td class="px-4 py-3 text-gray-600"><?php echo e($se->parent_code ?? '—'); ?></td>
            <td class="px-4 py-3 text-gray-600">
              <?php if($se->manager_name || $se->manager_email): ?>
                <?php echo e($se->manager_name ?? '—'); ?><br><span class="text-gray-400"><?php echo e($se->manager_email ?? ''); ?></span>
              <?php else: ?> —
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <a href="<?php echo e(route('admin.index', ['tab' => 'sub-entities', 'sub_action' => 'edit', 'selected_sub' => $se->id])); ?>"
                   class="p-1 rounded text-blue-600 hover:bg-blue-50" title="Modifier">
                  <i class="fas fa-pen w-4 h-4 text-xs"></i>
                </a>
                <form method="POST" action="<?php echo e(route('admin.sub-entities.destroy', $se->id)); ?>"
                  onsubmit="return confirm('Supprimer cette direction ?')">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="submit" class="p-1 rounded text-red-600 hover:bg-red-50" title="Supprimer">
                    <i class="fas fa-trash w-4 h-4 text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr id="sub-empty-row">
            <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">
              Aucune direction enregistrée. Créez votre première entité.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>


<?php elseif($tab === 'requested-acts'): ?>
<?php
    $actAction  = request('act_action');
    $selActId   = request('selected_act');
    $editAct    = ($actAction === 'edit' && $selActId) ? $requestedActs->firstWhere('id', $selActId) : null;
    $actAdminId = old('administration_id', $editAct?->administration_id ?? '');
    $actDirCode = old('direction_code',    $editAct?->direction_code    ?? '');
    $actAutoEnabled = old('auto_generate_enabled', $editAct?->auto_generate_enabled ? '1' : '0') === '1';
    $actAutoTemplateId = old('auto_template_id', $editAct?->auto_template_id ?? '');
    $actUniqueKeyField = old('unique_key_field', $editAct?->unique_key_field ?? '');
?>

<div class="grid grid-cols-1 xl:grid-cols-[1fr_1.1fr] gap-5">

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
    <div class="space-y-1">
      <h2 class="text-base font-bold text-gray-800"><?php echo e($editAct ? "Modifier l'acte" : 'Acte demande'); ?></h2>
      <p class="text-xs text-gray-500">Creez un acte demande avec l'administration, la direction, les pieces a fournir et les champs usager.</p>
    </div>

    <?php if(session('success') && request('tab') === 'requested-acts'): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

    </div>
    <?php endif; ?>
    <?php if($errors->any() && request('tab') === 'requested-acts'): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-1"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
    </div>
    <?php endif; ?>

    <?php if($editAct): ?>
    <form method="POST" action="<?php echo e(route('admin.requested-acts.update', $editAct->id)); ?>" class="space-y-4" id="act-form">
      <?php echo method_field('PUT'); ?>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.requested-acts.store')); ?>" class="space-y-4" id="act-form">
    <?php endif; ?>
      <?php echo csrf_field(); ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <?php if(isset($adminScope) && $adminScope && $adminScope['type'] === 'emitter'): ?>
        <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
        <div class="border border-gray-200 rounded-xl px-4 py-3 text-sm bg-gray-50 text-gray-700 flex items-center gap-2">
            <i class="fas fa-building text-gray-400"></i>
            <?php echo e($emitters->first()?->name ?? '--'); ?>

        </div>
        <?php else: ?>
        <select name="administration_id" id="act-admin-select" required
          onchange="actFilterDirections(this.value)"
          class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
          <option value="">Administration</option>
          <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $em): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($em->id); ?>" <?php echo e($actAdminId === $em->id ? 'selected' : ''); ?>><?php echo e($em->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php endif; ?>
        <select name="direction_code" id="act-dir-select"
          class="border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
          <option value="">Direction</option>
          <?php $__currentLoopData = $subEntities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $se): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option class="opt-dir" data-admin="<?php echo e($se->scope_id); ?>" value="<?php echo e($se->code); ?>" <?php echo e($actDirCode === $se->code ? 'selected' : ''); ?>>
            <?php echo e($se->code); ?> -- <?php echo e($se->name); ?>

          </option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>

      <input type="text" name="document_name" value="<?php echo e(old('document_name', $editAct?->document_name)); ?>"
        placeholder="Nom du document" required
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">

      <div class="rounded-xl border border-amber-200 bg-amber-50/40 p-4 space-y-3">
        <div class="flex items-start gap-3">
          <input type="hidden" name="auto_generate_enabled" value="0">
          <input type="checkbox" id="act-auto-generate" name="auto_generate_enabled" value="1" <?php echo e($actAutoEnabled ? 'checked' : ''); ?>

            onchange="toggleActAutoGenerateFields()"
            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-400">
          <div>
            <label for="act-auto-generate" class="text-sm font-semibold text-gray-800">Auto-génération à partir de la 2e demande publique</label>
            <p class="text-xs text-gray-600 mt-0.5">Un utilisateur partagé sur le template pourra valider, et la première validation clôture l'attente.</p>
          </div>
        </div>

        <div id="act-auto-fields" class="grid grid-cols-1 md:grid-cols-2 gap-3 <?php echo e($actAutoEnabled ? '' : 'hidden'); ?>">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Template de génération</label>
            <select name="auto_template_id" id="act-auto-template"
              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
              <option value="">Sélectionner un template</option>
              <?php $__currentLoopData = $requestedActTemplates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tplOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($tplOption->id); ?>" <?php echo e((string) $actAutoTemplateId === (string) $tplOption->id ? 'selected' : ''); ?>><?php echo e($tplOption->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Champ clé unique</label>
            <input type="text" name="unique_key_field" id="act-unique-key-field"
              value="<?php echo e($actUniqueKeyField); ?>"
              placeholder="Ex: numero_cni ou applicant_email"
              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
            <p class="text-[11px] text-gray-500 mt-1">Utilisez un champ usager (slug) ou un champ de base: applicant_email, applicant_phone.</p>
          </div>
        </div>
      </div>

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
          value="<?php echo e(old('required_documents', $editAct ? json_encode($editAct->required_documents ?? []) : '[]')); ?>">
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
            <option value="list">Liste</option>
          </select>
          <button type="button" onclick="actAddField()"
            class="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition">Ajouter</button>
        </div>
        <div id="act-field-list-options" class="hidden space-y-2">
          <label class="text-xs font-semibold text-gray-700">Elements de la liste</label>
          <textarea id="act-field-options" rows="3"
            placeholder="Saisir un élément par ligne ou séparé par des virgules"
            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none"></textarea>
        </div>
        <div id="act-fields-tags" class="flex flex-wrap gap-2 min-h-[28px]"></div>
        <input type="hidden" name="applicant_fields" id="act-fields-json"
          value="<?php echo e(old('applicant_fields', $editAct ? json_encode($editAct->applicant_fields ?? []) : '[]')); ?>">
      </div>

      <div class="flex gap-2 pt-1">
        <button type="submit"
          class="flex-1 rounded-xl px-4 py-3 text-sm text-white bg-amber-500 hover:bg-amber-600 font-semibold transition">
          <i class="fas <?php echo e($editAct ? 'fa-save' : 'fa-plus'); ?> mr-1"></i>
          <?php echo e($editAct ? 'Enregistrer les modifications' : "Enregistrer l'acte demande"); ?>

        </button>
        <?php if($editAct): ?>
        <a href="<?php echo e(route('admin.index', ['tab' => 'requested-acts'])); ?>"
          class="rounded-xl px-4 py-3 text-sm font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Annuler</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3 max-h-[780px] overflow-auto">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-800">Actes demandes</h3>
      <span class="text-xs text-gray-400" id="act-count"><?php echo e($requestedActs->count()); ?> element<?php echo e($requestedActs->count() > 1 ? 's' : ''); ?></span>
    </div>

    <input type="text" id="act-search" oninput="actSearch(this.value)"
      placeholder="Rechercher un acte (nom, administration, direction)..."
      class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">

    <?php if($requestedActs->isEmpty()): ?>
    <div class="rounded-xl border border-dashed border-gray-200 p-6 text-xs text-gray-500 text-center">
      Aucun acte demande enregistre pour le moment.
    </div>
    <?php else: ?>
    <div class="space-y-3" id="act-cards">
      <?php $__currentLoopData = $requestedActs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $act): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $actSearch = strtolower($act->document_name . ' ' . ($act->administration?->name ?? '') . ' ' . ($act->direction_code ?? '') . ' ' . implode(' ', $act->required_documents ?? []) . ' ' . collect($act->applicant_fields ?? [])->pluck('label')->implode(' '));
      ?>
      <div class="act-card rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2 <?php echo e($editAct && $editAct->id === $act->id ? 'border-amber-400 bg-amber-50' : ''); ?>"
        data-search="<?php echo e($actSearch); ?>">
        <p class="text-sm font-semibold text-gray-800"><?php echo e($act->document_name); ?></p>
        <p class="text-xs text-gray-600">Administration : <?php echo e($act->administration?->name ?? '&mdash;'); ?></p>
        <p class="text-xs text-gray-600">Direction : <?php echo e($act->direction_code ?? '&mdash;'); ?></p>
        <?php if($act->auto_generate_enabled): ?>
        <p class="text-xs text-amber-700">Auto-génération active · Template: <?php echo e($act->autoTemplate?->name ?? '—'); ?> · Clé: <?php echo e($act->unique_key_field ?? '—'); ?></p>
        <?php endif; ?>
        <p class="text-xs text-gray-400">Cree le <?php echo e($act->created_at->format('d/m/Y H:i')); ?></p>
        <?php if(!empty($act->required_documents)): ?>
        <div class="pt-1">
          <p class="text-xs font-semibold text-gray-700 mb-1">Documents a fournir :</p>
          <ul class="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
            <?php $__currentLoopData = $act->required_documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($doc); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if(!empty($act->applicant_fields)): ?>
        <div class="pt-1">
          <p class="text-xs font-semibold text-gray-700 mb-1">Champs usager :</p>
          <ul class="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
            <?php $__currentLoopData = $act->applicant_fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($f['label'] ?? ''); ?> (<?php echo e($f['inputType'] ?? ''); ?>)</li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
        </div>
        <?php endif; ?>
        <div class="pt-2 flex gap-2">
          <a href="<?php echo e(route('admin.index', ['tab' => 'requested-acts', 'act_action' => 'edit', 'selected_act' => $act->id])); ?>"
            class="text-xs px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200 transition">
            <i class="fas fa-pen mr-1 text-[10px]"></i> Modifier
          </a>
          <a href="<?php echo e(route('public.act-requests.by-admin', $act->administration_id)); ?>"
             target="_blank"
             class="text-[11px] px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 transition">
            <i class="fas fa-external-link-alt mr-1 text-[9px]"></i> Lien grand public
          </a>
          <form method="POST" action="<?php echo e(route('admin.requested-acts.destroy', $act->id)); ?>"
            onsubmit="return confirm('Supprimer cet acte ?')">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
              <i class="fas fa-trash mr-1 text-[10px]"></i> Supprimer
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>
  </section>
</div>

<?php $__env->startPush('scripts'); ?>
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

function actToggleFieldOptions() {
    var select = document.getElementById('act-field-type');
    var wrapper = document.getElementById('act-field-list-options');
    var textarea = document.getElementById('act-field-options');
    if (!select || !wrapper || !textarea) return;
    var isList = select.value === 'list';
    wrapper.classList.toggle('hidden', !isList);
    if (isList) {
        textarea.setAttribute('required', 'required');
    } else {
        textarea.removeAttribute('required');
    }
}
function actRenderFields() {
    var c = document.getElementById('act-fields-tags'); c.innerHTML = '';
    actFields.forEach(function(f, i) {
        var label = f.label || '';
        var type = f.inputType || 'text';
        var meta = '';
        if (type === 'list' && Array.isArray(f.options) && f.options.length) {
            meta = ' : ' + f.options.slice(0, 3).join(', ');
            if (f.options.length > 3) meta += '...';
        }
        var s = document.createElement('span');
        s.className = 'inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs text-indigo-700';
        s.innerHTML = label+' ('+type+meta+') <button type="button" onclick="actRemoveField('+i+')" class="text-indigo-600 hover:text-indigo-800 font-bold">&times;</button>';
        c.appendChild(s);
    });
    document.getElementById('act-fields-json').value = JSON.stringify(actFields);
}
function actAddField() {
    var label = document.getElementById('act-field-label').value.trim();
    var type  = document.getElementById('act-field-type').value;
    if (!label) return;
    if (type === 'list') {
        var listInput = document.getElementById('act-field-options');
        var options = [];
        if (listInput) {
            options = listInput.value.split(/\r?\n|,/) 
                .map(function(v){ return v.trim(); })
                .filter(function(v){ return v !== ''; });
        }
        if (!options.length) {
            alert('Saisissez au moins une valeur pour la liste.');
            return;
        }
        actFields.push({label:label, inputType:type, options:options});
        listInput.value = '';
    } else {
        actFields.push({label:label, inputType:type});
    }
    document.getElementById('act-field-label').value = '';
    actRenderFields();
    actToggleFieldOptions();
}
function actRemoveField(i) { actFields.splice(i,1); actRenderFields(); }

function actFilterDirections(adminId) {
    var sel = document.getElementById('act-dir-select');
    sel.querySelectorAll('option.opt-dir').forEach(function(opt) {
        opt.style.display = (!adminId || opt.dataset.admin === adminId) ? '' : 'none';
    });
    if (sel.value && sel.selectedOptions[0] && sel.selectedOptions[0].dataset.admin !== adminId) sel.value = '';
}

function toggleActAutoGenerateFields() {
  var checkbox = document.getElementById('act-auto-generate');
  var wrapper = document.getElementById('act-auto-fields');
  if (!checkbox || !wrapper) return;
  wrapper.classList.toggle('hidden', !checkbox.checked);
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
    var actTypeSelect = document.getElementById('act-field-type');
    if (actTypeSelect) {
        actTypeSelect.addEventListener('change', actToggleFieldOptions);
    }
    try { actDocs   = JSON.parse(document.getElementById('act-docs-json').value   || '[]'); } catch(e){}
    try { actFields = JSON.parse(document.getElementById('act-fields-json').value || '[]'); } catch(e){}
    actRenderDocs(); actRenderFields(); actToggleFieldOptions();
    var a = document.getElementById('act-admin-select');
    if (a && a.value) actFilterDirections(a.value);
    toggleActAutoGenerateFields();
});
</script>
<?php $__env->stopPush(); ?>


<?php elseif($tab === 'civil-status-types'): ?>
<?php
    $cstAction  = request('cst_action');
    $selCstId   = request('selected_cst');
    $editCst    = ($cstAction === 'edit' && $selCstId) ? $civilStatusRecordTypes->firstWhere('id', $selCstId) : null;
?>

<div class="grid grid-cols-1 xl:grid-cols-[1fr_1.1fr] gap-5">

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
    <div class="space-y-1">
      <h2 class="text-base font-bold text-gray-800"><?php echo e($editCst ? 'Modifier le type de dossier' : 'Nouveau type de dossier État civil'); ?></h2>
      <p class="text-xs text-gray-500">Paramétrez les types de dossiers de la base État civil (ex: Naissance, Mariage, Décès), les pièces à fournir et les champs de saisie du dossier.</p>
    </div>

    <?php if(session('success') && request('tab') === 'civil-status-types'): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

    </div>
    <?php endif; ?>
    <?php if($errors->any() && request('tab') === 'civil-status-types'): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-1"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
    </div>
    <?php endif; ?>

    <?php if($editCst): ?>
    <form method="POST" action="<?php echo e(route('admin.civil-status-types.update', $editCst->id)); ?>" class="space-y-4" id="cst-form">
      <?php echo method_field('PUT'); ?>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.civil-status-types.store')); ?>" class="space-y-4" id="cst-form">
    <?php endif; ?>
      <?php echo csrf_field(); ?>

      <?php if(!(isset($adminScope) && $adminScope && $adminScope['type'] === 'emitter')): ?>
      <div>
        <label class="block text-xs font-semibold text-gray-700 mb-1">Administration</label>
        <select name="administration_id" required
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none">
          <option value="">Sélectionner</option>
          <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $em): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($em->id); ?>" <?php echo e(old('administration_id', $editCst?->administration_id) === $em->id ? 'selected' : ''); ?>><?php echo e($em->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" name="code" value="<?php echo e(old('code', $editCst?->code)); ?>"
          placeholder="Code (ex: NAISSANCE)" required maxlength="50"
          class="border border-gray-300 rounded-xl px-4 py-3 text-sm uppercase placeholder:text-gray-400 focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none">
        <input type="text" name="name" value="<?php echo e(old('name', $editCst?->name)); ?>"
          placeholder="Libellé (ex: Naissance)" required
          class="border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none">
      </div>

      <textarea name="description" rows="2" placeholder="Description (optionnel)"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none"><?php echo e(old('description', $editCst?->description)); ?></textarea>

      <div class="rounded-xl border border-teal-200 bg-teal-50/40 p-4 space-y-3">
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Template partagé pour la génération de l'acte (optionnel)</label>
          <select name="auto_template_id"
            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none">
            <option value="">Aucun (à paramétrer plus tard)</option>
            <?php $__currentLoopData = $civilStatusRecordTypeTemplates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tplOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($tplOption->id); ?>" <?php echo e((string) old('auto_template_id', $editCst?->auto_template_id) === (string) $tplOption->id ? 'selected' : ''); ?>><?php echo e($tplOption->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <p class="text-[11px] text-gray-500 mt-1">Ce template sera utilisé par le workflow de génération d'acte (prochaine étape).</p>
        </div>
      </div>

      <div class="space-y-2">
        <label class="text-xs font-semibold text-gray-700">Pièces à fournir pour ouvrir un dossier</label>
        <div class="flex gap-2">
          <input type="text" id="cst-doc-input"
            onkeydown="if(event.key==='Enter'){event.preventDefault();cstAddDoc();}"
            placeholder="Ex: Certificat médical, Pièce d'identité..."
            class="flex-1 border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none">
          <button type="button" onclick="cstAddDoc()"
            class="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition">Ajouter</button>
        </div>
        <div id="cst-docs-tags" class="flex flex-wrap gap-2 min-h-[28px]"></div>
        <input type="hidden" name="required_documents" id="cst-docs-json"
          value="<?php echo e(old('required_documents', $editCst ? json_encode($editCst->required_documents ?? []) : '[]')); ?>">
      </div>

      <div class="space-y-2">
        <label class="text-xs font-semibold text-gray-700">Champs de saisie du dossier</label>
        <div class="grid grid-cols-1 md:grid-cols-[1fr_160px_auto] gap-2">
          <input type="text" id="cst-field-label"
            onkeydown="if(event.key==='Enter'){event.preventDefault();cstAddField();}"
            placeholder="Nom du champ (ex: Nom du père)"
            class="border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none">
          <select id="cst-field-type"
            class="border border-gray-300 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none">
            <option value="text">Texte</option>
            <option value="date">Date</option>
            <option value="number">Nombre</option>
            <option value="phone">Téléphone</option>
            <option value="email">Email</option>
            <option value="textarea">Texte long</option>
            <option value="list">Liste</option>
          </select>
          <button type="button" onclick="cstAddField()"
            class="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition">Ajouter</button>
        </div>
        <div id="cst-field-list-options" class="hidden space-y-2">
          <label class="text-xs font-semibold text-gray-700">Éléments de la liste</label>
          <textarea id="cst-field-options" rows="3"
            placeholder="Saisir un élément par ligne ou séparé par des virgules"
            class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none"></textarea>
        </div>
        <div id="cst-fields-tags" class="flex flex-wrap gap-2 min-h-[28px]"></div>
        <input type="hidden" name="fields_schema" id="cst-fields-json"
          value="<?php echo e(old('fields_schema', $editCst ? json_encode($editCst->fields_schema ?? []) : '[]')); ?>">
      </div>

      <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="cst-is-active" name="is_active" value="1" <?php echo e(old('is_active', $editCst?->is_active ?? true) ? 'checked' : ''); ?>

          class="h-4 w-4 rounded border-gray-300 text-teal-500 focus:ring-teal-400">
        <label for="cst-is-active" class="text-sm text-gray-700">Type actif</label>
      </div>

      <div class="flex gap-2 pt-1">
        <button type="submit"
          class="flex-1 rounded-xl px-4 py-3 text-sm text-white bg-teal-600 hover:bg-teal-700 font-semibold transition">
          <i class="fas <?php echo e($editCst ? 'fa-save' : 'fa-plus'); ?> mr-1"></i>
          <?php echo e($editCst ? 'Enregistrer les modifications' : 'Créer le type de dossier'); ?>

        </button>
        <?php if($editCst): ?>
        <a href="<?php echo e(route('admin.index', ['tab' => 'civil-status-types'])); ?>"
          class="rounded-xl px-4 py-3 text-sm font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Annuler</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3 max-h-[780px] overflow-auto">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-800">Types de dossiers État civil</h3>
      <span class="text-xs text-gray-400"><?php echo e($civilStatusRecordTypes->count()); ?> élément<?php echo e($civilStatusRecordTypes->count() > 1 ? 's' : ''); ?></span>
    </div>

    <?php if($civilStatusRecordTypes->isEmpty()): ?>
    <div class="rounded-xl border border-dashed border-gray-200 p-6 text-xs text-gray-500 text-center">
      Aucun type de dossier paramétré pour le moment. Créez par exemple "Naissance", "Mariage" et "Décès".
    </div>
    <?php else: ?>
    <div class="space-y-3">
      <?php $__currentLoopData = $civilStatusRecordTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2 <?php echo e($editCst && $editCst->id === $cst->id ? 'border-teal-400 bg-teal-50' : ''); ?>">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-gray-800"><?php echo e($cst->name); ?> <span class="text-xs font-mono text-gray-400">(<?php echo e($cst->code); ?>)</span></p>
          <?php if(!$cst->is_active): ?>
          <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-200 text-gray-600">Inactif</span>
          <?php endif; ?>
        </div>
        <?php if($cst->description): ?>
        <p class="text-xs text-gray-600"><?php echo e($cst->description); ?></p>
        <?php endif; ?>
        <?php if($cst->autoTemplate): ?>
        <p class="text-xs text-teal-700">Template lié : <?php echo e($cst->autoTemplate->name); ?></p>
        <?php endif; ?>
        <?php if(!empty($cst->required_documents)): ?>
        <div class="pt-1">
          <p class="text-xs font-semibold text-gray-700 mb-1">Pièces à fournir :</p>
          <ul class="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
            <?php $__currentLoopData = $cst->required_documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($doc); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if(!empty($cst->fields_schema)): ?>
        <div class="pt-1">
          <p class="text-xs font-semibold text-gray-700 mb-1">Champs du dossier :</p>
          <ul class="list-disc pl-5 text-xs text-gray-700 space-y-0.5">
            <?php $__currentLoopData = $cst->fields_schema; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($f['label'] ?? ''); ?> (<?php echo e($f['inputType'] ?? ''); ?>)</li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
        </div>
        <?php endif; ?>
        <p class="text-xs text-gray-400">Créé le <?php echo e($cst->created_at->format('d/m/Y H:i')); ?></p>
        <div class="pt-2 flex gap-2">
          <a href="<?php echo e(route('admin.index', ['tab' => 'civil-status-types', 'cst_action' => 'edit', 'selected_cst' => $cst->id])); ?>"
            class="text-xs px-3 py-1.5 rounded-lg bg-teal-100 text-teal-800 hover:bg-teal-200 transition">
            <i class="fas fa-pen mr-1 text-[10px]"></i> Modifier
          </a>
          <form method="POST" action="<?php echo e(route('admin.civil-status-types.destroy', $cst->id)); ?>"
            onsubmit="return confirm('Supprimer ce type de dossier ?')">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
              <i class="fas fa-trash mr-1 text-[10px]"></i> Supprimer
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>
  </section>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
var cstDocs = [], cstFields = [];
function cstRenderDocs() {
    var c = document.getElementById('cst-docs-tags'); c.innerHTML = '';
    cstDocs.forEach(function(doc, i) {
        var s = document.createElement('span');
        s.className = 'inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs text-blue-700';
        s.innerHTML = doc + ' <button type="button" onclick="cstRemoveDoc('+i+')" class="text-blue-600 hover:text-blue-800 font-bold">&times;</button>';
        c.appendChild(s);
    });
    document.getElementById('cst-docs-json').value = JSON.stringify(cstDocs);
}
function cstAddDoc() {
    var inp = document.getElementById('cst-doc-input'), val = inp.value.trim();
    if (!val) return; cstDocs.push(val); inp.value = ''; cstRenderDocs();
}
function cstRemoveDoc(i) { cstDocs.splice(i,1); cstRenderDocs(); }

function cstToggleFieldOptions() {
    var select = document.getElementById('cst-field-type');
    var wrapper = document.getElementById('cst-field-list-options');
    var textarea = document.getElementById('cst-field-options');
    if (!select || !wrapper || !textarea) return;
    var isList = select.value === 'list';
    wrapper.classList.toggle('hidden', !isList);
    if (isList) { textarea.setAttribute('required', 'required'); } else { textarea.removeAttribute('required'); }
}
function cstRenderFields() {
    var c = document.getElementById('cst-fields-tags'); c.innerHTML = '';
    cstFields.forEach(function(f, i) {
        var label = f.label || '';
        var type = f.inputType || 'text';
        var meta = '';
        if (type === 'list' && Array.isArray(f.options) && f.options.length) {
            meta = ' : ' + f.options.slice(0, 3).join(', ');
            if (f.options.length > 3) meta += '...';
        }
        var s = document.createElement('span');
        s.className = 'inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs text-indigo-700';
        s.innerHTML = label+' ('+type+meta+') <button type="button" onclick="cstRemoveField('+i+')" class="text-indigo-600 hover:text-indigo-800 font-bold">&times;</button>';
        c.appendChild(s);
    });
    document.getElementById('cst-fields-json').value = JSON.stringify(cstFields);
}
function cstAddField() {
    var label = document.getElementById('cst-field-label').value.trim();
    var type  = document.getElementById('cst-field-type').value;
    if (!label) return;
    if (type === 'list') {
        var listInput = document.getElementById('cst-field-options');
        var options = [];
        if (listInput) {
            options = listInput.value.split(/\r?\n|,/)
                .map(function(v){ return v.trim(); })
                .filter(function(v){ return v !== ''; });
        }
        if (!options.length) { alert('Saisissez au moins une valeur pour la liste.'); return; }
        cstFields.push({label:label, inputType:type, options:options});
        listInput.value = '';
    } else {
        cstFields.push({label:label, inputType:type});
    }
    document.getElementById('cst-field-label').value = '';
    cstRenderFields();
    cstToggleFieldOptions();
}
function cstRemoveField(i) { cstFields.splice(i,1); cstRenderFields(); }

document.addEventListener('DOMContentLoaded', function() {
    var cstTypeSelect = document.getElementById('cst-field-type');
    if (cstTypeSelect) { cstTypeSelect.addEventListener('change', cstToggleFieldOptions); }
    try { cstDocs   = JSON.parse(document.getElementById('cst-docs-json').value   || '[]'); } catch(e){}
    try { cstFields = JSON.parse(document.getElementById('cst-fields-json').value || '[]'); } catch(e){}
    cstRenderDocs(); cstRenderFields(); cstToggleFieldOptions();
});
</script>
<?php $__env->stopPush(); ?>

<?php elseif($tab === 'direction-types'): ?>
<?php
    $dtAction = request('dt_action');
    $selDtId  = request('selected_dt');
    $editDt   = ($dtAction === 'edit' && $selDtId) ? $directionTypes->firstWhere('id', $selDtId) : null;
?>

<div class="grid grid-cols-1 xl:grid-cols-[0.95fr_1.05fr] gap-5">

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
    <div class="space-y-1">
      <h2 class="text-base font-bold text-gray-800"><?php echo e($editDt ? 'Modifier le type' : 'Nouveau Type de Direction'); ?></h2>
      <p class="text-xs text-gray-500">Créez les types utilisés dans les formulaires des entités sous tutelle.</p>
    </div>

    <?php if(session('success') && request('tab') === 'direction-types'): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

    </div>
    <?php endif; ?>
    <?php if($errors->any() && request('tab') === 'direction-types'): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-1"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
    </div>
    <?php endif; ?>

    <?php if($editDt): ?>
    <form method="POST" action="<?php echo e(route('admin.direction-types.update', $editDt->id)); ?>" class="space-y-4">
      <?php echo method_field('PUT'); ?>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('admin.direction-types.store')); ?>" class="space-y-4">
    <?php endif; ?>
      <?php echo csrf_field(); ?>

      <input type="text" name="name" value="<?php echo e(old('name', $editDt?->name)); ?>"
        placeholder="Nom du type" required
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none">

      <textarea name="description" rows="4"
        placeholder="<?php echo e(__('personnel.ui.career.form_description_placeholder')); ?>"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none"><?php echo e(old('description', $editDt?->description)); ?></textarea>

      <div class="grid grid-cols-2 gap-3 pt-1">
        <button type="submit"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gradient-to-r from-blue-500 to-violet-600 font-semibold hover:opacity-90 transition">
          <i class="fas <?php echo e($editDt ? 'fa-save' : 'fa-plus'); ?> mr-1"></i>
          <?php echo e($editDt ? 'Modifier' : 'Créer'); ?>

        </button>
        <a href="<?php echo e(route('admin.index', ['tab' => 'direction-types'])); ?>"
          class="rounded-xl px-4 py-3 text-sm text-white bg-gray-500 font-semibold hover:bg-gray-600 transition text-center">
          Annuler
        </a>
      </div>
    </form>
  </section>

  
  <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="text-base font-semibold text-gray-800">Liste des types</h3>
      <span class="text-xs text-gray-400" id="dt-count"><?php echo e($directionTypes->count()); ?> type<?php echo e($directionTypes->count() > 1 ? 's' : ''); ?></span>
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
          <?php $__empty_1 = true; $__currentLoopData = $directionTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr data-search="<?php echo e(strtolower($dt->name . ' ' . ($dt->description ?? ''))); ?>"
              class="<?php echo e($editDt && $editDt->id === $dt->id ? 'bg-blue-50' : ''); ?>">
            <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($dt->name); ?></td>
            <td class="px-4 py-3 text-gray-600"><?php echo e($dt->description ?? '—'); ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <a href="<?php echo e(route('admin.index', ['tab' => 'direction-types', 'dt_action' => 'edit', 'selected_dt' => $dt->id])); ?>"
                   class="p-1 rounded text-blue-600 hover:bg-blue-50" title="Modifier">
                  <i class="fas fa-pen text-xs"></i>
                </a>
                <form method="POST" action="<?php echo e(route('admin.direction-types.destroy', $dt)); ?>"
                  onsubmit="return confirm('Supprimer ce type ?')">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="submit" class="p-1 rounded text-red-600 hover:bg-red-50" title="Supprimer">
                    <i class="fas fa-trash text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="3" class="px-4 py-8 text-center text-xs text-gray-500">Aucun type de direction enregistré.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>
<?php elseif($tab === 'routing'): ?>
<div class="flex items-center justify-between mb-5">
  <h2 class="text-lg font-bold text-gray-800">Règles de routage</h2>
    <button onclick="openModal('modal-routing-create')"
        class="px-4 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition flex items-center gap-2">
    <i class="fas fa-plus"></i> Nouvelle règle
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
              <th class="text-left px-5 py-3 font-semibold text-gray-600">Cible</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Condition</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Priorité</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Statut</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50" id="routing-tbody">
            <?php $__empty_1 = true; $__currentLoopData = $routingRules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr data-search="<?php echo e(strtolower(($rule->template?->name ?? '') . ' ' . ($rule->recipient?->name ?? '') . ' ' . ($rule->targetUser?->name ?? '') . ' ' . ($rule->condition_field ?? '') . ' ' . ($rule->condition_value ?? ''))); ?>"
                class="hover:bg-gray-50/50 transition">
                <td class="px-5 py-3.5 font-medium text-gray-800"><?php echo e($rule->template?->name ?? '—'); ?></td>
              <td class="px-5 py-3.5 text-gray-600">
                <?php if(($rule->target_type ?? 'recipient') === 'user'): ?>
                  <div class="font-medium text-gray-700"><?php echo e($rule->targetUser?->name ?? '—'); ?></div>
                  <div class="text-xs text-gray-500">Utilisateur</div>
                <?php else: ?>
                  <div class="font-medium text-gray-700"><?php echo e($rule->recipient?->name ?? '—'); ?></div>
                  <div class="text-xs text-gray-500">Administration destinataire</div>
                <?php endif; ?>
              </td>
                <td class="px-5 py-3.5 text-xs text-gray-500">
                    <?php if($rule->condition_field): ?>
                        <code class="bg-gray-100 px-1.5 py-0.5 rounded"><?php echo e($rule->condition_field); ?></code>
                        <?php echo e($rule->condition_operator); ?>

                        <code class="bg-gray-100 px-1.5 py-0.5 rounded"><?php echo e($rule->condition_value); ?></code>
                    <?php else: ?>
                        <span class="text-gray-400 italic">Toujours</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3.5 text-center font-bold text-gray-600"><?php echo e($rule->priority ?? 0); ?></td>
                <td class="px-5 py-3.5">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?php echo e($rule->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'); ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?php echo e($rule->is_active ? 'bg-green-500' : 'bg-gray-400'); ?>"></span>
                        <?php echo e($rule->is_active ? 'Active' : 'Inactive'); ?>

                    </span>
                </td>
                <td class="px-5 py-3.5 text-right">
                  <form method="POST" action="<?php echo e(route('admin.routing-rules.destroy', $rule)); ?>" onsubmit="return confirm('Supprimer cette règle ?')" class="inline">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <button class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg transition">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">Aucune règle de routage configurée.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if($routingRules->hasPages()): ?>
    <div class="px-5 py-4 border-t border-gray-100"><?php echo e($routingRules->appends(['tab'=>'routing'])->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->startPush('scripts'); ?>
<script>
function routingSearch(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#routing-tbody [data-search]').forEach(function(row) {
        row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
    });
}

function toggleRoutingTargetFields(targetType) {
  var recipientWrap = document.getElementById('routing-target-recipient-wrap');
  var userWrap = document.getElementById('routing-target-user-wrap');
  var recipientSelect = document.getElementById('routing-recipient-id');
  var userSelect = document.getElementById('routing-target-user-id');

  var isUser = (targetType === 'user');

  if (recipientWrap) recipientWrap.style.display = isUser ? 'none' : '';
  if (userWrap) userWrap.style.display = isUser ? '' : 'none';

  if (recipientSelect) {
    recipientSelect.required = !isUser;
    if (isUser) recipientSelect.value = '';
  }

  if (userSelect) {
    userSelect.required = isUser;
    if (!isUser) userSelect.value = '';
  }
}

document.addEventListener('DOMContentLoaded', function () {
  var targetTypeSelect = document.getElementById('routing-target-type');
  toggleRoutingTargetFields(targetTypeSelect ? targetTypeSelect.value : 'recipient');
});
</script>
<?php $__env->stopPush(); ?>
<div id="modal-routing-create" class="adm-modal">
    <div class="adm-modal-box">
        <button onclick="closeModal('modal-routing-create')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
    <h3 class="text-lg font-bold text-gray-800 mb-5">Nouvelle règle de routage</h3>
        <form method="POST" action="<?php echo e(route('admin.routing-rules.store')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Template <span class="text-red-500">*</span></label>
                <select name="template_id" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">« Sélectionner »</option>
                    <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($tpl->id); ?>"><?php echo e($tpl->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Type de cible <span class="text-red-500">*</span></label>
                  <select id="routing-target-type" name="target_type" onchange="toggleRoutingTargetFields(this.value)" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="recipient">Administration destinataire</option>
                    <option value="user">Utilisateur (même administration)</option>
                </select>
            </div>
                <div id="routing-target-recipient-wrap">
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Administration destinataire <span class="text-red-500">*</span></label>
                  <select id="routing-recipient-id" name="recipient_id" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">« Sélectionner »</option>
                    <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($recip->id); ?>"><?php echo e($recip->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                </div>
                <div id="routing-target-user-wrap" style="display:none;">
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Utilisateur (même administration) <span class="text-red-500">*</span></label>
                  <select id="routing-target-user-id" name="target_user_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    <option value="">« Sélectionner »</option>
                    <?php $__currentLoopData = $sameAdminRoutingUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $routingUser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($routingUser->id); ?>"><?php echo e($routingUser->name); ?> <?php if($routingUser->email): ?>(<?php echo e($routingUser->email); ?>)<?php endif; ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                  <p class="mt-1 text-xs text-gray-500">Seuls les utilisateurs affectés à la même administration peuvent être sélectionnés.</p>
                </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Champ</label>
                    <input type="text" name="condition_field" placeholder="status" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Opérateur</label>
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
              <label class="block text-sm font-semibold text-gray-700 mb-1">Priorité</label>
                <input type="number" name="priority" value="0" min="0" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div class="pt-2 flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modal-routing-create')" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Annuler</button>
              <button type="submit" class="px-5 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition">Créer</button>
            </div>
        </form>
    </div>
</div>


<?php elseif($tab === 'onlyoffice'): ?>
<?php
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
?>

<div class="max-w-2xl space-y-6">

  
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

  
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-1">Lecteur de documents</h3>
    <p class="text-sm text-gray-500 mb-4">Choisissez le lecteur à utiliser pour afficher et éditer les documents.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="oo-viewer-selector">
      
      <button type="button" onclick="selectOoViewer('onlyoffice')" id="oo-btn-onlyoffice"
        class="relative flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition cursor-pointer <?php echo e($ooViewer === 'onlyoffice' ? 'border-[#2453d6] bg-blue-50 shadow-md' : 'border-gray-200 bg-white hover:border-gray-300'); ?>">
        <span id="oo-check-onlyoffice" class="<?php echo e($ooViewer === 'onlyoffice' ? '' : 'hidden'); ?> absolute top-2 right-2 w-5 h-5 bg-[#2453d6] rounded-full flex items-center justify-center">
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
      
      <button type="button" onclick="selectOoViewer('native')" id="oo-btn-native"
        class="relative flex flex-col items-center gap-3 p-5 rounded-xl border-2 transition cursor-pointer <?php echo e($ooViewer === 'native' ? 'border-[#2453d6] bg-blue-50 shadow-md' : 'border-gray-200 bg-white hover:border-gray-300'); ?>">
        <span id="oo-check-native" class="<?php echo e($ooViewer === 'native' ? '' : 'hidden'); ?> absolute top-2 right-2 w-5 h-5 bg-[#2453d6] rounded-full flex items-center justify-center">
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

  
  <form method="POST" action="<?php echo e(route('admin.settings.save')); ?>" class="space-y-6">
    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <input type="hidden" name="tab" value="onlyoffice">
    <input type="hidden" name="onlyoffice_doc_viewer" id="oo-viewer-input" value="<?php echo e(old('onlyoffice_doc_viewer', $ooViewer)); ?>">

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
      <div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">Paramètres du serveur</h3>
        <p class="text-sm text-gray-500">
          L'emplacement du ONLYOFFICE Docs désigne l'adresse du serveur sur lequel est installé
          le service de document. Veuillez remplacer <code class="text-xs bg-gray-100 px-1 rounded">&lt;documentserver&gt;</code>
          avec l'adresse de votre serveur.
        </p>
      </div>

      <?php if(session('success') && request('tab') === 'onlyoffice'): ?>
      <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
        <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

      </div>
      <?php endif; ?>

      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Adresse du ONLYOFFICE Docs</label>
        <input type="url" name="onlyoffice_server_url"
          value="<?php echo e(old('onlyoffice_server_url', $ooUrl)); ?>"
          placeholder="https://<documentserver>/"
          class="w-full max-w-sm border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
      </div>

      
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
          value="<?php echo e(old('app_public_url', $appPublicUrl)); ?>"
          placeholder="https://votre-app.exemple.com"
          class="w-full max-w-sm border <?php echo e($appPublicUrl ? 'border-gray-300' : 'border-red-300 bg-red-50'); ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
        <?php if(!$appPublicUrl): ?>
        <p class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Non configurée : l'éditeur de templates ne peut pas charger les fichiers tant que ce champ est vide.</p>
        <?php endif; ?>
        <?php if($appPublicUrl): ?>
        <div class="mt-2 flex items-center gap-2">
            <button type="button" onclick="testOoBlankUrl()"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-50 border border-blue-200 text-blue-700 hover:bg-blue-100">
                <i class="fas fa-vial mr-1"></i> Tester l'accès
            </button>
            <span id="oo-url-test-result" class="text-xs"></span>
        </div>
        <?php endif; ?>
        <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
            <p class="font-semibold mb-1"><i class="fas fa-info-circle mr-1"></i>En développement local :</p>
            <p>Utilisez <a href="https://ngrok.com" target="_blank" class="underline font-semibold">ngrok</a> pour exposer votre serveur :</p>
            <code class="block mt-1 bg-amber-100 rounded px-2 py-1 font-mono text-xs">ngrok http 80</code>
            <p class="mt-1">Copiez l'URL <code>https://xxxx.ngrok-free.app</code> ci-dessus et enregistrez.</p>
        </div>
      </div>

      
      <div class="flex items-center gap-2">
        <input type="hidden" name="onlyoffice_disable_cert" value="0">
        <input id="oo-cert" type="checkbox" name="onlyoffice_disable_cert" value="1"
          <?php echo e($ooDisableCert ? 'checked' : ''); ?>

          class="h-4 w-4 rounded border-gray-300 text-[#2453d6]">
        <label for="oo-cert" class="text-sm text-gray-700">
          Désactiver la vérification du certificat <span class="text-gray-400 text-xs">(non sûr)</span>
        </label>
      </div>

      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Clé secrète <span class="text-gray-400 font-normal text-xs">(laisser vide pour désactiver)</span>
        </label>
        <div class="relative w-full max-w-sm">
          <input id="oo-secret-input" type="password" name="onlyoffice_secret"
            value="<?php echo e(old('onlyoffice_secret', $ooSecret)); ?>"
            placeholder="Saisir votre clé secrète"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm pr-9 focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
          <button type="button" onclick="toggleOoSecret()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <i id="oo-secret-eye" class="fas fa-eye text-sm"></i>
          </button>
        </div>
      </div>
    </div>

    
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
            <input type="number" name="qr_image_page" value="<?php echo e(old('qr_image_page', $qrPage)); ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">X</label>
            <input type="number" name="qr_image_x" value="<?php echo e(old('qr_image_x', $qrX)); ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">Y</label>
            <input type="number" name="qr_image_y" value="<?php echo e(old('qr_image_y', $qrY)); ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">Largeur</label>
            <input type="number" name="qr_image_width" value="<?php echo e(old('qr_image_width', $qrW)); ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
          <div>
            <label class="block text-[11px] text-gray-500 mb-1">Hauteur</label>
            <input type="number" name="qr_image_height" value="<?php echo e(old('qr_image_height', $qrH)); ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#2453d6]/30">
          </div>
        </div>

        
        <div class="mt-3">
          <p class="text-[11px] text-gray-400 mb-1">Aperçu position (proportionnel A4)</p>
          <div class="relative bg-white border border-gray-300 rounded" style="width:120px;height:170px;overflow:hidden;">
            <div id="qr-preview-box" class="absolute bg-[#2453d6]/20 border border-[#2453d6] rounded-sm text-[8px] text-[#2453d6] flex items-center justify-center font-bold" style="width:24px;height:14px;left:56px;top:116px;">QR</div>
          </div>
          <p class="text-[10px] text-gray-400 mt-1">A4&nbsp;: 595&times;842 pts</p>
        </div>
      </div>
    </div>

    
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
  var appUrl = '<?php echo e($appPublicUrl); ?>';
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


<?php elseif($tab === 'ai-integration'): ?>
<?php
    $geminiApiKey  = $settings['gemini_api_key']->value  ?? '';
    $geminiModel   = $settings['gemini_model']->value    ?? config('services.gemini.model', 'gemini-2.0-flash');
    $geminiTimeout = $settings['gemini_timeout']->value  ?? (string) config('services.gemini.timeout', 120);
    $geminiConfiguredViaEnv = $geminiApiKey === '' && (string) config('services.gemini.api_key', '') !== '';
?>

<div class="max-w-2xl space-y-6">

  
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <p class="text-xs font-bold uppercase tracking-widest text-violet-600 mb-1">INTELLIGENCE ARTIFICIELLE</p>
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Assistant IA pour les comptes rendus de réunion</h2>
    <p class="text-sm text-gray-500 mb-4">
      Configurez la clé API Google Gemini utilisée pour transcrire l'enregistrement audio d'une réunion
      et proposer automatiquement un compte rendu structuré. Le service est gratuit dans la limite du
      quota Google (clé personnelle à créer).
    </p>
    <div class="flex gap-4 text-sm font-semibold text-violet-600">
      <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noreferrer" class="hover:underline">Obtenir une clé API gratuite &#8599;</a>
    </div>
  </div>

  <?php if(session('success') && request('tab') === 'ai-integration'): ?>
  <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
    <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

  </div>
  <?php endif; ?>

  <?php if($geminiConfiguredViaEnv): ?>
  <div class="flex items-center gap-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl px-4 py-3 text-sm">
    <i class="fas fa-info-circle"></i> Une clé est actuellement définie via la variable d'environnement <code class="text-xs bg-blue-100 px-1 rounded">GEMINI_API_KEY</code> du serveur. Enregistrez une clé ici pour la gérer depuis cette interface (elle sera alors prioritaire).
  </div>
  <?php endif; ?>

  
  <form method="POST" action="<?php echo e(route('admin.settings.save')); ?>" class="space-y-6">
    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <input type="hidden" name="tab" value="ai-integration">

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
      <div>
        <h3 class="text-lg font-bold text-gray-900 mb-1">Paramètres Gemini</h3>
        <p class="text-sm text-gray-500">
          Ces paramètres remplacent la configuration du fichier <code class="text-xs bg-gray-100 px-1 rounded">.env</code> lorsqu'ils sont renseignés ici.
        </p>
      </div>

      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Clé API Gemini <span class="text-gray-400 font-normal text-xs">(laisser vide pour désactiver / conserver le .env)</span>
        </label>
        <div class="relative w-full max-w-sm">
          <input id="gemini-secret-input" type="password" name="gemini_api_key"
            value="<?php echo e(old('gemini_api_key', $geminiApiKey)); ?>"
            placeholder="Saisir votre clé API Gemini"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm pr-9 focus:outline-none focus:ring-2 focus:ring-violet-500">
          <button type="button" onclick="toggleGeminiSecret()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <i id="gemini-secret-eye" class="fas fa-eye text-sm"></i>
          </button>
        </div>
      </div>

      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
        <input type="text" name="gemini_model"
          value="<?php echo e(old('gemini_model', $geminiModel)); ?>"
          placeholder="gemini-2.0-flash"
          class="w-full max-w-sm border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
        <p class="text-xs text-gray-400 mt-1">Identifiant du modèle Gemini à utiliser pour la génération du compte rendu.</p>
      </div>

      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Délai d'attente (secondes)</label>
        <input type="number" min="10" max="600" name="gemini_timeout"
          value="<?php echo e(old('gemini_timeout', $geminiTimeout)); ?>"
          class="w-full max-w-[10rem] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
        <p class="text-xs text-gray-400 mt-1">Délai maximal accordé à l'appel à l'API avant abandon.</p>
      </div>
    </div>

    
    <div class="flex items-center gap-3">
      <button type="submit"
        class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
        <i class="fas fa-save"></i> Enregistrer
      </button>
    </div>
  </form>
</div>

<script>
function toggleGeminiSecret() {
  var inp = document.getElementById('gemini-secret-input');
  var eye = document.getElementById('gemini-secret-eye');
  if (inp.type === 'password') { inp.type = 'text';     eye.className = 'fas fa-eye-slash text-sm'; }
  else                         { inp.type = 'password'; eye.className = 'fas fa-eye text-sm'; }
}
</script>

<?php elseif($tab === 'users'): ?>
<?php
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
?>

<div class="space-y-4">

  
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center justify-between">
    <div>
      <h2 class="text-base font-semibold text-gray-800">Utilisateurs de l'application</h2>
      <p class="text-xs text-gray-500 mt-0.5"><?php echo e($filteredUsers->count()); ?> utilisateur<?php echo e($filteredUsers->count() !== 1 ? 's' : ''); ?></p>
    </div>
    <button type="button" onclick="openUserModal('create')"
      class="px-4 py-2 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-xs font-semibold rounded-lg transition">
      + Nouvel utilisateur
    </button>
  </div>

  <?php if(session('success') && request('tab') === 'users'): ?>
  <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
    <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

  </div>
  <?php endif; ?>

  
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-auto">
    <div class="p-4 border-b border-gray-100">
      <form method="GET" action="<?php echo e(route('admin.index')); ?>" class="flex items-center gap-2">
        <input type="hidden" name="tab" value="users">
        <input type="text" name="u_search" value="<?php echo e($usersSearch); ?>"
          placeholder="Rechercher un utilisateur (nom, email, rôle)..."
          class="w-full md:w-[420px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
        <?php if($usersSearch): ?>
        <a href="<?php echo e(route('admin.index', ['tab'=>'users'])); ?>" class="text-xs text-gray-400 hover:text-gray-600 whitespace-nowrap">✕ Effacer</a>
        <?php endif; ?>
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
          <th class="px-4 py-3 text-left">2FA</th>
          <th class="px-4 py-3 text-left">Date création</th>
          <th class="px-4 py-3 text-left">Date modification</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php $__empty_1 = true; $__currentLoopData = $filteredUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
          $parts   = preg_split('/\s+/', trim($u->full_name ?? $u->name ?? ''));
          $nom     = count($parts) > 1 ? end($parts) : ($u->name ?? '');
          $prenoms = count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) : '';
          $dir     = $dirAssignments->get($u->id);
        ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-800 font-medium"><?php echo e($nom ?: '-'); ?></td>
          <td class="px-4 py-3 text-gray-600"><?php echo e($prenoms ?: '-'); ?></td>
          <td class="px-4 py-3 text-gray-600">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
              <?php echo e($u->role === 'admin' ? 'bg-purple-100 text-purple-700' : ($u->role === 'signer' ? 'bg-blue-100 text-blue-700' : ($u->role === 'manager' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600'))); ?>">
              <?php echo e($u->role ?? '-'); ?>

            </span>
          </td>
          <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate" title="<?php echo e($dir?->direction_label ?? ''); ?>">
            <?php echo e($dir?->direction_label ?: '-'); ?>

          </td>
          <td class="px-4 py-3 text-gray-600"><?php echo e($u->email); ?></td>
          <td class="px-4 py-3 text-gray-600"><?php echo e($u->quota ?: '-'); ?></td>
          <td class="px-4 py-3">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold
              <?php echo e($u->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'); ?>">
              <?php echo e($u->status === 'active' ? 'Actif' : 'Désactivé'); ?>

            </span>
          </td>
          <td class="px-4 py-3">
            <?php $twoFactorEnabled = $u->two_factor_enabled !== false; ?>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold <?php echo e($twoFactorEnabled ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'); ?>">
              <?php echo e($twoFactorEnabled ? 'Activée' : 'Désactivée'); ?>

            </span>
          </td>
          <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?php echo e($u->created_at?->format('d/m/Y H:i') ?? '-'); ?></td>
          <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?php echo e($u->updated_at?->format('d/m/Y H:i') ?? '-'); ?></td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              
              <?php
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
              ?>
              <a href="<?php echo e(route('admin.index', ['tab' => 'users', 'u_search' => $usersSearch, 'u_edit' => $u->id, 'u_data' => $editUserPayload, 'page' => request('page')])); ?>"
                data-user="<?php echo e($editUserPayload); ?>"
                class="js-user-edit-link text-blue-600 hover:bg-blue-50 rounded p-1.5 transition" title="Modifier">
                <i class="fas fa-pen text-xs"></i>
              </a>
              
              <form method="POST" action="<?php echo e(route('admin.users-tab.notify-account', $u)); ?>" class="inline">
                <?php echo csrf_field(); ?>
                <button type="submit" class="text-amber-600 hover:bg-amber-50 rounded p-1.5 transition"
                  title="Notifier la création de compte par email">
                  <i class="fas fa-envelope text-xs"></i>
                </button>
              </form>
              
              <form method="POST" action="<?php echo e(route('admin.users-tab.toggle-status', $u)); ?>" class="inline">
                <?php echo csrf_field(); ?>
                <button type="submit"
                  class="<?php echo e($u->status === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50'); ?> rounded p-1.5 transition"
                  title="<?php echo e($u->status === 'active' ? 'Désactiver' : 'Activer'); ?>">
                  <i class="fas <?php echo e($u->status === 'active' ? 'fa-ban' : 'fa-check-circle'); ?> text-xs"></i>
                </button>
              </form>
              
              <form method="POST" action="<?php echo e(route('admin.users-tab.toggle-two-factor', $u)); ?>" class="inline">
                <?php echo csrf_field(); ?>
                <?php $twoFactorEnabled = $u->two_factor_enabled !== false; ?>
                <button type="submit"
                  class="<?php echo e($twoFactorEnabled ? 'text-indigo-600 hover:bg-indigo-50' : 'text-gray-600 hover:bg-gray-100'); ?> rounded p-1.5 transition"
                  title="<?php echo e($twoFactorEnabled ? 'Désactiver la double authentification' : 'Activer la double authentification'); ?>">
                  <i class="fas <?php echo e($twoFactorEnabled ? 'fa-shield-alt' : 'fa-shield'); ?> text-xs"></i>
                </button>
              </form>
              
              <form method="POST" action="<?php echo e(route('admin.users-tab.destroy', $u)); ?>" onsubmit="return confirm('Supprimer cet utilisateur ?')" class="inline">
                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                <button type="submit" class="text-red-600 hover:bg-red-50 rounded p-1.5 transition" title="Supprimer">
                  <i class="fas fa-trash text-xs"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td colspan="11" class="px-4 py-8 text-center text-gray-400">Aucun utilisateur trouvé.</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php if($users->hasPages()): ?>
    <div class="p-4 border-t border-gray-100"><?php echo e($users->appends(['tab'=>'users','u_search'=>$usersSearch])->links()); ?></div>
    <?php endif; ?>
  </div>
</div>


<div id="modal-user-create" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="text-base font-semibold text-gray-800">Créer un utilisateur</h3>
      <button type="button" onclick="closeUserModal('create')" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST" action="<?php echo e(route('admin.users-tab.store')); ?>" enctype="multipart/form-data"
      class="flex flex-col flex-1 overflow-y-auto px-6 py-4 space-y-4">
      <?php echo csrf_field(); ?>

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
          <?php $__currentLoopData = $profiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($p->id); ?>"><?php echo e($p->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <?php if(isset($adminScope) && $adminScope && !$canManageAdministration): ?>
        <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type']); ?>">
        <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
        <div class="col-span-2 border border-blue-100 rounded-lg px-3 py-2.5 text-sm bg-blue-50 text-blue-800 flex items-center gap-2">
            <i class="fas fa-building text-blue-400"></i>
            <span><?php echo e($adminScope['type'] === 'emitter' ? ($emitters->first()?->name ?? '--') : ($recipients->first()?->name ?? '--')); ?></span>
        </div>
        <?php else: ?>
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
        <?php endif; ?>
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

<div id="modal-user-edit" class="fixed inset-0 z-50 <?php echo e($editUserActionId ? 'flex' : 'hidden'); ?> items-center justify-center bg-black/40">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="text-base font-semibold text-gray-800">Modifier l'utilisateur</h3>
      <button type="button" onclick="closeUserModal('edit')" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form id="form-user-edit" method="POST" action="<?php echo e($editUserActionId ? route('admin.users-tab.update', $editUserActionId) : ''); ?>" enctype="multipart/form-data"
      class="flex flex-col flex-1 overflow-y-auto px-6 py-4 space-y-4">
      <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>

      <div class="grid grid-cols-2 gap-3">
        <input type="text" id="e-nom" name="nom" placeholder="Nom" required value="<?php echo e(old('nom', $editNom)); ?>"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <input type="text" id="e-prenoms" name="prenoms" placeholder="Prénoms" value="<?php echo e(old('prenoms', $editPrenoms)); ?>"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
      </div>

      <input type="text" id="e-name" name="name" placeholder="Nom à afficher" required value="<?php echo e(old('name', $editUser->name ?? ($editUserData['name'] ?? ''))); ?>"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">

      <select id="e-role" name="role" required
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">Rôle système</option>
        <option value="admin" <?php echo e(old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'admin' ? 'selected' : ''); ?>>Administrateur</option>
        <option value="manager" <?php echo e(old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'manager' ? 'selected' : ''); ?>>Manager</option>
        <option value="signer" <?php echo e(old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'signer' ? 'selected' : ''); ?>>Signataire</option>
        <option value="user" <?php echo e(old('role', $editUser->role ?? ($editUserData['role'] ?? '')) === 'user' ? 'selected' : ''); ?>>Utilisateur</option>
      </select>
      <select id="e-profile" name="profile_id"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">« Aucun profil applicatif (super-admin si rôle admin) »</option>
        <?php $__currentLoopData = $profiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($p->id); ?>" <?php echo e((string) old('profile_id', $editUser->profile_id ?? ($editUserData['profile_id'] ?? '')) === (string) $p->id ? 'selected' : ''); ?>><?php echo e($p->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>

      <div class="grid grid-cols-2 gap-3">
        <?php if(isset($adminScope) && $adminScope && !$canManageAdministration): ?>
        <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type']); ?>">
        <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
        <div class="col-span-2 border border-blue-100 rounded-lg px-3 py-2.5 text-sm bg-blue-50 text-blue-800 flex items-center gap-2">
            <i class="fas fa-building text-blue-400"></i>
            <span><?php echo e($adminScope['type'] === 'emitter' ? ($emitters->first()?->name ?? '--') : ($recipients->first()?->name ?? '--')); ?></span>
        </div>
        <?php else: ?>
        <select id="e-admin-type" name="administration_type" onchange="userAdminTypeChange('e')"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <option value="">Type d'administration</option>
          <option value="emitter" <?php echo e($editScopeType === 'emitter' ? 'selected' : ''); ?>>Émettrice</option>
          <option value="recipient" <?php echo e($editScopeType === 'recipient' ? 'selected' : ''); ?>>Destinataire</option>
        </select>
        <select id="e-admin-id" name="administration_id" onchange="userAdminIdChange('e')"
          class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
          <option value="">Administration</option>
          <?php if($editScopeType === 'recipient'): ?>
            <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($r->id); ?>" <?php echo e((string) $editScopeId === (string) $r->id ? 'selected' : ''); ?>><?php echo e($r->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php else: ?>
            <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($e->id); ?>" <?php echo e((string) $editScopeId === (string) $e->id ? 'selected' : ''); ?>><?php echo e($e->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php endif; ?>
        </select>
        <?php endif; ?>
      </div>

      <select id="e-sub-entity" name="sub_entity_id"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">Direction sous tutelle</option>
        <?php if($editScopeType && $editScopeId): ?>
          <?php $__currentLoopData = $subEntities->where('scope_type', $editScopeType)->where('scope_id', $editScopeId); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $se): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($se->id); ?>" <?php echo e((string) $editSubCode === (string) $se->code ? 'selected' : ''); ?>><?php echo e($se->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
      </select>

      <input type="email" id="e-email" name="email" placeholder="E-mail" required value="<?php echo e(old('email', $editUser->email ?? ($editUserData['email'] ?? ''))); ?>"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">

      <input type="tel" id="e-phone" name="phone" placeholder="Téléphone" value="<?php echo e(old('phone', $editUser->phone ?? ($editUserData['phone'] ?? ''))); ?>"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">

      <select id="e-quota" name="quota"
        class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:ring-2 focus:ring-[#2453d6] outline-none">
        <option value="">Quota par défaut</option>
        <option value="1 Go" <?php echo e(old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === '1 Go' ? 'selected' : ''); ?>>1 Go</option>
        <option value="5 Go" <?php echo e(old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === '5 Go' ? 'selected' : ''); ?>>5 Go</option>
        <option value="10 Go" <?php echo e(old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === '10 Go' ? 'selected' : ''); ?>>10 Go</option>
        <option value="Illimité" <?php echo e(old('quota', $editUser->quota ?? ($editUserData['quota'] ?? '')) === 'Illimité' ? 'selected' : ''); ?>>Illimité</option>
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
        <input type="checkbox" id="e-status" name="status" value="active" class="h-4 w-4 rounded border-gray-300" <?php echo e(old('status', (($editUser->status ?? ($editUserData['status'] ?? '')) === 'active' ? 'active' : '')) === 'active' ? 'checked' : ''); ?>> Actif
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
var __emitters   = <?php echo json_encode($emittersJson, 15, 512) ?>;
var __recipients = <?php echo json_encode($recipientsJson, 15, 512) ?>;
var __subEntities= <?php echo json_encode($subEntJson, 15, 512) ?>;
var __profiles   = <?php echo json_encode($profilesJson, 15, 512) ?>;
<?php
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
?>
var __editUserBootPayload = <?php echo json_encode($editUserBootPayload, 15, 512) ?>;

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
  document.getElementById('form-user-edit').method = 'POST'; // Laravel utilise POST avec <?php echo method_field('PUT'); ?>
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
</script><?php elseif($tab === 'theming'): ?>
<?php
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
?>

<div class="max-w-2xl mx-auto space-y-5">
    <?php if(session('theming_success')): ?>
        <div class="bg-green-50 border border-green-100 text-green-700 rounded-xl p-3 text-xs">
            <?php echo e(session('theming_success')); ?>

        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-1">Personnaliser l'apparence</h2>
        <p class="text-xs text-gray-500 mb-6">
          Cette extension permet de personnaliser facilement l'apparence de votre instance et des clients supportés.
            La personnalisation de l'apparence sera visible par tous les utilisateurs.
        </p>

        
        <div class="mb-6 p-4 rounded-xl border border-blue-100 bg-blue-50/40 space-y-3">
          <p class="text-xs font-semibold text-gray-700">Portée de personnalisation</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select id="t_type_sel" onchange="themingScopeTypeChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="emitter" <?php echo e($tType === 'emitter' ? 'selected' : ''); ?>>Administration Émettrice</option>
                    <option value="recipient" <?php echo e($tType === 'recipient' ? 'selected' : ''); ?>>Administration destinataire</option>
                </select>
                <select id="t_id_sel" onchange="themingScopeIdChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">Sélectionner une administration</option>
                    <?php if($tType === 'emitter'): ?>
                        <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $em): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($em->id); ?>" <?php echo e($tId === $em->id ? 'selected' : ''); ?>><?php echo e($em->name); ?> (<?php echo e($em->code); ?>)</option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php else: ?>
                        <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $re): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($re->id); ?>" <?php echo e($tId === $re->id ? 'selected' : ''); ?>><?php echo e($re->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>
                </select>
            </div>
            <p class="text-[11px] text-gray-600">
              Chaque administration possède sa propre configuration d'apparence. Les modifications enregistrées ici n'impactent pas les autres administrations.
            </p>
        </div>

        <form method="POST" action="<?php echo e(route('admin.theming.save')); ?>" enctype="multipart/form-data" class="space-y-6" id="theming-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="tab" value="theming">
            <input type="hidden" name="t_type" id="f_t_type" value="<?php echo e($tType); ?>">
            <input type="hidden" name="t_id" id="f_t_id" value="<?php echo e($tId); ?>">

            
            <div class="space-y-4">
                <div class="relative">
                    <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Nom</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <input type="text" name="app_name" id="t_app_name"
                            value="<?php echo e(old('app_name', $tAppName)); ?>"
                            class="flex-1 px-3 py-2.5 text-sm outline-none">
                        <button type="button" onclick="document.getElementById('t_app_name').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                    </div>
                </div>
                <div class="relative">
                    <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien web</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <input type="url" name="web_url" id="t_web_url"
                            value="<?php echo e(old('web_url', $tWebUrl)); ?>"
                            placeholder="https://"
                            class="flex-1 px-3 py-2.5 text-sm outline-none">
                        <button type="button" onclick="document.getElementById('t_web_url').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                    </div>
                </div>
                <div class="relative">
                    <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Slogan</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <input type="text" name="slogan" id="t_slogan"
                            value="<?php echo e(old('slogan', $tSlogan)); ?>"
                            class="flex-1 px-3 py-2.5 text-sm outline-none">
                        <button type="button" onclick="document.getElementById('t_slogan').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                    </div>
                </div>
            </div>

            
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Couleur principale</p>
              <p class="text-xs text-gray-500 mb-3">La couleur principale est utilisée pour mettre en évidence les éléments tels que les boutons importants. Elle peut être légèrement modifiée en fonction du schéma de couleurs actuel.</p>
                <div class="flex items-center gap-2">
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <span id="t_menu_color_hex" class="px-3 py-2 text-sm font-mono bg-white"><?php echo e($tMenuColor); ?></span>
                        <input type="color" name="menu_color" id="t_menu_color"
                            value="<?php echo e(old('menu_color', $tMenuColor)); ?>"
                            oninput="document.getElementById('t_menu_color_hex').textContent=this.value"
                            class="w-10 h-10 border-none cursor-pointer bg-transparent p-0"
                            title="Choisir la couleur principale">
                    </div>
                </div>
            </div>

            
            <div>
              <p class="text-sm font-medium text-gray-700 mb-2">Couleur d'arrière-plan</p>
              <p class="text-xs text-gray-500 mb-3">Au lieu d'une image d'arrière-plan, vous pouvez également définir une couleur unie d'arrière-plan. Si vous définissez une image d'arrière-plan, la modification de cette couleur influencera la couleur des icônes du menu de l'application.</p>
                <div class="flex items-center gap-2">
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                        <span id="t_bg_color_hex" class="px-3 py-2 text-sm font-mono bg-white"><?php echo e($tBgColor); ?></span>
                        <input type="color" name="bg_color" id="t_bg_color"
                            value="<?php echo e(old('bg_color', $tBgColor)); ?>"
                            oninput="document.getElementById('t_bg_color_hex').textContent=this.value"
                            class="w-10 h-10 border-none cursor-pointer bg-transparent p-0"
                            title="Choisir la couleur d'arrière-plan">
                    </div>
                </div>
            </div>

            
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
                <div id="t_logo_preview" class="mt-3 <?php echo e($tLogoPath ? '' : 'hidden'); ?>">
                    <?php if($tLogoPath): ?>
                        <img src="<?php echo e(asset('storage/' . ltrim($tLogoPath, '/'))); ?>" alt="Logo" class="h-16 object-contain rounded border border-gray-200 p-1">
                    <?php else: ?>
                        <img src="" alt="Logo preview" class="h-16 object-contain rounded border border-gray-200 p-1">
                    <?php endif; ?>
                </div>
            </div>

            
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
                <div id="t_bg_preview" class="mt-3 <?php echo e($tBgImage ? '' : 'hidden'); ?>">
                    <?php if($tBgImage): ?>
                        <img src="<?php echo e(asset('storage/' . ltrim($tBgImage, '/'))); ?>" alt="Image de fond" class="max-w-sm rounded-lg border border-gray-200 object-cover">
                    <?php else: ?>
                        <img src="" alt="Background preview" class="max-w-sm rounded-lg border border-gray-200 object-cover">
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="pt-4 border-t border-gray-100">
              <h3 class="text-base font-bold text-gray-800 mb-4">Options avancées</h3>
                <div class="space-y-4">
                    <div class="relative">
                  <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien vers la notice légale</label>
                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                            <input type="url" name="legal_notice_url" id="t_legal_url"
                                value="<?php echo e(old('legal_notice_url', $tLegalUrl)); ?>"
                                placeholder="https://"
                                class="flex-1 px-3 py-2.5 text-sm outline-none">
                            <button type="button" onclick="document.getElementById('t_legal_url').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                        </div>
                    </div>
                    <div class="relative">
                      <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-gray-500">Lien vers la politique de confidentialité</label>
                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                            <input type="url" name="privacy_policy_url" id="t_privacy_url"
                                value="<?php echo e(old('privacy_policy_url', $tPrivacyUrl)); ?>"
                                placeholder="https://"
                                class="flex-1 px-3 py-2.5 text-sm outline-none">
                            <button type="button" onclick="document.getElementById('t_privacy_url').value=''" class="px-3 text-gray-400 hover:text-gray-600 text-base">?</button>
                        </div>
                    </div>

                    
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
                        <div id="t_header_logo_preview" class="mt-3 <?php echo e($tHeaderLogo ? '' : 'hidden'); ?>">
                            <?php if($tHeaderLogo): ?>
                            <img src="<?php echo e(asset('storage/' . ltrim($tHeaderLogo, '/'))); ?>" alt="Logo en-tête" class="h-14 object-contain rounded border border-gray-200 p-1">
                            <?php else: ?>
                                <img src="" alt="Header logo preview" class="h-14 object-contain rounded border border-gray-200 p-1">
                            <?php endif; ?>
                        </div>
                    </div>

                    
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
                        <div id="t_favicon_preview" class="mt-3 <?php echo e($tFavicon ? '' : 'hidden'); ?>">
                            <?php if($tFavicon): ?>
                                <img src="<?php echo e(asset('storage/' . ltrim($tFavicon, '/'))); ?>" alt="Favicon" class="h-10 object-contain rounded border border-gray-200 p-1">
                            <?php else: ?>
                                <img src="" alt="Favicon preview" class="h-10 object-contain rounded border border-gray-200 p-1">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="pt-4 border-t border-gray-100">
              <h3 class="text-base font-bold text-gray-800 mb-2">Paramètres utilisateurs</h3>
                <label class="flex items-center gap-3 cursor-pointer" onclick="themingToggleDisable()">
                    <div id="t_disable_toggle"
                        class="relative w-11 h-6 rounded-full transition-colors <?php echo e($tDisableUser ? 'bg-blue-600' : 'bg-gray-300'); ?>">
                        <span id="t_disable_knob"
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform <?php echo e($tDisableUser ? 'translate-x-5' : 'translate-x-0'); ?>">
                        </span>
                    </div>
                    <span class="text-sm text-gray-700">Désactiver la gestion du thème par l'utilisateur</span>
                </label>
                <input type="hidden" name="disable_user_theming" id="t_disable_user_theming" value="<?php echo e($tDisableUser ? 'true' : 'false'); ?>">
                <p class="text-xs text-gray-500 mt-2">
                    Bien que vous puissiez sélectionner et personnaliser votre instance, les utilisateurs peuvent modifier leur arrière-plan et leurs couleurs.
                    Si vous voulez imposer votre personnalisation, vous pouvez activer cette option.
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    Installez l'extension PHP ImageMagick qui prend en charge les images SVG pour générer automatiquement des favicons à partir du logo téléversé et de la couleur indiquée.
                </p>
            </div>

            
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
const __themingEmitters  = <?php echo json_encode($emitters->map(fn($e)=>['id'=>$e->id, 'name'=>$e->name, 'code'=>$e->code])->values()) ?>;
const __themingRecipients= <?php echo json_encode($recipients->map(fn($r)=>['id'=>$r->id, 'name'=>$r->name])->values(), 512) ?>;

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
</script><?php elseif($tab === 'email-notifications'): ?>
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
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Administration</label>
            <select id="smtpAdminSelect" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                <option value="">— Choisir une administration —</option>
                <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($e->id); ?>" data-type="emitter"><?php echo e($e->name); ?><?php echo e($e->code ? ' ('.$e->code.')' : ''); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($r->id); ?>" data-type="recipient"><?php echo e($r->name); ?><?php echo e($r->code ? ' ('.$r->code.')' : ''); ?> [dest.]</option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        
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


<?php
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
?>
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
    <form method="POST" action="<?php echo e(route('admin.settings.save')); ?>" class="p-6 space-y-5">
        <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <input type="hidden" name="tab" value="email-notifications">

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Administration concernée</label>
            <?php if($otpScopeValue !== ''): ?>
                
                <select id="otpAdminScopeSelect" disabled
                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm bg-gray-100 text-gray-500 cursor-not-allowed">
                    <option selected><?php echo e($otpScopeLabel ?? 'Mon administration'); ?></option>
                </select>
                <input type="hidden" name="otp_admin_scope" value="<?php echo e($otpScopeValue); ?>">
                <p class="text-xs text-gray-400 mt-1">Le paramétrage s'applique uniquement à votre administration.</p>
            <?php else: ?>
                
                <select name="otp_admin_scope" id="otpAdminScopeSelect"
                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                    <option value="">« Toutes les administrations (par défaut) »</option>
                    <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="emitter:<?php echo e($e->id); ?>"><?php echo e($e->name); ?><?php echo e($e->code ? ' ('.$e->code.')' : ''); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="recipient:<?php echo e($r->id); ?>"><?php echo e($r->name); ?><?php echo e($r->code ? ' ('.$r->code.')' : ''); ?> [dest.]</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">Le choix du canal s'appliquera aux utilisateurs de l'administration sélectionnée.</p>
            <?php endif; ?>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Canal d'envoi du code OTP</label>
            <select name="otp_channel" id="otpChannelSelect" onchange="document.getElementById('whatsappOtpConfig').classList.toggle('hidden', this.value !== 'whatsapp')"
                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                <option value="email" <?php echo e($otpCurrent === 'email' ? 'selected' : ''); ?>>Email</option>
                <option value="whatsapp" <?php echo e($otpCurrent === 'whatsapp' ? 'selected' : ''); ?>>WhatsApp</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Si WhatsApp échoue ou si l'utilisateur n'a pas de numéro de téléphone, le code est envoyé par email.</p>
        </div>

        <div id="whatsappOtpConfig" class="<?php echo e($otpCurrent === 'whatsapp' ? '' : 'hidden'); ?> space-y-4 bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-green-700"><i class="fab fa-whatsapp"></i> Configuration API WhatsApp Cloud (Meta)</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Token d'accès API</label>
                    <input type="password" name="whatsapp_api_token" placeholder="<?php echo e(($settings['whatsapp_api_token']->value ?? '') ? 'Laisser vide pour conserver' : 'EAAxxxxxxx...'); ?>"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Phone Number ID</label>
                    <input type="text" name="whatsapp_phone_number_id" value="<?php echo e($settings['whatsapp_phone_number_id']->value ?? ''); ?>" placeholder="Ex: 123456789012345"
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
<?php if($otpScopeValue === ''): ?>
<script>
(function () {
    const OTP_CHANNEL_MAP = <?php echo json_encode($otpChannelMap, 15, 512) ?>;
    const scopeSelect = document.getElementById('otpAdminScopeSelect');
    const channelSelect = document.getElementById('otpChannelSelect');
    scopeSelect.addEventListener('change', function () {
        const value = OTP_CHANNEL_MAP[this.value] || OTP_CHANNEL_MAP[''] || 'email';
        channelSelect.value = value;
        document.getElementById('whatsappOtpConfig').classList.toggle('hidden', value !== 'whatsapp');
    });
})();
</script>
<?php endif; ?>


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
    <form method="POST" action="<?php echo e(route('admin.settings.save')); ?>" class="p-6 space-y-6">
        <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <input type="hidden" name="tab" value="email-notifications">

        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Administration concernée</label>
            <select name="chat_administration_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">« Toutes les administrations »</option>
                <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($e->id); ?>" <?php echo e(($settings['chat_administration_id']->value ?? '') === $e->id ? 'selected' : ''); ?>>
                    <?php echo e($e->name); ?><?php echo e($e->code ? ' ('.$e->code.')' : ''); ?>

                </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        
        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-xl px-5 py-4">
            <div>
                <p class="font-semibold text-gray-800 text-sm">Activer le chat en direct</p>
                <p class="text-xs text-gray-400 mt-0.5">Permet aux utilisateurs d'échanger des messages en temps réel.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="chat_enabled" value="0">
                <input type="checkbox" name="chat_enabled" value="1" class="sr-only peer"
                    <?php echo e(($settings['chat_enabled']->value ?? '1') == '1' ? 'checked' : ''); ?>>
                <div class="w-11 h-6 bg-gray-300 peer-checked:bg-blue-600 rounded-full peer peer-focus:ring-2 peer-focus:ring-blue-300 transition-all after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        
        <div>
            <h3 class="text-sm font-bold text-gray-800 mb-1">Portée des messages directs</h3>
            <p class="text-xs text-gray-500 mb-4">Définissez avec quels utilisateurs un membre peut initier une conversation privée.</p>
            <div class="space-y-3">
                <label class="flex items-start gap-4 p-4 border-2 rounded-xl cursor-pointer transition
                    <?php echo e(($settings['chat_scope']->value ?? 'all') === 'same_admin' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300'); ?>"
                    id="scope-same-label">
                    <input type="radio" name="chat_scope" value="same_admin"
                        <?php echo e(($settings['chat_scope']->value ?? 'all') === 'same_admin' ? 'checked' : ''); ?>

                        class="mt-0.5 w-4 h-4 text-blue-600 border-gray-300 accent-blue-600"
                        onchange="updateScopeStyle(this)">
                    <div>
                        <p class="font-semibold text-gray-800 text-sm">Même administration uniquement</p>
                        <p class="text-xs text-gray-500 mt-0.5">Un utilisateur ne peut chatter qu'avec les membres de son administration. Les messages directs vers d'autres administrations sont bloqués.</p>
                    </div>
                </label>
                <label class="flex items-start gap-4 p-4 border-2 rounded-xl cursor-pointer transition
                    <?php echo e(($settings['chat_scope']->value ?? 'all') === 'all' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300'); ?>"
                    id="scope-all-label">
                    <input type="radio" name="chat_scope" value="all"
                        <?php echo e(($settings['chat_scope']->value ?? 'all') === 'all' ? 'checked' : ''); ?>

                        class="mt-0.5 w-4 h-4 text-blue-600 border-gray-300 accent-blue-600"
                        onchange="updateScopeStyle(this)">
                    <div>
                        <p class="font-semibold text-gray-800 text-sm">Toutes les administrations</p>
                        <p class="text-xs text-gray-500 mt-0.5">Un utilisateur peut envoyer des messages directs à n'importe quel utilisateur connecté, quelle que soit son administration.</p>
                    </div>
                </label>
            </div>
        </div>

        
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


<?php elseif($tab === 'signature-provider'): ?>
<?php
    $sigAdminType = request('sig_admin_type', 'emitter');
    $sigAdminId   = request('sig_admin_id', '');
    $sigCfg       = $sigAdminId ? ($sigProviders[$sigAdminId] ?? null) : null;
?>

<div class="max-w-3xl mx-auto space-y-5">
    <?php if(session('sig_success')): ?>
        <div class="bg-green-50 border border-green-100 text-green-700 rounded-xl p-3 text-xs">
            <?php echo e(session('sig_success')); ?>

        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        
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

        
        <div class="mb-6 p-4 rounded-xl border border-blue-100 bg-blue-50/40 space-y-3">
            <p class="text-xs font-semibold text-gray-700">Administration concern&eacute;e</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select id="sig_type_sel" onchange="sigScopeTypeChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="emitter" <?php echo e($sigAdminType === 'emitter' ? 'selected' : ''); ?>>Administration &eacute;mettrice</option>
                    <option value="recipient" <?php echo e($sigAdminType === 'recipient' ? 'selected' : ''); ?>>Administration destinataire</option>
                </select>
                <select id="sig_id_sel" onchange="sigScopeIdChange(this.value)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">S&eacute;lectionner une administration</option>
                    <?php if($sigAdminType === 'emitter'): ?>
                        <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $em): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($em->id); ?>" <?php echo e($sigAdminId === $em->id ? 'selected' : ''); ?>><?php echo e($em->name); ?> (<?php echo e($em->code); ?>)</option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php else: ?>
                        <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $re): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($re->id); ?>" <?php echo e($sigAdminId === $re->id ? 'selected' : ''); ?>><?php echo e($re->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        
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

        <form method="POST" action="<?php echo e(route('admin.signature-provider.save')); ?>" id="sig-form" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sig_admin_type" id="f_sig_type" value="<?php echo e($sigAdminType); ?>">
            <input type="hidden" name="sig_admin_id"   id="f_sig_id"   value="<?php echo e($sigAdminId); ?>">

            
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                    <p class="text-sm font-medium text-gray-700">Activer la signature via API externe</p>
                    <p class="text-xs text-gray-400 mt-0.5">Si d&eacute;sactiv&eacute;, le syst&egrave;me utilise la signature locale interne.</p>
                </div>
                <div id="sig_active_toggle" onclick="sigToggleActive()"
                    class="relative w-11 h-6 rounded-full cursor-pointer transition-colors <?php echo e($sigCfg?->is_active ? 'bg-blue-600' : 'bg-gray-300'); ?>">
                    <span id="sig_active_knob"
                        class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform <?php echo e($sigCfg?->is_active ? 'translate-x-5' : 'translate-x-0'); ?>">
                    </span>
                </div>
                <input type="hidden" name="is_active" id="sig_is_active" value="<?php echo e($sigCfg?->is_active ? '1' : '0'); ?>">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Endpoint (URL de base de l'API)</label>
                    <input type="text" name="endpoint"
                        value="<?php echo e(old('endpoint', $sigCfg?->endpoint ?? '')); ?>"
                        placeholder="https://sgs-demo-test01.sunnystamp.com"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Sans slash final. Ex&nbsp;: https://sgs-demo-test01.sunnystamp.com</p>
                </div>

                
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">API Key (Bearer token)</label>
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-blue-300">
                        <input type="password" name="api_key" id="sig_api_key"
                            value="<?php echo e(old('api_key', $sigCfg?->api_key ?? '')); ?>"
                            placeholder="act_38Xcy1gjrQ9jTUfozSvpWYMi.xxxx"
                            class="flex-1 px-3 py-2 text-sm outline-none">
                        <button type="button" onclick="sigToggleApiKey()" id="sig_apikey_btn"
                            class="px-3 text-gray-400 hover:text-gray-600 transition text-xs whitespace-nowrap">
                            Afficher
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Token Bearer utilis&eacute; dans l'en-t&ecirc;te Authorization de tous les appels API.</p>
                </div>

                
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
                    
                    <div id="sig_test_result" class="hidden mt-2 p-2.5 rounded-lg text-xs"></div>
                    
                    <input type="hidden" name="provider_owner_user_id" value="<?php echo e(old('provider_owner_user_id', $sigCfg?->provider_owner_user_id ?? '')); ?>">
                </div>

                
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Tenant ID <span class="text-amber-600">&#x2605; Requis pour certaines op&eacute;rations avancées</span></label>
                    <input type="text" name="tenant_id" id="sig_tenant_id"
                        value="<?php echo e(old('tenant_id', $sigCfg?->tenant_id ?? '')); ?>"
                        placeholder="ten_Guj71mvWbKxFVg8mMnZE4CAv"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Identifiant du tenant sur la plateforme. Utilis&eacute; pour les op&eacute;rations au niveau tenant (param&egrave;tres emails, m&eacute;tadonn&eacute;es, etc.). R&eacute;cup&eacute;r&eacute; automatiquement lors du test de connexion.</p>
                </div>

                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Consent page ID &mdash; <span class="text-blue-600 font-medium">Signature</span></label>
                    <input type="text" name="consent_page_id"
                        value="<?php echo e(old('consent_page_id', $sigCfg?->consent_page_id ?? '')); ?>"
                        placeholder="cop_BgKmiR1nxZEeBiGtYhswaUUc"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Utilis&eacute; pour les &eacute;tapes de type <code class="bg-gray-100 px-1 rounded">stepType: signature</code>.</p>
                </div>

                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Consent page ID &mdash; <span class="text-purple-600 font-medium">Approbation</span></label>
                    <input type="text" name="consent_page_id_approval"
                        value="<?php echo e(old('consent_page_id_approval', $sigCfg?->consent_page_id_approval ?? '')); ?>"
                        placeholder="cop_Ka4BRrujjQ4VS1zE7GKg5oc9"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Utilis&eacute; pour les &eacute;tapes de type <code class="bg-gray-100 px-1 rounded">stepType: approval</code>. Peut &ecirc;tre diff&eacute;rent de celui de signature.</p>
                </div>

                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Profil de signature (Signature Profile ID)</label>
                    <input type="text" name="signature_profile_id"
                        value="<?php echo e(old('signature_profile_id', $sigCfg?->signature_profile_id ?? '')); ?>"
                        placeholder="sip_KA49jsZB5kMY82cGACwYgwp8"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                    <p class="text-xs text-gray-400 mt-0.5">Utilis&eacute; lors de l'association du document au workflow.</p>
                </div>

                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sign path</label>
                    <input type="text" name="sign_path"
                        value="<?php echo e(old('sign_path', $sigCfg?->sign_path ?? '/v1/sign')); ?>"
                        placeholder="/v1/sign"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                </div>

                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Timeout (ms)</label>
                    <input type="number" name="timeout_ms" min="1000"
                        value="<?php echo e(old('timeout_ms', $sigCfg?->timeout_ms ?? 30000)); ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                </div>

                
                <div class="md:col-span-2 rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <p class="text-sm font-medium text-blue-800">R&eacute;glage QR centralis&eacute; dans OnlyOffice</p>
                    <p class="text-xs text-blue-700 mt-0.5">La position du QR est d&eacute;sormais configur&eacute;e uniquement dans l'onglet <strong>OnlyOffice</strong> pour &eacute;viter les doublons.</p>
                </div>
            </div>

            
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div>
                    <p class="text-sm font-medium text-gray-700">V&eacute;rification SSL</p>
                    <p class="text-xs text-gray-400 mt-0.5">Conservez activ&eacute; en production.</p>
                </div>
                <div id="sig_ssl_toggle" onclick="sigToggleSsl()"
                    class="relative w-11 h-6 rounded-full cursor-pointer transition-colors <?php echo e(($sigCfg?->verify_ssl ?? true) ? 'bg-blue-600' : 'bg-gray-300'); ?>">
                    <span id="sig_ssl_knob"
                        class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform <?php echo e(($sigCfg?->verify_ssl ?? true) ? 'translate-x-5' : 'translate-x-0'); ?>">
                    </span>
                </div>
                <input type="hidden" name="verify_ssl" id="sig_verify_ssl" value="<?php echo e(($sigCfg?->verify_ssl ?? true) ? '1' : '0'); ?>">
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
const __sigTestUrl   = '<?php echo e(route('admin.signature-provider.test')); ?>';
const __sigCsrf      = '<?php echo e(csrf_token()); ?>';
const __sigEmitters  = <?php echo json_encode($emitters->map(fn($e)=>['id'=>$e->id, 'name'=>$e->name, 'code'=>$e->code])->values()) ?>;
const __sigRecipients= <?php echo json_encode($recipients->map(fn($r)=>['id'=>$r->id, 'name'=>$r->name])->values(), 512) ?>;

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
</script><?php elseif($tab === 'user-profiles'): ?>
<?php
$permissionTree = [
    'dashboard'        => ['label' => 'Tableau de bord', 'children' => []],
    'courrier'         => ['label' => 'Gestion Courrier', 'children' => [
        'courrier.tableau-de-bord'  => 'Tableau de bord',
        'courrier.enregistrement'   => 'Enregistrement',
      'courrier.envoi'            => 'Envoi',
      'courrier.reception'        => 'Réception',
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
        'administration.ai-integration'     => 'Intelligence Artificielle (IA)',
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
?>


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
            <?php $__empty_1 = true; $__currentLoopData = $profiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $profile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $perms = is_array($profile->permissions) ? ($profile->permissions['menuPermissions'] ?? $profile->permissions) : [];
                $userCount = \App\Models\User::where('profile_id', $profile->id)->count();
                $profileAdministrationLabel = $profile->administration_label;
                $profileAdministrationTypeLabel = $profile->administration_type_label;
            ?>
            <tr data-search="<?php echo e(strtolower($profile->name . ' ' . ($profile->description ?? '') . ' ' . $profileAdministrationLabel . ' ' . $profileAdministrationTypeLabel)); ?>"
                class="hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-semibold text-gray-800 truncate max-w-[150px]"><?php echo e($profile->name); ?></td>
                <td class="px-4 py-3 text-gray-500 truncate max-w-[160px]"><?php echo e($profile->description ?: '—'); ?></td>
                <td class="px-4 py-3 text-gray-500 max-w-[180px] hidden md:table-cell">
                    <div class="truncate"><?php echo e($profileAdministrationLabel); ?></div>
                    <div class="text-[11px] text-gray-400"><?php echo e($profileAdministrationTypeLabel); ?></div>
                </td>
                <td class="px-4 py-3 text-center hidden lg:table-cell">
                    <span class="inline-flex items-center gap-1 text-xs bg-purple-50 text-purple-700 border border-purple-100 px-2 py-0.5 rounded-full font-semibold">
                        <i class="fas fa-shield-alt"></i> <?php echo e(count($perms)); ?>

                    </span>
                </td>
                <td class="px-4 py-3 text-center hidden lg:table-cell">
                    <?php if($userCount > 0): ?>
                    <span class="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-full font-semibold">
                        <i class="fas fa-users"></i> <?php echo e($userCount); ?>

                    </span>
                    <?php else: ?>
                    <span class="text-xs text-gray-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-2">
                        <button type="button"
                           onclick="openProfileEditModal(<?php echo e(json_encode(['id'=>$profile->id,'name'=>$profile->name,'description'=>$profile->description ?? '','administration_type'=>$profile->effective_administration_type,'administration_id'=>$profile->administration_id,'perms'=>is_array($profile->permissions) ? ($profile->permissions['menuPermissions'] ?? $profile->permissions) : []])); ?>)"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition">
                            <i class="fas fa-pen"></i> Modifier
                        </button>
                        <form method="POST" action="<?php echo e(route('admin.profiles.destroy', $profile)); ?>" onsubmit="return confirm('Supprimer le rôle « <?php echo e(addslashes($profile->name)); ?> » ?')" class="inline">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">
                                <i class="fas fa-trash-alt"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                    <i class="fas fa-user-shield text-4xl text-gray-200 mb-3 block"></i>
                    Aucun rôle configuré. Cliquez sur <strong>Nouveau profil</strong> pour commencer.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php if($profiles->hasPages()): ?>
    <div class="px-5 py-3 border-t border-gray-100"><?php echo e($profiles->appends(['tab'=>'user-profiles'])->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->startPush('scripts'); ?>
<script>
function profilesSearch(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#profiles-tbody [data-search]').forEach(function(row) {
        row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
    });
}
</script>
<?php $__env->stopPush(); ?>

<?php
    $profileEmitterOptions = $emitters->map(fn($emitter) => ['id' => $emitter->id, 'name' => $emitter->name])->values();
    $profileRecipientOptions = $recipients->map(fn($recipient) => ['id' => $recipient->id, 'name' => $recipient->name])->values();
?>


<div id="modal-profile-create" class="adm-modal">
    <div class="adm-modal-box max-w-xl">
        <button onclick="closeModal('modal-profile-create')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-5">Nouveau profil</h3>
        <form method="POST" action="<?php echo e(route('admin.profiles.store')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
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
                    <?php if(isset($adminScope) && $adminScope): ?>
                    <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type']); ?>">
                    <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
                    <div class="w-full border border-gray-100 rounded-xl px-4 py-2.5 text-sm bg-gray-50 text-gray-700 flex items-center justify-between gap-3">
                      <span class="flex items-center gap-2 min-w-0">
                        <i class="fas fa-building text-gray-400"></i>
                        <span class="truncate"><?php echo e($adminScope['type'] === 'recipient' ? ($recipients->first()?->name ?? '--') : ($emitters->first()?->name ?? '--')); ?></span>
                      </span>
                      <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                        <?php echo e($adminScope['type'] === 'recipient' ? 'Destinataire' : 'Émettrice'); ?>

                      </span>
                    </div>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Permissions initiales</label>
              <p class="text-xs text-gray-500 mb-2">Les onglets et sous-onglets sont indépendants : vous pouvez sélectionner uniquement l'onglet, ou un ou plusieurs sous-onglets.</p>
                <div class="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100 max-h-64 overflow-y-auto">
                    <?php $__currentLoopData = $permissionTree; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parentKey => $parent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div>
                        <div class="flex items-center gap-3 px-4 py-2 bg-gray-50">
                            <input type="checkbox" name="permissions[]" value="<?php echo e($parentKey); ?>"
                                class="modal-parent-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                onchange="modalHandleParent(this)" data-group="<?php echo e($parentKey); ?>">
                            <span class="text-sm font-semibold text-gray-700"><?php echo e($parent['label']); ?></span>
                        </div>
                        <?php $__currentLoopData = $parent['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $childKey => $childLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex items-center gap-3 px-4 py-1.5 pl-10 hover:bg-blue-50/30 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="<?php echo e($childKey); ?>"
                                class="modal-child-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                data-parent="<?php echo e($parentKey); ?>" onchange="modalHandleChild(this)">
                            <span class="text-sm text-gray-600"><?php echo e($childLabel); ?></span>
                        </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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


<div id="modal-profile-edit" class="adm-modal">
    <div class="adm-modal-box max-w-xl">
        <button onclick="closeModal('modal-profile-edit')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
        <h3 class="text-lg font-bold text-gray-800 mb-5">Modifier le profil</h3>
        <form id="form-profile-edit" method="POST" action="" class="space-y-4" onsubmit="console.log('Form submitting to:', this.action); return true;">
            <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
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
                    <?php if(isset($adminScope) && $adminScope): ?>
                    <input type="hidden" name="administration_type" value="<?php echo e($adminScope['type']); ?>">
                    <input type="hidden" name="administration_id" value="<?php echo e($adminScope['id']); ?>">
                    <div class="w-full border border-gray-100 rounded-xl px-4 py-2.5 text-sm bg-gray-50 text-gray-700 flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 min-w-0">
                            <i class="fas fa-building text-gray-400"></i>
                            <span class="truncate"><?php echo e($adminScope['type'] === 'recipient' ? ($recipients->first()?->name ?? '--') : ($emitters->first()?->name ?? '--')); ?></span>
                        </span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                            <?php echo e($adminScope['type'] === 'recipient' ? 'Destinataire' : 'Émettrice'); ?>

                        </span>
                    </div>
                    <?php else: ?>
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
                    <?php endif; ?>
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
                    <?php $__currentLoopData = $permissionTree; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parentKey => $parent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div>
                        <div class="flex items-center gap-3 px-4 py-2 bg-gray-50">
                            <input type="checkbox" name="permissions[]" value="<?php echo e($parentKey); ?>"
                                class="edit-modal-parent-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                onchange="editModalHandleParent(this)" data-group="<?php echo e($parentKey); ?>">
                            <span class="text-sm font-semibold text-gray-700"><?php echo e($parent['label']); ?></span>
                        </div>
                        <?php $__currentLoopData = $parent['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $childKey => $childLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex items-center gap-3 px-4 py-1.5 pl-10 hover:bg-blue-50/30 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="<?php echo e($childKey); ?>"
                                class="edit-modal-child-perm w-4 h-4 text-slate-600 border-gray-300 rounded"
                                data-parent="<?php echo e($parentKey); ?>" onchange="editModalHandleChild(this)">
                            <span class="text-sm text-gray-600"><?php echo e($childLabel); ?></span>
                        </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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


<?php elseif($tab === 'instructions'): ?>
<?php $editInstruction = request('edit_instr') ? $instructions->firstWhere('id', (int) request('edit_instr')) : null; ?>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

    
    <div class="lg:col-span-2 space-y-4">

        
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-plus text-cyan-600 text-sm"></i>
                Nouvelle instruction
            </h2>
            <?php if(session('success') && request('tab') === 'instructions'): ?>
            <div class="mb-3 px-4 py-2.5 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm flex items-center gap-2">
                <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

            </div>
            <?php endif; ?>
            <form method="POST" action="<?php echo e(route('admin.instructions.store')); ?>" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="tab" value="instructions">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" required value="<?php echo e(old('nom')); ?>"
                        placeholder="Ex : Pour information, Pour visa…"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        placeholder="Description de l'instruction de traitement…"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 resize-none"><?php echo e(old('description')); ?></textarea>
                </div>
                <button type="submit"
                    class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2">
                    <i class="fas fa-plus text-xs"></i> Créer l'instruction
                </button>
            </form>
        </div>

        
        <?php if($editInstruction): ?>
        <div class="bg-white rounded-2xl border border-cyan-300 ring-2 ring-cyan-100 shadow-sm p-5">
            <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-pen text-cyan-600 text-xs"></i>
                Modifier : <?php echo e($editInstruction->nom); ?>

            </h3>
            <form method="POST" action="<?php echo e(route('admin.instructions.update', $editInstruction)); ?>" class="space-y-3">
                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                <input type="hidden" name="tab" value="instructions">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" required value="<?php echo e($editInstruction->nom); ?>"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 resize-none"><?php echo e($editInstruction->description); ?></textarea>
                </div>
                <div class="flex gap-3">
                    <a href="<?php echo e(route('admin.index', ['tab' => 'instructions'])); ?>"
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
        <?php endif; ?>
    </div>

    
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list-check text-cyan-600"></i>
                    Instructions de traitement
                </h2>
                <span class="text-xs bg-cyan-50 text-cyan-700 px-2.5 py-1 rounded-full font-semibold">
                    <?php echo e($instructions->count()); ?> instruction<?php echo e($instructions->count() !== 1 ? 's' : ''); ?>

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
                    <?php $__empty_1 = true; $__currentLoopData = $instructions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $instr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr data-search="<?php echo e(strtolower($instr->nom . ' ' . ($instr->description ?? ''))); ?>"
                        class="hover:bg-gray-50 transition <?php echo e(request('edit_instr') == $instr->id ? 'bg-cyan-50' : ''); ?>">
                        <td class="px-5 py-3.5 font-semibold text-gray-800"><?php echo e($instr->nom); ?></td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs"><?php echo e($instr->description ?: '—'); ?></td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?php echo e(route('admin.index', ['tab' => 'instructions', 'edit_instr' => $instr->id])); ?>"
                                    class="p-1.5 rounded-lg hover:bg-cyan-50 text-gray-400 hover:text-cyan-600 transition">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <form method="POST" action="<?php echo e(route('admin.instructions.destroy', $instr)); ?>"
                                    onsubmit="return confirm('Supprimer cette instruction ?')" class="inline">
                                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                    <input type="hidden" name="tab" value="instructions">
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="3" class="px-5 py-16 text-center text-gray-400">
                            <i class="fas fa-list-check text-4xl text-gray-200 mb-3 block"></i>
                            Aucune instruction configurée.<br>
                            <span class="text-xs">Créez votre première instruction à gauche.</span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function instrSearch(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#instr-tbody [data-search]').forEach(function(row) {
        row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
    });
}
</script>
<?php $__env->stopPush(); ?>

<?php elseif($tab === 'courrier-archiving'): ?>
<?php
  $archivalScopeValue = '';
  $archivalScopeType = null;
  $archivalScopeId = null;

  if (isset($adminScope) && $adminScope) {
    $archivalScopeType = $adminScope['type'];
    $archivalScopeId = $adminScope['id'];
    $archivalScopeValue = $archivalScopeType . ':' . $archivalScopeId;
  } else {
    $requestedArchivalScope = (string) request('archival_admin_scope', '');
    if (preg_match('/^(emitter|recipient):[0-9a-fA-F-]{36}$/', $requestedArchivalScope)) {
      [$archivalScopeType, $archivalScopeId] = explode(':', $requestedArchivalScope, 2);
      $archivalScopeValue = $requestedArchivalScope;
    }
  }

  $archivalScopeLabel = 'Globale (toutes administrations)';
  if ($archivalScopeType === 'emitter' && $archivalScopeId) {
    $archivalScopeLabel = ($emitters->firstWhere('id', $archivalScopeId)->name ?? $archivalScopeId) . ' (Émettrice)';
  } elseif ($archivalScopeType === 'recipient' && $archivalScopeId) {
    $archivalScopeLabel = ($recipients->firstWhere('id', $archivalScopeId)->name ?? $archivalScopeId) . ' (Destinataire)';
  }

  $resolveArchivalDays = function (string $baseKey) use ($settings, $archivalScopeValue) {
    if ($archivalScopeValue !== '') {
      $scopedKey = $baseKey . ':' . $archivalScopeValue;
      if ($settings->has($scopedKey)) {
        return max(0, (int) ($settings->get($scopedKey)->value ?? 0));
      }
    }
    return max(0, (int) ($settings->get($baseKey)->value ?? 0));
  };

  $archivalDays = $resolveArchivalDays('courrier_archival_days');
  $receptionArchivalDaysValue = $resolveArchivalDays('reception_archival_days');

  $courrierStatsQuery = \App\Models\Courrier::query();
  if ($archivalScopeId) {
    $courrierStatsQuery->where('administration_id', $archivalScopeId);
  }

    $archivalThresholdDate = $archivalDays > 0 ? now()->subDays($archivalDays) : null;
    $archivedCount = $archivalThresholdDate
    ? (clone $courrierStatsQuery)->where('created_at', '<', $archivalThresholdDate)->count()
        : 0;
  $totalCount = (clone $courrierStatsQuery)->count();

  $receptionStatsQuery = \App\Models\DocumentShare::query();
  if ($archivalScopeType === 'recipient' && $archivalScopeId) {
    $receptionStatsQuery->where('recipient_administration_id', $archivalScopeId);
  } elseif ($archivalScopeType === 'emitter') {
    $receptionStatsQuery->whereRaw('1 = 0');
  }

  $receptionArchivalThresholdDate = $receptionArchivalDaysValue > 0 ? now()->subDays($receptionArchivalDaysValue) : null;
  $receptionArchivedCount = $receptionArchivalThresholdDate
    ? (clone $receptionStatsQuery)->where('created_at', '<', $receptionArchivalThresholdDate)->distinct('document_id')->count('document_id')
    : 0;
  $receptionTotalCount = (clone $receptionStatsQuery)->distinct('document_id')->count('document_id');
?>

<div class="max-w-3xl mx-auto space-y-5">

    
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

        <?php if($archivalDays > 0): ?>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-stone-50 rounded-xl p-4 text-center border border-stone-100">
                <div class="text-2xl font-black text-stone-700"><?php echo e($archivalDays); ?></div>
                <div class="text-xs text-stone-500 mt-1">Jours de délai</div>
            </div>
            <div class="bg-red-50 rounded-xl p-4 text-center border border-red-100">
                <div class="text-2xl font-black text-red-600"><?php echo e(number_format($archivedCount)); ?></div>
                <div class="text-xs text-red-400 mt-1">Courriers archivés</div>
            </div>
            <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
                <div class="text-2xl font-black text-green-600"><?php echo e(number_format($totalCount - $archivedCount)); ?></div>
                <div class="text-xs text-green-500 mt-1">Courriers actifs</div>
            </div>
        </div>
        <?php else: ?>
        <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-amber-500"></i>
            <span class="text-sm text-amber-700">
                L'archivage automatique est <strong>désactivé</strong>. Tous les courriers (<?php echo e(number_format($totalCount)); ?>) sont visibles dans les sous-onglets.
            </span>
        </div>
        <?php endif; ?>
    </div>

    
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-sliders-h text-stone-500 text-sm"></i>
            Paramétrer le délai d'archivage
        </h3>

        <?php if(session('success') && request('tab') === 'courrier-archiving'): ?>
        <div class="mb-4 px-4 py-2.5 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm flex items-center gap-2">
            <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

        </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('admin.settings.save')); ?>" class="space-y-5">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="tab" value="courrier-archiving">

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Administration concernée</label>
            <?php if(isset($adminScope) && $adminScope): ?>
              <input type="hidden" name="archival_admin_scope" value="<?php echo e($archivalScopeValue); ?>">
              <select disabled
                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm bg-gray-100 text-gray-500 cursor-not-allowed">
                <option selected><?php echo e($archivalScopeLabel); ?></option>
              </select>
              <p class="mt-2 text-xs text-gray-400">
                Ce paramétrage s'applique uniquement à votre administration.
              </p>
            <?php else: ?>
              <select id="archivalScopeSelect" name="archival_admin_scope"
                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400">
                <option value="" <?php echo e($archivalScopeValue === '' ? 'selected' : ''); ?>>Globale (toutes administrations)</option>
                <optgroup label="Administrations émettrices">
                  <?php $__currentLoopData = $emitters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emitter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="emitter:<?php echo e($emitter->id); ?>" <?php echo e($archivalScopeValue === 'emitter:' . $emitter->id ? 'selected' : ''); ?>><?php echo e($emitter->name); ?></option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </optgroup>
                <optgroup label="Administrations destinataires">
                  <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recipient): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="recipient:<?php echo e($recipient->id); ?>" <?php echo e($archivalScopeValue === 'recipient:' . $recipient->id ? 'selected' : ''); ?>><?php echo e($recipient->name); ?></option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </optgroup>
              </select>
              <p class="mt-2 text-xs text-gray-400">
                En tant que SUPER ADMIN, choisissez l'administration sur laquelle appliquer ces paramètres.
              </p>
            <?php endif; ?>
          </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Délai d'archivage <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" name="courrier_archival_days"
                        value="<?php echo e($archivalDays > 0 ? $archivalDays : ''); ?>"
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
                    value="<?php echo e($receptionArchivalDaysValue > 0 ? $receptionArchivalDaysValue : ''); ?>"
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
                    <div class="text-xl font-black text-red-600"><?php echo e(number_format($receptionArchivedCount)); ?></div>
                    <div class="text-xs text-red-400 mt-1">Fichiers reçus archivés</div>
                  </div>
                  <div class="bg-green-50 rounded-xl p-3 text-center border border-green-100">
                    <div class="text-xl font-black text-green-600"><?php echo e(number_format(max($receptionTotalCount - $receptionArchivedCount, 0))); ?></div>
                    <div class="text-xs text-green-500 mt-1">Fichiers reçus actifs</div>
                  </div>
                </div>
              </div>

            
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-2">Suggestions rapides :</p>
                <div class="flex flex-wrap gap-2">
                    <?php $__currentLoopData = [30 => '1 mois', 90 => '3 mois', 180 => '6 mois', 365 => '1 an', 730 => '2 ans']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $days => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <button type="button"
                        onclick="document.querySelector('[name=courrier_archival_days]').value = '<?php echo e($days); ?>'"
                        class="px-3 py-1.5 text-xs rounded-lg border font-semibold transition
                            <?php echo e($archivalDays === $days ? 'bg-stone-600 text-white border-stone-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100'); ?>">
                        <?php echo e($label); ?>

                    </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                      <?php $__currentLoopData = [30 => 'Réception 1 mois', 90 => 'Réception 3 mois', 180 => 'Réception 6 mois', 365 => 'Réception 1 an']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $days => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <button type="button"
                        onclick="document.querySelector('[name=reception_archival_days]').value = '<?php echo e($days); ?>'"
                        class="px-3 py-1.5 text-xs rounded-lg border font-semibold transition
                          <?php echo e($receptionArchivalDaysValue === $days ? 'bg-stone-600 text-white border-stone-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100'); ?>">
                        <?php echo e($label); ?>

                      </button>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                <a href="<?php echo e(route('admin.index', ['tab' => 'courrier-archiving'])); ?>"
                    class="px-5 py-2.5 border border-gray-300 text-gray-600 hover:bg-gray-100 rounded-xl text-sm font-semibold transition">
                    Annuler
                </a>
            </div>
        </form>
    </div>

    
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

<?php endif; ?>
<?php $__env->startPush('scripts'); ?>
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

var archivalScopeSelect = document.getElementById('archivalScopeSelect');
if (archivalScopeSelect) {
  archivalScopeSelect.addEventListener('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('tab', 'courrier-archiving');
    if (this.value) {
      url.searchParams.set('archival_admin_scope', this.value);
    } else {
      url.searchParams.delete('archival_admin_scope');
    }
    window.location.href = url.toString();
  });
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->startPush('scripts'); ?>
<script>
var _adminBase = window._adminBase || <?php echo json_encode(route('admin.index', [], false)) ?>;

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
  emitter: <?php echo json_encode($profileEmitterOptions ?? [], 15, 512) ?>,
  recipient: <?php echo json_encode($profileRecipientOptions ?? [], 15, 512) ?>
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
    // set form action (without query params - use method spoofing with <?php echo method_field('PUT'); ?>)
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
<?php $__env->stopPush(); ?>


<?php $__env->startPush('scripts'); ?>
<link href="<?php echo e(asset('vendor/quill/quill.snow.css')); ?>" rel="stylesheet"
  onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css';">
<script src="<?php echo e(asset('vendor/quill/quill.min.js')); ?>"
    onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js';"></script>
<style>
/* -- Conteneur éditeur ----------------------------------------------- */
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
/* -- Sélecteurs police / taille -------------------------------------- */
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
/* -- Rendu des polices dans l'éditeur -------------------------------- */
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
            placeholder: 'Rédigez votre template ici… Ex: Je soussigné {{nom}}, né le {{date_naissance}}, demande…',
            modules: {
                toolbar: [
                /* Ligne 1 • Police, taille, titres */
                    [
                        { 'font': ['arial','times','courier','georgia','verdana','tahoma'] },
                        { 'size': ['8px','10px','12px','14px','16px','18px','20px','24px','36px','48px'] },
                        { 'header': [1, 2, 3, 4, false] }
                    ],
                /* Ligne 2 • Style texte */
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                /* Ligne 3 • Alignement, listes */
                    [{ 'align': [] }],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }, { 'list': 'check' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                /* Ligne 4 • Divers */
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

    /* -- Recharger le contenu quand un template est sélectionné -- */
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

    /* -- Insérer une variable {{}} à la position du curseur ------ */
    window.tplQuillInsertVar = function(varText) {
        if (!_quill) return;
        var range = _quill.getSelection(true);
        var pos = range ? range.index : _quill.getLength() - 1;
        _quill.insertText(pos, varText, { color: '#1d4ed8', bold: true, background: '#dbeafe' });
        _quill.insertText(pos + varText.length, ' ', { color: false, bold: false, background: false });
        _quill.setSelection(pos + varText.length + 1);
        _quill.focus();
    };

    /* -- Insérer une variable personnalisée ----------------------- */
    window.tplQuillInsertCustomVar = function() {
        var name = prompt('Nom de la variable (ex: nom_directeur, date_signature) :');
        if (!name) return;
        name = name.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
        if (!name) return;
        tplQuillInsertVar('[' + name + ']');
    };

    /* -- Compteur de mots/caractères ------------------------------- */
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

<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
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
    fetch(`<?php echo e(url('admin/smtp-settings')); ?>/${type}/${id}`, {
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
    fetch('<?php echo e(route('admin.smtp.settings.save')); ?>', {
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
    fetch('<?php echo e(route('admin.smtp.test')); ?>', {
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
<?php $__env->stopPush(); ?>


<?php if($tab === 'antivirus'): ?>
<?php
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
?>


<div class="mb-6 flex items-center gap-3 px-5 py-4 rounded-2xl border
    <?php echo e($avEnabled ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'); ?>">
    <i class="fas fa-shield-virus text-xl <?php echo e($avEnabled ? 'text-green-600' : 'text-amber-500'); ?>"></i>
    <div>
        <p class="font-semibold text-sm <?php echo e($avEnabled ? 'text-green-800' : 'text-amber-800'); ?>">
            Antivirus ClamAV — <?php echo e($avEnabled ? 'Activé' : 'Désactivé (mode développement)'); ?>

        </p>
        <p class="text-xs <?php echo e($avEnabled ? 'text-green-600' : 'text-amber-600'); ?> mt-0.5">
            <?php if($avEnabled): ?>
                Chaque fichier uploadé est scanné avant enregistrement. Les résultats sont consignés ci-dessous.
            <?php else: ?>
                Activez <code class="bg-amber-100 px-1 rounded font-mono">CLAMAV_ENABLED=true</code> dans <code class="bg-amber-100 px-1 rounded font-mono">.env</code> sur le serveur Ubuntu de production pour activer les scans.
            <?php endif; ?>
        </p>
    </div>
</div>


<?php if((method_exists($scanLogs, 'total') ? $scanLogs->total() : $scanLogs->count()) > 0 || $avResultFilter || $avContextFilter): ?>
<?php
    $hasAvTable   = \Illuminate\Support\Facades\Schema::hasTable('clamav_scan_logs');
    $totalScans   = $hasAvTable ? \App\Models\ClamAvScanLog::count() : 0;
    $cleanCount   = $hasAvTable ? \App\Models\ClamAvScanLog::where('result', 'clean')->count() : 0;
    $infectedCount= $hasAvTable ? \App\Models\ClamAvScanLog::where('result', 'infected')->count() : 0;
    $errorCount   = $hasAvTable ? \App\Models\ClamAvScanLog::where('result', 'error')->count() : 0;
?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-gray-800"><?php echo e(number_format($totalScans)); ?></p>
        <p class="text-xs text-gray-500 mt-1">Fichiers scannés</p>
    </div>
    <div class="bg-white rounded-2xl border border-green-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-green-600"><?php echo e(number_format($cleanCount)); ?></p>
        <p class="text-xs text-gray-500 mt-1">Propres</p>
    </div>
    <div class="bg-white rounded-2xl border border-red-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-red-600"><?php echo e(number_format($infectedCount)); ?></p>
        <p class="text-xs text-gray-500 mt-1">Infectés</p>
    </div>
    <div class="bg-white rounded-2xl border border-amber-200 p-4 text-center shadow-sm">
        <p class="text-2xl font-bold text-amber-600"><?php echo e(number_format($errorCount)); ?></p>
        <p class="text-xs text-gray-500 mt-1">Erreurs</p>
    </div>
</div>
<?php endif; ?>


<form method="GET" action="<?php echo e(route('admin.index')); ?>" class="flex flex-wrap gap-3 mb-5 items-end">
    <input type="hidden" name="tab" value="antivirus">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Résultat</label>
        <select name="av_result" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
            <option value="">Tous les résultats</option>
            <option value="clean"    <?php echo e($avResultFilter === 'clean'    ? 'selected' : ''); ?>>✓ Propre</option>
            <option value="infected" <?php echo e($avResultFilter === 'infected' ? 'selected' : ''); ?>>✗ Infecté</option>
            <option value="error"    <?php echo e($avResultFilter === 'error'    ? 'selected' : ''); ?>>⚠ Erreur</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Module</label>
        <select name="av_context" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
            <option value="">Tous les modules</option>
            <?php $__currentLoopData = $avContextLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($val); ?>" <?php echo e($avContextFilter === $val ? 'selected' : ''); ?>><?php echo e($lbl); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
    <?php if($avResultFilter || $avContextFilter): ?>
    <a href="<?php echo e(route('admin.index', ['tab' => 'antivirus'])); ?>" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-200 transition">
        <i class="fas fa-times mr-1"></i> Réinitialiser
    </a>
    <?php endif; ?>
</form>


<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <?php if($scanLogs->isEmpty()): ?>
    <div class="py-16 text-center text-gray-400">
        <i class="fas fa-shield-virus text-4xl mb-3"></i>
        <p class="text-sm font-medium">Aucun scan enregistré<?php echo e($avResultFilter || $avContextFilter ? ' pour ces filtres' : ''); ?>.</p>
        <?php if(!$avEnabled): ?>
        <p class="text-xs mt-1">Activez ClamAV sur le serveur pour commencer à scanner les fichiers.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
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
            <?php $__currentLoopData = $scanLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
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
            ?>
            <tr class="<?php echo e($log->result === 'infected' ? 'bg-red-50' : ''); ?> hover:bg-gray-50 transition">
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                    <?php echo e($log->scanned_at?->format('d/m/Y H:i:s') ?? '—'); ?>

                </td>
                <td class="px-4 py-3 max-w-[200px]">
                    <span class="block truncate font-medium text-gray-800" title="<?php echo e($log->file_name); ?>">
                        <?php echo e($log->file_name); ?>

                    </span>
                    <?php if($log->mime_type): ?>
                    <span class="text-xs text-gray-400"><?php echo e($log->mime_type); ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap"><?php echo e($sizeFormatted); ?></td>
                <td class="px-4 py-3 text-xs">
                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-medium"><?php echo e($contextLabel); ?></span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo e($resultClass); ?>">
                        <i class="<?php echo e($resultIcon); ?>"></i> <?php echo e($resultLabel); ?>

                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-red-700 font-mono">
                    <?php echo e($log->threat ?? '—'); ?>

                </td>
                <td class="px-4 py-3 text-xs text-gray-600"><?php echo e($userName); ?></td>
                <td class="px-4 py-3 text-xs text-gray-400 font-mono"><?php echo e($log->ip_address ?? '—'); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    <?php if(method_exists($scanLogs, 'hasPages') && $scanLogs->hasPages()): ?>
    <div class="px-4 py-3 border-t border-gray-100">
        <?php echo e($scanLogs->appends(request()->except('av_page'))->links()); ?>

    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\e-administration_laravel.worktrees\gestion-courrier-notification-email\resources\views/admin/index.blade.php ENDPATH**/ ?>