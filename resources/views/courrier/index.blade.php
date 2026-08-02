@extends('layouts.app')
@section('title', 'Gestion Courrier')
@section('page-title', 'Gestion Courrier')
@section('page-subtitle', 'Enregistrement, suivi et traitement des courriers')
@section('content')

{{-- Sub-tab bar --}}
<div class="flex flex-wrap gap-1.5 mb-6 bg-white rounded-2xl border border-gray-200 p-2 shadow-sm">
    @foreach($subtabs as $key => $meta)
    <a href="{{ route('courrier.' . $key) }}"
       title="{{ $meta['label'] }}"
       class="flex items-center gap-1.5 px-3 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition
              {{ $subtab === $key ? 'bg-[#2453d6] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' }}">
        <i class="{{ $meta['icon'] }} text-xs flex-shrink-0"></i>
        <span class="hidden sm:inline">{{ $meta['label'] }}</span>
    </a>
    @endforeach
</div>

{{-- ─────────────── ENREGISTREMENT ─────────────── --}}
@if($subtab === 'enregistrement')
@php $typeForm = request('type_courrier', 'arrive'); @endphp
@php $prochainNumero = $prochainNumero ?? (($typeForm === 'arrive' ? 'A' : 'D') . '-0001-DIR001-' . date('Y')); @endphp

<div class="max-w-4xl">
    <h2 class="text-xl font-bold text-gray-800 mb-5">Nouveau Courrier</h2>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm flex items-center gap-2">
        <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Tabs Arrivé / Départ --}}
    <div class="grid grid-cols-2 mb-6 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <a href="?type_courrier=arrive"
           class="text-center py-4 text-sm font-semibold transition border-b-2
                  {{ $typeForm === 'arrive' ? 'border-blue-600 text-blue-600 bg-blue-50/40' : 'border-transparent text-gray-400 hover:text-gray-600 hover:bg-gray-50' }}">
            Courrier Arrivé
        </a>
        <a href="?type_courrier=depart"
           class="text-center py-4 text-sm font-semibold transition border-b-2
                  {{ $typeForm === 'depart' ? 'border-blue-600 text-blue-600 bg-blue-50/40' : 'border-transparent text-gray-400 hover:text-gray-600 hover:bg-gray-50' }}">
            Courrier Départ
        </a>
    </div>

    <form method="POST" action="{{ route('courrier.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        <input type="hidden" name="type_courrier" value="{{ $typeForm }}">

        {{-- Numéro auto + Scanner --}}
        <div class="flex items-center justify-between bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-indigo-700 font-medium">
                <i class="far fa-clipboard"></i>
                Prochain numéro : <span class="font-bold">{{ $prochainNumero }}</span>
            </div>
            <button type="button" onclick="openOcrModal()"
                class="px-4 py-2 bg-violet-600 text-white text-xs font-semibold rounded-xl hover:bg-violet-700 transition flex items-center gap-1.5">
                <i class="fas fa-magic"></i> Scanner &amp; OCR
            </button>
        </div>

        {{-- Modal OCR --}}
        <div id="ocrModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-11 h-11 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-magic text-violet-600 text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Remplissage automatique par OCR</h3>
                        <p class="text-xs text-gray-500">Importez le scan du courrier — les champs seront pré-remplis</p>
                    </div>
                    <button onclick="closeOcrModal()" class="ml-auto text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                </div>

                {{-- Zone de dépôt --}}
                <div id="ocrDropZone"
                     class="border-2 border-dashed border-violet-300 rounded-xl p-8 text-center bg-violet-50 hover:bg-violet-100 transition cursor-pointer mb-4"
                     onclick="document.getElementById('ocrFileInput').click()"
                     ondragover="event.preventDefault();this.classList.add('border-violet-500')"
                     ondragleave="this.classList.remove('border-violet-500')"
                     ondrop="handleOcrDrop(event)">
                    <i class="fas fa-cloud-upload-alt text-3xl text-violet-300 mb-2 block"></i>
                    <p class="text-sm text-violet-700 font-medium">Cliquez ou glissez le fichier ici</p>
                    <p class="text-xs text-gray-400 mt-1">PDF, JPG, PNG (max 10 Mo)</p>
                    <input id="ocrFileInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="startOcr(this.files[0])">
                </div>
                <div id="ocrFileName" class="hidden mb-3 text-xs text-gray-600 flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                    <i class="fas fa-file text-gray-400"></i><span id="ocrFileNameText"></span>
                </div>

                {{-- Statut --}}
                <div id="ocrStatus" class="hidden mb-3 flex items-center gap-2 text-sm text-violet-700 bg-violet-50 border border-violet-200 rounded-xl px-4 py-3">
                    <i class="fas fa-spinner fa-spin"></i> <span id="ocrStatusText">Analyse en cours…</span>
                </div>
                <div id="ocrError" class="hidden mb-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-4 py-3"></div>
                <div id="ocrSuccess" class="hidden mb-3 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> <span>Champs pré-remplis avec succès. Vérifiez et corrigez si nécessaire.</span>
                </div>

                <div class="flex gap-3 justify-end">
                    <button onclick="closeOcrModal()" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                        Fermer
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Priorité</label>
                    <select name="urgence" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50">
                        <option value="normale">Normale</option>
                        <option value="urgent">Urgent</option>
                        <option value="tres_urgent">Très urgent</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Date d'émission <span class="text-red-500">*</span></label>
                    <input type="date" name="date_emission" required value="{{ date('Y-m-d') }}"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Objet <span class="text-red-500">*</span></label>
                <input type="text" name="objet" required placeholder=""
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ $typeForm === 'arrive' ? 'Expéditeur' : 'Destinataire' }} <span class="text-red-500">*</span>
                    </label>
                    <textarea name="{{ $typeForm === 'arrive' ? 'expediteur' : 'destinataire' }}" rows="3" required
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50 resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Numéro d'émission</label>
                    <input type="text" name="numero_emission" placeholder=""
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50">
                </div>
            </div>

            @if($typeForm === 'depart')
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Administration destinataire <span class="text-red-500">*</span></label>
                <select name="destinataire_administration_id" required
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50">
                    <option value="">Sélectionner une administration destinataire</option>
                    @foreach(($recipientAdministrations ?? collect()) as $recipientAdmin)
                    <option value="{{ $recipientAdmin->id }}">
                        {{ $recipientAdmin->name }}{{ !empty($recipientAdmin->code) ? ' (' . $recipientAdmin->code . ')' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Fichiers joints</label>
                <div class="border-2 border-dashed border-gray-200 rounded-xl px-4 py-6 text-center bg-gray-50 hover:bg-gray-100 transition cursor-pointer"
                     onclick="document.getElementById('input-pj').click()">
                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-300 mb-1 block"></i>
                    <p class="text-xs text-gray-400">Cliquez pour ajouter des fichiers ou glissez-les ici</p>
                    <p class="text-xs text-gray-300 mt-0.5">PDF, Word, Images (max 10 Mo)</p>
                    <input id="input-pj" type="file" name="pieces_jointes[]" multiple accept=".pdf,.doc,.docx,.jpg,.png" class="hidden"
                           onchange="updateFileList(this)">
                </div>
                <div id="file-list" class="mt-2 space-y-1"></div>
            </div>

            @if($typeForm === 'depart'){{-- champs direction/mode supprimés --}}@endif

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    {{ $typeForm === 'depart' ? "Accusé de réception" : 'Observations' }}
                </label>
                @if($typeForm === 'depart')
                <div class="border-2 border-dashed border-gray-200 rounded-xl px-4 py-5 text-center bg-gray-50 hover:bg-gray-100 transition cursor-pointer"
                     onclick="document.getElementById('input-accuse').click()">
                    <i class="fas fa-file-signature text-2xl text-gray-300 mb-1 block"></i>
                    <p class="text-xs text-gray-400">Cliquez pour joindre l'accusé de réception</p>
                    <p class="text-xs text-gray-300 mt-0.5">PDF, Image (max 10 Mo)</p>
                    <input id="input-accuse" type="file" name="accuse_reception" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                           onchange="updateAccuseLabel(this)">
                </div>
                <p id="accuse-label" class="mt-1.5 text-xs text-gray-500 hidden"></p>
                @else
                <textarea name="observations" rows="3" placeholder=""
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50 resize-none"></textarea>
                @endif
            </div>
        </div>

        <div class="flex gap-3 justify-end">
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-save"></i> Enregistrer
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// ── OCR Scanner ──────────────────────────────────────────────────────────────
function openOcrModal() {
    document.getElementById('ocrModal').classList.remove('hidden');
    document.getElementById('ocrStatus').classList.add('hidden');
    document.getElementById('ocrError').classList.add('hidden');
    document.getElementById('ocrSuccess').classList.add('hidden');
    document.getElementById('ocrFileName').classList.add('hidden');
}
function closeOcrModal() {
    document.getElementById('ocrModal').classList.add('hidden');
}
function handleOcrDrop(e) {
    e.preventDefault();
    document.getElementById('ocrDropZone').classList.remove('border-violet-500');
    const file = e.dataTransfer.files[0];
    if (file) startOcr(file);
}
function startOcr(file) {
    if (!file) return;

    const allowed = ['application/pdf','image/jpeg','image/jpg','image/png'];
    if (!allowed.includes(file.type) && !file.name.match(/\.(pdf|jpe?g|png)$/i)) {
        showOcrError('Format non accepté. Utilisez un PDF ou une image JPG/PNG.');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        showOcrError('Le fichier dépasse 10 Mo.');
        return;
    }

    document.getElementById('ocrFileNameText').textContent = file.name + ' (' + (file.size/1024).toFixed(0) + ' Ko)';
    document.getElementById('ocrFileName').classList.remove('hidden');
    document.getElementById('ocrError').classList.add('hidden');
    document.getElementById('ocrSuccess').classList.add('hidden');
    document.getElementById('ocrStatus').classList.remove('hidden');
    document.getElementById('ocrStatusText').textContent = 'Extraction du texte…';

    const fd = new FormData();
    fd.append('file', file);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

    fetch('{{ route("courrier.scan-ocr") }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': fd.get('_token') },
            body: fd
        })
        .then(async r => {
            document.getElementById('ocrStatus').classList.add('hidden');
            let data;
            try {
                data = await r.json();
            } catch (e) {
                showOcrError('Erreur serveur (HTTP ' + r.status + '). Consultez les logs Laravel.');
                return;
            }
            if (!r.ok || !data.ok) {
                let errMsg = '';
                if (data.errors) {
                    errMsg = Object.values(data.errors).flat().join(' ');
                } else {
                    errMsg = data.message ?? 'Erreur lors de l\'analyse (HTTP ' + r.status + ').';
                }
                if (errMsg.includes('failed to upload') || errMsg.includes('upload')) {
                    errMsg = 'Le fichier est trop volumineux pour le serveur (limite PHP dépassée). Utilisez un fichier de moins de 10 Mo.';
                }
                showOcrError(errMsg);
                return;
            }
            const filled = fillFormFields(data.fields);
            if (filled === 0) {
                showOcrError('Aucun champ reconnu. Le texte du document n\'a pas pu être lu (PDF scanné non lisible ou format non standard). Remplissez les champs manuellement.');
            } else {
                document.getElementById('ocrSuccess').querySelector('span').textContent =
                    filled + ' champ' + (filled > 1 ? 's' : '') + ' pré-rempli' + (filled > 1 ? 's' : '') + ' avec succès. Vérifiez et corrigez si nécessaire.';
                document.getElementById('ocrSuccess').classList.remove('hidden');
            }
        })
        .catch(err => {
            document.getElementById('ocrStatus').classList.add('hidden');
            showOcrError('Erreur réseau : ' + (err.message ?? 'connexion impossible.'));
        });
}
function showOcrError(msg) {
    const el = document.getElementById('ocrError');
    el.textContent = msg;
    el.classList.remove('hidden');
}
function fillFormFields(fields) {
    if (!fields) return 0;
    const typeForm = document.querySelector('input[name="type_courrier"]')?.value;
    let count = 0;

    if (fields.objet)           count += setField('input[name="objet"]', fields.objet);
    if (fields.date_emission)   count += setField('input[name="date_emission"]', fields.date_emission);
    if (fields.numero_emission) count += setField('input[name="numero_emission"]', fields.numero_emission);
    if (fields.urgence && fields.urgence !== 'normale') count += setSelect('select[name="urgence"]', fields.urgence);

    if (typeForm === 'arrive' && fields.expediteur)
        count += setField('textarea[name="expediteur"]', fields.expediteur);
    if (typeForm === 'depart' && fields.destinataire)
        count += setField('textarea[name="destinataire"]', fields.destinataire);

    return count;
}
function setField(selector, value) {
    const el = document.querySelector(selector);
    if (el && value) { el.value = value; el.dispatchEvent(new Event('input')); return 1; }
    return 0;
}
function setSelect(selector, value) {
    const el = document.querySelector(selector);
    if (el && value) { el.value = value; el.dispatchEvent(new Event('change')); return 1; }
    return 0;
}
document.getElementById('ocrModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeOcrModal();
});
// ─────────────────────────────────────────────────────────────────────────────

function updateFileList(input) {
    const list = document.getElementById('file-list');
    list.innerHTML = '';
    Array.from(input.files).forEach(f => {
        const div = document.createElement('div');
        div.className = 'flex items-center gap-2 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5';
        div.innerHTML = '<i class="fas fa-file text-gray-400"></i><span class="truncate">' + f.name + '</span><span class="ml-auto text-gray-400">' + (f.size/1024).toFixed(0) + ' Ko</span>';
        list.appendChild(div);
    });
}
function updateAccuseLabel(input) {
    const lbl = document.getElementById('accuse-label');
    if (input.files.length > 0) {
        lbl.textContent = '\u2714 ' + input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(0) + ' Ko)';
        lbl.classList.remove('hidden');
    } else {
        lbl.classList.add('hidden');
    }
}
</script>
@endpush

{{-- ─────────────── LISTE DES COURRIERS ─────────────── --}}
@elseif($subtab === 'envoi')
@php $rows = $courriersEnvoi ?? []; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-2xl font-bold text-gray-900">Envoi des Courriers Départ</h2>
    <span class="text-xs bg-blue-50 text-blue-700 border border-blue-100 px-3 py-1.5 rounded-full font-semibold">{{ count($rows) }} courrier(s)</span>
</div>

<form method="GET" action="{{ route('courrier.envoi') }}" class="mb-5">
    <div class="flex flex-wrap gap-3 items-center">
        <div class="flex-1 min-w-[220px] relative">
            <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Rechercher (numéro, objet, destinataire)..."
                class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 placeholder-gray-400">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">Filtrer</button>
    </div>
</form>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Numéro</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Objet</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Admin destinataire</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Agent</th>
                <th class="text-right px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($rows as $c)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-bold text-gray-900">{{ $c['num'] }}</td>
                <td class="px-6 py-4 text-gray-700">{{ $c['objet'] }}</td>
                <td class="px-6 py-4 text-gray-700">{{ $c['destinataire_admin'] ?? '—' }}</td>
                <td class="px-6 py-4"><span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">{{ $c['statut'] }}</span></td>
                <td class="px-6 py-4 text-gray-700">{{ $c['agent'] }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick="openPdfModal('{{ $c['fichier'] ?? '' }}', '{{ addslashes($c['num']) }}')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 transition" title="Voir" style="color:#3b82f6;">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                        <button onclick="openEditModal('{{ $c['id'] }}','{{ $c['num'] }}','{{ addslashes($c['objet']) }}','Départ','{{ $c['priorite'] }}','{{ $c['date_emission'] }}','{{ addslashes($c['numero_emission']) }}','{{ addslashes($c['destinataire'] ?? '') }}','{{ $c['destinataire_administration_id'] ?? '' }}')"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 transition" title="Modifier" style="color:#d97706;">
                            <i class="fa-solid fa-pen-to-square text-sm"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-16 text-center text-gray-400">Aucun courrier départ.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@elseif($subtab === 'reception')
@php $rows = $courriersReception ?? []; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-2xl font-bold text-gray-900">Réception des Courriers Départ</h2>
    <span class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-100 px-3 py-1.5 rounded-full font-semibold">{{ count($rows) }} courrier(s)</span>
</div>

<form method="GET" action="{{ route('courrier.reception') }}" class="mb-5">
    <div class="flex flex-wrap gap-3 items-center">
        <div class="flex-1 min-w-[220px] relative">
            <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Rechercher (numéro, objet, expéditeur)..."
                class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 placeholder-gray-400">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">Filtrer</button>
    </div>
</form>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Numéro</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Objet</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Admin émettrice</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                <th class="text-right px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($rows as $c)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-bold text-gray-900">{{ $c['num'] }}</td>
                <td class="px-6 py-4 text-gray-700">{{ $c['objet'] }}</td>
                <td class="px-6 py-4 text-gray-700">{{ $c['emetteur_admin'] ?? '—' }}</td>
                <td class="px-6 py-4"><span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">{{ $c['statut'] }}</span></td>
                <td class="px-6 py-4 text-right">
                    <button onclick="openPdfModal('{{ $c['fichier'] ?? '' }}', '{{ addslashes($c['num']) }}')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 transition" title="Voir" style="color:#3b82f6;">
                        <i class="fa-solid fa-eye text-sm"></i>
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-6 py-16 text-center text-gray-400">Aucun courrier reçu.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ─────────────── LISTE DES COURRIERS ─────────────── --}}
@elseif($subtab === 'liste')

{{-- Header --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-2xl font-bold text-gray-900">Liste des Courriers</h2>
    @php
        $courrierPermSvc = app(\App\Services\UserPermissionsService::class);
        $canCreateCourrier = auth()->user() ? $courrierPermSvc->can(auth()->user(), 'courrier.enregistrement') : false;
    @endphp
    @if($canCreateCourrier)
    <a href="{{ route('courrier.enregistrement') }}"
       class="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition flex items-center gap-2">
        <i class="fas fa-plus"></i> Nouveau
    </a>
    @endif
</div>

{{-- Barre filtres --}}
<form method="GET" action="{{ route('courrier.liste') }}" class="mb-5">
    <div class="flex flex-wrap gap-3 items-center">
        {{-- Boutons type --}}
        <div class="flex gap-2">
            @foreach(['tous'=>'Tous','arrive'=>'Arrivés','depart'=>'Départs'] as $val=>$label)
            <button type="submit" name="filtre" value="{{ $val }}"
                class="{{ ($filtre??'tous')===$val ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }} px-5 py-2 rounded-xl text-sm font-semibold transition">
                {{ $label }}
                @if($val !== 'tous' && isset($statut)) <input type="hidden" name="statut" value="{{ $statut }}">@endif
            </button>
            @endforeach
        </div>

        {{-- Menu statut --}}
        <select name="statut" onchange="this.form.submit()"
            class="border border-gray-200 rounded-xl px-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 text-gray-600">
            <option value="" {{ ($statut??'')=='' ? 'selected' : '' }}>Tous les statuts</option>
            <option value="En attente"    {{ ($statut??'')==='En attente'    ? 'selected' : '' }}>En attente</option>
            <option value="En traitement" {{ ($statut??'')==='En traitement' ? 'selected' : '' }}>En traitement</option>
            <option value="Traité"        {{ ($statut??'')==='Traité'        ? 'selected' : '' }}>Traité</option>
        </select>
        <input type="hidden" name="filtre" value="{{ $filtre ?? 'tous' }}">

        {{-- Recherche --}}
        <div class="flex-1 min-w-[200px] relative">
            <input type="text" name="q" value="{{ $search ?? '' }}"
                placeholder="Rechercher (objet, numéro, expéditeur)…"
                class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 placeholder-gray-400">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
        </div>
        <button type="submit"
            class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
            Filtrer
        </button>
        @if(($filtre??'tous') !== 'tous' || ($statut??'') !== '' || ($search??'') !== '')
        <a href="{{ route('courrier.liste') }}"
            class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-1.5">
            <i class="fas fa-times text-xs"></i> Réinitialiser
        </a>
        @endif
    </div>
</form>

{{-- Compteur --}}
<p class="text-xs text-gray-400 mb-3">{{ count($courriers) }} courrier(s) trouvé(s)</p>

{{-- Table --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Numéro</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Objet</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Type</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Priorité</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Agent</th>
                <th class="text-right px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($courriers as $c)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-bold text-gray-900 whitespace-nowrap">{{ $c['num'] }}</td>
                <td class="px-6 py-4 text-gray-700">{{ $c['objet'] }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full {{ $c['type']==='Arrivé' ? 'bg-indigo-50 text-indigo-700' : 'bg-orange-50 text-orange-700' }}">
                        <i class="fas {{ $c['type']==='Arrivé' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-[10px]"></i>
                        {{ $c['type'] }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    @php $priClass = match($c['priorite']) { 'Urgent'=>'text-orange-600 font-bold', 'Très urgent'=>'text-red-600 font-bold', default=>'text-blue-600 font-semibold' }; @endphp
                    <span class="{{ $priClass }} text-sm">{{ $c['priorite'] }}</span>
                </td>
                <td class="px-6 py-4">
                    @php $badgeClass = match($c['statut']) {
                        'En attente'    => 'bg-yellow-100 text-yellow-700',
                        'En traitement' => 'bg-blue-100 text-blue-700',
                        'Traité'        => 'bg-green-100 text-green-700',
                        default         => 'bg-gray-100 text-gray-600',
                    }; @endphp
                    <span class="{{ $badgeClass }} px-3 py-1 rounded-full text-xs font-semibold">{{ $c['statut'] }}</span>
                </td>
                <td class="px-6 py-4 text-gray-700">{{ $c['agent'] }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick="openPdfModal('{{ $c['fichier'] ?? '' }}', '{{ addslashes($c['num']) }}')"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 transition" title="Voir le courrier" style="color:#3b82f6;">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                        @if(($c['statut'] ?? '') !== 'Traité')
                        <button onclick="openEditModal('{{ $c['id'] ?? '' }}','{{ $c['num'] }}','{{ addslashes($c['objet']) }}','{{ $c['type'] }}','{{ $c['priorite'] }}','{{ $c['date_emission'] ?? '' }}','{{ addslashes($c['numero_emission'] ?? '') }}','{{ addslashes(($c['type'] ?? '') === 'Départ' ? ($c['destinataire'] ?? '') : ($c['expediteur'] ?? '')) }}','{{ $c['destinataire_administration_id'] ?? '' }}')"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 transition" title="Modifier" style="color:#d97706;">
                            <i class="fa-solid fa-pen-to-square text-sm"></i>
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                    <i class="fas fa-inbox text-4xl text-gray-200 mb-3 block"></i>
                    Aucun courrier trouvé pour ces critères.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>



{{-- ─────────────── IMPUTATION ─────────────── --}}
@elseif($subtab === 'imputation')

{{-- Accès refusé : rôle insuffisant --}}
@if(!empty($accesDenied))
<div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center mb-5">
        <i class="fas fa-lock text-3xl text-red-400"></i>
    </div>
    <h2 class="text-xl font-bold text-gray-800 mb-2">Accès restreint</h2>
    <p class="text-sm text-gray-500 max-w-sm">
        L'imputation des courriers est réservée aux profils
        <strong>Directeur</strong>, <strong>Directeur de Cabinet</strong>
        et <strong>Directeur Général</strong>.
    </p>
</div>
@else

{{-- Header --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-2xl font-bold text-gray-900">Imputation des Courriers</h2>
    <span class="text-xs bg-blue-50 text-blue-700 border border-blue-100 px-3 py-1.5 rounded-full font-semibold">
        {{ $courriers->count() }} courrier(s) à imputer
    </span>
</div>

{{-- Barre filtres --}}
<form method="GET" action="{{ route('courrier.imputation') }}" class="flex flex-wrap gap-3 items-center mb-5">
    <div class="flex gap-2">
        @foreach(['tous'=>'Tous','urgent'=>'Urgents'] as $val=>$label)
        <button type="submit" name="imp_filtre" value="{{ $val }}"
            class="{{ ($imputFiltre??'tous')===$val ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }} px-5 py-2 rounded-xl text-sm font-semibold transition">
            {{ $label }}
        </button>
        @endforeach
    </div>
    <div class="flex-1 min-w-[200px] relative">
        <input type="text" name="imp_q" value="{{ $imputSearch ?? '' }}"
            placeholder="Rechercher (numéro, objet, expéditeur)…"
            class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 placeholder-gray-400">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
    </div>
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
        Filtrer
    </button>
    @if(($imputFiltre??'tous')!=='tous' || ($imputSearch??'')!=='')
    <a href="{{ route('courrier.imputation') }}" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-1.5">
        <i class="fas fa-times text-xs"></i> Réinitialiser
    </a>
    @endif
</form>

{{-- Table --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Numéro</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Objet</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Expéditeur</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Priorité</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Enregistré par</th>
                <th class="text-right px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($courriers as $c)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-5 font-bold text-gray-900 whitespace-nowrap">{{ $c->numero }}</td>
                <td class="px-6 py-5 text-gray-700">{{ $c->objet }}</td>
                <td class="px-6 py-5 text-gray-600">{{ $c->expediteur ?? '—' }}</td>
                <td class="px-6 py-5">
                    @php
                        $priColor = match($c->urgence) {
                            'urgent','tres_urgent' => 'text-red-600 font-bold',
                            default => 'text-blue-600 font-semibold'
                        };
                    @endphp
                    <span class="{{ $priColor }}">{{ $c->priorite_libelle }}</span>
                </td>
                <td class="px-6 py-5 font-semibold text-gray-700 uppercase">
                    {{ $c->enregistrePar?->full_name ?? $c->enregistrePar?->name ?? '—' }}
                </td>
                <td class="px-6 py-5 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick="openPdfModal(
                                '{{ $c->pieces_jointes && count($c->pieces_jointes) ? asset('storage/'.$c->pieces_jointes[0]) : ($c->accuse_reception ? asset('storage/'.$c->accuse_reception) : '') }}',
                                '{{ addslashes($c->numero) }}')"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-600 hover:bg-blue-700 text-white transition shadow-sm" title="Voir le courrier">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                        <button onclick="openImputerModal('{{ $c->id }}','{{ $c->numero }}','{{ addslashes($c->objet) }}','{{ addslashes($c->expediteur ?? '') }}')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold transition shadow-sm">
                            Imputer
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-16 text-center text-gray-400">
                    <i class="fas fa-inbox text-4xl text-gray-200 mb-3 block"></i>
                    Aucun courrier arrivé en attente d'imputation.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- ─────────────── EN TRAITEMENT ─────────────── --}}
@elseif($subtab === 'en-traitement')
@php
    $rows = $enTraitementRows ?? collect();
@endphp

{{-- Header --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-2xl font-bold text-gray-900">Courriers En Traitement</h2>
    <span class="text-xs bg-orange-50 text-orange-700 border border-orange-100 px-3 py-1.5 rounded-full font-semibold">
        {{ $rows->count() }} courrier(s) en traitement
    </span>
</div>

{{-- Barre filtres --}}
<form method="GET" action="{{ route('courrier.en-traitement') }}" class="flex flex-wrap gap-3 items-center mb-5">
    <div class="flex-1 min-w-[200px] relative">
        <input type="text" name="et_q" value="{{ $etSearch ?? '' }}"
            placeholder="Rechercher (numéro, objet)…"
            class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 placeholder-gray-400">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
    </div>
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
        Filtrer
    </button>
    @if(($etSearch ?? '') !== '')
    <a href="{{ route('courrier.en-traitement') }}" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-1.5">
        <i class="fas fa-times text-xs"></i> Réinitialiser
    </a>
    @endif
</form>

{{-- Table --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Numéro</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Objet</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Expéditeur</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Imputé par</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Délai</th>
                <th class="text-right px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($rows as $c)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-5 font-bold text-gray-900 whitespace-nowrap">{{ $c['num'] }}</td>
                <td class="px-6 py-5 text-gray-700">{{ $c['objet'] }}</td>
                <td class="px-6 py-5 text-gray-600">{{ $c['expediteur'] ?: '—' }}</td>
                <td class="px-6 py-5 font-semibold text-gray-700 uppercase">{{ $c['imputer_par_code'] }}</td>
                <td class="px-6 py-5 text-gray-600">{{ $c['delai'] }}</td>
                <td class="px-6 py-5 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick="openPdfModal('{{ $c['fichier'] ?? '' }}', '{{ addslashes($c['num']) }}')"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-600 hover:bg-blue-700 text-white transition shadow-sm" title="Voir le courrier">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                        <button onclick="openTraiterModal('{{ $c['id'] }}','{{ $c['num'] }}','{{ addslashes($c['objet']) }}','{{ addslashes($c['expediteur'] ?? '') }}','{{ $c['delai'] }}')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl text-sm font-semibold transition shadow-sm">
                            Traiter
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-16 text-center text-gray-400">
                    <i class="fas fa-spinner text-4xl text-gray-200 mb-3 block"></i>
                    Aucun courrier en cours de traitement.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal Traiter --}}
<div id="modal-traiter" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTraiterModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i>
                Traiter le courrier — <span id="traiter-num" class="text-blue-600 text-sm ml-1"></span>
            </h3>
            <button onclick="closeTraiterModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('courrier.traiter') }}" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="courrier_id" id="traiter-id-hidden">
            <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-3 text-sm text-green-800 grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div><span class="font-semibold">Numéro :</span> <span id="traiter-num-value"></span></div>
                <div><span class="font-semibold">Délai :</span> <span id="traiter-delai"></span></div>
                <div><span class="font-semibold">Objet :</span> <span id="traiter-objet"></span></div>
                <div><span class="font-semibold">Expéditeur :</span> <span id="traiter-expediteur"></span></div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Nom de la réponse <span class="text-red-500">*</span></label>
                <input type="text" name="reponse_nom" required
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-400 bg-gray-50"
                    placeholder="Ex: Réponse invitation MASA">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Fichier réponse <span class="text-red-500">*</span></label>
                <input type="file" name="fichier_reponse" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpeg,.jpg,.png"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-400">
                <p class="mt-1 text-[11px] text-gray-400">Formats autorisés : PDF, Word, Excel, PowerPoint, JPEG, PNG.</p>
            </div>
            <div class="flex gap-3 justify-end pt-2 border-t border-gray-100">
                <button type="button" onclick="closeTraiterModal()"
                    class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">
                    Annuler
                </button>
                @if(!empty($canReimputer))
                <button type="button" onclick="reimputerDepuisTraitement()"
                    class="px-5 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition">
                    Réimputer
                </button>
                @endif
                <button type="submit"
                    class="px-6 py-2.5 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 transition flex items-center gap-2">
                    <i class="fas fa-check"></i> Traiter
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
var _traiterCourrier = null;

function openTraiterModal(id, num, objet, expediteur, delai) {
    _traiterCourrier = { id: id, num: num, objet: objet, expediteur: expediteur, delai: delai };
    document.getElementById('traiter-num').textContent = num;
    document.getElementById('traiter-num-value').textContent = num;
    document.getElementById('traiter-id-hidden').value = id;
    document.getElementById('traiter-objet').textContent = objet;
    document.getElementById('traiter-expediteur').textContent = expediteur || '—';
    document.getElementById('traiter-delai').textContent = delai || '—';
    document.getElementById('modal-traiter').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeTraiterModal() {
    document.getElementById('modal-traiter').classList.add('hidden');
    document.body.style.overflow = '';
}
function reimputerDepuisTraitement() {
    if (!_traiterCourrier) return;
    closeTraiterModal();
    openImputerModal(_traiterCourrier.id, _traiterCourrier.num, _traiterCourrier.objet, _traiterCourrier.expediteur);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeTraiterModal(); }
});
</script>
@endpush

{{-- ─────────────── SUIVI IMPUTATION ─────────────── --}}
@elseif($subtab === 'suivi-imputation')
@php
    $suiviImputations = $suiviRows ?? collect();
    $suiviSearch = $suiviSearch ?? '';
    $suiviSort = $suiviSort ?? 'recent';
@endphp
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    {{-- Titre --}}
    <div class="px-6 py-5 border-b border-gray-100 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Suivi des Imputations</h2>
            <p class="text-xs text-gray-400 mt-1">{{ $suiviImputations->count() }} courrier(s) trouvé(s)</p>
        </div>
        <form method="GET" action="{{ route('courrier.suivi-imputation') }}" class="flex flex-col gap-2 md:flex-row md:items-center">
            <select name="tri_date" onchange="this.form.submit()"
                class="border border-gray-200 rounded-xl px-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 text-gray-600">
                <option value="recent" {{ $suiviSort === 'recent' ? 'selected' : '' }}>Date récente</option>
                <option value="ancien" {{ $suiviSort === 'ancien' ? 'selected' : '' }}>Date ancienne</option>
            </select>
            <div class="relative min-w-[260px]">
                <input type="text" name="q" value="{{ $suiviSearch }}"
                    placeholder="Rechercher par numéro ou objet…"
                    class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 placeholder-gray-400">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            </div>
            <button type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
                Rechercher
            </button>
            @if($suiviSearch !== '')
            <a href="{{ route('courrier.suivi-imputation') }}"
                class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-1.5">
                <i class="fas fa-times text-xs"></i> Réinitialiser
            </a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
    <table class="w-full min-w-[1100px] text-sm">
        <thead>
            <tr class="border-b border-gray-100">
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Numéro</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Objet</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Statut</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Instruction</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Délai</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Date concernée</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Imputé à</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Fichier réponse</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($suiviImputations as $si)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-bold text-gray-800">{{ $si['num'] }}</td>
                <td class="px-6 py-4 text-gray-700 max-w-xs">{{ $si['objet'] }}</td>
                <td class="px-6 py-4 text-gray-700">
                    @php
                        $suiviBadge = match($si['suivi_statut'] ?? '') {
                            'Validé' => 'bg-green-100 text-green-700',
                            'Rejeté' => 'bg-red-100 text-red-700',
                            'En attente de validation' => 'bg-amber-100 text-amber-700',
                            default => 'bg-blue-100 text-blue-700',
                        };
                    @endphp
                    <span class="inline-flex items-center mt-1 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $suiviBadge }}">
                        {{ $si['suivi_statut'] ?? 'En cours de traitement' }}
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-700">{{ $si['instruction'] ?? '—' }}</td>
                <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <span>{{ $si['delai_traitement'] ?? '—' }}</span>
                        @if($si['delai_alert_one_day'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[11px] font-semibold">
                                Alerte J-1
                            </span>
                        @endif
                    </div>
                </td>
                <td class="px-6 py-4 text-gray-600 whitespace-nowrap">{{ $si['date_concernee'] }}</td>
                <td class="px-6 py-4 text-gray-600">{{ $si['impute_a'] }}</td>
                <td class="px-6 py-4 text-gray-400 text-xs">
                    @if($si['fichier_reponse'])
                        <a href="{{ $si['fichier_reponse'] }}" target="_blank"
                           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 transition font-semibold">
                            <i class="fas fa-download text-xs"></i> Télécharger
                        </a>
                    @else
                        <span class="text-gray-400">Aucun fichier</span>
                    @endif
                </td>
                <td class="px-6 py-4">
                    @if($si['can_validate'] ?? false)
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick="openDecisionModal('ok', '{{ route('courrier.ok-traitement', $si['id']) }}', '{{ addslashes($si['num']) }}', '{{ addslashes($si['objet']) }}')"
                            class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700">
                            <i class="fas fa-check mr-1"></i> OK
                        </button>
                        <button
                            type="button"
                            onclick="openDecisionModal('valider', '{{ route('courrier.valider-traitement', $si['id']) }}', '{{ addslashes($si['num']) }}', '{{ addslashes($si['objet']) }}')"
                            class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">
                            <i class="fas fa-check-double mr-1"></i> Valider
                        </button>
                        <button
                            type="button"
                            onclick="openDecisionModal('rejeter', '{{ route('courrier.rejeter-traitement', $si['id']) }}', '{{ addslashes($si['num']) }}', '{{ addslashes($si['objet']) }}')"
                            class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700">
                            <i class="fas fa-times mr-1"></i> Rejeter
                        </button>
                    </div>
                    @else
                        <span class="text-xs text-gray-400">En attente de retour</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="px-6 py-16 text-center text-gray-400">
                    <i class="fas fa-binoculars text-4xl text-gray-200 mb-3 block"></i>
                    Aucune imputation à suivre.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- ─────────────── COURRIER TRAITÉ ─────────────── --}}
@elseif($subtab === 'traite')
@php
    $courrierTraites = $traiteRows ?? collect();
    $traiteStatusFilter = $traiteStatusFilter ?? '';
    $traiteSearch = $traiteSearch ?? '';
    $traiteSort = $traiteSort ?? 'recent';
@endphp
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    {{-- Titre --}}
    <div class="px-6 py-5 border-b border-gray-100 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Courriers Traités</h2>
            <p class="text-xs text-gray-400 mt-1">{{ $courrierTraites->count() }} courrier(s) trouvé(s)</p>
        </div>
        <form method="GET" action="{{ route('courrier.traite') }}" class="flex flex-col gap-2 md:flex-row md:items-center">
            <select name="tri_date" onchange="this.form.submit()"
                class="border border-gray-200 rounded-xl px-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 text-gray-600">
                <option value="recent" {{ $traiteSort === 'recent' ? 'selected' : '' }}>Date récente</option>
                <option value="ancien" {{ $traiteSort === 'ancien' ? 'selected' : '' }}>Date ancienne</option>
            </select>
            <select name="statut_filtre" onchange="this.form.submit()"
                class="border border-gray-200 rounded-xl px-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 text-gray-600">
                <option value="" {{ $traiteStatusFilter === '' ? 'selected' : '' }}>Tous les statuts</option>
                <option value="En attente validation" {{ $traiteStatusFilter === 'En attente validation' ? 'selected' : '' }}>En attente validation</option>
                <option value="Validé" {{ $traiteStatusFilter === 'Validé' ? 'selected' : '' }}>Validé</option>
                <option value="Rejeté" {{ $traiteStatusFilter === 'Rejeté' ? 'selected' : '' }}>Rejeté</option>
            </select>
            <div class="relative min-w-[260px]">
                <input type="text" name="q" value="{{ $traiteSearch }}"
                    placeholder="Rechercher par numéro ou objet…"
                    class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 placeholder-gray-400">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            </div>
            <button type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
                Rechercher
            </button>
            @if($traiteStatusFilter !== '' || $traiteSearch !== '')
            <a href="{{ route('courrier.traite') }}"
                class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-1.5">
                <i class="fas fa-times text-xs"></i> Réinitialiser
            </a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
    <table class="w-full min-w-[1100px] text-sm">
        <thead>
            <tr class="border-b border-gray-100">
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Numéro</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Objet</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Traitement</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Date concernée</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Statut</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Fichier réponse</th>
                <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 tracking-wide uppercase">Fichier courrier</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($courrierTraites as $ct)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-5 font-bold text-gray-800 whitespace-nowrap">{{ $ct['num'] }}</td>
                <td class="px-6 py-5 text-gray-700 max-w-xs">{{ $ct['objet'] }}</td>
                <td class="px-6 py-5 text-gray-600">{{ $ct['traitement'] }}</td>
                <td class="px-6 py-5 text-gray-600 whitespace-nowrap">{{ $ct['date_concernee'] }}</td>
                <td class="px-6 py-5">
                    @php
                        $ctClass = match($ct['statut']) {
                            'Validé' => 'bg-green-100 text-green-700',
                            'Rejeté' => 'bg-red-100 text-red-700',
                            default => 'bg-orange-100 text-orange-700',
                        };
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $ctClass }}">
                        {{ $ct['statut'] }}
                    </span>
                </td>
                <td class="px-6 py-5">
                    @if($ct['fichier_reponse'])
                    <a href="{{ $ct['fichier_reponse'] }}" target="_blank"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 transition font-semibold text-xs">
                        <i class="fas fa-download text-xs"></i> Télécharger
                    </a>
                    @else
                    <span class="text-xs text-gray-400">Aucun fichier</span>
                    @endif
                </td>
                <td class="px-6 py-5">
                    <button onclick="openPdfModal('{{ $ct['fichier_courrier'] ?? '' }}', '{{ $ct['num'] }}')"
                        class="w-8 h-8 flex items-center justify-center rounded-full text-blue-600 hover:bg-blue-50 transition">
                        <i class="fas fa-eye text-base"></i>
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                    <i class="fas fa-check-circle text-4xl text-gray-200 mb-3 block"></i>
                    Aucun courrier traité pour le moment.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- ─────────────── ARCHIVES ─────────────── --}}
@elseif($subtab === 'archives')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Courriers Archivés</h2>
        @if(isset($archivalDays) && $archivalDays > 0)
        <p class="text-xs text-gray-400 mt-0.5">
            <i class="fas fa-clock mr-1"></i>
            Courriers enregistrés il y a plus de <strong>{{ $archivalDays }} jours</strong>
        </p>
        @endif
    </div>
</div>

@if(!isset($archivalDays) || $archivalDays <= 0)
<div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="w-20 h-20 rounded-full bg-stone-50 flex items-center justify-center mb-5">
        <i class="fas fa-archive text-3xl text-stone-300"></i>
    </div>
    <h3 class="text-lg font-bold text-gray-700 mb-2">Archivage non configuré</h3>
    <p class="text-sm text-gray-400 max-w-sm">
        L'archivage automatique n'est pas encore activé. Contactez un administrateur pour configurer le délai d'archivage.
    </p>
</div>
@else

{{-- Filtres --}}
<form method="GET" action="{{ route('courrier.archives') }}" class="mb-5">
    <div class="flex flex-wrap gap-3 items-center">
        <div class="flex gap-2">
            @foreach(['tous' => 'Tous', 'arrive' => 'Arrivés', 'depart' => 'Départs'] as $val => $lbl)
            <button type="submit" name="filtre" value="{{ $val }}"
                class="{{ ($filtre ?? 'tous') === $val ? 'bg-stone-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }} px-5 py-2 rounded-xl text-sm font-semibold transition">
                {{ $lbl }}
            </button>
            @endforeach
        </div>

        <select name="tri" onchange="this.form.submit()"
            class="border border-gray-200 rounded-xl px-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stone-300 text-gray-600">
            <option value="recent" {{ ($sort ?? 'recent') === 'recent' ? 'selected' : '' }}>Plus récent d'abord</option>
            <option value="ancien" {{ ($sort ?? 'recent') === 'ancien' ? 'selected' : '' }}>Plus ancien d'abord</option>
        </select>
        <input type="hidden" name="filtre" value="{{ $filtre ?? 'tous' }}">

        <div class="flex-1 min-w-[200px] relative">
            <input type="text" name="q" value="{{ $search ?? '' }}"
                placeholder="Rechercher (objet, numéro, expéditeur)…"
                class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-stone-300 placeholder-gray-400">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
        </div>
        <button type="submit"
            class="px-4 py-2 bg-stone-600 text-white rounded-xl text-sm font-semibold hover:bg-stone-700 transition">
            Filtrer
        </button>
        @if(($filtre ?? 'tous') !== 'tous' || ($search ?? '') !== '')
        <a href="{{ route('courrier.archives') }}"
            class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-1.5">
            <i class="fas fa-times text-xs"></i> Réinitialiser
        </a>
        @endif
    </div>
</form>

<p class="text-xs text-gray-400 mb-3">{{ count($courriers) }} courrier(s) archivé(s) trouvé(s)</p>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 bg-stone-50">
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Numéro</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Objet</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Type</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Priorité</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut final</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Date enreg.</th>
                <th class="text-left px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Agent</th>
                <th class="text-right px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($courriers as $c)
            <tr class="hover:bg-stone-50/50 transition opacity-80">
                <td class="px-6 py-4 font-bold text-gray-600 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fas fa-archive text-stone-400 text-xs"></i>
                        {{ $c['num'] }}
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-600">{{ $c['objet'] }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full
                        {{ $c['type'] === 'Arrivé' ? 'bg-indigo-50 text-indigo-600' : 'bg-orange-50 text-orange-600' }}">
                        <i class="fas {{ $c['type'] === 'Arrivé' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-[10px]"></i>
                        {{ $c['type'] }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    @php $priClass = match($c['priorite']) { 'Urgent' => 'text-orange-500', 'Très urgent' => 'text-red-500', default => 'text-gray-500' }; @endphp
                    <span class="{{ $priClass }} text-sm font-semibold">{{ $c['priorite'] }}</span>
                </td>
                <td class="px-6 py-4">
                    @php $badge = match($c['statut']) {
                        'En attente'    => 'bg-yellow-50 text-yellow-600',
                        'En traitement' => 'bg-blue-50 text-blue-600',
                        'Traité'        => 'bg-green-50 text-green-600',
                        default         => 'bg-gray-100 text-gray-500',
                    }; @endphp
                    <span class="{{ $badge }} px-3 py-1 rounded-full text-xs font-semibold">{{ $c['statut'] }}</span>
                </td>
                <td class="px-6 py-4 text-gray-500 text-xs whitespace-nowrap">{{ $c['date'] }}</td>
                <td class="px-6 py-4 text-gray-500">{{ $c['agent'] }}</td>
                <td class="px-6 py-4 text-right">
                    @if(!empty($c['fichier']))
                    <button onclick="openPdfModal('{{ $c['fichier'] }}', '{{ addslashes($c['num']) }}')"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-stone-100 hover:bg-stone-200 transition text-stone-500"
                        title="Voir le courrier">
                        <i class="fa-solid fa-eye text-sm"></i>
                    </button>
                    @else
                    <span class="text-gray-300 text-xs">—</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-6 py-16 text-center text-gray-400">
                    <i class="fas fa-archive text-4xl text-gray-200 mb-3 block"></i>
                    Aucun courrier archivé trouvé pour ces critères.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

@endif

{{-- ════════════════════════════════════════════════════════════
     MODAUX GLOBAUX — disponibles pour tous les sous-onglets
     ════════════════════════════════════════════════════════════ --}}

{{-- Modal Confirmation Valider/Rejeter --}}
<div id="modal-decision" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDecisionModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 id="decision-title" class="font-bold text-gray-800 text-sm"></h3>
            <button onclick="closeDecisionModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-5 py-4">
            <p class="text-sm text-gray-600 mb-2"><span class="font-semibold text-gray-800">Courrier :</span> <span id="decision-num"></span></p>
            <p class="text-sm text-gray-600"><span class="font-semibold text-gray-800">Objet :</span> <span id="decision-objet"></span></p>
            <p id="decision-message" class="mt-4 text-sm text-gray-700"></p>
        </div>
        <form id="decision-form" method="POST" class="px-5 pb-5 flex items-center justify-end gap-2">
            @csrf
            <button type="button" onclick="closeDecisionModal()" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">Annuler</button>
            <button id="decision-submit" type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold"></button>
        </form>
    </div>
</div>

{{-- Modal PDF Viewer --}}
<div id="modal-pdf" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closePdfModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col" style="height:85vh">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-file-pdf text-red-500"></i>
                <span id="pdf-modal-title" class="font-bold text-gray-800 text-sm"></span>
            </div>
            <button onclick="closePdfModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="flex-1 p-4 bg-gray-50 rounded-b-2xl overflow-hidden">
            <iframe id="pdf-viewer" src="" class="w-full h-full rounded-xl border border-gray-200 bg-white" style="min-height:0"></iframe>
            <div id="pdf-placeholder" class="hidden w-full h-full flex flex-col items-center justify-center text-gray-400">
                <i class="fa-solid fa-file-pdf text-6xl text-red-200 mb-4"></i>
                <p class="text-sm font-medium">Aucun fichier PDF associé à ce courrier.</p>
                <p class="text-xs text-gray-300 mt-1">Le fichier sera disponible après enregistrement.</p>
            </div>
        </div>
    </div>
</div>

{{-- Modal Modifier Courrier --}}
@php
    $recipientAdminOptions = \App\Models\RecipientAdministration::query()
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name', 'code']);
@endphp

<div id="modal-edit" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeEditModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col" style="max-height:90vh">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-amber-500"></i>
                Modifier le courrier — <span id="edit-num" class="text-amber-600"></span>
            </h3>
            <button onclick="closeEditModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="#" id="form-edit-courrier" enctype="multipart/form-data" class="flex flex-col flex-1 min-h-0">
            <div class="overflow-y-auto flex-1 p-5 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_courrier_num" id="edit-num-hidden">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Objet</label>
                    <input type="text" name="objet" id="edit-objet" required
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-gray-50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Priorité</label>
                    <select name="urgence" id="edit-priorite"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-gray-50">
                        <option value="normale">Normale</option>
                        <option value="urgent">Urgent</option>
                        <option value="tres_urgent">Très urgent</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Date d'émission</label>
                    <input type="date" name="date_emission" id="edit-date-emission"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-gray-50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Numéro d'émission</label>
                    <input type="text" name="numero_emission" id="edit-numero-emission"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-gray-50">
                </div>
            </div>
            <div>
                <label id="edit-expediteur-label" class="block text-xs font-semibold text-gray-700 mb-1">Expéditeur</label>
                <textarea name="expediteur" id="edit-expediteur" rows="2"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-gray-50 resize-none"></textarea>
            </div>
            <div id="edit-destinataire-admin-block" class="hidden">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Administration destinataire</label>
                <select id="edit-destinataire-admin" name="destinataire_administration_id"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-gray-50">
                    <option value="">Sélectionner une administration destinataire</option>
                    @foreach($recipientAdminOptions as $recipientAdmin)
                    <option value="{{ $recipientAdmin->id }}">{{ $recipientAdmin->name }}{{ !empty($recipientAdmin->code) ? ' (' . $recipientAdmin->code . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div id="edit-accuse-block" class="hidden">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Accusé de réception</label>
                <div class="border-2 border-dashed border-gray-200 rounded-xl px-4 py-5 text-center bg-gray-50 hover:bg-gray-100 transition cursor-pointer"
                     onclick="document.getElementById('edit-accuse-input').click()">
                    <i class="fas fa-file-signature text-2xl text-gray-300 mb-1 block"></i>
                    <p class="text-xs text-gray-400">Cliquez pour joindre l'accusé de réception</p>
                    <input id="edit-accuse-input" type="file" name="accuse_reception" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                           onchange="updateEditAccuseLabel(this)">
                </div>
                <p id="edit-accuse-label" class="mt-1.5 text-xs text-gray-500 hidden"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Remplacer le fichier</label>
                <div class="border-2 border-dashed border-gray-200 rounded-xl px-4 py-5 text-center bg-gray-50 hover:bg-gray-100 transition cursor-pointer"
                     onclick="document.getElementById('edit-file-input').click()">
                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-300 mb-1 block"></i>
                    <p class="text-xs text-gray-400">Cliquez pour sélectionner un nouveau fichier</p>
                    <input id="edit-file-input" type="file" name="fichier" accept=".pdf,.doc,.docx,.jpg,.png" class="hidden"
                           onchange="updateEditFileLabel(this)">
                </div>
                <p id="edit-file-label" class="mt-1.5 text-xs text-gray-500 hidden"></p>
            </div>
            </div>
            <div class="flex gap-3 justify-end px-5 py-4 border-t border-gray-100 flex-shrink-0 bg-white rounded-b-2xl">
                <button type="button" onclick="closeEditModal()"
                    class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Annuler</button>
                <button type="submit"
                    class="px-6 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-semibold hover:bg-amber-600 transition flex items-center gap-2">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Imputer (global) --}}
<div id="modal-imputer" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeImputerModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col" style="max-height:92vh">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <h3 class="font-bold text-gray-800 flex items-center gap-2 text-base">
                <i class="fas fa-share text-blue-500"></i> Imputation du courrier
            </h3>
            <button onclick="closeImputerModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="overflow-y-auto flex-1 px-6 py-5">
            <form id="form-imputer" method="POST" action="{{ route('courrier.imputer') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="courrier_id" id="imputer-courrier-id">
            <input type="hidden" name="_imputer_num" id="imputer-num-hidden">
            <div class="bg-gray-50 border border-gray-200 rounded-xl divide-y divide-gray-200 text-sm">
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="text-xs font-semibold text-gray-400 w-28 shrink-0">N° Courrier</span>
                    <span id="imputer-num" class="font-bold text-blue-700"></span>
                </div>
                <div class="flex items-start gap-3 px-4 py-3">
                    <span class="text-xs font-semibold text-gray-400 w-28 shrink-0 pt-0.5">Objet</span>
                    <span id="imputer-objet" class="text-gray-800"></span>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="text-xs font-semibold text-gray-400 w-28 shrink-0">Expéditeur</span>
                    <span id="imputer-expediteur" class="text-gray-800"></span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">
                    Entité(s) sous ma tutelle <span class="text-red-500">*</span>
                    <span class="text-gray-400 font-normal ml-1">— sélectionnez une entité et son instruction, puis ajoutez</span>
                </label>
                <div class="flex flex-col md:flex-row gap-2 items-stretch mb-3">
                    <select id="select-entite" class="w-full md:flex-1 min-w-0 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                        <option value="">— Entité —</option>
                        @forelse(($imputationEntities ?? collect()) as $ent)
                        <option value="{{ $ent->code }}" data-label="{{ $ent->name }}">{{ $ent->name }} ({{ $ent->code }})</option>
                        @empty
                        <option value="" disabled>Aucune entité fille disponible</option>
                        @endforelse
                    </select>
                    <select id="select-instruction" class="w-full md:flex-1 min-w-0 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                        <option value="">— Instruction —</option>
                        @foreach(\App\Models\Instruction::where('actif', true)->latest()->get() as $instr)
                        <option value="{{ $instr->id }}" data-label="{{ $instr->nom }}">{{ $instr->nom }}</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="ajouterEntite()"
                        class="w-full md:w-auto px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold transition flex items-center justify-center gap-1.5 shrink-0">
                        <i class="fas fa-plus text-xs"></i> Ajouter
                    </button>
                </div>
                <div id="entites-ajoutees" class="space-y-2"></div>
                <p id="entites-vide-msg" class="text-xs text-gray-400 text-center py-2">Aucune entité ajoutée.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Instructions particulières</label>
                <textarea name="instructions_particuliaires" rows="3" placeholder="Instructions générales applicables à toutes les entités…"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50 resize-none"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Délai de traitement <span class="text-red-500">*</span></label>
                <input type="date" name="delai_traitement" required
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50">
            </div>
            </form>
        </div>
        <div class="flex gap-3 justify-end px-6 py-4 border-t border-gray-100 shrink-0 bg-white rounded-b-2xl">
            <button type="button" onclick="closeImputerModal()"
                class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Annuler</button>
            <button type="submit" form="form-imputer"
                class="px-6 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-share"></i> Confirmer l'imputation
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
var __courrierUpdateUrlTemplate = @json(url('/courrier/__ID__'));
/* ── Config viewer (injectée depuis le controller) ── */
var __ooViewer = @json($ooViewer ?? 'native');
var __ooUrl    = @json($ooUrl    ?? '');
var __visualiserUrl = @json(route('courrier.visualiser'));

/* ── Ouvre le viewer (modal natif ou page OnlyOffice) ── */
function openPdfModal(fileUrl, title) {
    if (!fileUrl) {
        document.getElementById('pdf-modal-title').textContent = title || 'Document';
        document.getElementById('pdf-viewer').classList.add('hidden');
        document.getElementById('pdf-placeholder').classList.remove('hidden');
        document.getElementById('pdf-placeholder').classList.add('flex');
        document.getElementById('modal-pdf').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        return;
    }

    if (__ooViewer === 'onlyoffice' && __ooUrl) {
        // Ouvrir dans une nouvelle fenêtre/onglet — page dédiée OnlyOffice
        var params = new URLSearchParams({ file: fileUrl, title: title || 'Document' });
        window.open(__visualiserUrl + '?' + params.toString(), '_blank', 'noopener');
    } else {
        // Lecteur natif — iframe dans le modal
        document.getElementById('pdf-modal-title').textContent = title || 'Document';
        var viewer = document.getElementById('pdf-viewer');
        var placeholder = document.getElementById('pdf-placeholder');
        viewer.src = fileUrl;
        viewer.classList.remove('hidden');
        placeholder.classList.add('hidden');
        placeholder.classList.remove('flex');
        document.getElementById('modal-pdf').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}
function closePdfModal() {
    document.getElementById('modal-pdf').classList.add('hidden');
    document.getElementById('pdf-viewer').src = '';
    document.body.style.overflow = '';
}
/* ── Edit ── */
function openEditModal(id, num, objet, type, priorite, dateEmission, numeroEmission, expediteur, destinataireAdminId) {
    var isDepart = type === 'Départ';
    if (id) {
        document.getElementById('form-edit-courrier').action = __courrierUpdateUrlTemplate.replace('__ID__', id);
    }
    document.getElementById('edit-num').textContent = num;
    document.getElementById('edit-num-hidden').value = num;
    document.getElementById('edit-objet').value = objet;
    document.getElementById('edit-date-emission').value = dateEmission || '';
    document.getElementById('edit-numero-emission').value = numeroEmission || '';
    document.getElementById('edit-expediteur').value = expediteur || '';
    document.getElementById('edit-expediteur-label').textContent = isDepart ? 'Destinataire' : 'Expéditeur';
    var destAdminBlock = document.getElementById('edit-destinataire-admin-block');
    var destAdminSelect = document.getElementById('edit-destinataire-admin');
    destAdminBlock.classList.toggle('hidden', !isDepart);
    if (isDepart && destAdminSelect) {
        destAdminSelect.value = destinataireAdminId || '';
    }
    var accuseBlock = document.getElementById('edit-accuse-block');
    if (isDepart) { accuseBlock.classList.remove('hidden'); }
    else { accuseBlock.classList.add('hidden'); document.getElementById('edit-accuse-input').value = ''; document.getElementById('edit-accuse-label').classList.add('hidden'); }
    var priMap = {'Normale':'normale','Urgent':'urgent','Très urgent':'tres_urgent'};
    document.getElementById('edit-priorite').value = priMap[priorite] || 'normale';
    document.getElementById('edit-file-label').classList.add('hidden');
    document.getElementById('edit-file-input').value = '';
    document.getElementById('modal-edit').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeEditModal() { document.getElementById('modal-edit').classList.add('hidden'); document.body.style.overflow = ''; }
function updateEditAccuseLabel(input) {
    var lbl = document.getElementById('edit-accuse-label');
    lbl.textContent = input.files.length > 0 ? '✔ ' + input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(0) + ' Ko)' : '';
    lbl.classList.toggle('hidden', input.files.length === 0);
}
function updateEditFileLabel(input) {
    var lbl = document.getElementById('edit-file-label');
    lbl.textContent = input.files.length > 0 ? '✔ ' + input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(0) + ' Ko)' : '';
    lbl.classList.toggle('hidden', input.files.length === 0);
}
/* ── Imputer ── */
var _imputerEntites = {};
function openImputerModal(id, num, objet, expediteur) {
    document.getElementById('imputer-courrier-id').value = id;
    document.getElementById('imputer-num').textContent = num;
    document.getElementById('imputer-num-hidden').value = num;
    document.getElementById('imputer-objet').textContent = objet;
    document.getElementById('imputer-expediteur').textContent = expediteur || '—';
    document.getElementById('select-entite').value = '';
    document.getElementById('select-instruction').value = '';
    document.getElementById('entites-ajoutees').innerHTML = '';
    document.getElementById('entites-vide-msg').classList.remove('hidden');
    _imputerEntites = {};
    document.getElementById('modal-imputer').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeImputerModal() { document.getElementById('modal-imputer').classList.add('hidden'); document.body.style.overflow = ''; }
function imputerSafeKey(val) {
    return encodeURIComponent((val || '').toString());
}
function ajouterEntite() {
    var selE = document.getElementById('select-entite');
    var selI = document.getElementById('select-instruction');
    var val = selE.value; var instrVal = selI.value;
    if (!val || !instrVal) { selE.classList.toggle('border-red-400', !val); selI.classList.toggle('border-red-400', !instrVal); return; }
    selE.classList.remove('border-red-400'); selI.classList.remove('border-red-400');
    var label = selE.options[selE.selectedIndex].dataset.label;
    var instrLabel = selI.options[selI.selectedIndex].dataset.label;
    if (_imputerEntites[val]) {
        _imputerEntites[val].instrVal = instrVal; _imputerEntites[val].instrLabel = instrLabel;
        var row = document.getElementById('entite-row-' + imputerSafeKey(val));
        if (row) { row.querySelector('.instr-badge').textContent = instrLabel; row.querySelector('input[name$="[instruction]"]').value = instrVal; }
    } else { _imputerEntites[val] = { label, instrVal, instrLabel }; renderEntiteRow(val); }
    selE.value = ''; selI.value = '';
    document.getElementById('entites-vide-msg').classList.add('hidden');
}
function renderEntiteRow(val) {
    var e = _imputerEntites[val];
    var row = document.createElement('div');
    row.id = 'entite-row-' + imputerSafeKey(val);
    row.className = 'flex flex-wrap md:flex-nowrap items-start md:items-center gap-2 md:gap-3 bg-blue-50 border border-blue-200 rounded-xl px-3 md:px-4 py-2.5';
    row.innerHTML = '<i class="fas fa-building text-blue-400 text-sm shrink-0"></i>' +
        '<span class="w-full md:flex-1 min-w-0 text-sm font-semibold text-gray-800 break-words">' + e.label + '</span>' +
        '<span class="instr-badge max-w-full text-xs bg-white border border-blue-300 text-blue-700 px-2.5 py-1 rounded-full font-semibold break-words">' + e.instrLabel + '</span>' +
        '<input type="hidden" name="entites[' + val + '][entite]" value="' + val + '">' +
        '<input type="hidden" name="entites[' + val + '][instruction]" value="' + e.instrVal + '">' +
        '<button type="button" onclick="retirerEntite(\'' + val + '\')" class="md:ml-1 w-6 h-6 flex items-center justify-center rounded-full hover:bg-red-100 text-gray-400 hover:text-red-500 transition"><i class="fas fa-times text-xs"></i></button>';
    document.getElementById('entites-ajoutees').appendChild(row);
}
function retirerEntite(val) {
    delete _imputerEntites[val];
    var row = document.getElementById('entite-row-' + imputerSafeKey(val)); if (row) row.remove();
    if (Object.keys(_imputerEntites).length === 0) document.getElementById('entites-vide-msg').classList.remove('hidden');
}

/* ── Modal décision Suivi Imputation ── */
function openDecisionModal(action, url, numero, objet) {
    var title = document.getElementById('decision-title');
    var msg = document.getElementById('decision-message');
    var submitBtn = document.getElementById('decision-submit');
    var form = document.getElementById('decision-form');

    document.getElementById('decision-num').textContent = numero || '—';
    document.getElementById('decision-objet').textContent = objet || '—';
    form.action = url;

    if (action === 'ok') {
        title.textContent = 'Confirmer OK';
        msg.textContent = 'Cette action valide localement le traitement et le classe en courrier traité.';
        submitBtn.textContent = 'OK';
        submitBtn.className = 'px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700';
    } else if (action === 'valider') {
        title.textContent = 'Confirmer la validation';
        msg.textContent = 'Cette action valide et transmet au niveau parent lorsque disponible.';
        submitBtn.textContent = 'Valider';
        submitBtn.className = 'px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700';
    } else {
        title.textContent = 'Confirmer le rejet';
        msg.textContent = 'Cette action rejettera la réponse et renverra le courrier en traitement.';
        submitBtn.textContent = 'Rejeter';
        submitBtn.className = 'px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700';
    }

    document.getElementById('modal-decision').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeDecisionModal() {
    document.getElementById('modal-decision').classList.add('hidden');
    document.body.style.overflow = '';
}

/* ── Escape global ── */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closePdfModal(); closeEditModal(); closeImputerModal(); if(typeof closeTraiterModal==='function') closeTraiterModal(); if(typeof closeDecisionModal==='function') closeDecisionModal(); if(typeof closeImputerModal_en==='function') closeImputerModal_en(); }
});
</script>
@endpush

@endsection
