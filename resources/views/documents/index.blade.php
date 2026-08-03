@extends('layouts.app')
@section('title', 'Mes Documents')
@section('page-title', 'Mes Documents')
@section('content')

@php
    // Sérialisation JSON des données pour JS
    $docsJson = $documents->map(fn($d) => [
        'id'           => $d->id,
        'owner_id'     => $d->owner_id,
        'is_owner'     => (string) $d->owner_id === (string) auth()->id(),
        'can_share'    => ((string) $d->owner_id === (string) auth()->id()) || ((string) ($d->created_by ?? '') === (string) auth()->id()),
        'share_permission' => $documentAccessPermissions[(string) $d->id] ?? ((string) $d->owner_id === (string) auth()->id() ? 'modification' : 'lecture'),
        'can_edit_content' => ((string) $d->owner_id === (string) auth()->id()) || (($documentAccessPermissions[(string) $d->id] ?? 'lecture') === 'modification'),
        'title'        => $d->title,
        'description'  => $d->description,
        'file_path'    => $d->file_path,
        'final_file_path' => $d->final_file_path,
        'file_size'    => $d->file_size,
        'mime_type'    => $d->mime_type,
        'status'       => $d->status,
        'shares_count' => $sharesCount[$d->id] ?? 0,
        'is_courrier_depart_tagged' => str_contains(mb_strtolower(trim((string) ($d->title ?? '')) . ' ' . trim((string) ($d->description ?? '')), 'UTF-8'), 'courrier depart')
            || str_contains(mb_strtolower(trim((string) ($d->title ?? '')) . ' ' . trim((string) ($d->description ?? '')), 'UTF-8'), '[courrier_depart]'),
        'next_courrier_depart_number' => $nextCourrierDepart ?? null,
        'act_validation' => $actValidationByDocument[(string) $d->id] ?? null,
        'created_at'   => $d->created_at?->toISOString(),
        'updated_at'   => $d->updated_at?->toISOString(),
    ])->values();

    $prefsJson = $preferences->map(fn($p) => [
        'documentId'  => $p->document_id,
        'isFavorite'  => (bool) $p->is_favorite,
        'labelCodes'  => $p->label_codes ?? [],
    ])->values();

    $recipientsJson = $recipientAdministrations->map(fn($r) => [
        'id'   => $r->id,
        'name' => $r->name,
        'sector' => strtolower((string) (($r->metadata['sector'] ?? ''))),
    ])->values();

    $internalUsersJson = $internalUsers->map(fn($u) => [
        'id' => $u->id,
        'name' => $u->full_name ?: $u->name,
        'email' => $u->email,
        'subEntityCode' => optional($u->directionAssignments->first())->sub_entity_code,
    ])->values();

    $internalSubEntitiesJson = collect($internalSubEntities)->values();
@endphp



<!-- Inputs fichiers cachés -->
<input type="file" id="fileInput" class="hidden" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv">
<input type="file" id="folderInput" class="hidden" multiple webkitdirectory directory accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv">

<!-- Barre d'actions supérieure -->
<div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
    <div class="relative" id="newMenuWrapper">
        <button onclick="toggleNewMenu()" class="bg-[#2453d6] text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 hover:bg-[#1f47bb]">
            <i class="fas fa-plus"></i> Nouveau <i class="fas fa-chevron-down text-xs ml-1"></i>
        </button>
        <div id="newMenu" class="hidden absolute left-0 top-11 w-56 bg-white border border-gray-200 rounded-2xl shadow-2xl z-50 py-2">
            <button onclick="handleMenuAction('upload-files')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-upload text-gray-400 w-4"></i> Importer des fichiers</button>
            <button onclick="handleMenuAction('upload-folder')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-folder-plus text-gray-400 w-4"></i> Importer un dossier</button>
            <button onclick="handleMenuAction('new-folder')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-folder text-blue-500 w-4"></i> Nouveau dossier</button>
            <hr class="my-1 border-gray-100">
            <button onclick="handleMenuAction('new-doc')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-file-word text-blue-600 w-4"></i> Document Word</button>
            <button onclick="handleMenuAction('new-sheet')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-file-excel text-green-600 w-4"></i> Feuille Excel</button>
            <button onclick="handleMenuAction('new-presentation')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-file-powerpoint text-orange-500 w-4"></i> Présentation</button>
            <button onclick="handleMenuAction('new-pdf-form')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-file-pdf text-red-500 w-4"></i> Formulaire PDF</button>
            <hr class="my-1 border-gray-100">
            <button onclick="handleMenuAction('request-file')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-bell text-gray-400 w-4"></i> Demander un fichier</button>
        </div>
    </div>

    <div class="flex-1 max-w-sm">
        <input type="text" id="searchInput" oninput="applyFilters()" placeholder="Rechercher un document..."
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
</div>

<!-- Onglets dossiers -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 flex flex-wrap items-center gap-2 mb-3">
    <button onclick="setFolderTab(null)" id="tab-root"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#2453d6] text-white">
        Racine
    </button>
    <!-- Onglets dossiers dynamiques injectés par JS -->
    <div id="folderTabsContainer" class="flex flex-wrap gap-2"></div>
</div>

<!-- Sous-onglets -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 flex flex-wrap items-center gap-2 mb-3">
    <button onclick="setSubTab('all')" id="subtab-all"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#2453d6] text-white">
        Tous
    </button>
    <button onclick="setSubTab('favorites')" id="subtab-favorites"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100">
        Favoris (<span id="favCount">0</span>)
    </button>
    <button onclick="setSubTab('labels')" id="subtab-labels"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100">
        Étiquettes
    </button>
    <input type="text" id="labelSearch" oninput="applyFilters()" placeholder="Code étiquette (ex: ETQ-RH-001)"
           class="hidden ml-auto w-full sm:w-72 border border-gray-300 rounded-lg px-3 py-1.5 text-xs">
    <a href="{{ route('documents.trash') }}" class="ml-auto px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-600 hover:bg-red-100 flex items-center gap-1.5">
        <i class="fas fa-trash-alt"></i> Corbeille
    </a>
</div>

<!-- Barre de progression upload -->
<div id="uploadProgress" class="hidden mb-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
    <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-gray-700">Import en cours…</span>
        <span id="uploadProgressText" class="text-xs text-gray-500">0 / 0</span>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-2">
        <div id="uploadProgressBar" class="bg-[#2453d6] h-2 rounded-full transition-all duration-300" style="width:0%"></div>
    </div>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
    <div id="tableEmpty" class="hidden p-10 text-center text-gray-400">
        <i class="fas fa-folder-open text-4xl mb-3 block text-gray-200"></i>
        <span id="emptyMsg">Aucun document pour l'instant.</span>
    </div>
    <table id="docTable" class="w-full text-sm">
        <thead class="border-b bg-gray-50/80">
            <tr>
            <th class="text-left py-3 px-4 font-semibold text-gray-700 cursor-pointer select-none hover:text-blue-600" onclick="sortBy('title')">
                Nom <i id="sort-icon-title" class="fas fa-sort text-gray-300 ml-1 text-xs"></i>
            </th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700 cursor-pointer select-none hover:text-blue-600" onclick="sortBy('type')">
                Type <i id="sort-icon-type" class="fas fa-sort text-gray-300 ml-1 text-xs"></i>
            </th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Taille</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700 cursor-pointer select-none hover:text-blue-600" onclick="sortBy('date')">
                Modifié <i id="sort-icon-date" class="fas fa-sort text-gray-300 ml-1 text-xs"></i>
            </th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Statut</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody id="docTableBody"></tbody>
    </table>
</div>

<!-- ═══════════════════════════════════════════════════════
     Modal : Renommer
═══════════════════════════════════════════════════════ -->
<div id="renameModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800"><i class="fas fa-pencil-alt text-blue-500 mr-2"></i>Renommer</h3>
            <button onclick="document.getElementById('renameModal').classList.add('hidden')" class="h-8 w-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 text-xl">&times;</button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Nouveau nom</label>
            <input type="text" id="renameInput" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Nom du document"
                   onkeydown="if(event.key==='Enter') confirmRename()">
            <p id="renameError" class="hidden text-xs text-red-600"></p>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
            <button onclick="document.getElementById('renameModal').classList.add('hidden')" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Annuler</button>
            <button onclick="confirmRename()" class="px-3 py-2 text-xs font-semibold rounded-lg bg-[#2453d6] text-white hover:bg-[#1f47bb]">Renommer</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Modal : Déplacer
═══════════════════════════════════════════════════════ -->
<div id="moveModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800"><i class="fas fa-folder-open text-yellow-500 mr-2"></i>Déplacer vers</h3>
            <button onclick="document.getElementById('moveModal').classList.add('hidden')" class="h-8 w-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 text-xl">&times;</button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Dossier de destination</label>
            <select id="moveSelect" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Racine</option>
            </select>
            <p class="text-xs text-gray-400">Ou créer un nouveau dossier :</p>
            <input type="text" id="moveNewFolder" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Nom du nouveau dossier (optionnel)">
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
            <button onclick="document.getElementById('moveModal').classList.add('hidden')" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Annuler</button>
            <button onclick="confirmMove()" class="px-3 py-2 text-xs font-semibold rounded-lg bg-[#2453d6] text-white hover:bg-[#1f47bb]">Déplacer</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Modal : Étiquettes
═══════════════════════════════════════════════════════ -->
<div id="labelsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-800"><i class="fas fa-tag text-emerald-500 mr-2"></i>Gérer les étiquettes</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="labelsDocTitle"></p>
            </div>
            <button onclick="document.getElementById('labelsModal').classList.add('hidden')" class="h-8 w-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 text-xl">&times;</button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <div id="labelsChips" class="flex flex-wrap gap-2 min-h-8"></div>
            <div class="flex gap-2">
                <input type="text" id="labelNewInput" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex: ETQ-RH-001"
                       onkeydown="if(event.key==='Enter'||event.key===','){event.preventDefault();addLabelChip()}">
                <button onclick="addLabelChip()" class="px-3 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">+ Ajouter</button>
            </div>
            <p class="text-xs text-gray-400">Appuyez sur Entrée ou virgule pour ajouter une étiquette.</p>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
            <button onclick="document.getElementById('labelsModal').classList.add('hidden')" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Annuler</button>
            <button onclick="confirmLabels()" class="px-3 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Enregistrer</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Modal : Supprimer (confirmation)
═══════════════════════════════════════════════════════ -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl border border-gray-100">
        <div class="px-5 py-5 text-center">
            <div class="mx-auto mb-4 h-12 w-12 rounded-full bg-red-50 flex items-center justify-center">
                <i class="fas fa-trash-alt text-red-500 text-lg"></i>
            </div>
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Supprimer le document</h3>
            <p class="text-xs text-gray-500">« <span id="deleteDocTitle" class="font-semibold text-gray-700"></span> » sera supprimé définitivement. Cette action est irréversible.</p>
        </div>
        <div class="px-5 pb-5 flex justify-center gap-3">
            <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Annuler</button>
            <button onclick="confirmDelete()" class="px-4 py-2 text-xs font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700">Supprimer</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Modal : Détails + Versions
═══════════════════════════════════════════════════════ -->
<div id="detailsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-gray-100 max-h-[90vh] flex flex-col">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <div>
                <h3 class="text-sm font-semibold text-gray-800"><i class="fas fa-info-circle text-blue-500 mr-2"></i>Détails du document</h3>
                <p class="text-xs text-gray-500 mt-0.5 truncate max-w-xs" id="detailsDocTitle"></p>
            </div>
            <button onclick="document.getElementById('detailsModal').classList.add('hidden')" class="h-8 w-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 text-xl">&times;</button>
        </div>
        <div class="px-5 py-4 overflow-y-auto flex-1">
            <!-- Infos -->
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-xs mb-5">
                <div><span class="font-semibold text-gray-500 block mb-0.5">Type</span><span id="details-type" class="text-gray-800"></span></div>
                <div><span class="font-semibold text-gray-500 block mb-0.5">Taille</span><span id="details-size" class="text-gray-800"></span></div>
                <div><span class="font-semibold text-gray-500 block mb-0.5">Statut</span><span id="details-status" class="text-gray-800"></span></div>
                <div><span class="font-semibold text-gray-500 block mb-0.5">Partagé</span><span id="details-shares" class="text-gray-800"></span></div>
                <div class="col-span-2"><span class="font-semibold text-gray-500 block mb-0.5">Créé le</span><span id="details-created" class="text-gray-800"></span></div>
                <div class="col-span-2"><span class="font-semibold text-gray-500 block mb-0.5">Modifié le</span><span id="details-updated" class="text-gray-800"></span></div>
            </div>

            <!-- Changer le statut -->
            <div id="detailsStatusActions" class="mb-5 p-3 bg-gray-50 rounded-xl border border-gray-100">
                <p class="text-xs font-semibold text-gray-700 mb-2">Changer le statut</p>
                <div class="flex gap-2 flex-wrap">
                    <button onclick="handleChangeStatus('draft')" class="px-3 py-1 text-xs rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 font-semibold border border-gray-200">Brouillon</button>
                    <button onclick="handleChangeStatus('active')" class="px-3 py-1 text-xs rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold border border-indigo-200">Actif</button>
                    <button onclick="handleChangeStatus('archived')" class="px-3 py-1 text-xs rounded-lg bg-orange-50 text-orange-700 hover:bg-orange-100 font-semibold border border-orange-200">Archivé</button>
                </div>
            </div>

            <!-- Historique des versions -->
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-2">Historique des versions</p>
                <div id="detailsVersions" class="space-y-2">
                    <div class="text-xs text-gray-400 italic">Chargement…</div>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex justify-end flex-shrink-0">
            <button onclick="document.getElementById('detailsModal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Fermer</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Modal : Statut changé (toast-like)
═══════════════════════════════════════════════════════ -->
<div id="toastMsg" class="hidden fixed bottom-6 right-6 z-[100] bg-gray-900 text-white text-xs font-semibold px-4 py-3 rounded-xl shadow-2xl flex items-center gap-2">
    <i class="fas fa-check-circle text-green-400"></i>
    <span id="toastText"></span>
</div>

<!-- Modal partage -->
<div id="shareModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-orange-300 flex flex-col max-h-[90vh]">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <div>
                <h3 class="text-sm font-semibold text-gray-800">Partager le fichier</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="shareDocTitle"></p>
            </div>
            <button onclick="closeShareModal()" class="h-8 w-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 text-xl">&times;</button>
        </div>

        <div class="px-5 pt-4 pb-2 overflow-y-auto flex-1">
            <!-- Modes -->
            <div class="inline-flex rounded-lg bg-white p-1 mb-4 gap-1 border border-gray-100 flex-wrap">
                <button onclick="setShareMode('internal')" id="sm-internal"
                        class="px-3 py-1.5 text-xs font-semibold rounded-md border bg-gray-100 border-gray-300 text-gray-800 shadow-sm">
                    Partage interne
                </button>
                <button onclick="setShareMode('external')" id="sm-external"
                        class="px-3 py-1.5 text-xs font-semibold rounded-md border bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100">
                    Partage externe (Email)
                </button>
                <button onclick="setShareMode('admin')" id="sm-admin"
                        class="px-3 py-1.5 text-xs font-semibold rounded-md border bg-green-50 border-green-200 text-green-700 hover:bg-green-100">
                    Administration destinataire
                </button>
            </div>

            <!-- Contenu interne -->
            <div id="share-internal" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Cible de partage interne</label>
                    <select id="shareInternalTargetType" onchange="toggleInternalTargetType()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="user">Un utilisateur</option>
                        <option value="sub_entity">Utilisateurs d'une entité sous tutelle</option>
                    </select>
                </div>
                <div id="shareInternalUserWrap">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Utilisateur interne</label>
                    <select id="shareInternalUserId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></select>
                </div>
                <div id="shareInternalSubEntityWrap" class="hidden">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Entité sous tutelle</label>
                    <select id="shareInternalSubEntityCode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Droits</label>
                    <select id="sharePermission" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="lecture">Lecture</option>
                        <option value="modification">Modification</option>
                    </select>
                </div>
            </div>

            <!-- Contenu externe -->
            <div id="share-external" class="hidden space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Adresse Email externe</label>
                    <input type="email" id="shareExternalEmail" placeholder="exemple@domaine.com"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <p class="text-xs text-gray-500">Un lien sécurisé sera envoyé à cette adresse email.</p>
            </div>

            <!-- Contenu administration -->
            <div id="share-admin" class="hidden space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Filtrer par secteur</label>
                        <select id="shareAdminSector" onchange="filterRecipientAdministrations()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Tous les secteurs</option>
                            <option value="fiscalite">Fiscalité &amp; Finances</option>
                            <option value="social">Protection Sociale</option>
                            <option value="travail">Travail &amp; Emploi</option>
                            <option value="urbanisme">Urbanisme &amp; Logement</option>
                            <option value="education">Éducation &amp; Formation</option>
                            <option value="sante">Santé</option>
                            <option value="justice">Justice</option>
                            <option value="environnement">Environnement</option>
                            <option value="commerce">Commerce &amp; Industrie</option>
                            <option value="banques">Banques</option>
                            <option value="securite">Sécurité</option>
                            <option value="administration">Administration</option>
                            <option value="agriculture">Agriculture</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Numero de suivi de traitement</label>
                        <input type="text" id="shareTrackingNumber" onblur="lookupShareByTrackingNumber()" placeholder="Ex: DACT-202604-123456"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div id="shareTrackingStatus" class="hidden rounded-lg px-3 py-2 text-xs"></div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Administration destinataire</label>
                    <select id="shareAdminId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Sélectionner une administration</option>
                        @foreach($recipientAdministrations as $admin)
                        <option value="{{ $admin->id }}" data-sector="{{ strtolower((string) (($admin->metadata['sector'] ?? '')) ) }}">{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nom et prénoms</label>
                    <input type="text" id="shareFullName" placeholder="Ex: KOUADIO Jean Michel"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Matricule</label>
                    <input type="text" id="shareMatricule" placeholder="Ex: MTR-2026-001"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" id="shareApplicantEmail" placeholder="exemple@domaine.com"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Téléphone</label>
                    <input type="tel" id="shareApplicantPhone" placeholder="Ex: 0700000000"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div id="shareRibWrapper" class="hidden">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        RIB <span class="text-red-500">*</span>
                    </label>
                      <input type="text" id="shareApplicantRib" placeholder="Ex: CI650 01001 010485800008 06" oninput="formatShareRib(this)" onblur="formatShareRib(this)"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono tracking-wide">
                    <p class="text-xs text-gray-400 mt-1">Obligatoire pour les établissements bancaires.</p>
                </div>
            </div>

            <!-- Délai de validité -->
            <div class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                    <input type="checkbox" id="shareHasDelay" onchange="toggleDelay()"> Définir un délai de validité
                </label>
                <div id="delayFields" class="hidden grid grid-cols-2 gap-2">
                    <input type="number" id="shareDelayValue" min="1" value="24" placeholder="Ex: 24"
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <select id="shareDelayUnit" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="hours">Heures</option>
                        <option value="days">Jours</option>
                    </select>
                </div>
            </div>

            <div id="shareStatus" class="hidden mt-3 rounded-lg px-3 py-2 text-xs"></div>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2 flex-shrink-0">
            <button onclick="closeShareModal()" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Annuler</button>
            <button onclick="submitShare()" class="px-3 py-2 text-xs font-semibold rounded-lg bg-[#2453d6] text-white hover:bg-[#1f47bb]">Partager</button>
        </div>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const _BASE = '{{ url("documents") }}';
const ROUTES = {
    favorite:         (id) => `${_BASE}/${id}/favorite`,
    labels:           (id) => `${_BASE}/${id}/labels`,
    rename:           (id) => `${_BASE}/${id}/rename`,
    move:             (id) => `${_BASE}/${id}/move`,
    share:            (id) => `${_BASE}/${id}/share`,
    destroy:          (id) => `${_BASE}/${id}`,
    versions:         (id) => `${_BASE}/${id}/versions`,
    status:           (id) => `${_BASE}/${id}/status`,
    convertPdf:       (id) => `${_BASE}/${id}/convert-pdf`,
    createNew:        '{{ url("documents/new") }}',
    uploadAjax:       '{{ url("documents/upload-ajax") }}',
    shareLookupTracking: '{{ route("documents.share.lookupTracking") }}',
    download:         (id) => `${_BASE}/${id}/download`,
    validateActRequest: (id) => `${_BASE}/${id}/validate-act-request`,
    onlyofficeConfig: '{{ url("documents/onlyoffice-config") }}',
};

const OO_URL = @json($onlyofficeUrl ?? '');
const RECIPIENT_ADMINS = @json($recipientsJson);
const INTERNAL_USERS = @json($internalUsersJson);
const INTERNAL_SUB_ENTITIES = @json($internalSubEntitiesJson);

let allDocs = @json($docsJson);
let prefs   = @json($prefsJson);
let activeFolderTab = null;
let activeSubTab    = 'all';
let openActionsId   = null;
let shareDocId      = null;
let shareMode       = 'internal';
let folderTabs      = [];
let sortField       = null;
let sortDir         = 'asc';
let _renameDocId    = null;
let _moveDocId      = null;
let _labelsDocId    = null;
let _deleteDocId    = null;
let _detailsDocId   = null;
let _labelsList     = [];

function resetInternalShareOptions() {
    const userSelect = document.getElementById('shareInternalUserId');
    const subEntitySelect = document.getElementById('shareInternalSubEntityCode');

    const userOptions = ['<option value="">Sélectionner un utilisateur</option>'];
    INTERNAL_USERS.forEach((u) => {
        const label = `${u.name || 'Utilisateur'}${u.email ? ' (' + u.email + ')' : ''}`;
        userOptions.push(`<option value="${u.id}">${label}</option>`);
    });
    userSelect.innerHTML = userOptions.join('');

    const subOptions = ['<option value="">Sélectionner une entité sous tutelle</option>'];
    INTERNAL_SUB_ENTITIES.forEach((s) => {
        const count = Number(s.users_count || 0);
        const label = `${s.label || s.code} (${count} utilisateur${count > 1 ? 's' : ''})`;
        subOptions.push(`<option value="${s.code}">${label}</option>`);
    });
    subEntitySelect.innerHTML = subOptions.join('');
}

function toggleInternalTargetType() {
    const targetType = document.getElementById('shareInternalTargetType').value;
    document.getElementById('shareInternalUserWrap').classList.toggle('hidden', targetType !== 'user');
    document.getElementById('shareInternalSubEntityWrap').classList.toggle('hidden', targetType !== 'sub_entity');
}

function resetRecipientSectorFilter() {
    const sectorSelect = document.getElementById('shareAdminSector');
    const sectorOptions = [
        ['fiscalite', 'Fiscalité & Finances'],
        ['social', 'Protection Sociale'],
        ['travail', 'Travail & Emploi'],
        ['urbanisme', 'Urbanisme & Logement'],
        ['education', 'Éducation & Formation'],
        ['sante', 'Santé'],
        ['justice', 'Justice'],
        ['environnement', 'Environnement'],
        ['commerce', 'Commerce & Industrie'],
        ['banques', 'Banques'],
        ['securite', 'Sécurité'],
        ['administration', 'Administration'],
        ['agriculture', 'Agriculture'],
        ['autre', 'Autre'],
    ];
    sectorSelect.innerHTML = '<option value="">Tous les secteurs</option>' +
        sectorOptions.map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
}

function toggleRibField() {
    const sector = (document.getElementById('shareAdminSector').value || '').toLowerCase();
    const wrapper = document.getElementById('shareRibWrapper');
    const isBanque = sector === 'banques';
    wrapper.classList.toggle('hidden', !isBanque);
    if (!isBanque) document.getElementById('shareApplicantRib').value = '';
}

function formatShareRib(input) {
    if (!input) return;

    const raw = String(input.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (!raw) {
        input.value = '';
        return;
    }

    // Masque attendu: CI + 3 chiffres + 5 chiffres + 12 chiffres + 2 chiffres.
    let normalized = raw;
    if (normalized.startsWith('CI')) {
        const digits = normalized.slice(2).replace(/\D/g, '').slice(0, 22);
        const p1 = digits.slice(0, 3);
        const p2 = digits.slice(3, 8);
        const p3 = digits.slice(8, 20);
        const p4 = digits.slice(20, 22);

        const parts = [];
        if (p1) parts.push('CI' + p1);
        if (p2) parts.push(p2);
        if (p3) parts.push(p3);
        if (p4) parts.push(p4);
        input.value = parts.join(' ');
        return;
    }

    // Fallback pendant la saisie si le préfixe CI n'est pas encore saisi.
    input.value = normalized.slice(0, 24);
}

function filterRecipientAdministrations() {
    const sector = (document.getElementById('shareAdminSector').value || '').trim().toLowerCase();
    const select = document.getElementById('shareAdminId');
    const previousValue = select.value;

    const options = ['<option value="">Sélectionner une administration</option>'];
    RECIPIENT_ADMINS
        .filter((a) => (!sector || (a.sector || '') === sector))
        .forEach((a) => {
            options.push(`<option value="${a.id}" data-sector="${a.sector || ''}">${a.name}</option>`);
        });

    select.innerHTML = options.join('');
    if (previousValue && RECIPIENT_ADMINS.some((a) => a.id === previousValue)) {
        select.value = previousValue;
        if (select.value !== previousValue) {
            select.value = '';
        }
    }
    toggleRibField();
}

async function lookupShareByTrackingNumber() {
    const input = document.getElementById('shareTrackingNumber');
    const statusEl = document.getElementById('shareTrackingStatus');
    const trackingNumber = (input.value || '').trim();

    const showStatus = (message, ok) => {
        statusEl.textContent = message;
        statusEl.className = `rounded-lg px-3 py-2 text-xs ${ok ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`;
        statusEl.classList.remove('hidden');
    };

    const adminSelect = document.getElementById('shareAdminId');
    const sectorSelect = document.getElementById('shareAdminSector');

    if (!trackingNumber) {
        statusEl.classList.add('hidden');
        unlockRecipientAdministration(adminSelect, sectorSelect);
        return;
    }

    try {
        const resp = await fetch(ROUTES.shareLookupTracking, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
            body: JSON.stringify({ tracking_number: trackingNumber }),
        });

        const data = await resp.json();
        if (!resp.ok || !data.ok || !data.data) {
            throw new Error(data.message || 'Numero de suivi introuvable.');
        }

        const result = data.data;
        unlockRecipientAdministration(adminSelect, sectorSelect);

        // Sélectionner le secteur de l'administration liée au numéro de suivi
        const foundAdmin = RECIPIENT_ADMINS.find((a) => String(a.id) === String(result.recipient_administration_id || ''));
        sectorSelect.value = foundAdmin ? (foundAdmin.sector || '') : '';

        filterRecipientAdministrations();
        adminSelect.value = result.recipient_administration_id || '';
        document.getElementById('shareFullName').value = result.applicant_full_name || '';
        document.getElementById('shareMatricule').value = result.applicant_matricule || '';
        document.getElementById('shareApplicantEmail').value = result.applicant_email || '';
        document.getElementById('shareApplicantPhone').value = result.applicant_phone || '';

        const adminLabel = result.recipient_administration_name || 'Administration trouvee';
        if (result.recipient_administration_id) {
            lockRecipientAdministration(adminSelect, sectorSelect);
        }
        showStatus(`Informations chargees (${adminLabel}).`, true);
    } catch (err) {
        unlockRecipientAdministration(adminSelect, sectorSelect);
        showStatus(err.message || 'Impossible de recuperer les informations de suivi.', false);
    }
}

function lockRecipientAdministration(adminSelect, sectorSelect) {
    adminSelect.disabled = true;
    adminSelect.classList.add('bg-gray-100', 'cursor-not-allowed', 'text-gray-500');
    if (sectorSelect) {
        sectorSelect.disabled = true;
        sectorSelect.classList.add('bg-gray-100', 'cursor-not-allowed', 'text-gray-500');
    }
}

function unlockRecipientAdministration(adminSelect, sectorSelect) {
    adminSelect.disabled = false;
    adminSelect.classList.remove('bg-gray-100', 'cursor-not-allowed', 'text-gray-500');
    if (sectorSelect) {
        sectorSelect.disabled = false;
        sectorSelect.classList.remove('bg-gray-100', 'cursor-not-allowed', 'text-gray-500');
    }
}

// Helpers
const isFolder = (doc) => doc.description === '[folder]';
const getFolder = (doc) => {
    if (!doc.description) return null;
    const m = doc.description.match(/^Dossier:\s*(.+)$/i);
    return m ? m[1].trim() : null;
};
const getFavoriteIds = () => prefs.filter(p => p.isFavorite).map(p => p.documentId);
const getLabels = (id) => (prefs.find(p => p.documentId === id) || {}).labelCodes || [];
const isFav = (id) => prefs.some(p => p.documentId === id && p.isFavorite);

const ext = (doc) => {
    const n = (doc.title || '').toLowerCase();
    const i = n.lastIndexOf('.');
    if (i >= 0 && i < n.length - 1) return n.slice(i + 1);
    // Fallback sur final_file_path puis file_path
    const fp = (doc.final_file_path || doc.file_path || '').toLowerCase();
    const j = fp.lastIndexOf('.');
    if (j >= 0) return fp.slice(j + 1);
    if ((doc.mime_type || '').toLowerCase() === 'application/pdf') return 'pdf';
    return '';
};
const iconClass = (doc) => {
    if (isFolder(doc)) return 'fas fa-folder text-blue-600';
    const e = ext(doc);
    if (e === 'pdf')  return 'fas fa-file-pdf text-orange-500';
    if (e === 'docx') return 'fas fa-file-word text-blue-600';
    if (e === 'xlsx') return 'fas fa-file-excel text-green-600';
    if (e === 'pptx') return 'fas fa-file-powerpoint text-red-400';
    return 'fas fa-file text-gray-500';
};
const colorClass = (doc) => {
    if (isFolder(doc)) return 'text-gray-600';
    const e = ext(doc);
    if (e === 'pdf')  return 'text-orange-500';
    if (e === 'docx') return 'text-blue-600';
    if (e === 'xlsx') return 'text-green-600';
    if (e === 'pptx') return 'text-red-400';
    return 'text-gray-500';
};
const fmtDate = (d) => new Date(d).toLocaleDateString('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric' });
const fmtSize = (bytes) => {
    if (!bytes || isNaN(bytes)) return '—';
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
};
const statusLabel = { draft:'Brouillon', active:'Actif', signed:'Signé', archived:'Archivé', pending_signature:'En attente', sent:'Envoye' };

function post(url, body = {}) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        body: JSON.stringify(body),
    }).then(r => r.json());
}

// ---- Onglets dossiers ----
function setFolderTab(folder) {
    activeFolderTab = folder;
    document.getElementById('tab-root').className =
        `px-3 py-1.5 rounded-lg text-xs font-semibold ${!folder ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`;
    document.querySelectorAll('.folder-tab').forEach(btn => {
        btn.className = `folder-tab px-3 py-1.5 rounded-lg text-xs font-semibold ${btn.dataset.folder === folder ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`;
    });
    renderTable();
}

function openFolderInTab(folderName) {
    if (!folderTabs.includes(folderName)) {
        folderTabs.push(folderName);
        const btn = document.createElement('button');
        btn.className = 'folder-tab px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200';
        btn.dataset.folder = folderName;
        btn.textContent = folderName;
        btn.onclick = () => setFolderTab(folderName);
        document.getElementById('folderTabsContainer').appendChild(btn);
    }
    setFolderTab(folderName);
}

// ---- Sous-onglets ----
function setSubTab(tab) {
    activeSubTab = tab;
    ['all','favorites','labels'].forEach(t => {
        const btn = document.getElementById(`subtab-${t}`);
        if (t === 'all') {
            btn.className = `px-3 py-1.5 rounded-lg text-xs font-semibold ${tab === 'all' ? 'bg-[#2453d6] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`;
        } else if (t === 'favorites') {
            btn.className = `px-3 py-1.5 rounded-lg text-xs font-semibold ${tab === 'favorites' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'}`;
        } else {
            btn.className = `px-3 py-1.5 rounded-lg text-xs font-semibold ${tab === 'labels' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}`;
        }
    });
    const ls = document.getElementById('labelSearch');
    if (tab === 'labels') ls.classList.remove('hidden'); else { ls.classList.add('hidden'); ls.value = ''; }
    renderTable();
}

// ---- Filtrage & rendu ----
function getDisplayedDocs() {
    const search = document.getElementById('searchInput').value.trim().toLowerCase();
    let docs;
    if (!activeFolderTab) {
        docs = allDocs.filter(d => isFolder(d) || !getFolder(d));
    } else {
        docs = allDocs.filter(d => !isFolder(d) && getFolder(d) === activeFolderTab);
    }

    if (search) docs = docs.filter(d => d.title.toLowerCase().includes(search));

    if (activeSubTab === 'favorites') {
        docs = docs.filter(d => !isFolder(d) && isFav(d.id));
    } else if (activeSubTab === 'labels') {
        const code = document.getElementById('labelSearch').value.trim().toUpperCase();
        docs = docs.filter(d => !isFolder(d) && getLabels(d.id).length > 0);
        if (code) docs = docs.filter(d => getLabels(d.id).includes(code));
    }

    // Tri
    if (sortField) {
        docs = [...docs].sort((a, b) => {
            let va, vb;
            if (sortField === 'title') { va = a.title.toLowerCase(); vb = b.title.toLowerCase(); }
            else if (sortField === 'type') { va = isFolder(a) ? '0' : ext(a); vb = isFolder(b) ? '0' : ext(b); }
            else if (sortField === 'date') { va = a.updated_at || a.created_at; vb = b.updated_at || b.created_at; }
            if (va < vb) return sortDir === 'asc' ? -1 : 1;
            if (va > vb) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });
    }

    return docs;
}

function applyFilters() { renderTable(); }

function sortBy(field) {
    if (sortField === field) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        sortField = field;
        sortDir = 'asc';
    }
    // Update icons
    ['title','type','date'].forEach(f => {
        const icon = document.getElementById(`sort-icon-${f}`);
        if (!icon) return;
        if (f === field) {
            icon.className = `fas fa-sort-${sortDir === 'asc' ? 'up' : 'down'} text-blue-500 ml-1 text-xs`;
        } else {
            icon.className = 'fas fa-sort text-gray-300 ml-1 text-xs';
        }
    });
    renderTable();
}

function renderTable() {
    document.getElementById('subtab-favorites').innerHTML =
        `Favoris (<span id="favCount">${getFavoriteIds().length}</span>)`;

    const docs = getDisplayedDocs();
    const tbody = document.getElementById('docTableBody');
    const empty = document.getElementById('tableEmpty');
    const table = document.getElementById('docTable');

    if (docs.length === 0) {
        empty.classList.remove('hidden');
        table.classList.add('hidden');
        const msgs = {
            favorites: 'Aucun fichier favori pour le moment.',
            labels: 'Aucun fichier étiqueté ne correspond au code saisi.',
        };
        document.getElementById('emptyMsg').textContent =
            msgs[activeSubTab] || (activeFolderTab ? `Aucun fichier dans « ${activeFolderTab} ».` : "Aucun document pour l'instant.");
        return;
    }

    empty.classList.add('hidden');
    table.classList.remove('hidden');

    tbody.innerHTML = docs.map(doc => {
        const labels = getLabels(doc.id);
        const canManage = !!doc.is_owner;
        const canShare = !!doc.can_share;
        const canConvertPdf = canManage && !isFolder(doc) && ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'].includes(ext(doc));
        const canValidateActRequest = !!(doc.act_validation && doc.act_validation.can_validate);
        const labelsHtml = labels.slice(0, 3).map(c =>
            `<span class="inline-flex items-center rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 text-[10px] font-semibold">${c}</span>`
        ).join('') + (labels.length > 3 ? `<span class="inline-flex items-center rounded-md bg-gray-50 text-gray-600 border border-gray-200 px-1.5 py-0.5 text-[10px] font-semibold">+${labels.length - 3}</span>` : '');

        return `<tr class="border-b hover:bg-gray-50 cursor-pointer" ondblclick="handleDblClick(event, '${doc.id}')">
            <td class="py-3 px-4">
                <div class="flex items-center gap-3">
                    <i class="${iconClass(doc)} text-lg"></i>
                    <div class="min-w-0">
                        <span class="font-medium text-gray-800 block truncate">${doc.title}</span>
                        ${canShare ? '' : `<span class="inline-flex items-center rounded-md bg-slate-100 text-slate-700 border border-slate-200 px-1.5 py-0.5 text-[10px] font-semibold">Partagé avec moi • ${doc.can_edit_content ? 'Modification' : 'Lecture seule'}</span>`}
                        ${(doc.is_courrier_depart_tagged && doc.next_courrier_depart_number)
                            ? `<span class="inline-flex items-center rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 px-1.5 py-0.5 text-[10px] font-semibold mt-1">N° courrier départ disponible: ${doc.next_courrier_depart_number}</span>`
                            : ''}
                        ${labels.length > 0 ? `<div class="mt-1 flex flex-wrap gap-1">${labelsHtml}</div>` : ''}
                    </div>
                </div>
            </td>
            <td class="py-3 px-4 ${colorClass(doc)}">${isFolder(doc) ? 'Dossier' : (ext(doc).toUpperCase() || 'Fichier')}</td>
            <td class="py-3 px-4 text-gray-500 text-xs">${isFolder(doc) ? '—' : fmtSize(doc.file_size)}</td>
            <td class="py-3 px-4 text-gray-600">${fmtDate(doc.updated_at || doc.created_at)}</td>
            <td class="py-3 px-4">
                <span class="text-xs px-2 py-0.5 rounded-full ${doc.status === 'signed' ? 'bg-green-100 text-green-700' : doc.status === 'sent' ? 'bg-emerald-100 text-emerald-700' : doc.status === 'draft' ? 'bg-gray-100 text-gray-600' : doc.status === 'archived' ? 'bg-orange-100 text-orange-700' : 'bg-indigo-100 text-indigo-700'}">
                    ${statusLabel[doc.status] || doc.status}
                </span>
            </td>
            <td class="py-3 px-4">
                <div data-row-actions="true" class="relative flex items-center justify-end gap-1">
                    ${canShare ? `<button onclick="event.stopPropagation(); openShareModal('${doc.id}')"
                            title="Partager ${doc.shares_count > 0 ? '('+doc.shares_count+')' : ''}"
                            class="h-8 px-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 flex items-center gap-1 transition text-xs">
                        <i class="fas fa-user-plus text-sm"></i>
                        ${doc.shares_count > 0 ? `<span class="bg-blue-100 text-blue-700 rounded-full px-1.5 py-0.5 text-[10px] font-bold">${doc.shares_count}</span>` : ''}
                    </button>` : ''}
                    <button onclick="event.stopPropagation(); toggleActions('${doc.id}')"
                            title="Plus d'actions"
                            class="h-8 w-8 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 grid place-items-center transition">
                        <i class="fas fa-ellipsis-h text-sm"></i>
                    </button>
                    <div id="actions-${doc.id}" class="hidden absolute right-0 top-10 w-56 bg-white border border-gray-200 rounded-2xl shadow-2xl z-50 py-2">
                        <button onclick="handleFavorite('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-star ${isFav(doc.id) ? 'text-amber-500' : 'text-gray-300'} w-4"></i>
                            ${isFav(doc.id) ? 'Retirer des favoris' : 'Ajouter aux favoris'}
                        </button>
                        <button onclick="handleDetails('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-info-circle text-gray-400 w-4"></i> Ouvrir les détails
                        </button>
                        <button onclick="handleLabels('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-tag text-gray-400 w-4"></i> Gérer les étiquettes
                        </button>
                        ${canManage ? '<hr class="my-1 border-gray-100">' : ''}
                        ${canManage ? `<button onclick="handleRename('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-pencil-alt text-gray-400 w-4"></i> Renommer
                        </button>
                        <button onclick="handleMove('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-folder-open text-gray-400 w-4"></i> Déplacer ou copier
                        </button>
                        <hr class="my-1 border-gray-100">` : ''}
                        ${canValidateActRequest ? `<button onclick="handleValidateActRequest('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-emerald-700 hover:bg-emerald-50">
                            <i class="fas fa-check-circle text-emerald-500 w-4"></i> Valider la demande d'acte
                        </button>` : ''}
                        ${canValidateActRequest ? '<hr class="my-1 border-gray-100">' : ''}
                        <a href="${ROUTES.download(doc.id)}" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-download text-gray-400 w-4"></i> Télécharger
                        </a>
                        ${canConvertPdf ? `<button onclick="handleConvertPdf('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-file-pdf text-red-400 w-4"></i> Convertir en PDF
                        </button>` : ''}
                        ${canManage ? '<hr class="my-1 border-gray-100">' : ''}
                        ${canManage ? `<button onclick="handleDelete('${doc.id}')" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fas fa-trash-alt text-red-400 w-4"></i> Supprimer
                        </button>` : ''}
                    </div>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ---- Double-clic ----
function handleDblClick(event, id) {
    if (event.target.closest('[data-row-actions="true"]')) return;
    const doc = allDocs.find(d => d.id === id);
    if (!doc) return;
    if (isFolder(doc)) { openFolderInTab(doc.title); return; }
    const e = ext(doc);
    const ooExts = ['docx','xlsx','pptx','odt','ods','odp','doc','xls','ppt','pdf'];
    if (ooExts.includes(e)) {
        openInOnlyOffice(id);
    } else {
        // txt, csv ou autre → télécharger
        window.location.href = ROUTES.download(id);
    }
}

// ---- Actions dropdown ----
function toggleActions(id) {
    if (openActionsId && openActionsId !== id) {
        const prev = document.getElementById(`actions-${openActionsId}`);
        if (prev) prev.classList.add('hidden');
    }
    const el = document.getElementById(`actions-${id}`);
    if (!el) return;
    el.classList.toggle('hidden');
    openActionsId = el.classList.contains('hidden') ? null : id;
}

document.addEventListener('mousedown', (e) => {
    if (!e.target.closest('[data-row-actions="true"]') && openActionsId) {
        const el = document.getElementById(`actions-${openActionsId}`);
        if (el) el.classList.add('hidden');
        openActionsId = null;
    }
    if (!e.target.closest('#newMenuWrapper')) {
        document.getElementById('newMenu').classList.add('hidden');
    }
});

// ---- Favoris ----
async function handleFavorite(id) {
    const data = await post(ROUTES.favorite(id));
    const idx = prefs.findIndex(p => p.documentId === id);
    if (idx >= 0) prefs[idx].isFavorite = data.is_favorite;
    else prefs.push({ documentId: id, isFavorite: data.is_favorite, labelCodes: [] });
    renderTable();
}

async function handleConvertPdf(id) {
    toggleActions(id);
    const doc = allDocs.find((d) => d.id === id);
    if (!doc) return;

    const confirmed = window.confirm(`Convertir « ${doc.title} » en PDF ?`);
    if (!confirmed) return;

    try {
        const resp = await fetch(ROUTES.convertPdf(id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
            body: JSON.stringify({}),
        });

        const data = await resp.json();
        if (!resp.ok || !data.ok) {
            showToast(data.message || 'Conversion PDF impossible.');
            return;
        }

        if (data.final_file_path) {
            if (data.title) {
                doc.title = data.title;
            }
            if (data.file_path) {
                doc.file_path = data.file_path;
            }
            doc.final_file_path = data.final_file_path;
            doc.mime_type = 'application/pdf';
        }
        if (typeof data.file_size !== 'undefined') {
            doc.file_size = Number(data.file_size) || doc.file_size;
        }

        renderTable();
        showToast(data.message || 'Document converti en PDF.');
    } catch (e) {
        showToast('Erreur lors de la conversion PDF.');
    }
}

async function handleValidateActRequest(id) {
    toggleActions(id);
    const doc = allDocs.find((d) => d.id === id);
    if (!doc || !doc.act_validation || !doc.act_validation.can_validate) {
        alert('Vous n\'êtes pas autorisé à valider cette demande.');
        return;
    }

    const trackingNumber = doc.act_validation.tracking_number || '';
    const ask = confirm(`Confirmer la validation de la demande ${trackingNumber ? 'N° ' + trackingNumber : ''} ?`);
    if (!ask) return;

    try {
        const resp = await fetch(ROUTES.validateActRequest(id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        });

        const data = await resp.json();
        if (!resp.ok || !data.ok) {
            throw new Error(data.message || 'La validation a échoué.');
        }

        doc.act_validation.can_validate = false;
        doc.act_validation.validated_at = new Date().toISOString();
        renderTable();
        alert(data.message || 'Validation enregistrée.');
    } catch (e) {
        alert(e.message || 'La validation a échoué.');
    }
}

// ---- Déplacer (modal) ----
function handleMove(id) {
    toggleActions(id);
    _moveDocId = id;
    const select = document.getElementById('moveSelect');
    // Remplir les dossiers existants
    const folders = allDocs.filter(d => isFolder(d)).map(d => d.title);
    select.innerHTML = '<option value="">Racine</option>' +
        folders.map(f => `<option value="${f}">${f}</option>`).join('');
    // Présélectionner le dossier courant
    const doc = allDocs.find(d => d.id === id);
    const cur = getFolder(doc);
    if (cur) select.value = cur;
    document.getElementById('moveNewFolder').value = '';
    document.getElementById('moveModal').classList.remove('hidden');
}
async function confirmMove() {
    const select = document.getElementById('moveSelect');
    const newFolder = document.getElementById('moveNewFolder').value.trim();
    const dest = newFolder || select.value;
    await post(ROUTES.move(_moveDocId), { folder: dest });
    const doc = allDocs.find(d => d.id === _moveDocId);
    doc.description = dest ? `Dossier: ${dest}` : null;
    document.getElementById('moveModal').classList.add('hidden');
    renderTable();
    showToast(dest ? `Déplacé vers « ${dest} »` : 'Déplacé à la racine');
}

// ---- Étiquettes (modal) ----
function handleLabels(id) {
    toggleActions(id);
    _labelsDocId = id;
    const doc = allDocs.find(d => d.id === id);
    document.getElementById('labelsDocTitle').textContent = doc.title;
    _labelsList = [...getLabels(id)];
    document.getElementById('labelNewInput').value = '';
    renderLabelChips();
    document.getElementById('labelsModal').classList.remove('hidden');
}
function renderLabelChips() {
    const container = document.getElementById('labelsChips');
    if (_labelsList.length === 0) {
        container.innerHTML = '<span class="text-xs text-gray-400 italic">Aucune étiquette</span>';
        return;
    }
    container.innerHTML = _labelsList.map((c, i) => `
        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-1 text-xs font-semibold">
            ${c}
            <button onclick="_labelsList.splice(${i},1);renderLabelChips()" class="ml-1 text-emerald-500 hover:text-red-500 font-bold leading-none">&times;</button>
        </span>`).join('');
}
function addLabelChip() {
    const input = document.getElementById('labelNewInput');
    const val = input.value.trim().toUpperCase();
    if (val && !_labelsList.includes(val)) { _labelsList.push(val); renderLabelChips(); }
    input.value = '';
    input.focus();
}
async function confirmLabels() {
    const data = await post(ROUTES.labels(_labelsDocId), { codes: _labelsList.join(',') });
    const idx = prefs.findIndex(p => p.documentId === _labelsDocId);
    if (idx >= 0) prefs[idx].labelCodes = data.label_codes;
    else prefs.push({ documentId: _labelsDocId, isFavorite: false, labelCodes: data.label_codes });
    document.getElementById('labelsModal').classList.add('hidden');
    renderTable();
    showToast('Étiquettes enregistrées');
}

// ---- Supprimer (modal) ----
function handleDelete(id) {
    // Fermer le menu déroulant d'abord
    const menu = document.getElementById(`actions-${id}`);
    if (menu) menu.classList.add('hidden');
    openActionsId = null;

    _deleteDocId = id;
    const doc = allDocs.find(d => d.id === id);
    document.getElementById('deleteDocTitle').textContent = doc.title;
    document.getElementById('deleteModal').classList.remove('hidden');
}
async function confirmDelete() {
    const btn = document.querySelector('#deleteModal button[onclick="confirmDelete()"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Suppression…'; }
    try {
        const resp = await fetch(ROUTES.destroy(_deleteDocId), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        });
        if (resp.ok) {
            const title = allDocs.find(d => d.id === _deleteDocId)?.title || 'Document';
            allDocs = allDocs.filter(d => d.id !== _deleteDocId);
            document.getElementById('deleteModal').classList.add('hidden');
            renderTable();
            showToast(`« ${title} » supprimé définitivement`);
        } else {
            const err = await resp.json().catch(() => ({}));
            showToast(err.message || 'Erreur lors de la suppression.');
            document.getElementById('deleteModal').classList.add('hidden');
        }
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Supprimer'; }
    }
}

// ---- Détails (modal) ----
async function handleDetails(id) {
    toggleActions(id);
    _detailsDocId = id;
    const doc = allDocs.find(d => d.id === id);
    const statusActions = document.getElementById('detailsStatusActions');
    document.getElementById('detailsDocTitle').textContent = doc.title;
    document.getElementById('details-type').textContent = isFolder(doc) ? 'Dossier' : (ext(doc).toUpperCase() || 'Fichier');
    document.getElementById('details-size').textContent = fmtSize(doc.file_size);
    document.getElementById('details-status').textContent = statusLabel[doc.status] || doc.status;
    document.getElementById('details-shares').textContent = doc.shares_count > 0 ? `${doc.shares_count} partage(s)` : 'Non partagé';
    document.getElementById('details-created').textContent = new Date(doc.created_at).toLocaleString('fr-FR');
    document.getElementById('details-updated').textContent = new Date(doc.updated_at || doc.created_at).toLocaleString('fr-FR');
    document.getElementById('detailsVersions').innerHTML = '<div class="text-xs text-gray-400 italic">Chargement…</div>';
    statusActions.classList.toggle('hidden', !doc.is_owner);
    document.getElementById('detailsModal').classList.remove('hidden');

    if (!doc.is_owner) {
        document.getElementById('detailsVersions').innerHTML = '<div class="text-xs text-gray-400 italic">Historique des versions disponible uniquement pour le proprietaire.</div>';
        return;
    }

    // Charger les versions
    try {
        const resp = await fetch(ROUTES.versions(id), { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } });
        const versions = await resp.json();
        const container = document.getElementById('detailsVersions');
        if (!versions.length) {
            container.innerHTML = '<div class="text-xs text-gray-400 italic">Aucune version enregistrée.</div>';
        } else {
            container.innerHTML = versions.map(v => `
                <div class="flex items-start gap-3 p-2 rounded-lg bg-gray-50 border border-gray-100">
                    <div class="h-6 w-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center flex-shrink-0">v${v.version}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-700 truncate">${v.change_log || 'Sans note'}</p>
                        <p class="text-xs text-gray-400">${v.created_at ? new Date(v.created_at).toLocaleString('fr-FR') : ''}</p>
                    </div>
                </div>
            `).join('');
        }
    } catch(e) {
        document.getElementById('detailsVersions').innerHTML = '<div class="text-xs text-red-400">Erreur lors du chargement.</div>';
    }
}

// ---- Changer statut (depuis modal détails) ----
async function handleChangeStatus(status) {
    if (!_detailsDocId) return;
    const doc = allDocs.find(d => d.id === _detailsDocId);
    if (!doc || !doc.is_owner) {
        showToast('Seul le proprietaire peut modifier le statut.');
        return;
    }
    const data = await post(ROUTES.status(_detailsDocId), { status });
    doc.status = data.status;
    document.getElementById('details-status').textContent = statusLabel[data.status] || data.status;
    renderTable();
    showToast(`Statut mis à jour : ${statusLabel[data.status] || data.status}`);
}

// ---- Toast ----
function showToast(msg, duration = 3000) {
    const toast = document.getElementById('toastMsg');
    document.getElementById('toastText').textContent = msg;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), duration);
}

// ---- Nouveau menu ----
function toggleNewMenu() {
    document.getElementById('newMenu').classList.toggle('hidden');
}

async function handleMenuAction(action) {
    document.getElementById('newMenu').classList.add('hidden');

    if (action === 'upload-files') { document.getElementById('fileInput').click(); return; }
    if (action === 'upload-folder') { document.getElementById('folderInput').click(); return; }

    const titles = {
        'new-folder':       null,
        'new-doc':          'Nouveau document.docx',
        'new-sheet':        'Nouvelle feuille.xlsx',
        'new-presentation': 'Nouvelle présentation.pptx',
        'new-pdf-form':     'Nouveau formulaire.pdf',
        'request-file':     `Demande de fichier ${new Date().toLocaleDateString('fr-FR')}`,
    };

    let title = titles[action];
    let type  = action === 'new-folder' ? 'folder' : action.replace('new-', '');

    if (action === 'new-folder') {
        title = window.prompt('Nom du dossier');
        if (!title || !title.trim()) return;
        title = title.trim();
    }

    let data;
    try {
        data = await post(ROUTES.createNew, { title, type, folder: activeFolderTab });
    } catch(err) {
        showToast(err.message || 'Erreur lors de la création.');
        return;
    }
    if (!data?.id) {
        showToast(data?.message || 'Erreur lors de la création.');
        return;
    }
    allDocs.unshift(data);
    renderTable();

    // Ouvrir immédiatement dans l'éditeur si c'est un fichier Office
    const e = (title || '').split('.').pop().toLowerCase();
    if (['docx','xlsx','pptx'].includes(e)) {
        openInOnlyOffice(data.id);
    }
}

// ---- Upload fichiers ----
async function uploadFile(file) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('title', file.name);
    if (activeFolderTab) fd.append('folder', activeFolderTab);
    const resp = await fetch(ROUTES.uploadAjax, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        body: fd,
    });
    const data = await resp.json();
    if (!resp.ok) {
        const msg = data?.errors?.file?.[0] || data?.message || 'Erreur lors de l\'envoi du fichier.';
        throw new Error(msg);
    }
    return data;
}

document.getElementById('folderInput').addEventListener('change', async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;
    const folderNames = new Set();
    files.forEach(file => {
        const rel = file.webkitRelativePath || '';
        const top = rel.split('/')[0];
        if (top) folderNames.add(top);
    });
    for (const folderName of folderNames) {
        try {
            const data = await post(ROUTES.createNew, { title: folderName, type: 'folder', folder: null });
            if (data?.id) allDocs.unshift(data);
            else showToast(data?.message || `Erreur création dossier « ${folderName} »`);
        } catch(err) { showToast(err.message || `Erreur création dossier « ${folderName} »`); }
    }

    const total = files.length;
    let done = 0;
    const progressWrap = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    progressWrap.classList.remove('hidden');
    progressBar.style.width = '0%';
    progressText.textContent = `0 / ${total}`;

    for (const file of files) {
        const rel = file.webkitRelativePath || '';
        const top = rel.split('/')[0] || null;
        try {
            const data = await uploadFile(file);
            if (data.id) allDocs.unshift({ id: data.id, title: data.title,
                description: top ? `Dossier: ${top}` : null,
                mime_type: data.mime_type, file_path: data.file_path,
                file_size: data.file_size || 0, shares_count: 0,
                status: 'draft', created_at: new Date().toISOString(), updated_at: new Date().toISOString() });
        } catch(err) { showToast(`${file.name} : ${err.message}`); }
        done++;
        progressBar.style.width = `${Math.round(done / total * 100)}%`;
        progressText.textContent = `${done} / ${total}`;
    }
    e.target.value = '';
    setTimeout(() => progressWrap.classList.add('hidden'), 1500);
    renderTable();
});

document.getElementById('fileInput').addEventListener('change', async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    const total = files.length;
    let done = 0;
    const progressWrap = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    progressWrap.classList.remove('hidden');
    progressBar.style.width = '0%';
    progressText.textContent = `0 / ${total}`;

    for (const file of files) {
        try {
            const data = await uploadFile(file);
            if (data.id) {
                allDocs.unshift({
                    id: data.id, title: data.title,
                    description: activeFolderTab ? `Dossier: ${activeFolderTab}` : null,
                    mime_type: data.mime_type, file_path: data.file_path,
                    file_size: data.file_size || 0, shares_count: 0,
                    status: 'draft',
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                });
            }
        } catch(err) { showToast(`${file.name} : ${err.message}`); }
        done++;
        progressBar.style.width = `${Math.round(done / total * 100)}%`;
        progressText.textContent = `${done} / ${total}`;
    }
    e.target.value = '';
    setTimeout(() => progressWrap.classList.add('hidden'), 1500);
    renderTable();
});

// ---- Modal partage ----
function openShareModal(id) {
    shareDocId = id;
    const doc = allDocs.find(d => d.id === id);
    if (!doc || !doc.is_owner) {
        showToast('Seul le proprietaire peut partager ce document.');
        return;
    }
    document.getElementById('shareDocTitle').textContent = doc.title;
    document.getElementById('shareStatus').classList.add('hidden');
    document.getElementById('shareExternalEmail').value = '';
    document.getElementById('shareFullName').value = '';
    document.getElementById('shareMatricule').value = '';
    document.getElementById('shareApplicantEmail').value = '';
    document.getElementById('shareApplicantPhone').value = '';
    document.getElementById('shareApplicantRib').value = '';
    document.getElementById('shareRibWrapper').classList.add('hidden');
    document.getElementById('shareTrackingNumber').value = '';
    document.getElementById('shareTrackingStatus').classList.add('hidden');
    unlockRecipientAdministration(document.getElementById('shareAdminId'), document.getElementById('shareAdminSector'));
    document.getElementById('shareHasDelay').checked = false;
    document.getElementById('delayFields').classList.add('hidden');
    resetInternalShareOptions();
    resetRecipientSectorFilter();
    filterRecipientAdministrations();
    document.getElementById('shareInternalTargetType').value = 'user';
    toggleInternalTargetType();
    setShareMode('internal');
    document.getElementById('shareModal').classList.remove('hidden');
}

function closeShareModal() {
    document.getElementById('shareModal').classList.add('hidden');
    shareDocId = null;
}

function setShareMode(mode) {
    shareMode = mode;
    ['internal','external','admin'].forEach(m => {
        document.getElementById(`share-${m}`).classList.add('hidden');
        const btn = document.getElementById(`sm-${m === 'admin' ? 'admin' : m}`);
    });
    document.getElementById(`share-${mode}`).classList.remove('hidden');

    // Styles boutons
    const styles = {
        internal: { active: 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm', inactive: 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100' },
        external: { active: 'bg-blue-100 border-blue-300 text-blue-800 shadow-sm', inactive: 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100' },
        admin:    { active: 'bg-green-100 border-green-300 text-green-800 shadow-sm', inactive: 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100' },
    };
    ['internal','external','admin'].forEach(m => {
        const btn = document.getElementById(`sm-${m}`);
        btn.className = `px-3 py-1.5 text-xs font-semibold rounded-md border ${mode === m ? styles[m].active : styles[m].inactive}`;
    });
}

function toggleDelay() {
    const checked = document.getElementById('shareHasDelay').checked;
    document.getElementById('delayFields').classList.toggle('hidden', !checked);
}

async function submitShare() {
    const statusEl = document.getElementById('shareStatus');
    const show = (msg, ok) => {
        statusEl.textContent = msg;
        statusEl.className = `mt-3 rounded-lg px-3 py-2 text-xs ${ok ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`;
        statusEl.classList.remove('hidden');
    };

    const hasDelay = document.getElementById('shareHasDelay').checked;
    const payload = {
        mode: shareMode,
        permission: document.getElementById('sharePermission').value,
        hasDelay,
        delayValue: hasDelay ? document.getElementById('shareDelayValue').value : null,
        delayUnit: hasDelay ? document.getElementById('shareDelayUnit').value : null,
    };

    if (shareMode === 'internal') {
        const internalTargetType = document.getElementById('shareInternalTargetType').value;
        payload.internalTargetType = internalTargetType;

        if (internalTargetType === 'user') {
            const userId = document.getElementById('shareInternalUserId').value;
            if (!userId) { show('Veuillez sélectionner un utilisateur interne.', false); return; }
            payload.internalUserId = userId;
        } else {
            const subEntityCode = document.getElementById('shareInternalSubEntityCode').value;
            if (!subEntityCode) { show('Veuillez sélectionner une entité sous tutelle.', false); return; }
            payload.internalSubEntityCode = subEntityCode;
        }
    } else if (shareMode === 'external') {
        const email = document.getElementById('shareExternalEmail').value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { show('Adresse email invalide.', false); return; }
        payload.recipientEmail = email;
    } else {
        const adminId = document.getElementById('shareAdminId').value;
        const trackingNumber = document.getElementById('shareTrackingNumber').value.trim();
        const fullName = document.getElementById('shareFullName').value.trim();
        const mat = document.getElementById('shareMatricule').value.trim();
        const email = document.getElementById('shareApplicantEmail').value.trim();
        const phone = document.getElementById('shareApplicantPhone').value.trim();
        const sector = (document.getElementById('shareAdminSector').value || '').toLowerCase();
        const rib = document.getElementById('shareApplicantRib').value.trim();
        if (!trackingNumber || !adminId || !fullName || !mat || !email) { show('Tous les champs sont obligatoires.', false); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { show('Adresse email invalide.', false); return; }
        if (sector === 'banques' && !rib) { show('Le RIB est obligatoire pour les établissements bancaires.', false); return; }
        payload.trackingNumber = trackingNumber;
        payload.recipientAdministrationId = adminId;
        payload.applicantFullName = fullName;
        payload.applicantMatricule = mat;
        payload.applicantEmail = email;
        if (phone) payload.applicantPhone = phone;
        if (rib) payload.applicantRib = rib;
    }

    await post(ROUTES.share(shareDocId), payload).then(data => {
        if (!data || data.ok !== true) {
            show(data?.message || 'Erreur lors du partage.', false);
            return;
        }

        const doc = allDocs.find(d => d.id === shareDocId);
        if (doc && data.shares_count !== undefined) {
            const parsedShares = Number.parseInt(data.shares_count, 10);
            doc.shares_count = Number.isNaN(parsedShares) ? doc.shares_count : parsedShares;
        }
        if (data.document_status && doc) doc.status = data.document_status;

        const suffix = data.created_shares ? ` (${data.created_shares} destinataire(s))` : '';
        show(data.message || `« ${doc?.title} » a été partagé avec succès${suffix}.`, true);
        renderTable();
    }).catch(() => show('Erreur lors du partage.', false));
}

// ---- OnlyOffice document editor ----
let _ooDocEditorInstance = null;

async function openInOnlyOffice(docId) {
    if (!OO_URL) {
        alert('Le serveur OnlyOffice n\'est pas configuré. Contactez l\'administrateur.');
        return;
    }
    const modal = document.getElementById('ooDocModal');
    const status = document.getElementById('ooDocStatus');
    const ph = document.getElementById('ooDocPlaceholder');
    modal.classList.remove('hidden');
    status.textContent = 'Chargement de l\'éditeur…';
    status.classList.remove('hidden');
    ph.innerHTML = '';

    // Détruire instance précédente
    if (_ooDocEditorInstance) {
        try { _ooDocEditorInstance.destroyEditor(); } catch(e) {}
        _ooDocEditorInstance = null;
    }

    let cfg;
    try {
        const resp = await fetch(ROUTES.onlyofficeConfig, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
            body: JSON.stringify({ document_id: docId }),
        });
        cfg = await resp.json();
        if (!resp.ok || cfg.error) throw new Error(cfg.error || 'Erreur config');
    } catch(err) {
        status.textContent = 'Erreur : ' + err.message;
        return;
    }

    // Charger api.js si pas déjà chargé
    if (!window.DocsAPI) {
        await new Promise((resolve, reject) => {
            const old = document.getElementById('oo-doc-api-script');
            if (old) old.remove();
            const s = document.createElement('script');
            s.id = 'oo-doc-api-script';
            s.src = cfg.ooUrl + '/web-apps/apps/api/documents/api.js';
            s.onload = resolve;
            s.onerror = () => reject(new Error('Impossible de charger api.js depuis ' + cfg.ooUrl));
            document.head.appendChild(s);
        }).catch(err => {
            status.textContent = 'Erreur : ' + err.message;
            return null;
        });
    }

    if (!window.DocsAPI) return;

    status.classList.add('hidden');

    const ooConfig = cfg.ooConfig || {
        document: {
            fileType: cfg.fileType,
            key:      cfg.key,
            title:    cfg.title,
            url:      cfg.url,
            permissions: { edit: true, download: true, print: true },
        },
        documentType: cfg.documentType,
        editorConfig: {
            mode: 'edit', lang: 'fr',
            callbackUrl: '',
            user: { id: 'u', name: '' },
            customization: { autosave: true, compactHeader: true },
        },
    };
    ooConfig.height = '100%';
    ooConfig.width = '100%';
    ooConfig.type = 'desktop';
    if (cfg.token) ooConfig.token = cfg.token;

    try {
        _ooDocEditorInstance = new DocsAPI.DocEditor('ooDocPlaceholder', ooConfig);
    } catch(err) {
        status.textContent = 'Erreur initialisation : ' + err.message;
        status.classList.remove('hidden');
    }
}

function closeOoDocModal() {
    document.getElementById('ooDocModal').classList.add('hidden');
    if (_ooDocEditorInstance) {
        try { _ooDocEditorInstance.destroyEditor(); } catch(e) {}
        _ooDocEditorInstance = null;
    }
    document.getElementById('ooDocPlaceholder').innerHTML = '';
}

function maybeOpenOnlyOfficeFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const docId = (params.get('open_oo') || '').trim();
    if (!docId) return;

    openInOnlyOffice(docId);

    params.delete('open_oo');
    const query = params.toString();
    const nextUrl = `${window.location.pathname}${query ? '?' + query : ''}${window.location.hash || ''}`;
    window.history.replaceState({}, '', nextUrl);
}

// Init
renderTable();
maybeOpenOnlyOfficeFromQuery();
</script>

<!-- Modal OnlyOffice Editeur de document -->
<div id="ooDocModal" class="hidden fixed inset-0 bg-black/60 z-50 flex flex-col">
    <div class="flex items-center justify-between px-4 py-2 bg-gray-900 text-white text-sm">
        <span class="font-semibold">Éditeur de document</span>
        <button onclick="closeOoDocModal()" class="h-8 px-3 rounded text-gray-300 hover:text-white hover:bg-gray-700 text-sm font-bold">&times; Fermer</button>
    </div>
    <div id="ooDocStatus" class="hidden px-4 py-2 text-sm text-yellow-200 bg-gray-800"></div>
    <div id="ooDocPlaceholder" class="flex-1 bg-white"></div>
</div>

@endsection
