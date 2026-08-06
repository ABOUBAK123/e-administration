@extends('layouts.app')
@section('title', 'Détail réunion')
@section('page-title', $meeting->title)
@section('page-subtitle', 'Détails, participants et émargement')

@section('content')
@php
    $currentUserId = (string) auth()->id();
    $isWriter = (string) ($meeting->minutes_writer_id ?? '') === $currentUserId;
    $isValidator = (string) ($meeting->validator_id ?? '') === $currentUserId;
@endphp
@php
    $isOrganizer = isset($isOrganizer) ? $isOrganizer : ((string) ($meeting->organizer_id ?? '') === (string) auth()->id());
@endphp
@include('meetings._nav')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div><span class="text-gray-500">Type:</span> {{ $meeting->meeting_type }}</div>
            <div><span class="text-gray-500">Salle:</span> {{ $meeting->room?->name }} ({{ $meeting->room?->location }})</div>
            <div>
                <span class="text-gray-500">Modalité:</span>
                @if($meeting->is_virtual)
                    <span class="font-semibold text-blue-700">Virtuelle / hybride</span>
                    @if($meeting->meeting_link)
                        — <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener" class="text-[#2453d6] font-semibold hover:underline">Rejoindre la visioconférence</a>
                    @endif
                @else
                    Présentiel
                @endif
            </div>
            <div><span class="text-gray-500">Début:</span> {{ $meeting->starts_at?->format('d/m/Y H:i') }}</div>
            <div><span class="text-gray-500">Fin:</span> {{ $meeting->ends_at?->format('d/m/Y H:i') }}</div>
            <div><span class="text-gray-500">Délai fixé:</span> {{ $meeting->processing_deadline?->format('d/m/Y H:i') ?: '—' }}</div>
            <div><span class="text-gray-500">Organisateur:</span> {{ $meeting->organizer?->name }}</div>
            <div><span class="text-gray-500">Rédacteur:</span> {{ $meeting->minutesWriter?->name }}</div>
            <div><span class="text-gray-500">Validateur:</span> {{ $meeting->validator?->name ?: '—' }}</div>
            <div><span class="text-gray-500">Workflow:</span> <span class="font-semibold">{{ $meeting->workflow_status ?? 'draft' }}</span></div>
            <div><span class="text-gray-500">Validé par:</span> {{ $meeting->validatedByUser?->name ?: '—' }}</div>
            <div><span class="text-gray-500">Date de validation:</span> {{ $meeting->validated_at?->format('d/m/Y H:i') ?: '—' }}</div>
        </div>

        <div>
            <h3 class="font-semibold text-gray-800 mb-1">Ordre du jour</h3>
            <div class="text-sm text-gray-700 whitespace-pre-line">{{ $meeting->agenda ?: 'Aucun ordre du jour.' }}</div>
        </div>

        <div>
            <h3 class="font-semibold text-gray-800 mb-1">Participants ({{ $meeting->participants->count() }})</h3>
            <ul class="text-sm text-gray-700 space-y-1">
                @forelse($meeting->participants as $p)
                <li>- {{ $p->full_name ?: $p->user?->name }} ({{ $p->email ?: $p->user?->email }})</li>
                @empty
                <li class="text-gray-400">Aucun participant enregistré.</li>
                @endforelse
            </ul>
        </div>

        <div>
            <h3 class="font-semibold text-gray-800 mb-2">Pièces jointes de la réunion</h3>
            <ul class="text-sm text-gray-700 space-y-1">
                @forelse((array)($meeting->attachments ?? []) as $file)
                    <li>- {{ $file['name'] ?? 'Document' }}</li>
                @empty
                    <li class="text-gray-400">Aucune pièce jointe.</li>
                @endforelse
            </ul>
        </div>

        <div class="border-t border-gray-100 pt-4 space-y-4">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-file-word text-blue-500"></i>
                Modèle de compte rendu
            </h3>

            @if($meeting->minutes_template && str_starts_with($meeting->minutes_template, '/storage/'))

            {{-- Fichier modèle --}}
            <div class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                <i class="fas fa-file-word text-2xl text-blue-500"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ basename($meeting->minutes_template) }}</p>
                    <p class="text-xs text-gray-500">Modèle Word uploadé</p>
                </div>
                <div class="flex gap-2 shrink-0">
                    @if($isWriter || $isValidator || $isOrganizer)
                    <button type="button" id="btn-analyze-tpl" onclick="analyzeTemplate()"
                            class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 whitespace-nowrap">
                        <i class="fas fa-robot mr-1"></i> Analyser le modèle
                    </button>
                    @endif
                    @if($isWriter || $isValidator)
                    <button type="button" onclick="openTemplateInOO()"
                            class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#2453d6] text-white text-xs font-semibold hover:bg-[#1f47bb] whitespace-nowrap">
                        <i class="fas fa-edit mr-1"></i> OnlyOffice
                    </button>
                    @endif
                </div>
            </div>

            {{-- Panneau Agent IA --}}
            <div id="tpl-analysis-panel"
                 class="{{ ($meeting->template_variables && count($meeting->template_variables)) ? '' : 'hidden' }} rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-4">

                <div class="flex items-center justify-between flex-wrap gap-2">
                    <span class="flex items-center gap-2 text-sm font-semibold text-emerald-800">
                        <i class="fas fa-robot text-emerald-600"></i> Agent IA — Analyse du modèle
                    </span>
                    <span id="tpl-seal-badge"
                          class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $meeting->template_sealed_path ? 'bg-blue-100 text-blue-700' : 'hidden' }}">
                        <i class="fas fa-lock mr-1"></i> Zones de signature scellées
                    </span>
                </div>

                {{-- Badges des variables --}}
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">
                        <i class="fas fa-code text-gray-400 mr-1"></i>
                        Variables détectées (<span id="tpl-var-count">{{ $meeting->template_variables ? count($meeting->template_variables) : 0 }}</span>)
                    </p>
                    <div id="tpl-var-tags" class="flex flex-wrap gap-2">
                        @if($meeting->template_variables)
                            @foreach($meeting->template_variables as $var)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white border border-blue-300 text-blue-700 text-xs font-mono">
                                <i class="fas fa-at text-blue-400 text-[10px]"></i>{{ $var }}
                            </span>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- Formulaire de remplissage --}}
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-3 uppercase tracking-wide">
                        <i class="fas fa-pen text-gray-400 mr-1"></i>
                        Renseigner les variables
                    </p>
                    <div id="tpl-var-inputs" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if($meeting->template_variables)
                            @foreach($meeting->template_variables as $var)
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-gray-700 font-mono">{{ '{' . '{ ' . $var . ' }' . '}' }}</label>
                                <input type="text" data-varname="{{ $var }}"
                                       class="tpl-var-input border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-200"
                                       placeholder="{{ $var }}">
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- Bouton générer --}}
                <div class="flex items-center gap-3 pt-1">
                    <button type="button" onclick="generateDocument()"
                            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-[#1f47bb] shadow">
                        <i class="fas fa-file-download"></i> Générer le document final
                    </button>
                    <span id="tpl-generate-status" class="text-xs text-gray-500 hidden"></span>
                </div>
            </div>

            {{-- Message avant première analyse --}}
            <div id="tpl-not-analyzed"
                 class="{{ ($meeting->template_variables && count($meeting->template_variables)) ? 'hidden' : '' }} text-xs text-gray-500 italic flex items-center gap-2 py-1">
                <i class="fas fa-info-circle text-blue-400"></i>
                Cliquez sur "Analyser le modèle" pour détecter les variables <code class="bg-gray-100 px-1 rounded">{{ '{' . '{ variable }' . '}' }}</code>
                et sceller les zones de signature <code class="bg-gray-100 px-1 rounded">@@@</code>.
            </div>

            @else
            <p class="text-sm text-gray-400 italic">Aucun modèle de compte rendu chargé pour cette réunion.</p>
            @endif

            {{-- Contenu libre --}}
            @if($isWriter || $isValidator)
            <div class="border-t border-gray-100 pt-3 space-y-2" id="ai-minutes-block">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    <i class="fas fa-robot text-purple-500 mr-1"></i> IA : écouter la réunion et proposer un compte rendu
                </p>
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" id="ai-record-btn" onclick="toggleAiRecording()"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg bg-purple-600 text-white text-xs font-semibold hover:bg-purple-700">
                        <i class="fas fa-microphone"></i> <span id="ai-record-btn-label">Démarrer l'écoute</span>
                    </button>
                    <span id="ai-record-timer" class="text-xs text-gray-500 hidden">00:00</span>
                    <button type="button" id="ai-generate-btn" onclick="generateAiMinutes()" disabled
                            class="hidden items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 disabled:opacity-50">
                        <i class="fas fa-magic"></i> Générer la proposition
                    </button>
                    <span id="ai-minutes-status" class="text-xs text-gray-500"></span>
                </div>
                <p class="text-[11px] text-gray-400 italic">Le texte généré est une proposition à relire et corriger avant enregistrement.</p>
            </div>
            @endif

            <form method="POST" action="{{ route('meetings.minutes.update', $meeting) }}" class="space-y-2 border-t border-gray-100 pt-3">
                @csrf
                <label class="block text-xs font-semibold text-gray-600 mb-1">Contenu libre du compte rendu</label>
                <textarea name="minutes_content" id="minutes_content" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Rédiger le compte rendu...">{{ old('minutes_content', $meeting->minutes_content ?? '') }}</textarea>
                <input type="text" name="note" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Note de version (optionnel)">
                <button class="px-3 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-[#1f47bb]">Enregistrer une version</button>
            </form>
        </div>


        <div class="border-t border-gray-100 pt-4 space-y-3">
            <h3 class="font-semibold text-gray-800">Validation et signature</h3>
            <div class="flex flex-wrap gap-2">
                @if($isWriter && $meeting->workflow_status === 'draft')
                    <form method="POST" action="{{ route('meetings.workflow', $meeting) }}">@csrf<input type="hidden" name="action" value="submit_validation"><button class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold">Envoyer en validation</button></form>
                @endif

                @if($isValidator && $meeting->workflow_status === 'in_validation')
                    <form method="POST" action="{{ route('meetings.workflow', $meeting) }}">@csrf<input type="hidden" name="action" value="validate"><button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold">Valider</button></form>
                    <form method="POST" action="{{ route('meetings.workflow', $meeting) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="action" value="request_review">
                        <input type="text" name="review_comment" placeholder="Commentaire de relecture" class="border border-gray-300 rounded-lg px-2 py-1 text-xs">
                        <button class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs font-semibold">Demander relecture</button>
                    </form>
                @endif

                @if($isWriter)
                    <form method="POST" action="{{ route('meetings.workflow', $meeting) }}" id="sign-form">
                        @csrf
                        <input type="hidden" name="action" value="sign_writer">
                        <input type="hidden" name="signature" id="writer-signature-input">
                        <button type="button" onclick="submitWriterSignature()" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold">Signature électronique du rédacteur</button>
                    </form>
                @endif

                @if($isWriter)
                    <form method="POST" action="{{ route('meetings.workflow', $meeting) }}">@csrf<input type="hidden" name="action" value="publish"><button class="px-3 py-1.5 rounded-lg bg-purple-600 text-white text-xs font-semibold">Publier et diffuser</button></form>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs text-gray-500 mb-2">Zone de signature du rédacteur</p>
                <canvas id="writer-signature-pad" width="520" height="120" class="w-full border border-gray-300 rounded bg-white"></canvas>
                <button type="button" onclick="clearWriterSignature()" class="mt-2 text-xs text-gray-600 hover:underline">Effacer la signature</button>
                <p class="text-xs text-gray-500 mt-1">Signé le: {{ $meeting->writer_signed_at?->format('d/m/Y H:i') ?: '—' }}</p>
            </div>

            @if($meeting->review_requested)
                <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm px-3 py-2">
                    Relecture demandée: {{ $meeting->review_comment ?: 'Aucun commentaire' }}
                </div>
            @endif
        </div>

        <div class="border-t border-gray-100 pt-4">
            <h3 class="font-semibold text-gray-800 mb-2">Historique des versions</h3>
            <ul class="space-y-2 text-sm">
                @forelse($meeting->minutesVersions as $version)
                    <li class="border border-gray-200 rounded-lg px-3 py-2">
                        <div class="font-semibold text-gray-700">Version {{ $version->version_no }} - {{ $version->workflow_status ?: 'draft' }}</div>
                        <div class="text-xs text-gray-500">{{ $version->created_at?->format('d/m/Y H:i') }} par {{ $version->creator?->name ?: 'Utilisateur' }}</div>
                        <div class="text-xs text-gray-600 mt-1">{{ $version->note ?: 'Mise à jour' }}</div>
                    </li>
                @empty
                    <li class="text-gray-400">Aucune version enregistrée.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Émargement QR</h3>
        <div class="text-xs text-gray-500 mb-3">Lien public pour scanner et signer la présence.</div>

        <div id="meeting-qr-print" class="mb-3 border border-gray-200 rounded-xl p-4 bg-gray-50 text-center">
            <div class="text-xs text-gray-500">Scanner pour accéder au formulaire d'émargement</div>
            @if(!empty($qrImageDataUri))
                <img src="{{ $qrImageDataUri }}" alt="QR émargement" class="mx-auto mt-2 h-48 w-48 object-contain">
            @else
                <div class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1">
                    Aperçu QR indisponible. Utilisez le lien ci-dessous.
                </div>
            @endif
            <div class="mt-2 text-[11px] text-gray-600">{{ $meeting->title }}</div>
        </div>

        <input type="text" readonly value="{{ $qrUrl }}" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-xs mb-3">
        <div class="flex items-center gap-2">
            <a href="{{ $qrUrl }}" target="_blank" class="inline-block px-3 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-[#1f47bb]">Ouvrir la page QR</a>
            <button type="button" onclick="window.print()" class="inline-block px-3 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-100">Imprimer le QR</button>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <a href="{{ route('meetings.dashboard', $meeting) }}" class="text-sm text-[#2453d6] font-semibold hover:underline">Tableau de présence</a>
            <div class="text-xs text-gray-500 mt-1">Présents: {{ $meeting->attendances->count() }}</div>
        </div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #meeting-qr-print, #meeting-qr-print * { visibility: visible !important; }
    #meeting-qr-print {
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        border: 0;
        background: #fff;
        margin: 0;
        padding: 20px;
    }
}
</style>

<script>
(function () {
    const canvas = document.getElementById('writer-signature-pad');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    let drawing = false;

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function start(e) {
        drawing = true;
        const p = getPos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function move(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = getPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }

    function end() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: true });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    window.clearWriterSignature = function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    window.submitWriterSignature = function () {
        const data = canvas.toDataURL('image/png');
        document.getElementById('writer-signature-input').value = data;
        document.getElementById('sign-form').submit();
    };
})();
</script>

{{-- ===== Modal OnlyOffice ÔÇô Mod├¿le de compte rendu ===== --}}
<div id="oo-tpl-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 hidden" role="dialog" aria-modal="true">
    <div class="bg-white rounded-2xl shadow-2xl flex flex-col w-full max-w-6xl mx-4" style="height:90vh">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <div>
                <p class="font-bold text-gray-900 text-sm">Mod├¿le de compte rendu ÔÇô <span class="text-[#2453d6]">{{ $meeting->title }}</span></p>
                <p class="text-xs text-gray-400">├ëditeur OnlyOffice ÔÇô les modifications sont sauvegard├®es automatiquement</p>
            </div>
            <button onclick="closeOoTplModal()" class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
        </div>
        <div id="oo-tpl-loading" class="flex-1 flex items-center justify-center text-gray-500 text-sm gap-3">
            <svg class="animate-spin h-6 w-6 text-[#2453d6]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            Chargement de l'├®diteurÔÇª
        </div>
        <iframe id="oo-tpl-frame" class="flex-1 hidden rounded-b-2xl" frameborder="0" allowfullscreen></iframe>
        <div id="oo-tpl-error" class="hidden px-5 py-4 text-sm text-red-700 bg-red-50 rounded-b-2xl"></div>
    </div>
</div>

<script>
@php
    try {
        $tplOoConfigUrl = route('meetings.template.oo.config', $meeting);
    } catch (\Exception $e) {
        $tplOoConfigUrl = null;
    }
@endphp
const OO_TPL_CONFIG_URL = @json($tplOoConfigUrl);
const OO_TPL_CSRF = '{{ csrf_token() }}';

async function openTemplateInOO() {
    if (!OO_TPL_CONFIG_URL) {
        alert('La route OnlyOffice n\'est pas disponible. V├®rifiez le cache des routes c├┤t├® serveur (php artisan route:cache).');
        return;
    }
    const modal   = document.getElementById('oo-tpl-modal');
    const loading = document.getElementById('oo-tpl-loading');
    const frame   = document.getElementById('oo-tpl-frame');
    const errBox  = document.getElementById('oo-tpl-error');

    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    frame.classList.add('hidden');
    frame.src = '';
    errBox.classList.add('hidden');

    try {
        const resp = await fetch(OO_TPL_CONFIG_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': OO_TPL_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
        });
        const data = await resp.json();

        if (!resp.ok || data.error) {
            throw new Error(data.error || 'Erreur lors du chargement de la configuration OnlyOffice.');
        }

        const ooBase = data.onlyofficeUrl.replace(/\/$/, '');
        const html = `<!DOCTYPE html><html><head>
            <meta charset="utf-8">
            <style>html,body,#oo-editor{margin:0;padding:0;height:100%;width:100%;overflow:hidden}</style>
            <script src="${ooBase}/web-apps/apps/api/documents/api.js"><\/script>
        </head><body>
            <div id="oo-editor"></div>
            <script>
            new DocsAPI.DocEditor("oo-editor", ${JSON.stringify(data.config)});
            <\/script>
        </body></html>`;

        const blob = new Blob([html], { type: 'text/html' });
        frame.src = URL.createObjectURL(blob);
        frame.onload = () => {
            loading.classList.add('hidden');
            frame.classList.remove('hidden');
        };
    } catch (err) {
        loading.classList.add('hidden');
        errBox.textContent = err.message;
        errBox.classList.remove('hidden');
    }
}

function closeOoTplModal() {
    const modal = document.getElementById('oo-tpl-modal');
    const frame = document.getElementById('oo-tpl-frame');
    modal.classList.add('hidden');
    frame.src = '';
}

document.getElementById('oo-tpl-modal')?.addEventListener('click', function (e) {
    if (e.target === this) closeOoTplModal();
});

    // ─── Agent IA : Analyser le modèle ──────────────────────────────────────────
    @php
        try { $tplAnalyzeUrl  = route('meetings.template.analyze',  $meeting); } catch (\Exception $e) { $tplAnalyzeUrl  = null; }
        try { $tplGenerateUrl = route('meetings.template.generate', $meeting); } catch (\Exception $e) { $tplGenerateUrl = null; }
    @endphp
    const TPL_ANALYZE_URL  = @json($tplAnalyzeUrl);
    const TPL_GENERATE_URL = @json($tplGenerateUrl);

    async function analyzeTemplate() {
        if (!TPL_ANALYZE_URL) { alert('Route analyse non disponible.'); return; }
        const btn = document.getElementById('btn-analyze-tpl');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Analyse en cours…'; }

        try {
            const resp = await fetch(TPL_ANALYZE_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': OO_TPL_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            });
            const data = await resp.json();
            if (!resp.ok || data.error) throw new Error(data.error || 'Erreur analyse.');

            // Afficher le panneau
            document.getElementById('tpl-analysis-panel')?.classList.remove('hidden');
            document.getElementById('tpl-not-analyzed')?.classList.add('hidden');
            document.getElementById('tpl-var-count').textContent = data.variables.length;

            // Générer les badges
            const tagsEl = document.getElementById('tpl-var-tags');
            tagsEl.innerHTML = data.variables.map(v =>
                `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white border border-blue-300 text-blue-700 text-xs font-mono"><i class="fas fa-at text-blue-400 text-[10px]"></i>${v}</span>`
            ).join('');

            // Générer les champs de saisie
            const inputsEl = document.getElementById('tpl-var-inputs');
            inputsEl.innerHTML = data.variables.map(v => `
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-700 font-mono">${'{{ ' + v + ' }}'}</label>
                    <input type="text" data-varname="${v}"
                           class="tpl-var-input border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:border-blue-400"
                           placeholder="${v}">
                </div>`).join('');

            // Sceau
            const badge = document.getElementById('tpl-seal-badge');
            if (badge && data.signatureZones > 0) {
                badge.classList.remove('hidden');
                badge.className = badge.className.replace('hidden', '') + ' bg-blue-100 text-blue-700';
                badge.innerHTML = `<i class="fas fa-lock mr-1"></i> ${data.signatureZones} zone(s) de signature scellée(s)`;
            }

            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check mr-1"></i> Analysé'; btn.classList.replace('bg-emerald-600','bg-gray-400'); }
        } catch (err) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-robot mr-1"></i> Analyser le modèle'; }
            alert('Erreur : ' + err.message);
        }
    }

    async function generateDocument() {
        if (!TPL_GENERATE_URL) { alert('Route génération non disponible.'); return; }

        // Collecter les valeurs des champs
        const variables = {};
        document.querySelectorAll('.tpl-var-input').forEach(input => {
            const name = input.dataset.varname;
            if (name) variables[name] = input.value;
        });

        const statusEl = document.getElementById('tpl-generate-status');
        if (statusEl) { statusEl.textContent = 'Génération en cours…'; statusEl.classList.remove('hidden'); }

        try {
            const resp = await fetch(TPL_GENERATE_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': OO_TPL_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/octet-stream, application/json' },
                body: JSON.stringify({ variables }),
            });

            if (!resp.ok) {
                const err = await resp.json().catch(() => ({ error: 'Erreur serveur.' }));
                throw new Error(err.error || 'Erreur lors de la génération.');
            }

            // Déclencher le téléchargement
            const blob = await resp.blob();
            const cd   = resp.headers.get('Content-Disposition') || '';
            const match = cd.match(/filename="?([^"]+)"?/);
            const fileName = match ? match[1] : 'compte_rendu.docx';
            const url = URL.createObjectURL(blob);
            const a   = document.createElement('a');
            a.href = url; a.download = fileName; a.click();
            URL.revokeObjectURL(url);

            if (statusEl) { statusEl.textContent = 'Document téléchargé !'; }
        } catch (err) {
            if (statusEl) { statusEl.textContent = ''; statusEl.classList.add('hidden'); }
            alert('Erreur : ' + err.message);
        }
    }

    // ─── IA : écoute et proposition de compte rendu ─────────────────────────────
    @php
        try { $aiMinutesUrl = route('meetings.ai.minutes', $meeting); } catch (\Exception $e) { $aiMinutesUrl = null; }
    @endphp
    const AI_MINUTES_URL = @json($aiMinutesUrl);

    let aiMediaRecorder = null;
    let aiAudioChunks = [];
    let aiRecordedBlob = null;
    let aiTimerInterval = null;
    let aiTimerSeconds = 0;

    async function toggleAiRecording() {
        const btn = document.getElementById('ai-record-btn');
        const label = document.getElementById('ai-record-btn-label');
        const statusEl = document.getElementById('ai-minutes-status');

        if (aiMediaRecorder && aiMediaRecorder.state === 'recording') {
            aiMediaRecorder.stop();
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Votre navigateur ne permet pas l\'accès au microphone (HTTPS requis en dehors de localhost).');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            aiAudioChunks = [];
            aiRecordedBlob = null;
            aiMediaRecorder = new MediaRecorder(stream);

            aiMediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size > 0) aiAudioChunks.push(e.data); };
            aiMediaRecorder.onstop = () => {
                aiRecordedBlob = new Blob(aiAudioChunks, { type: aiMediaRecorder.mimeType || 'audio/webm' });
                stream.getTracks().forEach(t => t.stop());
                clearInterval(aiTimerInterval);
                document.getElementById('ai-record-timer').classList.add('hidden');
                label.textContent = 'Démarrer l\'écoute';
                btn.classList.replace('bg-gray-500', 'bg-purple-600');
                const genBtn = document.getElementById('ai-generate-btn');
                genBtn.classList.remove('hidden');
                genBtn.classList.add('inline-flex');
                genBtn.disabled = false;
                if (statusEl) statusEl.textContent = 'Enregistrement prêt (' + Math.round(aiRecordedBlob.size / 1024) + ' Ko).';
            };

            aiMediaRecorder.start();
            label.textContent = 'Arrêter l\'écoute';
            btn.classList.replace('bg-purple-600', 'bg-gray-500');
            if (statusEl) statusEl.textContent = '';

            aiTimerSeconds = 0;
            const timerEl = document.getElementById('ai-record-timer');
            timerEl.classList.remove('hidden');
            timerEl.textContent = '00:00';
            aiTimerInterval = setInterval(() => {
                aiTimerSeconds++;
                const m = String(Math.floor(aiTimerSeconds / 60)).padStart(2, '0');
                const s = String(aiTimerSeconds % 60).padStart(2, '0');
                timerEl.textContent = `${m}:${s}`;
            }, 1000);
        } catch (err) {
            alert('Impossible d\'accéder au microphone : ' + err.message);
        }
    }

    async function generateAiMinutes() {
        if (!AI_MINUTES_URL) { alert('Route de génération IA non disponible.'); return; }
        if (!aiRecordedBlob) { alert('Aucun enregistrement disponible. Démarrez l\'écoute puis arrêtez-la avant de générer.'); return; }

        const genBtn = document.getElementById('ai-generate-btn');
        const statusEl = document.getElementById('ai-minutes-status');
        genBtn.disabled = true;
        if (statusEl) statusEl.textContent = 'Transcription et rédaction en cours par l\'IA…';

        const formData = new FormData();
        formData.append('audio', aiRecordedBlob, 'reunion.webm');

        try {
            const resp = await fetch(AI_MINUTES_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData,
            });
            const data = await resp.json();

            if (!resp.ok || data.error) {
                throw new Error(data.error || 'Erreur lors de la génération du compte rendu par IA.');
            }

            const textarea = document.getElementById('minutes_content');
            textarea.value = data.minutes_content;
            if (statusEl) statusEl.textContent = 'Proposition générée. Relisez et corrigez avant d\'enregistrer.';
        } catch (err) {
            if (statusEl) statusEl.textContent = '';
            alert('Erreur IA : ' + err.message);
        } finally {
            genBtn.disabled = false;
        }
    }
</script>
@endsection
