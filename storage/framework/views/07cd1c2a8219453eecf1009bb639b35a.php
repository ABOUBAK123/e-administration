<?php $__env->startSection('title', 'Templates partagés'); ?>
<?php $__env->startSection('page-title', 'Templates partagés'); ?>
<?php $__env->startSection('page-subtitle', 'Modèles de documents — remplissez les variables et générez votre fichier'); ?>

<?php $__env->startSection('content'); ?>
<?php use Illuminate\Support\Facades\Storage; ?>


<?php if(!$templates->isEmpty()): ?>
<script id="gen-tpl-store" type="application/json">
<?php echo json_encode(
    $templates->mapWithKeys(function($tpl) {
        return [$tpl->id => [
            'id'        => $tpl->id,
            'name'      => $tpl->name,
            'file_type' => $tpl->file_type ?? '',
            'content'   => $tpl->content ?? '',
            'db_vars'   => $tpl->variables->map(function($v) {
                return [
                    'key'           => $v->key,
                    'label'         => $v->label ?: $v->key,
                    'field_type'    => $v->field_type ?: 'text',
                    'required'      => (bool)$v->required,
                    'placeholder'   => $v->placeholder ?? '',
                    'default_value' => $v->default_value ?? '',
                    'options'       => $v->options ?? [],
                ];
            })->values()->toArray(),
            'docx_vars' => $tpl->docx_vars ?? [],
        ]];
    }),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
); ?>

</script>
<?php endif; ?>


<div class="flex items-center gap-4 mb-6">
    <form method="GET" action="<?php echo e(route('shared-templates.index')); ?>" class="flex-1 max-w-md flex gap-2">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Rechercher un template…"
                class="w-full border border-gray-300 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6] bg-white">
        </div>
        <button type="submit" class="px-4 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold hover:bg-[#1f47bb] transition">Chercher</button>
        <?php if($search): ?>
            <a href="<?php echo e(route('shared-templates.index')); ?>" class="px-4 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-100 transition"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>


<?php if($templates->isEmpty()): ?>
    <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-full bg-purple-50 flex items-center justify-center mb-5">
            <i class="fas fa-layer-group text-4xl text-purple-300"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-1">Aucun template partagé</h3>
        <p class="text-sm text-gray-400 max-w-sm">Les modèles partagés par l'administrateur apparaîtront ici.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $ft = $tpl->file_type ?? 'docx';
            if ($ft === 'xlsx')       { $extIcon = 'fa-file-excel';      $extColor = 'text-green-500 bg-green-100'; }
            elseif ($ft === 'pptx')  { $extIcon = 'fa-file-powerpoint'; $extColor = 'text-orange-500 bg-orange-100'; }
            elseif ($ft === 'pdf')   { $extIcon = 'fa-file-pdf';        $extColor = 'text-red-500 bg-red-100'; }
            else                     { $extIcon = 'fa-file-word';       $extColor = 'text-blue-500 bg-blue-100'; }
        ?>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition p-5 flex flex-col gap-3">
            <div class="flex items-start gap-3">
                <div class="h-12 w-12 rounded-xl <?php echo e($extColor); ?> flex items-center justify-center flex-shrink-0">
                    <i class="fas <?php echo e($extIcon); ?> text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-800 text-sm leading-tight"><?php echo e($tpl->name); ?></h3>
                    <p class="text-xs text-gray-400 mt-0.5"><?php echo e(strtoupper($ft)); ?></p>
                    <?php if($tpl->administration): ?>
                        <p class="text-xs text-gray-500 mt-0.5 truncate">
                            <i class="fas fa-building mr-1 text-[10px]"></i><?php echo e($tpl->administration->name); ?>

                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php
                $allVars = $tpl->variables->count() > 0
                    ? $tpl->variables
                    : collect($tpl->docx_vars ?? [])->map(fn($v) => (object)['label' => $v['label'], 'key' => $v['key']]);
            ?>
            <?php if($allVars->count() > 0): ?>
            <div class="flex flex-wrap gap-1">
                <?php $__currentLoopData = $allVars->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="inline-block bg-blue-50 text-blue-700 text-[10px] font-medium px-2 py-0.5 rounded-full border border-blue-100"><?php echo e($v->label ?: $v->key); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php if($allVars->count() > 3): ?>
                    <span class="inline-block bg-gray-100 text-gray-500 text-[10px] px-2 py-0.5 rounded-full">+<?php echo e($allVars->count() - 3); ?></span>
                <?php endif; ?>
            </div>
            <?php elseif($tpl->content): ?>
                <p class="text-[11px] text-gray-400 italic">Variables détectées depuis le contenu</p>
            <?php else: ?>
                <p class="text-[11px] text-gray-400 italic">Aucune variable</p>
            <?php endif; ?>

            <div class="flex gap-2 mt-auto">
                <button type="button"
                    data-tpl-id="<?php echo e($tpl->id); ?>"
                    class="js-open-generate flex-1 flex items-center justify-center gap-2 py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-xs font-bold rounded-xl transition shadow-sm">
                    <i class="fas fa-wpforms"></i> Remplir &amp; Générer
                </button>
                <?php if($tpl->storage_path && Storage::disk('public')->exists($tpl->storage_path)): ?>
                <a href="<?php echo e(asset('storage/' . $tpl->storage_path)); ?>" target="_blank"
                    class="py-2.5 px-3 bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs rounded-xl transition" title="Aperçu">
                    <i class="fas fa-eye"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endif; ?>


<div id="modal-generate"
     class="fixed inset-0 z-[9999] hidden items-center justify-center p-4"
     style="background:rgba(15,23,42,0.6);backdrop-filter:blur(3px);">

    <div id="modal-generate-box"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[88vh] flex flex-col overflow-hidden"
         style="transform:scale(0.95);transition:transform .15s ease;">

        <div class="flex items-start justify-between px-6 py-5 flex-shrink-0"
             style="background:linear-gradient(135deg,#2453d6,#3b6ee8);">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-wpforms"></i> Générer un document
                </h2>
                <p id="gen-tpl-name" class="text-xs text-blue-200 mt-1 font-medium max-w-xs truncate"></p>
            </div>
            <button id="gen-close-btn"
                class="w-7 h-7 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/35 text-white transition flex-shrink-0 mt-0.5">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <div id="gen-feedback" class="hidden flex-shrink-0 px-6 pt-4"></div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            <form id="gen-form" novalidate>
                <div id="gen-hint" class="hidden mb-4 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Renseignez les champs ci-dessous correspondant aux variables <strong>&#123;&#123;…&#125;&#125;</strong> du template.
                </div>

                <div class="mb-4">
                    <label for="gen-output-format" class="block text-xs font-semibold text-gray-700 mb-1">Format de sortie</label>
                    <select id="gen-output-format" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2453d6] outline-none bg-white transition">
                        <option value="source">Format source du template (DOCX/XLSX/PPTX)</option>
                        <option value="pdf" selected>PDF signable</option>
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">Les templates Office sont convertis automatiquement en PDF pour le circuit de signature. La source editable est conservee dans les versions si la conversion reussit.</p>
                </div>

                <div class="mb-4">
                    <label for="gen-folder" class="block text-xs font-semibold text-gray-700 mb-1">Dossier de destination</label>
                    <select id="gen-folder" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2453d6] outline-none bg-white transition">
                        <option value="">Aucun (racine de Mes Documents)</option>
                        <?php $__currentLoopData = $folders ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $folderName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($folderName); ?>"><?php echo e($folderName); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <option value="__new__">+ Nouveau dossier…</option>
                    </select>
                    <input id="gen-folder-new" type="text" placeholder="Nom du nouveau dossier"
                        class="hidden mt-2 w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2453d6] outline-none bg-white transition">
                    <p class="text-[11px] text-gray-500 mt-1">Le document généré sera placé dans ce dossier de "Mes Documents".</p>
                </div>

                <div id="gen-fields-container" class="space-y-4"></div>
                <div id="gen-no-fields" class="hidden text-center py-10">
                    <div class="w-16 h-16 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-alt text-2xl text-gray-300"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-600">Aucune variable détectée</p>
                    <p class="text-xs text-gray-400 mt-1">Ce template sera copié tel quel dans vos documents.</p>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50 flex items-center gap-3">
            <div id="gen-progress" class="hidden flex items-center gap-2">
                <i class="fas fa-spinner fa-spin text-[#2453d6] text-sm"></i>
                <span class="text-xs text-gray-600">Génération en cours…</span>
            </div>
            <div id="gen-footer-btns" class="flex gap-2 ml-auto">
                <button type="button" id="gen-cancel-btn"
                    class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm hover:bg-gray-100 transition font-medium">
                    Annuler
                </button>
                <button type="button" id="gen-submit-btn"
                    class="px-6 py-2.5 rounded-xl bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-bold transition flex items-center gap-2 shadow-sm">
                    <i class="fas fa-file-export text-xs"></i> Générer
                </button>
            </div>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    var BASE_URL  = '<?php echo e(url("shared-templates")); ?>';
    var DOCS_URL  = '<?php echo e(route("documents.index")); ?>';

    /* Lire le store JSON injecté par Blade dans <script type="application/json"> */
    var tplStore = {};
    var storeEl  = document.getElementById('gen-tpl-store');
    if (storeEl) {
        try { tplStore = JSON.parse(storeEl.textContent || '{}'); }
        catch(e) { console.error('gen-tpl-store parse error', e); }
    }

    /* slugify — même algo que SharedTemplates.tsx (app Node.js)
       "N'DJOMON Ohouo Landry Marius" => "n_djomon_ohouo_landry_marius" */
    function slugify(text) {
        return (text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/\u0027/g, '_')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') || 'var';
    }

    /* Extraire les variables du content.
       RegExp construit dynamiquement pour éviter que Blade interprète les accolades. */
    var OB = '{', CB = '}';
    var RE_VAR_CURLY = new RegExp(OB + OB + '\\s*([^' + CB + ']+?)\\s*' + CB + CB, 'g');
    var RE_VAR_SQUARE = /\[([^\[\]]+?)\]/g;

    // Variables injectees automatiquement par le backend a la generation.
    // Elles ne doivent pas apparaitre comme champs a saisir.
    var AUTO_KEYS = new Set([
        'document_number',
        'qr_verify_url',
        'date_du_jour',
        'date_today',
        'date',
        'aujourd_hui',
        'date_generation',
        'nom_responsable',
        'responsable',
        'signataire',
        'nom_signataire'
    ]);

    function cleanVarToken(token) {
        return String(token || '').trim();
    }

    function extractContentVars(content) {
        if (!content) return {};
        RE_VAR_CURLY.lastIndex = 0;
        RE_VAR_SQUARE.lastIndex = 0;
        var out = {}, m;
        while ((m = RE_VAR_CURLY.exec(content)) !== null) {
            var orig = cleanVarToken(m[1]);
            var slug = slugify(orig);
            if (slug && !out[slug]) out[slug] = orig;
        }
        while ((m = RE_VAR_SQUARE.exec(content)) !== null) {
            var orig2 = cleanVarToken(m[1]);
            var slug2 = slugify(orig2);
            if (slug2 && !out[slug2]) out[slug2] = orig2;
        }
        return out;
    }

    /* Merge variables BDD (prioritaires) + variables détectées dans content + variables docx */
    function buildFields(dbVars, cmap, docxVars) {
        var map = new Map();
        // 1. Variables BDD (priorité max — ont label, type, etc.)
        (dbVars || []).forEach(function(v) {
            if (!v || !v.key) return;
            if (AUTO_KEYS.has(v.key)) return;
            map.set(v.key, {
                key: v.key, label: v.label || v.key,
                ft: v.field_type || 'text',
                ph: v.placeholder || '', def: v.default_value || '',
                req: !!v.required,
                opts: Array.isArray(v.options) ? v.options : [],
            });
        });
        // 2. Variables extraites du XML docx (si pas déjà dans BDD)
        (docxVars || []).forEach(function(v) {
            if (!v || !v.key) return;
            if (AUTO_KEYS.has(v.key)) return;
            if (!map.has(v.key)) {
                map.set(v.key, {
                    key: v.key, label: v.label || v.key,
                    ft: v.field_type || 'text',
                    ph: v.placeholder || '', def: v.default_value || '',
                    req: !!v.required,
                    opts: Array.isArray(v.options) ? v.options : [],
                });
            }
        });
        // 3. Variables extraites du champ content texte (si pas déjà présentes)
        Object.keys(cmap).forEach(function(slug) {
            if (AUTO_KEYS.has(slug)) return;
            if (!map.has(slug)) {
                map.set(slug, { key: slug, label: cmap[slug], ft: 'text', ph: '', def: '', req: false, opts: [] });
            }
        });
        return Array.from(map.values());
    }

    /* Construire un champ de formulaire */
    function mkField(f) {
        var wrap = document.createElement('div');
        wrap.className = 'space-y-1';

        var lbl = document.createElement('label');
        lbl.className = 'flex items-center flex-wrap gap-2 text-xs font-semibold text-gray-700';
        lbl.appendChild(document.createTextNode(f.label));

        if (f.label !== f.key) {
            var badge = document.createElement('code');
            badge.className = 'text-[10px] font-mono bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded';
            badge.textContent = OB + OB + f.key + CB + CB;
            lbl.appendChild(badge);
        }
        if (f.req) {
            var star = document.createElement('span');
            star.className = 'text-red-500 font-bold ml-0.5';
            star.textContent = ' *';
            lbl.appendChild(star);
        }
        wrap.appendChild(lbl);

        var inp;
        if (f.ft === 'textarea') {
            inp = document.createElement('textarea');
            inp.className = 'w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2453d6] outline-none resize-y min-h-[80px] transition';
            inp.rows = 3;
        } else if (f.ft === 'select' && f.opts && f.opts.length) {
            inp = document.createElement('select');
            inp.className = 'w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2453d6] outline-none bg-white transition';
            var bl = document.createElement('option');
            bl.value = ''; bl.textContent = '— Sélectionner —';
            inp.appendChild(bl);
            f.opts.forEach(function(o) {
                var op = document.createElement('option');
                op.value = o; op.textContent = o;
                inp.appendChild(op);
            });
        } else {
            inp = document.createElement('input');
            inp.type = f.ft === 'date' ? 'date' : f.ft === 'number' ? 'number' : 'text';
            inp.className = 'w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2453d6] outline-none transition';
        }
        inp.name          = 'values[' + f.key + ']';
        inp.dataset.key   = f.key;
        inp.dataset.label = f.label;
        if (f.ph)  inp.placeholder = f.ph;
        if (f.def) inp.value       = f.def;
        if (f.req) inp.required    = true;
        inp.addEventListener('input', function() {
            this.classList.remove('border-red-400', 'ring-2', 'ring-red-200');
        });
        wrap.appendChild(inp);
        return wrap;
    }

    var _tid = '';

    function openModal(tpl) {
        _tid = tpl.id;
        document.getElementById('gen-tpl-name').textContent =
            tpl.name + (tpl.file_type ? ' \u2014 ' + tpl.file_type.toUpperCase() : '');

        var outputFormatEl = document.getElementById('gen-output-format');
        if (outputFormatEl) {
            outputFormatEl.value = 'pdf';
        }

        var folderEl = document.getElementById('gen-folder');
        var folderNewEl = document.getElementById('gen-folder-new');
        if (folderEl) { folderEl.value = ''; }
        if (folderNewEl) { folderNewEl.value = ''; folderNewEl.classList.add('hidden'); }

        var fileType = String(tpl.file_type || '').toLowerCase();
        var isOffice = ['docx', 'xlsx', 'pptx'].indexOf(fileType) !== -1;
        var docxVars = Array.isArray(tpl.docx_vars) ? tpl.docx_vars : [];
        var dbVars = Array.isArray(tpl.db_vars) ? tpl.db_vars : [];

        // Si le core DOCX a des variables, il devient la reference stricte.
        // Evite d'afficher des champs historiques de la BDD qui n'existent plus dans le fichier.
        if (isOffice && docxVars.length > 0) {
            var coreKeys = new Set(docxVars.map(function(v) { return v && v.key ? String(v.key) : ''; }));
            dbVars = dbVars.filter(function(v) { return v && v.key && coreKeys.has(String(v.key)); });
        }

        // Pour les templates Office, la source de verite est le fichier (db_vars/docx_vars).
        // Evite d'ajouter des champs fantomes issus du texte brut extrait dans content.
        var cmap   = isOffice ? {} : extractContentVars(tpl.content || '');
        var fields = buildFields(dbVars, cmap, docxVars);
        var cont   = document.getElementById('gen-fields-container');
        var noF    = document.getElementById('gen-no-fields');
        var hint   = document.getElementById('gen-hint');
        cont.innerHTML = '';

        if (fields.length === 0) {
            cont.classList.add('hidden'); noF.classList.remove('hidden'); hint.classList.add('hidden');
        } else {
            cont.classList.remove('hidden'); noF.classList.add('hidden'); hint.classList.remove('hidden');
            fields.forEach(function(f) { cont.appendChild(mkField(f)); });
        }

        hideFb();
        document.getElementById('gen-progress').classList.add('hidden');
        document.getElementById('gen-footer-btns').classList.remove('hidden');
        document.getElementById('gen-submit-btn').disabled = false;

        var modal = document.getElementById('modal-generate');
        var box   = document.getElementById('modal-generate-box');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        requestAnimationFrame(function() { box.style.transform = 'scale(1)'; });
    }

    function closeModal() {
        var modal = document.getElementById('modal-generate');
        var box   = document.getElementById('modal-generate-box');
        box.style.transform = 'scale(0.95)';
        setTimeout(function() { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 130);
    }

    function showFb(type, msg) {
        var box = document.getElementById('gen-feedback');
        var ok  = type === 'success';
        box.innerHTML = '<div class="rounded-xl px-4 py-3 text-xs font-medium flex items-start gap-2 '
            + (ok ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700')
            + '"><i class="fas ' + (ok ? 'fa-check-circle' : 'fa-exclamation-circle') + ' flex-shrink-0 mt-0.5"></i><span>' + msg + '</span></div>';
        box.classList.remove('hidden');
    }
    function hideFb() { var b = document.getElementById('gen-feedback'); b.innerHTML = ''; b.classList.add('hidden'); }

    function doSubmit() {
        if (!_tid) return;
        var form = document.getElementById('gen-form');
        var btn  = document.getElementById('gen-submit-btn');
        var prog = document.getElementById('gen-progress');
        var foot = document.getElementById('gen-footer-btns');
        var outputFormatEl = document.getElementById('gen-output-format');
        var vals = {}, miss = [];

        form.querySelectorAll('[data-key]').forEach(function(inp) {
            vals[inp.dataset.key] = inp.value;
            if (inp.required && !inp.value.trim()) {
                miss.push(inp.dataset.label || inp.dataset.key);
                inp.classList.add('border-red-400', 'ring-2', 'ring-red-200');
            } else {
                inp.classList.remove('border-red-400', 'ring-2', 'ring-red-200');
            }
        });

        if (miss.length > 0) { showFb('error', 'Champs obligatoires : ' + miss.join(', ')); return; }

        var folderEl = document.getElementById('gen-folder');
        var folderNewEl = document.getElementById('gen-folder-new');
        var folderValue = '';
        if (folderEl) {
            if (folderEl.value === '__new__') {
                folderValue = folderNewEl ? folderNewEl.value.trim() : '';
                if (!folderValue) { showFb('error', 'Veuillez saisir un nom de dossier.'); return; }
            } else {
                folderValue = folderEl.value;
            }
        }

        hideFb(); btn.disabled = true; prog.classList.remove('hidden');
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        fetch(BASE_URL + '/' + _tid + '/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({
                values: vals,
                output_format: outputFormatEl ? outputFormatEl.value : 'pdf',
                folder: folderValue,
            }),
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            prog.classList.add('hidden'); btn.disabled = false;
            if (!data.success) { showFb('error', data.message || 'Erreur.'); return; }
            var msg = (data.message || 'Document généré !');
            if (data.warning) msg += ' ' + data.warning;
            showFb('success', msg + ' Redirection…');
            foot.classList.add('hidden');
            setTimeout(function() { window.location.href = DOCS_URL; }, 2000);
        })
        .catch(function(err) {
            prog.classList.add('hidden'); btn.disabled = false;
            showFb('error', 'Erreur réseau.'); console.error(err);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {

        /* Bascule vers le champ texte libre quand "+ Nouveau dossier…" est choisi */
        var folderSelectEl = document.getElementById('gen-folder');
        var folderNewInputEl = document.getElementById('gen-folder-new');
        if (folderSelectEl && folderNewInputEl) {
            folderSelectEl.addEventListener('change', function() {
                folderNewInputEl.classList.toggle('hidden', folderSelectEl.value !== '__new__');
                if (folderSelectEl.value === '__new__') { folderNewInputEl.focus(); }
            });
        }

        /* Délégation : clic sur .js-open-generate */
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.js-open-generate');
            if (!btn) return;
            var id = btn.getAttribute('data-tpl-id');
            if (!id || !tplStore[id]) { console.error('Template introuvable:', id, tplStore); return; }
            openModal(tplStore[id]);
        });

        var el;
        el = document.getElementById('gen-cancel-btn');
        if (el) el.addEventListener('click', closeModal);

        el = document.getElementById('gen-close-btn');
        if (el) el.addEventListener('click', closeModal);

        el = document.getElementById('gen-submit-btn');
        if (el) el.addEventListener('click', doSubmit);

        el = document.getElementById('modal-generate');
        if (el) el.addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    });

    window._genStore = tplStore;
})();
</script>
<?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\e-administration_laravel.worktrees\gestion-courrier-notification-email\resources\views/shared-templates/index.blade.php ENDPATH**/ ?>