<?php $__env->startSection('title', 'Workflows'); ?>
<?php $__env->startSection('page-title', 'Workflows'); ?>
<?php $__env->startSection('content'); ?>

<?php
$autoCreate = request('create');
$embedded = request('embedded') === '1';
$wfJson = $workflows->map(fn($w) => [
    'id'          => $w->id,
    'name'        => $w->name,
    'description' => $w->description,
    'status'      => $w->status,
    'created_by'  => $w->created_by,
    'docs_to_sign'  => $w->docs_to_sign ?? [],
    'attached_docs' => $w->attached_docs ?? [],
    'updated_at'  => $w->updated_at?->toISOString(),
    'steps'       => $w->steps->map(fn($s) => [
        'id'                 => $s->id,
        'name'               => $s->name,
        'type'               => $s->type,
        'order'              => $s->order,
        'assignee_id'        => $s->assignee_id,
        'requires_signature' => (bool)$s->requires_signature,
        'assignee'           => $s->assignee
            ? ['id' => $s->assignee->id, 'name' => $s->assignee->name, 'email' => $s->assignee->email]
            : null,
    ])->values(),
    'executions' => $w->executions->map(fn($e) => [
        'id'           => $e->id,
        'workflow_id'  => $e->workflow_id,
        'status'       => $e->status,
        'current_step' => $e->current_step,
        'document_id'  => $e->document_id,
        'signed_file_path' => $e->document?->signed_file_path,
    ])->values(),
])->values();

$usersJson = $users->map(fn($u) => [
    'id'    => $u->id,
    'name'  => $u->full_name ?: $u->name,
    'email' => $u->email,
])->values();

$docsJson = $documents->map(fn($d) => [
    'id'        => $d->id,
    'title'     => $d->title,
    'file_path' => $d->file_path,
    'mime_type' => $d->mime_type,
])->values();

$currentUserId = Auth::id();
?>

<style>
    #zm-viewer-shell {
        scrollbar-width: auto;
        scrollbar-color: #16a34a #dcfce7;
    }

    #zm-viewer-shell::-webkit-scrollbar {
        width: 16px;
    }

    #zm-viewer-shell::-webkit-scrollbar-track {
        background: #dcfce7;
        border-left: 1px solid #bbf7d0;
    }

    #zm-viewer-shell::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #22c55e 0%, #15803d 100%);
        border-radius: 999px;
        border: 3px solid #dcfce7;
        min-height: 56px;
    }

    #zm-viewer-shell::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #16a34a 0%, #166534 100%);
    }

    .zm-page-shell {
        position: relative;
        width: fit-content;
        margin: 0 auto;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
        background: #fff;
    }

    .zm-page-canvas,
    .zm-zone-layer {
        display: block;
    }

    .zm-zone-layer {
        position: absolute;
        inset: 0;
        cursor: crosshair;
    }

    .zm-page-badge {
        position: sticky;
        top: 12px;
        margin: 0 auto 8px;
        width: fit-content;
        z-index: 5;
        background: rgba(15, 23, 42, 0.78);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        border-radius: 999px;
        padding: 6px 12px;
        backdrop-filter: blur(4px);
    }
</style>


<div id="feedback-bar"  class="hidden p-3 bg-blue-100 text-blue-800 rounded-xl mb-4 text-sm font-medium"></div>
<div id="success-popup" class="hidden fixed top-4 right-4 z-[90] px-4 py-3 rounded-xl border border-green-200 bg-green-50 text-green-800 shadow-lg text-sm font-semibold"></div>


<div class="flex justify-end gap-2 mb-4">
    <button onclick="openCreateModal('template')"
        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
        <i class="fas fa-layer-group"></i> Nouveau Modèle
    </button>
    <button onclick="openCreateModal('workflow')"
        class="px-4 py-2 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
        <i class="fas fa-plus"></i> Nouveau Workflow
    </button>
</div>


<div class="flex justify-end mb-3 relative">
    <button onclick="toggleTileSettings()" id="tile-settings-btn"
        class="h-10 w-10 rounded-lg border border-gray-200 bg-white text-gray-600 hover:text-[#2453d6] hover:border-[#2453d6] flex items-center justify-center transition"
        title="Paramétrage des vignettes">
        <i class="fas fa-cog"></i>
    </button>
    <div id="tile-settings-dropdown"
        class="hidden absolute top-12 right-0 w-72 bg-white border border-gray-200 rounded-xl shadow-xl z-30 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 font-semibold text-gray-800 text-sm">Affichage des vignettes</div>
        <label class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 text-sm text-gray-800 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" id="tile-chk-attente" onchange="toggleTile('toValidate',this.checked)" class="h-4 w-4 rounded"> À signer / valider
        </label>
        <label class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 text-sm text-gray-800 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" id="tile-chk-brouillon" onchange="toggleTile('drafts',this.checked)" class="h-4 w-4 rounded"> Brouillons
        </label>
        <label class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 text-sm text-gray-800 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" id="tile-chk-encours" onchange="toggleTile('started',this.checked)" class="h-4 w-4 rounded"> Démarrés
        </label>
        <label class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 text-sm text-gray-800 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" id="tile-chk-termine" onchange="toggleTile('finished',this.checked)" class="h-4 w-4 rounded"> Terminés
        </label>
        <label class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 text-sm text-gray-800 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" id="tile-chk-rejete" onchange="toggleTile('stopped',this.checked)" class="h-4 w-4 rounded"> Rejetés
        </label>
        <label class="flex items-center gap-3 px-4 py-3 text-sm text-gray-800 cursor-pointer hover:bg-gray-50">
            <input type="checkbox" id="tile-chk-all" onchange="toggleAllTiles(this.checked)" class="h-4 w-4 rounded"> Tous les parapheurs
        </label>
    </div>
</div>


<section class="space-y-3 mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Parapheurs</h2>
    <div class="h-px bg-gray-200"></div>
    <div id="tiles-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 pt-1">
        <div id="tile-box-attente" class="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5] cursor-pointer hover:shadow-md transition" onclick="setFilter('En attente')">
            <div class="bg-[#e04934] text-white text-sm font-semibold px-3 py-1.5">À signer / valider</div>
            <div class="px-4 py-5 flex items-end justify-between">
                <span id="tile-attente" class="text-5xl text-gray-500 font-light">0</span>
                <i class="fas fa-file-signature text-gray-300 text-5xl"></i>
            </div>
        </div>
        <div id="tile-box-brouillon" class="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5] cursor-pointer hover:shadow-md transition" onclick="setFilter('Brouillon')">
            <div class="bg-[#9f9fa3] text-white text-sm font-semibold px-3 py-1.5">Brouillons</div>
            <div class="px-4 py-5 flex items-end justify-between">
                <span id="tile-brouillon" class="text-5xl text-gray-500 font-light">0</span>
                <i class="fas fa-file-alt text-gray-300 text-5xl"></i>
            </div>
        </div>
        <div id="tile-box-encours" class="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5] cursor-pointer hover:shadow-md transition" onclick="setFilter('En cours')">
            <div class="bg-[#dec10a] text-white text-sm font-semibold px-3 py-1.5">Démarrés</div>
            <div class="px-4 py-5 flex items-end justify-between">
                <span id="tile-encours" class="text-5xl text-gray-500 font-light">0</span>
                <i class="fas fa-spinner text-gray-300 text-5xl"></i>
            </div>
        </div>
        <div id="tile-box-termine" class="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5] cursor-pointer hover:shadow-md transition" onclick="setFilter('Terminé')">
            <div class="bg-[#95bc3d] text-white text-sm font-semibold px-3 py-1.5">Terminés</div>
            <div class="px-4 py-5 flex items-end justify-between">
                <span id="tile-termine" class="text-5xl text-gray-500 font-light">0</span>
                <i class="fas fa-check-circle text-gray-300 text-5xl"></i>
            </div>
        </div>
        <div id="tile-box-rejete" class="rounded-xl overflow-hidden border border-gray-200 bg-[#f5f5f5] cursor-pointer hover:shadow-md transition" onclick="setFilter('Rejeté')">
            <div class="bg-[#e04934] text-white text-sm font-semibold px-3 py-1.5">Rejetés</div>
            <div class="px-4 py-5 flex items-end justify-between">
                <span id="tile-rejete" class="text-5xl text-gray-500 font-light">0</span>
                <i class="fas fa-ban text-gray-300 text-5xl"></i>
            </div>
        </div>
    </div>
</section>


<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <h2 class="text-2xl font-bold text-gray-800">Workflows créés</h2>
        <input type="text" id="search-input" placeholder="Rechercher un workflow..."
            oninput="renderTable()"
            class="w-full md:w-80 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
    </div>
    <div class="flex flex-wrap gap-2 mb-4">
        <?php $__currentLoopData = ['Tous','En attente','En cours','Terminé','Rejeté','Brouillon']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <button onclick="setFilter('<?php echo e($lbl); ?>')" data-filter="<?php echo e($lbl); ?>"
            class="filter-btn px-3 py-1 rounded-full text-xs font-medium border transition-colors bg-white text-gray-600 border-gray-300">
            <?php echo e($lbl); ?>

        </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nom</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Propriétaire</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Modifié</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Statut</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Progression</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody id="workflow-tbody">
                <tr><td colspan="6" class="text-center py-10 text-gray-400 italic">Chargement...</td></tr>
            </tbody>
        </table>
    </div>
</div>


<div id="modal-main" class="hidden fixed inset-0 z-50 bg-black/40 flex items-start justify-center p-4 overflow-y-auto">
    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-gray-100 my-6">

        
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 id="modal-title" class="text-lg font-bold text-gray-900">Nouveau Workflow</h2>
                <p id="modal-subtitle" class="text-xs text-gray-400 hidden"></p>
                <div id="modal-signed-badge" class="hidden mt-1 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-semibold">
                    <i class="fas fa-badge-check"></i>
                    Document signé disponible
                </div>
            </div>
            <button onclick="closeModal()" class="h-8 w-8 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 text-xl leading-none transition">&times;</button>
        </div>

        
        <div class="px-6 py-3 border-b border-gray-100 bg-gray-50">
            <div id="stepper-row" class="flex items-center gap-2 flex-wrap"></div>
        </div>

        
        <div id="view-progress-bar" class="hidden px-6 py-3 border-b border-gray-100 bg-blue-50">
            <div class="flex items-center justify-between mb-1.5">
                <p class="text-xs font-semibold text-gray-700">Progression du traitement</p>
                <span id="view-status-badge" class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"></span>
            </div>
            <div class="w-full bg-white rounded-full h-2 border border-blue-100 overflow-hidden">
                <div id="view-progress-fill" class="h-2 bg-[#2453d6] transition-all" style="width:0%"></div>
            </div>
            <p id="view-step-label" class="text-xs text-gray-500 mt-1"></p>
        </div>

        
        <div id="modal-error-bar" class="hidden mx-6 mt-3 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0"></i>
            <span id="modal-error-text"></span>
        </div>

        
        <div class="px-6 py-5">
            <div id="modal-body" class="min-h-[200px]"></div>

            
            <div class="flex items-center justify-between pt-5 border-t border-gray-100 mt-5">
                <button id="btn-prev" onclick="prevStep()"
                    class="hidden px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 flex items-center gap-2 transition">
                    <i class="fas fa-arrow-left text-xs"></i> Précédent
                </button>
                <div class="flex gap-2 ml-auto">
                    <button onclick="closeModal()"
                        class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 transition">
                        Annuler
                    </button>
                    <button id="btn-next" onclick="nextStep()"
                        class="hidden px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-[#1f47bb] flex items-center gap-2 transition">
                        Suivant <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    <button id="btn-submit" onclick="submitForm()"
                        class="hidden px-5 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 flex items-center gap-2 transition">
                        <i class="fas fa-check"></i> <span id="btn-submit-label">Créer</span>
                    </button>
                    <button id="btn-close-view" onclick="closeModal()"
                        class="hidden px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-[#1f47bb] transition">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<div id="modal-detail" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-2xl max-h-[90vh] flex flex-col">

        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 id="detail-title" class="text-lg font-bold text-gray-900"></h2>
                <p id="detail-desc" class="text-xs text-gray-500 mt-0.5 hidden"></p>
            </div>
            <button onclick="closeDetail()" class="h-8 w-8 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 text-xl leading-none ml-4 transition">&times;</button>
        </div>

        <div class="px-6 py-3 border-b border-gray-100 bg-blue-50">
            <div class="flex items-center justify-between mb-1.5">
                <p class="text-xs font-semibold text-gray-700">Progression</p>
                <span id="detail-status-badge" class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"></span>
            </div>
            <div class="w-full bg-white rounded-full h-2 border border-blue-100 overflow-hidden">
                <div id="detail-progress" class="h-2 bg-[#2453d6] rounded-full transition-all" style="width:0%"></div>
            </div>
            <p id="detail-pct-label" class="text-xs text-gray-500 mt-1"></p>
        </div>

        <div id="detail-actions" class="px-6 py-3 border-b border-gray-100 gap-2 hidden flex">
            <button onclick="advanceWorkflow()"
                class="px-4 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700 flex items-center gap-1.5 transition">
                <i class="fas fa-step-forward"></i> Avancer l'étape
            </button>
            <button onclick="rejectWorkflow()"
                class="px-4 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 flex items-center gap-1.5 transition">
                <i class="fas fa-ban"></i> Rejeter
            </button>
        </div>

        <div class="flex border-b border-gray-100 px-6">
            <button onclick="setDetailTab('steps')" id="tab-steps"
                class="py-3 mr-6 text-sm font-medium border-b-2 border-[#2453d6] text-[#2453d6] transition-colors">
                Étapes du workflow
            </button>
            <button onclick="setDetailTab('documents')" id="tab-docs"
                class="py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors">
                Documents (0)
            </button>
        </div>

        <div id="detail-body" class="overflow-y-auto flex-1 px-6 py-4"></div>
    </div>
</div>


<div id="modal-delete" class="hidden fixed inset-0 z-[60] bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="flex items-center gap-3 mb-3">
            <div class="h-10 w-10 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fas fa-trash text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Supprimer le workflow</h3>
        </div>
        <p class="text-gray-600 text-sm mb-6">Cette action est irréversible. Le workflow et toutes ses étapes seront définitivement supprimés.</p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200 font-medium text-sm transition">Annuler</button>
            <button onclick="confirmDelete()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm transition">Supprimer</button>
        </div>
    </div>
</div>




<div id="zone-modal" class="hidden fixed inset-0 z-[200] bg-black/60 flex items-center justify-center p-4" onclick="if(event.target===this)closeZoneModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[96vw] h-[92vh] flex flex-col overflow-hidden">

        <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-4 bg-white flex-shrink-0">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-gray-800">Document PDF - Zones de signature</h2>
                <p id="zm-doc-name" class="text-sm text-gray-600 truncate mt-0.5"></p>
                <p id="zm-zone-count" class="text-sm text-gray-500">0 zone de signature à positionner</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap justify-end">
                <button type="button" onclick="zmAddDefaultZone()"
                    class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition">
                    Zone signature
                </button>
                <button type="button" onclick="zmResetZone()"
                    class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold transition">
                    Effacer zone
                </button>
                <button type="button" onclick="zmSaveZone()" id="zm-save-btn"
                    class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-semibold transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                    Enregistrer zone
                </button>
                <button type="button" onclick="zmSealZone()" id="zm-seal-btn"
                    class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                    Sceller zone
                </button>
                <button type="button" onclick="closeZoneModal()"
                    class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold transition">
                    Fermer
                </button>
            </div>
        </div>

        <div id="zm-viewer-shell" class="relative flex-1 bg-gray-100 overflow-y-auto overflow-x-hidden px-5 py-6">
            <div id="zm-pages" class="space-y-6"></div>

            <div id="zm-pdf-loading" class="absolute inset-0 flex items-center justify-center bg-gray-100/70">
                <div class="flex flex-col items-center gap-2 text-gray-500">
                    <i class="fas fa-spinner fa-spin text-3xl text-violet-600"></i>
                    <span class="text-sm">Chargement du PDF…</span>
                </div>
            </div>

            <div id="zm-pdf-error" class="hidden absolute inset-0 grid place-items-center bg-white p-6 text-center">
                <div>
                    <p class="text-sm font-semibold text-red-600 mb-2">Impossible de charger le PDF.</p>
                    <p class="text-xs text-gray-500 mb-3">Le document est inaccessible depuis cette URL.</p>
                    <a id="zm-open-new-tab" href="#" target="_blank" rel="noreferrer" class="text-sm font-semibold text-[#2453d6] underline">
                        Ouvrir le PDF dans un nouvel onglet
                    </a>
                </div>
            </div>
        </div>

        <input type="hidden" id="zm-zone-label" value="">
    </div>

</div>

<script>
window.__wfPdfJsWorker = '<?php echo e(asset('vendor/pdfjs/pdf.worker.min.js')); ?>';

function wfInitPdfJsWorker() {
    if (!window.pdfjsLib || !window.pdfjsLib.GlobalWorkerOptions) {
        return false;
    }
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = window.__wfPdfJsWorker;
    return true;
}

function wfLoadPdfJsFallback() {
    if (document.getElementById('wf-pdfjs-fallback')) {
        return;
    }

    const cdnScript = document.createElement('script');
    cdnScript.id = 'wf-pdfjs-fallback';
    cdnScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
    cdnScript.onload = function () {
        window.__wfPdfJsWorker = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        wfInitPdfJsWorker();
    };
    document.head.appendChild(cdnScript);
}
</script>
<script src="<?php echo e(asset('vendor/pdfjs/pdf.min.js')); ?>" onload="wfInitPdfJsWorker()" onerror="wfLoadPdfJsFallback()"></script>

<script>
// ── Données initiales ──────────────────────────────────────
let workflows   = <?php echo json_encode($wfJson, 15, 512) ?>;
const USERS     = <?php echo json_encode($usersJson, 15, 512) ?>;
const DOCUMENTS = <?php echo json_encode($docsJson, 15, 512) ?>;
const ME        = <?php echo json_encode($currentUserId, 15, 512) ?>;
const IS_EMBEDDED = <?php echo json_encode($embedded, 15, 512) ?>;
const CSRF      = '<?php echo e(csrf_token()); ?>';
const WORKFLOWS_BASE = '<?php echo e(url('/workflows')); ?>';
const WORKFLOW_TEMPLATES_BASE = '<?php echo e(url('/workflow-templates')); ?>';
const SIGNED_DOC_DOWNLOAD_TEMPLATE = <?php echo json_encode(route('signatures.signed-document', ['executionId' => '__EXEC_ID__']), 512) ?>;
let templates   = [];
let statusFilter = 'Tous';

// ── Préférences vignettes ──────────────────────────────────
let visibleTiles = loadTilePref();
function loadTilePref() {
    try { return Object.assign({toValidate:true,drafts:true,started:true,finished:true,stopped:true}, JSON.parse(localStorage.getItem('wf_tiles')||'{}')); }
    catch { return {toValidate:true,drafts:true,started:true,finished:true,stopped:true}; }
}
function saveTilePref() { localStorage.setItem('wf_tiles', JSON.stringify(visibleTiles)); }

// ── Helpers ────────────────────────────────────────────────
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function getUserName(id) {
    const u = USERS.find(x => x.id === id);
    return u ? u.name : (id ? id.slice(0,8)+'…' : '—');
}
function getDocTitle(id) {
    const d = DOCUMENTS.find(x => x.id === id);
    return d ? d.title : 'Document ' + (id||'').slice(0,8) + '…';
}

function setWorkflowName(value) {
    form.name = value || '';
}

function setWorkflowDescription(value) {
    form.description = value || '';
}

function setWorkflowNotifyEmails(value) {
    form.notifyEmails = value || '';
}

function setWorkflowNotifyCc(value) {
    form.notifyCc = value || '';
}

// ── Calcul statut ──────────────────────────────────────────
function getTracking(wf) {
    const execs = wf.executions || [];
    const total     = execs.length;
    const pending   = execs.filter(e => /pending|en_attente/i.test(e.status)).length;
    const inProg    = execs.filter(e => /in_progress|started/i.test(e.status)).length;
    const completed = execs.filter(e => /complet|approved|termine|valide/i.test(e.status)).length;
    const rejected  = execs.filter(e => /reject|arrete|stopped/i.test(e.status)).length;

    let label = 'Brouillon', cls = 'bg-gray-100 text-gray-700';
    if (total > 0) {
        if (rejected === total)     { label = 'Rejeté';     cls = 'bg-red-100 text-red-800'; }
        else if (completed === total) { label = 'Terminé';  cls = 'bg-green-100 text-green-800'; }
        else if (inProg > 0)        { label = 'En cours';  cls = 'bg-amber-100 text-amber-800'; }
        else if (pending > 0)       { label = 'En attente';cls = 'bg-blue-100 text-blue-800'; }
        else                        { label = 'En cours';  cls = 'bg-amber-100 text-amber-800'; }
    }
    const totalSteps = Math.max((wf.steps||[]).length, 1);
    const active = execs.find(e => /in_progress|pending/i.test(e.status)) || execs[0];
    const curStep = active ? Math.max(1, Math.min(Number(active.current_step||1), totalSteps)) : 0;
    const progressedSum = execs.reduce((sum, e) => {
        const status = String(e.status || '').toLowerCase();
        if (/complet|approved|termine|valide/.test(status)) return sum + totalSteps;
        if (/reject|arrete|stopped/.test(status)) {
            const s = Math.max(1, Math.min(Number(e.current_step || 1), totalSteps));
            return sum + (s - 1);
        }
        const s = Math.max(1, Math.min(Number(e.current_step || 1), totalSteps));
        return sum + (s - 1);
    }, 0);
    const pct = total > 0 ? Math.round((progressedSum / (total * totalSteps)) * 100) : 0;
    return { total, pending, inProg, completed, rejected, label, cls, pct, curStep, totalSteps };
}

function getCompletedSignedDownloadUrl(wf) {
    const completedExecWithSigned = (wf.executions || []).find((e) => {
        const s = String(e.status || '').toLowerCase();
        const isCompleted = /complet|approved|termine|valide/.test(s) || s === 'completed';
        return isCompleted && !!e.signed_file_path;
    });

    if (!completedExecWithSigned || !completedExecWithSigned.id) {
        return '';
    }

    return SIGNED_DOC_DOWNLOAD_TEMPLATE.replace('__EXEC_ID__', encodeURIComponent(completedExecWithSigned.id));
}

function computeTileCounts() {
    const c = { 'En attente':0, 'Brouillon':0, 'En cours':0, 'Terminé':0, 'Rejeté':0 };
    workflows.forEach(wf => { const t = getTracking(wf); if (c[t.label] !== undefined) c[t.label]++; });
    return c;
}

// ── Rendu table ────────────────────────────────────────────
function renderTable() {
    const q = (document.getElementById('search-input').value||'').toLowerCase();
    const filtered = workflows.filter(wf => {
        const t = getTracking(wf);
        if (statusFilter !== 'Tous' && t.label !== statusFilter) return false;
        if (q && !(wf.name||'').toLowerCase().includes(q)) return false;
        return true;
    });

    const tbody = document.getElementById('workflow-tbody');
    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400 italic">Aucun workflow trouvé</td></tr>`;
        return;
    }
    tbody.innerHTML = filtered.map((wf, idx) => {
        const t = getTracking(wf);
        const isOwner = wf.created_by === ME;
        const date = wf.updated_at ? new Date(wf.updated_at).toLocaleDateString('fr-FR') : '—';
        const rowBg = idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/60';
        const signedDownloadUrl = getCompletedSignedDownloadUrl(wf);
        const showSignedDownload = isOwner && !!signedDownloadUrl;
        return `<tr class="${rowBg} border-b border-gray-100 hover:bg-blue-50/30 transition-colors">
            <td class="px-4 py-3">
                <p class="text-sm font-semibold text-gray-900">${esc(wf.name)}</p>
                ${wf.description ? `<p class="text-xs text-gray-400 truncate max-w-xs">${esc(wf.description)}</p>` : ''}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">${isOwner ? '<span class="text-blue-600 font-medium">Moi</span>' : esc(getUserName(wf.created_by))}</td>
            <td class="px-4 py-3 text-sm text-gray-500">${date}</td>
            <td class="px-4 py-3">
                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold ${t.cls}">${t.label}</span>
                ${t.total > 0 ? `<div class="text-[10px] text-gray-400 mt-1">
                    ${t.pending > 0 ? `<span>Attente: ${t.pending}</span> ` : ''}
                    ${t.inProg > 0  ? `<span>En cours: ${t.inProg}</span> ` : ''}
                    ${t.completed > 0 ? `<span>Fini: ${t.completed}</span>` : ''}
                </div>` : ''}
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="w-20 bg-gray-200 rounded-full h-1.5">
                        <div class="bg-[#2453d6] h-1.5 rounded-full" style="width:${t.pct}%"></div>
                    </div>
                    <span class="text-xs text-gray-600 font-medium w-8">${t.pct}%</span>
                </div>
                <p class="text-[10px] text-gray-400 mt-0.5">
                    ${t.total > 0
                        ? `${t.completed}/${t.total} exec.${t.curStep > 0 ? ` · Étape ${t.curStep}/${t.totalSteps}` : ''}`
                        : 'Aucune exécution'}
                </p>
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1.5">
                    <button onclick='openDetail(${JSON.stringify(wf)})'
                        class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Détails">
                        <i class="fas fa-eye text-sm"></i>
                    </button>
                    ${showSignedDownload
                        ? `<a href="${signedDownloadUrl}" target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-green-300 text-green-700 bg-green-50 hover:bg-green-100 text-xs font-semibold transition" title="Télécharger le document signé">
                            <i class="fa-solid fa-file-pdf text-sm"></i>
                            Voir signé
                        </a>`
                        : `<button onclick='openViewMode(${JSON.stringify(wf)})'
                            class="p-1.5 text-purple-500 hover:bg-purple-50 rounded-lg transition" title="Vue lecture">
                            <i class="fas fa-expand text-sm"></i>
                        </button>
                        <button onclick='duplicateWorkflow("${wf.id}")'
                            class="p-1.5 text-green-500 hover:bg-green-50 rounded-lg transition" title="Dupliquer">
                            <i class="fas fa-copy text-sm"></i>
                        </button>`}
                    ${isOwner ? `<button onclick='openDeleteModal("${wf.id}")'
                        class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition" title="Supprimer">
                        <i class="fas fa-trash-alt text-sm"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderTiles() {
    const c = computeTileCounts();
    document.getElementById('tile-attente').textContent   = c['En attente'];
    document.getElementById('tile-brouillon').textContent = c['Brouillon'];
    document.getElementById('tile-encours').textContent   = c['En cours'];
    document.getElementById('tile-termine').textContent   = c['Terminé'];
    document.getElementById('tile-rejete').textContent    = c['Rejeté'];

    const map = {toValidate:'attente',drafts:'brouillon',started:'encours',finished:'termine',stopped:'rejete'};
    Object.entries(map).forEach(([key, sfx]) => {
        document.getElementById('tile-box-'+sfx).style.display = visibleTiles[key] ? '' : 'none';
        const chk = document.getElementById('tile-chk-'+sfx);
        if (chk) chk.checked = visibleTiles[key];
    });
    const allChk = document.getElementById('tile-chk-all');
    if (allChk) allChk.checked = Object.values(visibleTiles).every(Boolean);
}

function setFilter(label) {
    statusFilter = label;
    document.querySelectorAll('.filter-btn').forEach(btn => {
        const active = btn.dataset.filter === label;
        btn.className = `filter-btn px-3 py-1 rounded-full text-xs font-medium border transition-colors ${active
            ? 'bg-[#2453d6] text-white border-[#2453d6]'
            : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'}`;
    });
    renderTable();
}

function toggleTileSettings() { document.getElementById('tile-settings-dropdown').classList.toggle('hidden'); }
function toggleTile(key, val) { visibleTiles[key] = val; saveTilePref(); renderTiles(); }
function toggleAllTiles(val) { Object.keys(visibleTiles).forEach(k => visibleTiles[k] = val); saveTilePref(); renderTiles(); }
document.addEventListener('click', e => {
    const btn = document.getElementById('tile-settings-btn');
    const drp = document.getElementById('tile-settings-dropdown');
    if (btn && drp && !btn.contains(e.target) && !drp.contains(e.target)) drp.classList.add('hidden');
});

// ══════════════════════════════════════════════════════════
// MODAL CRÉATION
// ══════════════════════════════════════════════════════════
let creationMode = 'workflow';  // 'workflow' | 'template'
let isViewMode   = false;
let viewingWf    = null;
let currentStep  = 1;
var form         = emptyForm();
let isSubmitting = false;

function emptyForm() {
    return {
        templateId: '',
        name: '', description: '',
        validationSteps: [{id:1, approverId:''}],
        signatureSteps:  [{id:1, signerId:''}],
        docsToSign:   [],
        attachedDocs: [],
        docZones:     {},   // {docId: {page,x,y,width,height,label}}
        sealedDocZones: {}, // {docId: true}
        notifyEmail:  true,
        notifyEmails: '', notifyCc: '',
        notifyStages: {
            onValidationStep: true,
            onSignatureStep:  true,
            onApproved:       true,
            onRejected:       false,
            onCompleted:      true,
        },
        sendDownloadLink: true,
    };
}

// Nombre d'étapes selon le mode
function maxSteps() {
    if (isViewMode)                 return 3; // Général / Attribution / Opération
    if (creationMode === 'template') return 4; // Général / Attribution / Notifications / Résumé
    return 5;                                  // Général / Attribution / Documents / Notifications / Résumé
}

function stepLabel(n) {
    if (isViewMode) {
        return ['Général','Attribution','Opération'][n-1];
    }
    if (creationMode === 'template') {
        return ['Général','Attribution','Notifications','Résumé'][n-1];
    }
    return ['Général','Attribution','Documents','Notifications','Opération'][n-1];
}

// ── Ouvrir modal création ──────────────────────────────────
function openCreateModal(mode) {
    creationMode = mode;
    isViewMode   = false;
    viewingWf    = null;
    form         = emptyForm();
    currentStep  = 1;
    isSubmitting = false;
    document.getElementById('modal-title').textContent =
        mode === 'template' ? 'Nouveau Modèle de Workflow' : 'Nouveau Workflow';
    document.getElementById('modal-subtitle').classList.add('hidden');
    document.getElementById('modal-signed-badge').classList.add('hidden');
    document.getElementById('view-progress-bar').classList.add('hidden');
    document.getElementById('modal-main').classList.remove('hidden');
    renderStep();
}

// ── Ouvrir mode vue (lecture seule) ───────────────────────
function openViewMode(wf) {
    creationMode = 'workflow';
    isViewMode   = true;
    viewingWf    = wf;
    currentStep  = 1;

    // Pré-remplir le formulaire depuis le workflow
    const valSteps = (wf.steps||[]).filter(s => !s.requires_signature)
        .sort((a,b) => a.order-b.order)
        .map((s,i) => ({id:i+1, approverId: s.assignee_id||''}));
    const sigSteps = (wf.steps||[]).filter(s => s.requires_signature)
        .sort((a,b) => a.order-b.order)
        .map((s,i) => ({id:i+1, signerId: s.assignee_id||''}));

    form = emptyForm();
    form.name             = wf.name || '';
    form.description      = wf.description || '';
    form.validationSteps  = valSteps.length ? valSteps : [{id:1,approverId:''}];
    form.signatureSteps   = sigSteps.length ? sigSteps : [{id:1,signerId:''}];
    form.docsToSign       = wf.docs_to_sign || [];
    form.attachedDocs     = wf.attached_docs || [];

    // En-tête modal
    document.getElementById('modal-title').textContent = wf.name || 'Détails du workflow';
    const subEl = document.getElementById('modal-subtitle');
    const signedBadgeEl = document.getElementById('modal-signed-badge');
    if (wf.description) {
        subEl.textContent = wf.description;
        subEl.classList.remove('hidden');
    } else {
        subEl.classList.add('hidden');
    }

    const signedDownloadUrl = getCompletedSignedDownloadUrl(wf);
    if (signedBadgeEl) {
        signedBadgeEl.classList.toggle('hidden', !signedDownloadUrl);
    }

    // Barre de progression
    const t = getTracking(wf);
    const vpb = document.getElementById('view-progress-bar');
    vpb.classList.remove('hidden');
    document.getElementById('view-status-badge').className = `inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${t.cls}`;
    document.getElementById('view-status-badge').textContent = t.label;
    document.getElementById('view-progress-fill').style.width = t.pct + '%';
    document.getElementById('view-step-label').textContent =
        t.curStep > 0 ? `Étape actuelle: ${t.curStep}/${t.totalSteps}` : 'Aucune exécution démarrée';

    document.getElementById('modal-main').classList.remove('hidden');
    renderStep();
}

function closeModal() {
    if (IS_EMBEDDED && window.parent && window.parent !== window) {
        window.parent.postMessage({ source: 'wf-embedded', type: 'close' }, '*');
        return;
    }
    document.getElementById('modal-main').classList.add('hidden');
    document.getElementById('modal-signed-badge').classList.add('hidden');
    clearModalError();
    isViewMode = false; viewingWf = null;
}

// ── Rendu stepper + corps ──────────────────────────────────
function renderStep() {
    const max = maxSteps();

    // Stepper
    let stepperHtml = '';
    for (let n = 1; n <= max; n++) {
        const active = n === currentStep;
        const done   = n < currentStep;
        const cirCls = active
            ? 'bg-[#2453d6] text-white ring-2 ring-[#2453d6]/30'
            : done ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500';
        const txtCls = active ? 'text-[#2453d6] font-semibold' : done ? 'text-green-600' : 'text-gray-400';
        stepperHtml += `<div class="flex items-center gap-1.5">
            ${n > 1 ? `<div class="w-6 h-px ${done ? 'bg-green-400' : 'bg-gray-200'}"></div>` : ''}
            <span class="h-7 w-7 rounded-full inline-flex items-center justify-center text-xs font-bold ${cirCls}">
                ${done ? '✓' : n}
            </span>
            <span class="text-xs ${txtCls} whitespace-nowrap">${stepLabel(n)}</span>
        </div>`;
    }
    document.getElementById('stepper-row').innerHTML = stepperHtml;

    // Boutons navigation
    const btnPrev   = document.getElementById('btn-prev');
    const btnNext   = document.getElementById('btn-next');
    const btnSubmit = document.getElementById('btn-submit');
    const btnClose  = document.getElementById('btn-close-view');
    const lblSubmit = document.getElementById('btn-submit-label');

    btnPrev.classList.toggle('hidden', currentStep === 1);
    btnNext.classList.toggle('hidden', currentStep >= max || isViewMode);
    btnSubmit.classList.toggle('hidden', currentStep !== max || isViewMode || creationMode === 'workflow');
    btnClose.classList.toggle('hidden', !isViewMode);

    if (lblSubmit) {
        lblSubmit.textContent = creationMode === 'template' ? 'Créer le modèle' : 'Créer le workflow';
    }

    // Corps selon étape
    const body = document.getElementById('modal-body');

    // ── Étape 1 : Général ──────────────────────────────────
    if (currentStep === 1) {
        body.innerHTML = renderStepGeneral();
    }
    // ── Étape 2 : Attribution ──────────────────────────────
    else if (currentStep === 2) {
        body.innerHTML = renderStepAttribution();
    }
    // ── Étape 3 : Documents (workflow) / Opération (view) / Notifications (template) ──
    else if (currentStep === 3) {
        if (isViewMode) {
            body.innerHTML = renderStepOperation();
        } else if (creationMode === 'workflow') {
            body.innerHTML = renderStepDocuments();
        } else {
            // template step 3 = Notifications
            body.innerHTML = renderStepNotifications();
        }
    }
    // ── Étape 4 : Notifications (workflow) / Résumé (template) ──
    else if (currentStep === 4) {
        if (creationMode === 'workflow' && !isViewMode) {
            body.innerHTML = renderStepNotifications();
        } else if (creationMode === 'template' && !isViewMode) {
            body.innerHTML = renderStepSummary();
        }
    }
    // ── Étape 5 : Opération (workflow) ────────────────────────
    else if (currentStep === 5) {
        body.innerHTML = renderStepOperationCreate();
    }
}

// ── Étape 1 : Général ─────────────────────────────────────
function renderStepGeneral() {
    const ro = isViewMode;
    const tplOpts = templates.map(t =>
        `<option value="${t.id}" ${form.templateId===t.id?'selected':''}>${esc(t.name)}</option>`
    ).join('');

    return `<div class="space-y-4">
        ${creationMode === 'workflow' && !isViewMode ? `
        <div class="space-y-1.5">
            <label class="block text-sm font-semibold text-gray-700">
                <i class="fas fa-layer-group text-emerald-500 mr-1"></i> Utiliser un modèle (optionnel)
            </label>
            <select id="f-template" onchange="applyTemplate(this.value)"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
                <option value="">— Aucun modèle sélectionné —</option>
                ${tplOpts || '<option disabled>Aucun modèle disponible</option>'}
            </select>
            ${templates.length === 0 ? '<p class="text-xs text-gray-400">Créez des modèles pour les réutiliser ici.</p>' : ''}
        </div>
        <hr class="border-gray-100">` : ''}

        <div class="space-y-1.5">
            <label class="block text-sm font-semibold text-gray-700">
                ${creationMode === 'template' ? 'Nom du modèle' : 'Nom du workflow'} <span class="text-red-500">*</span>
            </label>
            <input type="text" id="f-name"
                value="${esc(form.name)}"
                ${ro ? 'readonly' : ''}
                oninput="setWorkflowName(this.value)"
                placeholder="${creationMode === 'template' ? 'Ex: Circuit de validation standard' : 'Ex: Approbation contrat fournisseur'}"
                class="w-full border ${ro ? 'bg-gray-50 border-gray-200' : 'border-gray-300 focus:ring-2 focus:ring-[#2453d6]'} rounded-lg px-3 py-2.5 text-sm focus:outline-none transition">
        </div>

        <div class="space-y-1.5">
            <label class="block text-sm font-semibold text-gray-700">Description</label>
            <textarea id="f-desc" rows="3"
                ${ro ? 'readonly' : ''}
                oninput="setWorkflowDescription(this.value)"
                placeholder="Décrivez l'objectif de ce ${creationMode === 'template' ? 'modèle' : 'workflow'}..."
                class="w-full border ${ro ? 'bg-gray-50 border-gray-200' : 'border-gray-300 focus:ring-2 focus:ring-[#2453d6]'} rounded-lg px-3 py-2.5 text-sm focus:outline-none transition resize-none">${esc(form.description)}</textarea>
        </div>
    </div>`;
}

// ── Étape 2 : Attribution ─────────────────────────────────
function renderStepAttribution() {
    return `<div class="space-y-5">${renderValidationBlock()}${renderSignatureBlock()}</div>`;
}

function renderValidationBlock() {
    const ro = isViewMode;
    const rows = form.validationSteps.map((s,i) => `
        <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-green-200/60 shadow-sm">
            <span class="h-9 w-9 rounded-full bg-green-600 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">${i+1}</span>
            <select ${ro?'disabled':''} data-val-step="${i}" onchange="setValidationAssignee(${i}, this.value)" oninput="setValidationAssignee(${i}, this.value)"
                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 ${ro?'bg-gray-50':''}">
                <option value="">— Choisir un validateur —</option>
                ${USERS.map(u=>`<option value="${u.id}" ${String(getValidationAssignee(s))===String(u.id)?'selected':''}>${esc(u.name)} · ${esc(u.email)}</option>`).join('')}
            </select>
            ${!ro && form.validationSteps.length > 1
                ? `<button type="button" onclick="removeValStep(${i})" class="p-2 text-red-400 hover:bg-red-50 rounded-lg transition" title="Retirer"><i class="fas fa-times text-sm"></i></button>`
                : ''}
        </div>`).join('');

    return `<div class="bg-green-50/70 border border-green-200 rounded-xl p-4 space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 bg-green-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check text-white text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Étapes de validation</h3>
                    <p class="text-xs text-gray-500">Les validateurs approuvent le document</p>
                </div>
            </div>
            ${!ro ? `<button type="button" onclick="addValStep()"
                class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg flex items-center gap-1 transition">
                <i class="fas fa-plus text-xs"></i> Ajouter
            </button>` : ''}
        </div>
        <div class="space-y-2">${rows}</div>
    </div>`;
}

function renderSignatureBlock() {
    const ro = isViewMode;
    const rows = form.signatureSteps.map((s,i) => `
        <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-blue-200/60 shadow-sm">
            <span class="h-9 w-9 rounded-full bg-[#2453d6] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">${i+1}</span>
            <select ${ro?'disabled':''} data-sig-step="${i}" onchange="setSignatureAssignee(${i}, this.value)" oninput="setSignatureAssignee(${i}, this.value)"
                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400 ${ro?'bg-gray-50':''}">
                <option value="">— Choisir un signataire —</option>
                ${USERS.map(u=>`<option value="${u.id}" ${String(getSignatureAssignee(s))===String(u.id)?'selected':''}>${esc(u.name)} · ${esc(u.email)}</option>`).join('')}
            </select>
            ${!ro && form.signatureSteps.length > 1
                ? `<button type="button" onclick="removeSigStep(${i})" class="p-2 text-red-400 hover:bg-red-50 rounded-lg transition" title="Retirer"><i class="fas fa-times text-sm"></i></button>`
                : ''}
        </div>`).join('');

    return `<div class="bg-blue-50/70 border border-blue-200 rounded-xl p-4 space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 bg-[#2453d6] rounded-lg flex items-center justify-center">
                    <i class="fas fa-pen-nib text-white text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Étapes de signature</h3>
                    <p class="text-xs text-gray-500">Les signataires apposent leur signature électronique</p>
                </div>
            </div>
            ${!ro ? `<button type="button" onclick="addSigStep()"
                class="px-3 py-1.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-xs font-semibold rounded-lg flex items-center gap-1 transition">
                <i class="fas fa-plus text-xs"></i> Ajouter
            </button>` : ''}
        </div>
        <div class="space-y-2">${rows}</div>
    </div>`;
}

function addValStep()     { form.validationSteps.push({id:Date.now(), approverId:''}); renderStep(); }
function removeValStep(i) { if (form.validationSteps.length > 1) { form.validationSteps.splice(i,1); renderStep(); } }
function addSigStep()     { form.signatureSteps.push({id:Date.now(), signerId:''}); renderStep(); }
function removeSigStep(i) { if (form.signatureSteps.length > 1) { form.signatureSteps.splice(i,1); renderStep(); } }

function getValidationAssignee(step) {
    return step?.approverId || step?.approver_id || '';
}

function getSignatureAssignee(step) {
    return step?.signerId || step?.signer_id || '';
}

function setValidationAssignee(index, value) {
    if (!form.validationSteps[index]) return;
    form.validationSteps[index].approverId = value || '';
    form.validationSteps[index].approver_id = value || '';
}

function setSignatureAssignee(index, value) {
    if (!form.signatureSteps[index]) return;
    form.signatureSteps[index].signerId = value || '';
    form.signatureSteps[index].signer_id = value || '';
}

function syncAttributionFromDom() {
    document.querySelectorAll('select[data-val-step]').forEach((el) => {
        const i = Number(el.getAttribute('data-val-step'));
        if (Number.isInteger(i) && form.validationSteps[i]) {
            setValidationAssignee(i, el.value || '');
        }
    });

    document.querySelectorAll('select[data-sig-step]').forEach((el) => {
        const i = Number(el.getAttribute('data-sig-step'));
        if (Number.isInteger(i) && form.signatureSteps[i]) {
            setSignatureAssignee(i, el.value || '');
        }
    });
}

// ── Étape 3 : Documents (workflow seulement) ───────────────
let docTab = 'mes-docs'; // 'mes-docs' | 'ordinateur'
const UPLOAD_AJAX_URL = '<?php echo e(route("documents.uploadAjax")); ?>';

function setDocTab(tab) {
    docTab = tab;
    const t1 = document.getElementById('doc-tab-mes');
    const t2 = document.getElementById('doc-tab-upload');
    const p1 = document.getElementById('doc-panel-mes');
    const p2 = document.getElementById('doc-panel-upload');
    if (!t1) return;
    const activeTab  = 'py-2 px-4 text-sm font-semibold border-b-2 border-[#2453d6] text-[#2453d6] transition-colors';
    const inactiveTab = 'py-2 px-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors';
    t1.className = tab === 'mes-docs'   ? activeTab : inactiveTab;
    t2.className = tab === 'ordinateur' ? activeTab : inactiveTab;
    p1.classList.toggle('hidden', tab !== 'mes-docs');
    p2.classList.toggle('hidden', tab !== 'ordinateur');
}

async function uploadDocFile(input) {
    const file = input.files[0];
    if (!file) return;
    const label = document.getElementById('upload-doc-label');
    if (label) label.textContent = '⏳ Upload en cours…';
    const fd = new FormData();
    fd.append('file', file);
    fd.append('title', file.name.replace(/\.[^.]+$/, ''));
    try {
        const resp = await fetch(UPLOAD_AJAX_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF },
            body: fd,
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.message || 'Erreur upload');
        // Ajouter au DOCUMENTS local
        if (!DOCUMENTS.find(d => d.id === data.id)) {
            DOCUMENTS.push({ id: data.id, title: data.title, file_path: data.file_path, mime_type: data.mime_type });
        }
        if (!form.docsToSign.includes(data.id)) {
            form.docsToSign.push(data.id);
        }
        input.value = '';
        renderStep();
    } catch(e) {
        if (label) label.textContent = '❌ ' + (e.message || 'Erreur upload');
    }
}

function renderStepDocuments() {
    const docOpts = DOCUMENTS.map(d =>
        `<option value="${d.id}">${esc(d.title)}</option>`
    ).join('');
    const requiredZones = Math.max(1, form.signatureSteps.filter(s => s.signerId).length);

    const toSignRows = form.docsToSign.map(id => {
        const doc  = DOCUMENTS.find(d => d.id === id);
        const zones = Array.isArray(form.docZones[id])
            ? form.docZones[id]
            : form.docZones[id]
                ? [form.docZones[id]]
                : [];
        const isSealed = Boolean(form.sealedDocZones[id]);
        const zoneCount = zones.length;
        const isPdf = !doc || !doc.mime_type || doc.mime_type === 'application/pdf';
        const sizeKb = doc && doc.file_size ? (doc.file_size / 1024).toFixed(0) + ' KB' : '';
        const zoneTxt = zoneCount > 0
            ? `<span class="${isSealed ? 'text-emerald-700' : zoneCount === requiredZones ? 'text-blue-700' : 'text-purple-600'} font-semibold">${zoneCount}/${requiredZones} zone(s)${isSealed ? ' • Scellée' : ''}</span>`
            : `<span class="text-amber-500 italic">0/${requiredZones} zone</span>`;
        return `
        <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-3 py-2.5">
            <div class="h-9 w-9 flex-shrink-0 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-pdf text-red-500 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">${esc(getDocTitle(id))}</p>
                <p class="text-xs text-gray-400">${sizeKb}${sizeKb ? ' • ' : ''}${zoneTxt}</p>
            </div>
            ${isPdf ? (isSealed
                ? `<button type="button" disabled
                    class="flex-shrink-0 px-3 py-1.5 rounded-lg bg-gray-100 text-gray-500 text-xs font-semibold cursor-not-allowed flex items-center gap-1.5">
                    <i class="fas fa-lock"></i> Scellée
                </button>`
                : `<button type="button" onclick="openZoneModal('${id}')"
                    class="flex-shrink-0 px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="fas fa-vector-square"></i> Positionner
                </button>`)
            : ''}
            <button type="button" onclick="removeDocToSign('${id}')"
                class="flex-shrink-0 h-8 w-8 flex items-center justify-center rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                <i class="fas fa-trash text-xs"></i>
            </button>
        </div>`;
    }).join('');

    const attachRows = form.attachedDocs.map(id => `
        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2">
            <i class="fas fa-paperclip text-emerald-500 flex-shrink-0"></i>
            <span class="flex-1 text-sm text-gray-800 truncate">${esc(getDocTitle(id))}</span>
            <button type="button" onclick="removeAttached('${id}')"
                class="text-red-400 hover:text-red-600 text-xs px-2 py-1 hover:bg-red-50 rounded transition">
                <i class="fas fa-times"></i>
            </button>
        </div>`).join('');

    return `<div class="space-y-5">

        <!-- Documents à signer -->
        <div class="border border-red-200 bg-red-50/50 rounded-xl p-4 space-y-3">
            <div class="flex items-center gap-2">
                <div class="h-7 w-7 bg-red-500 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-signature text-white text-xs"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Documents à signer</h3>
                    <p class="text-xs text-gray-500">Sélectionnez les documents qui nécessitent une signature</p>
                </div>
            </div>

            <!-- Onglets source -->
            <div class="flex border-b border-red-200">
                <button type="button" id="doc-tab-mes"
                    onclick="setDocTab('mes-docs')"
                    class="py-2 px-4 text-sm font-semibold border-b-2 border-[#2453d6] text-[#2453d6] transition-colors">
                    <i class="fas fa-folder-open mr-1"></i>Mes Documents
                </button>
                <button type="button" id="doc-tab-upload"
                    onclick="setDocTab('ordinateur')"
                    class="py-2 px-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-upload mr-1"></i>Depuis mon ordinateur
                </button>
            </div>

            <!-- Panneau Mes Documents -->
            <div id="doc-panel-mes">
                ${DOCUMENTS.length > 0
                    ? `<select onchange="addDocToSign(this)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
                        <option value="">+ Ajouter un document à signer</option>
                        ${docOpts}
                      </select>`
                    : '<p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"><i class="fas fa-info-circle mr-1"></i>Aucun document disponible. Utilisez l\'onglet "Depuis mon ordinateur".</p>'}
            </div>

            <!-- Panneau Upload ordinateur -->
            <div id="doc-panel-upload" class="hidden">
                <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-red-300 rounded-xl cursor-pointer bg-white hover:bg-red-50 transition">
                    <input type="file" class="hidden" accept=".pdf,.docx,.xlsx,.pptx,.odt" onchange="uploadDocFile(this)">
                    <i class="fas fa-cloud-upload-alt text-3xl text-red-400 mb-2"></i>
                    <span id="upload-doc-label" class="text-xs text-gray-500 font-medium">Cliquez ou glissez un fichier ici</span>
                    <span class="text-xs text-gray-400 mt-0.5">PDF, DOCX, XLSX — max 50 Mo</span>
                </label>
            </div>

            <div class="space-y-2">${toSignRows}</div>
            <p class="text-xs text-gray-500 font-medium">${form.docsToSign.length} document(s) sélectionné(s)</p>
        </div>

        <!-- Pièces jointes -->
        <div class="border border-emerald-200 bg-emerald-50/50 rounded-xl p-4 space-y-3">
            <div class="flex items-center gap-2">
                <div class="h-7 w-7 bg-emerald-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-paperclip text-white text-xs"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Pièces jointes</h3>
                    <p class="text-xs text-gray-500">Documents annexes, sans signature requise</p>
                </div>
            </div>
            ${DOCUMENTS.length > 0 ? `
            <select onchange="addAttached(this)"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white">
                <option value="">+ Ajouter une pièce jointe</option>
                ${docOpts}
            </select>` : ''}
            <div class="space-y-2">${attachRows}</div>
            <p class="text-xs text-gray-500 font-medium">${form.attachedDocs.length} document(s) sélectionné(s)</p>
        </div>
    </div>`;
}

function addDocToSign(sel) {
    const id = sel.value;
    if (id && !form.docsToSign.includes(id)) { form.docsToSign.push(id); renderStep(); } else { sel.value = ''; }
}
function removeDocToSign(id) {
    form.docsToSign = form.docsToSign.filter(x => x !== id);
    delete form.docZones[id];
    delete form.sealedDocZones[id];
    renderStep();
}
function addAttached(sel) {
    const id = sel.value;
    if (id && !form.attachedDocs.includes(id)) { form.attachedDocs.push(id); renderStep(); } else { sel.value = ''; }
}

// ── Positionnement de zone de signature (lecteur intégré) ───────────────
const zm = {
    docId: null,
    pdfDoc: null,
    scale: 1.28,
    zones: [],
    selectedZoneIndex: -1,
    drawing: false,
    dragMode: null,
    sx: 0,
    sy: 0,
    dragOffsetX: 0,
    dragOffsetY: 0,
    activePage: 1,
    renderToken: 0,
    handleSize: 18,
    originRect: null,
};

function getRequiredSignatureZoneCount() {
    return Math.max(1, form.signatureSteps.filter(s => getSignatureAssignee(s)).length);
}

function getDocZones(docId) {
    const zones = form.docZones[docId];
    if (Array.isArray(zones)) return zones;
    return zones ? [zones] : [];
}

function zmNormalizeZone(zone, index = 0) {
    if (!zone || typeof zone !== 'object') return null;
    const page = Number(zone.page || 1);
    const normalized = {
        page: Number.isFinite(page) && page > 0 ? page : 1,
        label: zone.label || `Signature ${index + 1}`,
    };

    // Normaliser les noms des propriétés : utiliser TOUJOURS w et h (pas width/height)
    if (zone.width !== undefined && zone.height !== undefined) {
        // Format ancien avec width/height (pourcentages)
        normalized.x = parseFloat(zone.x ?? 0);
        normalized.y = parseFloat(zone.y ?? 0);
        normalized.w = parseFloat(zone.width ?? 20);
        normalized.h = parseFloat(zone.height ?? 10);
        normalized._pct = true;
        return normalized;
    }

    // Format standard avec w/h (pixels)
    normalized.x = parseFloat(zone.x ?? 0);
    normalized.y = parseFloat(zone.y ?? 0);
    normalized.w = parseFloat(zone.w ?? 120);
    normalized.h = parseFloat(zone.h ?? 52);
    normalized._pct = false;
    return normalized;
}

function zmStoreSelectedLabel() {
    if (zm.selectedZoneIndex < 0 || !zm.zones[zm.selectedZoneIndex]) return;
    zm.zones[zm.selectedZoneIndex].label = document.getElementById('zm-zone-label').value.trim() || `Signature ${zm.selectedZoneIndex + 1}`;
}

function zmCreateDefaultZone(pageNumber, index) {
    const lastPage = Number(zm.pdfDoc?.numPages || pageNumber || 1);
    const targetPage = Number.isFinite(lastPage) && lastPage > 0 ? lastPage : 1;
    const zoneC = zmGetZoneCanvas(targetPage) || zmGetZoneCanvas(1);
    if (!zoneC) return null;

    const w = Math.max(120, Math.round(zoneC.width * 0.22));
    const h = Math.max(52, Math.round(zoneC.height * 0.1));
    const baseX = Math.max(10, Math.round(zoneC.width * 0.08));

    const stepY = Math.max(60, Math.round(h * 1.2));
    const maxY = Math.max(40, zoneC.height - h - 40);
    const minY = Math.max(40, Math.round(zoneC.height * 0.12));

    return {
        page: Number(targetPage),
        x: baseX,
        y: Math.max(minY, maxY - (index * stepY)),
        w,
        h,
        _pct: false,
        label: `Signature ${index + 1}`,
    };
}

function zmEnsureRequiredZones() {
    const required = getRequiredSignatureZoneCount();
    while (zm.zones.length < required) {
        const zone = zmCreateDefaultZone(zm.activePage || 1, zm.zones.length);
        if (!zone) break;
        zm.zones.push(zone);
    }
    if (zm.zones.length > required) {
        zm.zones = zm.zones.slice(0, required);
    }
    if (zm.zones.length > 0 && zm.selectedZoneIndex < 0) {
        zm.selectedZoneIndex = 0;
    }
    if (zm.selectedZoneIndex >= zm.zones.length) {
        zm.selectedZoneIndex = zm.zones.length - 1;
    }
}

function zmUpdateHeader(docName) {
    document.getElementById('zm-doc-name').textContent = docName || '';
    const required = getRequiredSignatureZoneCount();
    const current = zm.zones.length;
    document.getElementById('zm-zone-count').textContent = `${current}/${required} zone(s) de signature`;
    document.getElementById('zm-save-btn').disabled = current === 0;
    document.getElementById('zm-seal-btn').disabled = current !== required;
}

function zmShowDebug() {
    return;
}

function zmUpdateDebug() {
    return;
}

function zmGetInlineUrl(docId) {
    return `<?php echo e(url('/documents')); ?>/${encodeURIComponent(docId)}/download?inline=1&t=${Date.now()}`;
}

function zmGetZoneCanvas(pageNumber) {
    return document.querySelector(`.zm-zone-layer[data-page="${pageNumber}"]`);
}

function zmSyncOverlaySize() {
    const shells = document.querySelectorAll('.zm-page-shell');
    console.log('[ZM] Syncing overlay size for', shells.length, 'pages');
    shells.forEach((pageShell) => {
        const pageNumber = Number(pageShell.dataset.page || '0');
        const pageCanvas = pageShell.querySelector('.zm-page-canvas');
        const zoneCanvas = zmGetZoneCanvas(pageNumber);
        if (!pageCanvas || !zoneCanvas) {
            console.warn('[ZM] Page', pageNumber, '- missing canvas:', !pageCanvas ? 'pdf' : 'zone');
            return;
        }
        zoneCanvas.width = pageCanvas.width;
        zoneCanvas.height = pageCanvas.height;
        zoneCanvas.style.width = pageCanvas.width + 'px';
        zoneCanvas.style.height = pageCanvas.height + 'px';
        console.log('[ZM] Page', pageNumber, '- synced to', zoneCanvas.width, 'x', zoneCanvas.height);
    });
}

async function zmRenderDocument() {
    if (!zm.pdfDoc) return;
    const pagesRoot = document.getElementById('zm-pages');
    const loading = document.getElementById('zm-pdf-loading');
    const token = ++zm.renderToken;
    pagesRoot.innerHTML = '';

    for (let pageNumber = 1; pageNumber <= zm.pdfDoc.numPages; pageNumber++) {
        if (token !== zm.renderToken) return;
        const page = await zm.pdfDoc.getPage(pageNumber);
        const viewport = page.getViewport({ scale: zm.scale });
        const wrapper = document.createElement('div');
        wrapper.className = 'space-y-2';
        wrapper.innerHTML = `
            <div class="zm-page-badge">Page ${pageNumber}</div>
            <div class="zm-page-shell" data-page="${pageNumber}">
                <canvas class="zm-page-canvas"></canvas>
                <canvas class="zm-zone-layer" data-page="${pageNumber}"></canvas>
            </div>
        `;
        pagesRoot.appendChild(wrapper);

        const pageShell = wrapper.querySelector('.zm-page-shell');
        const pageCanvas = wrapper.querySelector('.zm-page-canvas');
        const zoneCanvas = wrapper.querySelector('.zm-zone-layer');
        pageCanvas.width = viewport.width;
        pageCanvas.height = viewport.height;
        zoneCanvas.width = viewport.width;
        zoneCanvas.height = viewport.height;
        zoneCanvas.style.width = viewport.width + 'px';
        zoneCanvas.style.height = viewport.height + 'px';
        zoneCanvas.onmousedown = zmMouseDown;
        zoneCanvas.onmousemove = zmMouseMove;
        zoneCanvas.onmouseup = zmMouseUp;
        zoneCanvas.onmouseleave = null;

        await page.render({ canvasContext: pageCanvas.getContext('2d'), viewport }).promise;
        pageShell.style.width = viewport.width + 'px';
        pageShell.style.height = viewport.height + 'px';
    }

    loading.classList.add('hidden');
    zmSyncOverlaySize();
    zmEnsureRequiredZones();
    zmUpdateHeader(document.getElementById('zm-doc-name').textContent || '');
    zmDrawZone();
    if (zm.selectedZoneIndex >= 0) {
        document.getElementById('zm-zone-label').value = zm.zones[zm.selectedZoneIndex]?.label || '';
    }
    const targetPage = zm.zones[zm.selectedZoneIndex]?.page;
    if (targetPage) {
        const shell = document.querySelector(`.zm-page-shell[data-page="${targetPage}"]`);
        const viewer = document.getElementById('zm-viewer-shell');
        if (shell && viewer) {
            viewer.scrollTop = shell.offsetTop - 40;
        } else if (shell) {
            shell.scrollIntoView({ block: 'center' });
        }
    }
}

function openZoneModal(docId) {
    const doc = DOCUMENTS.find(d => d.id === docId);
    if (!doc || form.sealedDocZones[docId]) return;

    zm.docId = docId;
    zm.pdfDoc = null;
    const rawZones = getDocZones(docId);
    console.log('[ZM OPEN] Raw zones:', rawZones.length, rawZones.map(z => ({page: z.page, x: z.x, y: z.y})));

    zm.activePage = Number(getDocZones(docId)[0]?.page || 1);
    console.log('[ZM OPEN] Setting activePage to', zm.activePage);

    zm.zones = getDocZones(docId)
        .map((zone, index) => zmNormalizeZone(zone, index))
        .filter(Boolean);
    zm.selectedZoneIndex = zm.zones.length > 0 ? 0 : -1;
    zm.dragMode = null;

    console.log('[ZM OPEN] After normalization:', zm.zones.length, 'zones', zm.zones.map(z => ({page: z.page})));

    // Créer les zones manquantes automatiquement (aucune zone si c'est le premier accès)
    zmEnsureRequiredZones();
    console.log('[ZM OPEN] After ensure:', zm.zones.length, 'zones', zm.zones.map(z => ({page: z.page})));

    const modal = document.getElementById('zone-modal');
    const loading = document.getElementById('zm-pdf-loading');
    const errorBox = document.getElementById('zm-pdf-error');
    const openNewTab = document.getElementById('zm-open-new-tab');
    const pagesRoot = document.getElementById('zm-pages');
    const inlineUrl = zmGetInlineUrl(docId);

    if (!wfInitPdfJsWorker()) {
        wfLoadPdfJsFallback();
        loading.classList.add('hidden');
        errorBox.classList.remove('hidden');
        openNewTab.href = inlineUrl;
        return;
    }

    zmUpdateHeader(doc.title || 'Document');
    zmShowDebug();
    zmUpdateDebug();
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Notify parent frame to expand to full-screen when zone modal opens in embedded mode
    if (IS_EMBEDDED && window.parent && window.parent !== window) {
        window.parent.postMessage({ source: 'wf-embedded', type: 'zone-modal-open' }, '*');
    }

    loading.classList.remove('hidden');
    errorBox.classList.add('hidden');
    openNewTab.href = inlineUrl;
    pagesRoot.innerHTML = '';
    document.getElementById('zm-zone-label').value = zm.selectedZoneIndex >= 0 ? (zm.zones[zm.selectedZoneIndex]?.label || '') : '';

    window.addEventListener('resize', zmHandleResize);
    window.addEventListener('mouseup', zmGlobalMouseUp);
    console.log('[ZM] Loading PDF from:', inlineUrl);

    pdfjsLib.getDocument(inlineUrl).promise.then((pdf) => {
        console.log('[ZM] PDF loaded successfully:', pdf.numPages, 'pages');
        zm.pdfDoc = pdf;
        return zmRenderDocument();
    }).catch((err) => {
        console.error('[ZM] PDF loading error:', err);
        loading.classList.add('hidden');
        errorBox.classList.remove('hidden');
        zmUpdateDebug();
    });
}

function closeZoneModal() {
    // Notify parent frame to restore modal size when zone modal closes in embedded mode
    if (IS_EMBEDDED && window.parent && window.parent !== window) {
        window.parent.postMessage({ source: 'wf-embedded', type: 'zone-modal-close' }, '*');
    }
    document.getElementById('zone-modal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('zm-pages').innerHTML = '';
    window.removeEventListener('resize', zmHandleResize);
    window.removeEventListener('mouseup', zmGlobalMouseUp);
    zm.docId = null;
    zm.pdfDoc = null;
    zm.zones = [];
    zm.selectedZoneIndex = -1;
    zm.drawing = false;
    zm.dragMode = null;
    zm.dragOffsetX = 0;
    zm.dragOffsetY = 0;
}

function zmGlobalMouseUp() {
    if (!zm.dragMode || zm.dragMode === 'draw') return;
    zm.dragMode = null;
    zm.originRect = null;
    zm.dragOffsetX = 0;
    zm.dragOffsetY = 0;
    zmUpdateHeader(document.getElementById('zm-doc-name').textContent || '');
    zmDrawZone();
}

function zmHandleResize() {
    if (zm.pdfDoc) zmRenderDocument();
}

function zmDrawZone() {
    zmUpdateDebug();
    const canvases = document.querySelectorAll('.zm-zone-layer');
    console.log('[ZM] zmDrawZone called: zones=', zm.zones.length, 'canvases=', canvases.length);

    canvases.forEach((zoneCanvas) => {
        const pageNumber = Number(zoneCanvas.dataset.page || '0');
        const ctx = zoneCanvas.getContext('2d');

        if (!ctx) {
            console.warn('[ZM] No context for canvas page', pageNumber);
            return;
        }

        console.log('[ZM] Canvas page', pageNumber, 'size:', zoneCanvas.width, 'x', zoneCanvas.height);
        ctx.clearRect(0, 0, zoneCanvas.width, zoneCanvas.height);

        zm.zones.forEach((zone, index) => {
            if (Number(zone.page || 1) !== pageNumber) return;
            let x, y, w, h;
            if (zone._pct) {
                x = zone.x / 100 * zoneCanvas.width;
                y = zone.y / 100 * zoneCanvas.height;
                w = zone.w / 100 * zoneCanvas.width;
                h = zone.h / 100 * zoneCanvas.height;
            } else {
                x = zone.x;
                y = zone.y;
                w = zone.w;
                h = zone.h;
            }

            const isSelected = index === zm.selectedZoneIndex;
            console.log('[ZM] Drawing zone', index, 'at page', pageNumber, ':', x, y, w, 'x', h);
            ctx.strokeStyle = isSelected ? 'rgba(22, 163, 74, 0.98)' : 'rgba(37, 99, 235, 0.95)';
            ctx.lineWidth = isSelected ? 3 : 2;
            ctx.setLineDash(isSelected ? [] : [6, 3]);
            ctx.strokeRect(x, y, w, h);
            ctx.fillStyle = isSelected ? 'rgba(22, 163, 74, 0.18)' : 'rgba(37, 99, 235, 0.12)';
            ctx.fillRect(x, y, w, h);

            ctx.setLineDash([]);
            ctx.fillStyle = isSelected ? '#166534' : '#2563eb';
            ctx.font = 'bold 12px sans-serif';
            ctx.fillText(`SIGNATURE ${index + 1}`, x + 8, y + 18);

            if (isSelected) {
                const handle = zm.handleSize;
                ctx.fillStyle = '#16a34a';
                ctx.fillRect(x + w - handle, y + h - handle, handle, handle);
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(x + w - handle + 5, y + h - 5);
                ctx.lineTo(x + w - 5, y + h - handle + 5);
                ctx.stroke();
            }
        });
    });
}

function zmAddDefaultZone() {
    zmStoreSelectedLabel();
    if (zm.zones.length >= getRequiredSignatureZoneCount()) return;
    const zone = zmCreateDefaultZone(zm.activePage || 1, zm.zones.length);
    if (!zone) return;
    zm.zones.push(zone);
    zm.selectedZoneIndex = zm.zones.length - 1;
    zm.activePage = zone.page;
    document.getElementById('zm-zone-label').value = zm.zones[zm.selectedZoneIndex].label;
    zmUpdateHeader(document.getElementById('zm-doc-name').textContent || '');
    zmDrawZone();
}

function zmMouseDown(e) {
    const pageNumber = Number(e.currentTarget.dataset.page || '1');
    const rect = e.currentTarget.getBoundingClientRect();
    zm.activePage = pageNumber;
    const px = e.clientX - rect.left;
    const py = e.clientY - rect.top;
    const handle = zm.handleSize;

    for (let index = zm.zones.length - 1; index >= 0; index -= 1) {
        const zone = zm.zones[index];
        if (zone.page !== pageNumber) continue;
        const zoneRect = zone._pct
            ? { x: zone.x / 100 * e.currentTarget.width, y: zone.y / 100 * e.currentTarget.height, w: zone.w / 100 * e.currentTarget.width, h: zone.h / 100 * e.currentTarget.height }
            : { x: zone.x, y: zone.y, w: zone.w, h: zone.h };
        const onHandle = px >= zoneRect.x + zoneRect.w - handle && px <= zoneRect.x + zoneRect.w && py >= zoneRect.y + zoneRect.h - handle && py <= zoneRect.y + zoneRect.h;
        const insideZone = px >= zoneRect.x && px <= zoneRect.x + zoneRect.w && py >= zoneRect.y && py <= zoneRect.y + zoneRect.h;
        if (onHandle || insideZone) {
            zmStoreSelectedLabel();
            zm.selectedZoneIndex = index;
            zm.dragMode = onHandle ? 'resize' : 'move';
            if (zone._pct) {
                zone.x = zoneRect.x;
                zone.y = zoneRect.y;
                zone.w = zoneRect.w;
                zone.h = zoneRect.h;
                zone._pct = false;
            }
            zm.originRect = zoneRect;
            zm.sx = px;
            zm.sy = py;
            zm.dragOffsetX = px - zoneRect.x;
            zm.dragOffsetY = py - zoneRect.y;
            document.getElementById('zm-zone-label').value = zone.label || `Signature ${index + 1}`;
            zmDrawZone();
            return;
        }
    }

    if (zm.zones.length >= getRequiredSignatureZoneCount()) {
        return;
    }

    zm.drawing = true;
    zm.dragMode = 'draw';
    zm.sx = px;
    zm.sy = py;
    zm.selectedZoneIndex = -1;
    zmUpdateHeader(document.getElementById('zm-doc-name').textContent || '');
}

function zmMouseMove(e) {
    if (!zm.drawing && !zm.dragMode) return;
    const pageNumber = Number(e.currentTarget.dataset.page || '1');
    const rect = e.currentTarget.getBoundingClientRect();
    const cx = e.clientX - rect.left;
    const cy = e.clientY - rect.top;
    const zoneC = zmGetZoneCanvas(pageNumber);
    if (!zoneC) return;
    const ctx = zoneC.getContext('2d');

    if (zm.dragMode === 'draw') {
        if (pageNumber !== zm.activePage) return;
        zmDrawZone();
        const x = Math.min(zm.sx, cx);
        const y = Math.min(zm.sy, cy);
        const w = Math.abs(cx - zm.sx);
        const h = Math.abs(cy - zm.sy);
        ctx.strokeStyle = 'rgba(37, 99, 235, 0.95)';
        ctx.lineWidth = 2;
        ctx.setLineDash([6, 3]);
        ctx.strokeRect(x, y, w, h);
        ctx.fillStyle = 'rgba(37, 99, 235, 0.12)';
        ctx.fillRect(x, y, w, h);
        return;
    }

    if (zm.selectedZoneIndex < 0) return;
    const zone = zm.zones[zm.selectedZoneIndex];
    if (!zone) return;
    const dx = cx - zm.sx;
    const dy = cy - zm.sy;

    if (zm.dragMode === 'move') {
        const currentW = zone.w || zm.originRect.w;
        const currentH = zone.h || zm.originRect.h;
        zone.page = pageNumber;
        zone.x = Math.max(0, Math.min(zoneC.width - currentW, cx - zm.dragOffsetX));
        zone.y = Math.max(0, Math.min(zoneC.height - currentH, cy - zm.dragOffsetY));
        zone.w = currentW;
        zone.h = currentH;
        zone._pct = false;
        zm.activePage = pageNumber;
    }

    if (zm.dragMode === 'resize') {
        if (zone.page !== pageNumber) return;
        zone.x = zm.originRect.x;
        zone.y = zm.originRect.y;
        zone.w = Math.max(80, Math.min(zoneC.width - zm.originRect.x, zm.originRect.w + dx));
        zone.h = Math.max(36, Math.min(zoneC.height - zm.originRect.y, zm.originRect.h + dy));
        zone._pct = false;
    }

    zmDrawZone();
}

function zmMouseUp(e) {
    const pageNumber = Number(e.currentTarget.dataset.page || '1');
    const rect = e.currentTarget.getBoundingClientRect();
    const cx = e.clientX - rect.left;
    const cy = e.clientY - rect.top;
    if (zm.dragMode === 'draw') {
        const x = Math.min(zm.sx, cx);
        const y = Math.min(zm.sy, cy);
        const w = Math.abs(cx - zm.sx);
        const h = Math.abs(cy - zm.sy);

        if (w >= 10 && h >= 10) {
            zm.zones.push({ page: pageNumber, x, y, w, h, _pct: false, label: `Signature ${zm.zones.length + 1}` });
            zm.selectedZoneIndex = zm.zones.length - 1;
            zm.activePage = pageNumber;
            document.getElementById('zm-zone-label').value = zm.zones[zm.selectedZoneIndex].label;
        }
    }

    zm.drawing = false;
    zm.dragMode = null;
    zm.originRect = null;
    zm.dragOffsetX = 0;
    zm.dragOffsetY = 0;

    zmUpdateHeader(document.getElementById('zm-doc-name').textContent || '');
    zmDrawZone();
}

function zmResetZone() {
    zm.zones = [];
    zm.selectedZoneIndex = -1;
    document.getElementById('zm-zone-label').value = '';
    document.querySelectorAll('.zm-zone-layer').forEach((zoneCanvas) => {
        zoneCanvas.getContext('2d').clearRect(0, 0, zoneCanvas.width, zoneCanvas.height);
    });
    zmUpdateHeader(document.getElementById('zm-doc-name').textContent || '');
}

function zmPersistZone({ seal = false, closeAfter = false } = {}) {
    if (zm.zones.length === 0 || !zm.docId) return;
    zmStoreSelectedLabel();
    form.docZones[zm.docId] = zm.zones.map((zone, index) => {
        const zoneC = zmGetZoneCanvas(zone.page || 1);
        if (!zoneC) return null;
        const currentZone = zone._pct
            ? {
                x: zone.x / 100 * zoneC.width,
                y: zone.y / 100 * zoneC.height,
                w: zone.w / 100 * zoneC.width,
                h: zone.h / 100 * zoneC.height,
            }
            : zone;

        return {
            page: zone.page || 1,
            x: parseFloat((currentZone.x / zoneC.width * 100).toFixed(3)),
            y: parseFloat((currentZone.y / zoneC.height * 100).toFixed(3)),
            w: parseFloat((currentZone.w / zoneC.width * 100).toFixed(3)),
            h: parseFloat((currentZone.h / zoneC.height * 100).toFixed(3)),
            _pct: true,
            label: zone.label || `Signature ${index + 1}`,
        };
    }).filter(Boolean);

    if (seal) {
        form.sealedDocZones[zm.docId] = true;
    } else {
        delete form.sealedDocZones[zm.docId];
    }

    renderStep();
    if (closeAfter) {
        closeZoneModal();
    }
}

function zmSaveZone() {
    zmPersistZone({ seal: false, closeAfter: false });
}

function zmSealZone() {
    zmPersistZone({ seal: true, closeAfter: true });
}
function removeAttached(id) { form.attachedDocs = form.attachedDocs.filter(x => x !== id); renderStep(); }

// ── Étape 5 : Opération (création workflow) ───────────────
function renderStepOperationCreate() {
    syncAttributionFromDom();
    const valOk = form.validationSteps.filter(s=>getValidationAssignee(s));
    const sigOk = form.signatureSteps.filter(s=>getSignatureAssignee(s));

    return `<div class="space-y-4 text-sm">

        <!-- Résumé compact -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-3">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-info-circle text-[#2453d6]"></i> Résumé
            </h3>
            <div class="grid grid-cols-[auto,1fr] gap-x-3 gap-y-1.5">
                <span class="font-semibold text-gray-500">Nom :</span>
                <span class="text-gray-800">${esc(form.name)||'<em class="text-gray-400">Non renseigné</em>'}</span>
                ${form.description ? `<span class="font-semibold text-gray-500">Description :</span><span class="text-gray-700">${esc(form.description)}</span>` : ''}
            </div>
            <div class="flex gap-4 flex-wrap mt-1">
                <span class="inline-flex items-center gap-1.5 text-xs text-green-700 bg-green-100 px-3 py-1 rounded-full">
                    <i class="fas fa-check"></i> ${valOk.length} validateur(s)
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-blue-700 bg-blue-100 px-3 py-1 rounded-full">
                    <i class="fas fa-pen-nib"></i> ${sigOk.length} signataire(s)
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs text-red-700 bg-red-100 px-3 py-1 rounded-full">
                    <i class="fas fa-file-alt"></i> ${form.docsToSign.length} doc(s) à signer
                </span>
            </div>
        </div>

        <!-- Étapes -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-green-50 rounded-xl border border-green-200 p-3 space-y-1.5">
                <p class="text-xs font-bold text-green-800 flex items-center gap-1"><i class="fas fa-check-circle"></i> Validateurs</p>
                ${valOk.length > 0
                    ? valOk.map((s,i)=>`<p class="text-xs text-gray-700"><span class="text-gray-400">${i+1}.</span> ${esc(getUserName(getValidationAssignee(s)))}</p>`).join('')
                    : '<p class="text-xs text-gray-400 italic">Aucun</p>'}
            </div>
            <div class="bg-blue-50 rounded-xl border border-blue-200 p-3 space-y-1.5">
                <p class="text-xs font-bold text-blue-800 flex items-center gap-1"><i class="fas fa-pen-nib"></i> Signataires</p>
                ${sigOk.length > 0
                    ? sigOk.map((s,i)=>`<p class="text-xs text-gray-700"><span class="text-gray-400">${i+1}.</span> ${esc(getUserName(getSignatureAssignee(s)))}</p>`).join('')
                    : '<p class="text-xs text-gray-400 italic">Aucun</p>'}
            </div>
        </div>

        ${valOk.length + sigOk.length === 0 ? `
        <div class="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs font-medium">
            <i class="fas fa-exclamation-triangle"></i>
            Attention : aucune étape configurée. Le workflow ne pourra pas être démarré.
        </div>` : ''}

        <!-- Actions -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3 shadow-sm">
            <p class="text-sm font-bold text-gray-700 text-center">Actions disponibles</p>
            <div class="flex justify-center gap-3 flex-wrap">
                <button type="button" onclick="wfDupliquer()"
                    class="px-6 py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white font-bold rounded-xl flex items-center gap-2 transition shadow-sm text-sm">
                    <i class="fas fa-copy"></i> Dupliquer
                </button>
                <button type="button" onclick="wfSupprimer()"
                    class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl flex items-center gap-2 transition shadow-sm text-sm">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
                <button type="button" id="btn-demarrer" onclick="wfDemarrer()"
                    class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl flex items-center gap-2 transition shadow-sm text-sm"
                    ${valOk.length + sigOk.length === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''}>
                    <i class="fas fa-play"></i> Démarrer
                </button>
            </div>
            <p class="text-xs text-gray-400 text-center">"Démarrer" crée le workflow et notifie les intervenants.</p>
        </div>
    </div>`;
}

async function wfDemarrer() {
    if (isSubmitting) return;
    syncAttributionFromDom();
    const valOk = form.validationSteps.filter(s=>getValidationAssignee(s));
    const sigOk = form.signatureSteps.filter(s=>getSignatureAssignee(s));
    if (!form.name.trim()) { showModalError('Le nom du workflow est obligatoire.'); return; }
    if (valOk.length + sigOk.length === 0) { showModalError('Ajoutez au moins un validateur ou signataire avant de démarrer.'); return; }

    isSubmitting = true;
    const btn = document.getElementById('btn-demarrer');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Démarrage…'; }

    const steps = [
        ...valOk.map((s,i) => ({ name:`Validation ${i+1}`, type:'approve', assignee_id:getValidationAssignee(s), order:i+1, requires_signature:false })),
        ...sigOk.map((s,i) => ({ name:`Signature ${i+1}`, type:'sign',    assignee_id:getSignatureAssignee(s),   order:valOk.length+i+1, requires_signature:true })),
    ];

    try {
        // 1. Créer le workflow
        const resp1 = await fetch(WORKFLOWS_BASE, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
            body: JSON.stringify({
                name: form.name.trim(),
                description: form.description.trim(),
                steps,
                docs_to_sign:  form.docsToSign,
                attached_docs: form.attachedDocs,
                doc_zones:     form.docZones,
            }),
        });
        const d1 = await resp1.json();
        if (!resp1.ok) throw new Error(d1.message || 'Erreur création workflow');

        // 2. Démarrer l'exécution
        const resp2 = await fetch(`${WORKFLOWS_BASE}/${d1.workflow.id}/execute`, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
            body: JSON.stringify({ doc_zones: form.docZones }),
        });
        const d2 = await resp2.json();
        if (!resp2.ok) throw new Error(d2.message || 'Erreur démarrage workflow');

        // 3. Mettre à jour la liste locale
        const executions = Array.isArray(d2.executions) && d2.executions.length
            ? d2.executions
            : (d2.execution ? [d2.execution] : []);
        const newWf = { ...d1.workflow, executions };
        workflows.unshift(newWf);
        closeModal();
        renderTiles();
        renderTable();
        showSuccess('✅ Workflow démarré ! Les intervenants ont reçu leurs demandes d\'action dans l\'onglet Signatures.');
    } catch(err) {
        showModalError(err.message || 'Une erreur est survenue.');
    } finally {
        isSubmitting = false;
    }
}

function wfDupliquer() {
    // Sauvegarde la config actuelle, réouvre le modal avec le même form
    const saved = JSON.parse(JSON.stringify(form));
    closeModal();
    openCreateModal('workflow');
    form = { ...saved, name: saved.name ? saved.name + ' (copie)' : '' };
    renderStep();
}

function wfSupprimer() {
    // Annule la création (ferme le modal)
    closeModal();
}

// ── Étape Notifications ────────────────────────────────────
function renderStepNotifications() {
    const stages = [
        {key:'onValidationStep', label:'À chaque étape de validation'},
        {key:'onSignatureStep',  label:'À chaque étape de signature'},
        {key:'onApproved',       label:'Workflow entièrement approuvé'},
        {key:'onRejected',       label:'Workflow rejeté'},
        {key:'onCompleted',      label:'Document signé disponible (fin du circuit)'},
    ];
    const tog = form.notifyEmail;
    return `<div class="space-y-4">
        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-xl bg-gray-50">
            <div>
                <p class="text-sm font-bold text-gray-800">Notifications par e-mail</p>
                <p class="text-xs text-gray-500">Informer les parties prenantes à chaque étape clé</p>
            </div>
            <button type="button" onclick="form.notifyEmail=!form.notifyEmail;renderStep()"
                class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full cursor-pointer transition-colors ${tog?'bg-[#2453d6]':'bg-gray-300'}">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow mt-1 transition-transform ${tog?'translate-x-6':'translate-x-1'}"></span>
            </button>
        </div>
        ${tog ? `
        <div class="border border-amber-200 bg-amber-50/60 rounded-xl p-4 space-y-4">
            <div class="space-y-1.5">
                <label class="block text-sm font-semibold text-gray-700">Destinataires (To)</label>
                <input type="text" value="${esc(form.notifyEmails)}" oninput="setWorkflowNotifyEmails(this.value)"
                    placeholder="ex: chef@org.fr, secretaire@org.fr"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
            </div>
            <div class="space-y-1.5">
                <label class="block text-sm font-semibold text-gray-700">Copie (Cc) — optionnel</label>
                <input type="text" value="${esc(form.notifyCc)}" oninput="setWorkflowNotifyCc(this.value)"
                    placeholder="ex: direction@org.fr"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
            </div>
            <div class="space-y-2">
                <p class="text-sm font-semibold text-gray-700">Déclencher la notification lors de :</p>
                ${stages.map(s=>`
                <label class="flex items-center gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[#2453d6]"
                        ${form.notifyStages[s.key]?'checked':''}
                        onchange="form.notifyStages['${s.key}']=this.checked">
                    <span class="text-sm text-gray-700">${s.label}</span>
                </label>`).join('')}
            </div>
            <label class="flex items-center gap-2.5 cursor-pointer select-none pt-2 border-t border-amber-200">
                <input type="checkbox" class="h-4 w-4 rounded border-gray-300"
                    ${form.sendDownloadLink?'checked':''}
                    onchange="form.sendDownloadLink=this.checked">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Inclure un lien de téléchargement</p>
                    <p class="text-xs text-gray-500">Un lien sécurisé sera joint dans le dernier e-mail</p>
                </div>
            </label>
        </div>` : ''}
    </div>`;
}

// ── Étape Résumé ───────────────────────────────────────────
function renderStepSummary() {
    const valOk = form.validationSteps.filter(s=>s.approverId);
    const sigOk = form.signatureSteps.filter(s=>s.signerId);
    const isWf  = creationMode === 'workflow';

    return `<div class="space-y-4 text-sm">
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-3">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-info-circle text-[#2453d6]"></i> Informations générales
            </h3>
            <div class="grid grid-cols-[auto,1fr] gap-x-3 gap-y-1.5 text-sm">
                <span class="font-semibold text-gray-600">Nom :</span>
                <span class="text-gray-800">${esc(form.name)||'<em class="text-gray-400">Non renseigné</em>'}</span>
                ${form.description ? `
                <span class="font-semibold text-gray-600">Description :</span>
                <span class="text-gray-700">${esc(form.description)}</span>` : ''}
            </div>
        </div>

        <div class="bg-green-50 rounded-xl border border-green-200 p-4 space-y-2">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-check-circle text-green-600"></i>
                Validateurs <span class="ml-1 px-2 py-0.5 bg-green-600 text-white text-xs rounded-full">${valOk.length}</span>
            </h3>
            ${valOk.length > 0
                ? valOk.map((s,i)=>`<p class="text-gray-700"><span class="font-medium text-gray-500">Étape ${i+1} :</span> ${esc(getUserName(s.approverId))}</p>`).join('')
                : '<p class="text-gray-400 italic text-xs">Aucun validateur ajouté</p>'}
        </div>

        <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 space-y-2">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-pen-nib text-blue-600"></i>
                Signataires <span class="ml-1 px-2 py-0.5 bg-[#2453d6] text-white text-xs rounded-full">${sigOk.length}</span>
            </h3>
            ${sigOk.length > 0
                ? sigOk.map((s,i)=>`<p class="text-gray-700"><span class="font-medium text-gray-500">Étape ${i+1} :</span> ${esc(getUserName(s.signerId))}</p>`).join('')
                : '<p class="text-gray-400 italic text-xs">Aucun signataire ajouté</p>'}
        </div>

        ${isWf ? `
        <div class="bg-red-50 rounded-xl border border-red-200 p-4 space-y-2">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-file-signature text-red-500"></i>
                Documents à signer <span class="ml-1 px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">${form.docsToSign.length}</span>
            </h3>
            ${form.docsToSign.length > 0
                ? form.docsToSign.map(id=>`<p class="text-gray-700 text-xs"><i class="fas fa-file text-red-400 mr-1"></i>${esc(getDocTitle(id))}</p>`).join('')
                : '<p class="text-gray-400 italic text-xs">Aucun document sélectionné</p>'}
        </div>
        <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-4 space-y-2">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-paperclip text-emerald-500"></i>
                Pièces jointes <span class="ml-1 px-2 py-0.5 bg-emerald-600 text-white text-xs rounded-full">${form.attachedDocs.length}</span>
            </h3>
            ${form.attachedDocs.length > 0
                ? form.attachedDocs.map(id=>`<p class="text-gray-700 text-xs"><i class="fas fa-paperclip text-emerald-400 mr-1"></i>${esc(getDocTitle(id))}</p>`).join('')
                : '<p class="text-gray-400 italic text-xs">Aucune pièce jointe</p>'}
        </div>` : ''}

        <div class="bg-amber-50 rounded-xl border border-amber-200 p-4">
            <h3 class="font-bold text-gray-800 flex items-center gap-2 mb-1">
                <i class="fas fa-bell text-amber-500"></i> Notifications
            </h3>
            ${form.notifyEmail
                ? `<p class="text-gray-700 text-xs"><i class="fas fa-check text-green-500 mr-1"></i>Activées${form.notifyEmails ? ' → ' + esc(form.notifyEmails) : ''}</p>
                   ${form.sendDownloadLink ? '<p class="text-gray-600 text-xs"><i class="fas fa-link text-blue-500 mr-1"></i>Lien de téléchargement inclus</p>' : ''}`
                : '<p class="text-gray-400 italic text-xs">Désactivées</p>'}
        </div>

        ${valOk.length + sigOk.length === 0 ? `
        <div class="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs font-medium">
            <i class="fas fa-exclamation-triangle"></i>
            Attention : aucune étape de validation ou signature n'a été configurée.
        </div>` : ''}
    </div>`;
}

// ── Étape Opération (vue lecture seule, step 3) ────────────
function renderStepOperation() {
    if (!viewingWf) return '';
    const isOwner = viewingWf.created_by === ME;
    const signedDownloadUrl = getCompletedSignedDownloadUrl(viewingWf);
    const showSignedDownload = isOwner && !!signedDownloadUrl;
    const steps = (viewingWf.steps||[]).slice().sort((a,b) => a.order - b.order);
    const stepsHtml = steps.map(s => {
        const isSig = s.requires_signature;
        return `<div class="flex items-center gap-3 p-3.5 rounded-xl border ${isSig?'border-blue-200 bg-blue-50':'border-green-200 bg-green-50'}">
            <div class="h-9 w-9 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0 ${isSig?'bg-[#2453d6]':'bg-green-600'}">${s.order}</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800">${esc(s.name||'Étape '+s.order)}</p>
                <p class="text-xs text-gray-500">${isSig?'Signature':'Validation'} · ${esc(getUserName(s.assignee_id))}</p>
            </div>
            <i class="fas ${isSig?'fa-pen-nib text-blue-400':'fa-check text-green-500'} flex-shrink-0"></i>
        </div>`;
    }).join('');

    return `<div class="space-y-4">
        <div class="space-y-2">
            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                <i class="fas fa-sitemap text-gray-400"></i> Circuit des étapes (${steps.length})
            </h3>
            ${steps.length > 0 ? `<div class="space-y-2">${stepsHtml}</div>`
              : '<p class="text-gray-400 text-sm text-center py-8 bg-gray-50 rounded-xl">Aucune étape définie</p>'}
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
            <p class="text-sm font-bold text-gray-700 mb-3">Actions</p>
            <div class="flex flex-wrap gap-2">
                ${showSignedDownload
                    ? `<a href="${signedDownloadUrl}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-green-300 text-green-700 bg-green-50 hover:bg-green-100 text-xs font-semibold transition">
                        <i class="fa-solid fa-file-pdf text-sm"></i>
                        Voir signé
                    </a>`
                    : `<button type="button" onclick='duplicateWorkflow("${viewingWf.id}")'
                        class="px-4 py-2 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg flex items-center gap-2 transition">
                        <i class="fas fa-copy"></i> Dupliquer
                    </button>`}
                <button type="button" onclick='closeModal();openDeleteModal("${viewingWf.id}")'
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg flex items-center gap-2 transition">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </div>
        </div>
    </div>`;
}

// ── Navigation entre étapes ────────────────────────────────
function nextStep() {
    clearModalError();

    // Étape 1 : lire la valeur directement depuis le DOM
    if (currentStep === 1) {
        const nameEl = document.getElementById('f-name');
        if (nameEl) form.name = nameEl.value;
        const descEl = document.getElementById('f-desc');
        if (descEl) form.description = descEl.value;
        if (!form.name.trim()) {
            showModalError('Le nom est obligatoire pour continuer.'); return;
        }
    }

    // Étape 2 : avertissement non bloquant (on peut continuer sans sélection)
    if (currentStep === 2) {
        syncAttributionFromDom();
        const valOk = form.validationSteps.filter(s => getValidationAssignee(s)).length;
        const sigOk = form.signatureSteps.filter(s => getSignatureAssignee(s)).length;
        if (valOk + sigOk === 0) {
            // Warning visible mais non bloquant — on avance quand même
            showModalError('Aucun assigné sélectionné. Vous pouvez continuer mais au moins un est requis à la soumission.', 'warning');
        }
    }

    currentStep = Math.min(currentStep + 1, maxSteps());
    renderStep();
}
function prevStep() {
    clearModalError();
    currentStep = Math.max(currentStep - 1, 1);
    renderStep();
}

// ── Appliquer un modèle ────────────────────────────────────
function applyTemplate(id) {
    form.templateId = id;
    if (!id) return;
    const tpl = templates.find(t => t.id === id);
    if (!tpl) return;
    form.name        = tpl.name || '';
    form.description = tpl.description || '';
    form.validationSteps = Array.isArray(tpl.validation_steps) && tpl.validation_steps.length
        ? tpl.validation_steps.map((s,i) => ({id:i+1, approverId: s.approver_id || s.approverId || ''}))
        : [{id:1, approverId:''}];
    form.signatureSteps = Array.isArray(tpl.signature_steps) && tpl.signature_steps.length
        ? tpl.signature_steps.map((s,i) => ({id:i+1, signerId: s.signer_id || s.signerId || ''}))
        : [{id:1, signerId:''}];
    renderStep();
    showSuccess('Modèle appliqué — veuillez vérifier et ajuster les champs.');
}

// ── Soumission ─────────────────────────────────────────────
async function submitForm() {
    if (isSubmitting) return;
    const valOk = form.validationSteps.filter(s=>s.approverId);
    const sigOk = form.signatureSteps.filter(s=>s.signerId);
    if (!form.name.trim()) { showModalError('Le nom est obligatoire.'); return; }
    if (valOk.length + sigOk.length === 0) { showModalError('Ajoutez au moins un validateur ou signataire.'); return; }

    isSubmitting = true;
    document.getElementById('btn-submit').disabled = true;
    document.getElementById('btn-submit').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';

    const steps = [
        ...valOk.map((s,i) => ({
            name: `Validation ${i+1}`,
            type: 'approve',
            assignee_id: s.approverId,
            order: i + 1,
            requires_signature: false,
        })),
        ...sigOk.map((s,i) => ({
            name: `Signature ${i+1}`,
            type: 'sign',
            assignee_id: s.signerId,
            order: valOk.length + i + 1,
            requires_signature: true,
        })),
    ];

    const notifConfig = form.notifyEmail ? {
        enabled: true,
        emails: form.notifyEmails,
        cc: form.notifyCc,
        stages: form.notifyStages,
        sendDownloadLink: form.sendDownloadLink,
    } : { enabled: false };

    try {
        if (creationMode === 'template') {
            const resp = await fetch(WORKFLOW_TEMPLATES_BASE, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                body: JSON.stringify({
                    name: form.name.trim(),
                    description: form.description.trim(),
                    validation_steps: valOk.map((s,i) => ({id:i+1, approver_id:s.approverId})),
                    signature_steps:  sigOk.map((s,i) => ({id:i+1, signer_id:s.signerId})),
                    notification_config: notifConfig,
                }),
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.message || 'Erreur serveur');
            templates.unshift(data.template);
            closeModal();
            showSuccess('Modèle créé avec succès !');
        } else {
            const resp = await fetch(WORKFLOWS_BASE, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                body: JSON.stringify({
                    name: form.name.trim(),
                    description: form.description.trim(),
                    steps,
                    docs_to_sign:  form.docsToSign,
                    attached_docs: form.attachedDocs,
                }),
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.message || 'Erreur serveur');
            workflows.unshift(data.workflow);
            if (IS_EMBEDDED && window.parent && window.parent !== window) {
                window.parent.postMessage({
                    source: 'wf-embedded',
                    type: 'workflow-created',
                    workflowId: data.workflow?.id || null,
                },
                '*');
            }
            closeModal();
            renderTiles();
            renderTable();
            showSuccess('Workflow créé avec succès !');
        }
    } catch(err) {
        showModalError(err.message || 'Une erreur est survenue. Veuillez réessayer.');
    } finally {
        isSubmitting = false;
        const btn = document.getElementById('btn-submit');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-check"></i> <span id="btn-submit-label">${creationMode==='template'?'Créer le modèle':'Créer le workflow'}</span>`;
        }
    }
}

// ══════════════════════════════════════════════════════════
// POPUP DÉTAIL
// ══════════════════════════════════════════════════════════
let detailWf  = null;
let detailTab = 'steps';

function openDetail(wf) {
    detailWf  = wf;
    detailTab = 'steps';

    document.getElementById('detail-title').textContent = wf.name || 'Workflow';
    const descEl = document.getElementById('detail-desc');
    if (wf.description) { descEl.textContent = wf.description; descEl.classList.remove('hidden'); }
    else descEl.classList.add('hidden');

    const t = getTracking(wf);
    document.getElementById('detail-status-badge').className = `inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${t.cls}`;
    document.getElementById('detail-status-badge').textContent = t.label;
    document.getElementById('detail-progress').style.width = t.pct + '%';
    document.getElementById('detail-pct-label').textContent =
        `${t.pct}% · ${t.completed}/${t.total} exécution(s)${t.curStep > 0 ? ` · Étape ${t.curStep}/${t.totalSteps}` : ''}`;

    // Actions avancer / rejeter
    const actEl   = document.getElementById('detail-actions');
    const hasInProg = (wf.executions||[]).some(e => /in_progress|started/i.test(e.status));
    if (wf.created_by === ME && hasInProg) {
        actEl.classList.remove('hidden');
    } else {
        actEl.classList.add('hidden');
    }

    setDetailTab('steps');
    document.getElementById('modal-detail').classList.remove('hidden');
}

function closeDetail() {
    document.getElementById('modal-detail').classList.add('hidden');
    detailWf = null;
}

function setDetailTab(tab) {
    detailTab = tab;
    const docsCount = ((detailWf?.docs_to_sign||[]).length + (detailWf?.attached_docs||[]).length);
    const activeStyle = 'py-3 mr-6 text-sm font-medium border-b-2 border-[#2453d6] text-[#2453d6] transition-colors';
    const inactStyle  = 'py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors';
    document.getElementById('tab-steps').className = tab==='steps'  ? activeStyle : inactStyle + ' mr-6';
    document.getElementById('tab-docs').className  = tab==='documents' ? activeStyle : inactStyle;
    document.getElementById('tab-docs').textContent = `Documents (${docsCount})`;
    renderDetailBody();
}

function renderDetailBody() {
    const body = document.getElementById('detail-body');
    if (!detailWf) return;

    if (detailTab === 'steps') {
        const steps = (detailWf.steps||[]).slice().sort((a,b) => a.order-b.order);
        if (!steps.length) {
            body.innerHTML = '<p class="text-gray-400 text-sm text-center py-12">Aucune étape définie pour ce workflow</p>';
            return;
        }
        body.innerHTML = '<div class="space-y-3">' + steps.map(s => {
            const isSig = s.requires_signature;
            return `<div class="flex items-center gap-3 p-3.5 rounded-xl border ${isSig?'border-blue-200 bg-blue-50':'border-green-200 bg-green-50'}">
                <div class="h-9 w-9 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0 ${isSig?'bg-[#2453d6]':'bg-green-600'}">${s.order}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800">${esc(s.name||'Étape '+s.order)}</p>
                    <p class="text-xs text-gray-500 mt-0.5">${isSig?'Signature':'Validation'} · ${esc(getUserName(s.assignee_id))}</p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium ${isSig?'bg-blue-100 text-blue-700':'bg-green-100 text-green-700'}">${isSig?'Signature':'Validation'}</span>
            </div>`;
        }).join('') + '</div>';
        return;
    }

    // Onglet documents
    const toSign   = detailWf.docs_to_sign  || [];
    const attached = detailWf.attached_docs  || [];

    function docCard(id, badge) {
        const d  = DOCUMENTS.find(x => x.id === id);
        const title = d ? d.title : 'Document ' + id.slice(0,8) + '…';
        const fp    = d?.file_path;
        return `<div class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 transition-colors">
            <div class="h-9 w-9 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-file-alt text-[#2453d6] text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">${esc(title)}</p>
                <span class="text-xs text-gray-400">${badge}</span>
            </div>
            ${fp ? `<div class="flex gap-1 flex-shrink-0">
                <button onclick="window.open('${fp}','_blank')"
                    class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Visualiser">
                    <i class="fas fa-eye text-xs"></i>
                </button>
                <a href="${fp}" download
                    class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition" title="Télécharger">
                    <i class="fas fa-download text-xs"></i>
                </a>
            </div>` : ''}
        </div>`;
    }

    body.innerHTML = `<div class="space-y-5">
        <div>
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                Documents à signer <span class="text-red-500">(${toSign.length})</span>
            </h3>
            ${toSign.length > 0
                ? `<div class="space-y-2">${toSign.map(id=>docCard(id,'À signer')).join('')}</div>`
                : '<p class="text-gray-400 text-xs italic py-3 text-center bg-gray-50 rounded-xl">Aucun document à signer</p>'}
        </div>
        <div>
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                Pièces jointes <span class="text-emerald-600">(${attached.length})</span>
            </h3>
            ${attached.length > 0
                ? `<div class="space-y-2">${attached.map(id=>docCard(id,'Pièce jointe')).join('')}</div>`
                : '<p class="text-gray-400 text-xs italic py-3 text-center bg-gray-50 rounded-xl">Aucune pièce jointe</p>'}
        </div>
    </div>`;
}

// ── Avancer / Rejeter ──────────────────────────────────────
async function advanceWorkflow() {
    const id = detailWf?.id;
    if (!id) return;
    try {
        const resp = await fetch(`${WORKFLOWS_BASE}/${id}/advance`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error||'Erreur');
        const wf = workflows.find(w => w.id === id);
        if (wf && data.execution) {
            wf.executions = wf.executions.map(e => e.id===data.execution.id ? data.execution : e);
            detailWf = wf;
            openDetail(wf);
        }
        renderTiles(); renderTable();
        showSuccess('Étape avancée avec succès !');
    } catch(e) { showError(e.message || 'Impossible d\'avancer l\'étape.'); }
}

async function rejectWorkflow() {
    const id = detailWf?.id;
    if (!id) return;
    try {
        const resp = await fetch(`${WORKFLOWS_BASE}/${id}/reject`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error||'Erreur');
        const wf = workflows.find(w => w.id === id);
        if (wf && data.execution) {
            wf.executions = wf.executions.map(e => e.id===data.execution.id ? data.execution : e);
        }
        renderTiles(); renderTable();
        closeDetail();
        showSuccess('Workflow rejeté.');
    } catch(e) { showError(e.message || 'Impossible de rejeter.'); }
}

// ── Dupliquer ──────────────────────────────────────────────
async function duplicateWorkflow(id) {
    try {
        const resp = await fetch(`${WORKFLOWS_BASE}/${id}/duplicate`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.message||'Erreur');
        workflows.unshift(data.workflow);
        closeModal();
        renderTiles(); renderTable();
        showSuccess('Workflow dupliqué !');
    } catch(e) { showError('Erreur lors de la duplication.'); }
}

// ── Supprimer ──────────────────────────────────────────────
let pendingDeleteId = null;
function openDeleteModal(id)  { pendingDeleteId = id; document.getElementById('modal-delete').classList.remove('hidden'); }
function closeDeleteModal()   { pendingDeleteId = null; document.getElementById('modal-delete').classList.add('hidden'); }

async function confirmDelete() {
    if (!pendingDeleteId) return;
    try {
        const resp = await fetch(`${WORKFLOWS_BASE}/${pendingDeleteId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
        });
        if (!resp.ok) { const d = await resp.json(); throw new Error(d.message||'Erreur'); }
        workflows = workflows.filter(w => w.id !== pendingDeleteId);
        closeDeleteModal();
        renderTiles(); renderTable();
        showSuccess('Workflow supprimé.');
    } catch(e) { showError('Erreur lors de la suppression.'); closeDeleteModal(); }
}

// ── Toasts & erreurs ──────────────────────────────────────
function showModalError(msg, type) {
    const bar  = document.getElementById('modal-error-bar');
    const text = document.getElementById('modal-error-text');
    if (!bar || !text) { showError(msg); return; }
    text.textContent = msg;
    if (type === 'warning') {
        bar.className = 'mx-6 mt-3 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl text-sm font-medium flex items-center gap-2';
        bar.querySelector('i').className = 'fas fa-exclamation-triangle text-amber-500 flex-shrink-0';
    } else {
        bar.className = 'mx-6 mt-3 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium flex items-center gap-2';
        bar.querySelector('i').className = 'fas fa-exclamation-circle text-red-500 flex-shrink-0';
    }
    bar.classList.remove('hidden');
    setTimeout(() => bar.classList.add('hidden'), 6000);
}
function clearModalError() {
    const bar = document.getElementById('modal-error-bar');
    if (bar) bar.classList.add('hidden');
}

function showError(msg) {
    const el = document.getElementById('feedback-bar');
    if (!el) return;
    el.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${msg}`;
    el.className = 'p-3 bg-red-100 text-red-800 rounded-xl mb-4 text-sm font-medium';
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}
function showSuccess(msg) {
    const el = document.getElementById('success-popup');
    el.innerHTML = `<i class="fas fa-check-circle mr-2 text-green-500"></i>${msg}`;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}

async function refreshWorkflows({silent = true} = {}) {
    try {
        const resp = await fetch(WORKFLOWS_BASE, { headers: {'Accept':'application/json'} });
        if (!resp.ok) throw new Error('refresh failed');
        const data = await resp.json();
        if (Array.isArray(data)) {
            workflows = data;
            renderTiles();
            renderTable();
        }
    } catch (e) {
        if (!silent) showError('Impossible de rafraîchir la liste des workflows.');
    }
}

// ── Init ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    if (IS_EMBEDDED) {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) sidebar.style.display = 'none';

        const mainContent = document.querySelector('.main-content');
        if (mainContent) mainContent.style.marginLeft = '0';

        const topHeader = document.querySelector('.main-content > header');
        if (topHeader) topHeader.style.display = 'none';

        const mainEl = document.querySelector('.main-content main');
        if (mainEl) {
            mainEl.style.padding = '0';
            mainEl.style.height = '100vh';
        }

        const modalMain = document.getElementById('modal-main');
        if (modalMain) {
            const root = modalMain.parentElement;
            if (root) {
                const keepVisibleIds = new Set(['modal-main', 'zone-modal']);
                Array.from(root.children).forEach(function (child) {
                    if (!keepVisibleIds.has(child.id) && child.tagName !== 'SCRIPT') {
                        child.style.display = 'none';
                    }
                });
            }

            modalMain.classList.remove('hidden', 'fixed', 'inset-0', 'bg-black/40', 'p-4', 'overflow-y-auto');
            modalMain.classList.add('relative', 'bg-transparent', 'p-0', 'overflow-visible', 'h-full');

            const modalPanel = modalMain.firstElementChild;
            if (modalPanel) {
                modalPanel.classList.remove('max-w-2xl', 'my-6');
                modalPanel.classList.add('max-w-none', 'w-full', 'h-full', 'rounded-none', 'border-0', 'shadow-none');
            }
        }
    }

    // Charger les modèles via AJAX
    try {
        const resp = await fetch(WORKFLOW_TEMPLATES_BASE, { headers: {'Accept':'application/json'} });
        if (resp.ok) templates = await resp.json();
    } catch { templates = []; }

    await refreshWorkflows({silent: true});
    renderTiles();
    const _hashFilterMap = { '#termine': 'Terminé', '#en-cours': 'En cours', '#rejete': 'Rejeté', '#en-attente': 'En attente' };
    setFilter(_hashFilterMap[window.location.hash] || 'Tous');

    // Rafraîchir automatiquement le tableau créateur pour suivre l'évolution
    setInterval(() => refreshWorkflows({silent: true}), 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshWorkflows({silent: true});
    });

    const autoCreate = <?php echo json_encode($autoCreate, 15, 512) ?>;
    if (autoCreate === 'workflow' || autoCreate === 'template') {
        openCreateModal(autoCreate);
        const url = new URL(window.location.href);
        url.searchParams.delete('create');
        window.history.replaceState({}, '', url.toString());
    }
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\e-administration_laravel\resources\views/workflows/index.blade.php ENDPATH**/ ?>