@extends('layouts.app')
@section('title', 'Vérification QR')
@section('page-title', 'Vérification QR')
@section('page-subtitle', 'Vérifiez l\'authenticité d\'un document généré ou signé électroniquement')
@section('content')

<div class="max-w-2xl mx-auto">

    {{-- Hero --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center mb-6">
        <div class="w-20 h-20 rounded-full bg-blue-50 flex items-center justify-center mx-auto mb-5">
            <i class="fas fa-qrcode text-4xl text-[#2453d6]"></i>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">Vérifier l'authenticité d'un document</h2>
        <p class="text-sm text-gray-500 max-w-md mx-auto">
            Entrez le token ou scannez le QR code présent en pied de page du document pour vérifier son authenticité et télécharger une copie certifiée.
        </p>
    </div>

    {{-- Formulaire --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Token de vérification</label>
                <div class="flex gap-2">
                    <input type="text" id="verify-token"
                        value="{{ $token }}"
                        placeholder="Entrez le token présent sur le document..."
                        class="flex-1 border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6] font-mono">
                    <button onclick="verifyToken()"
                        class="px-5 py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white font-semibold rounded-xl text-sm transition flex items-center gap-2">
                        <i class="fas fa-search"></i> Vérifier
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Numéro du document généré</label>
                <div class="flex gap-2">
                    <input type="text" id="verify-number"
                        placeholder="Ex: ADM - ENTITE - 00001 - 2026"
                        class="flex-1 border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6] font-mono">
                    <button onclick="verifyDocumentNumber()"
                        class="px-5 py-2.5 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition flex items-center gap-2">
                        <i class="fas fa-hashtag"></i> Vérifier numéro
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    Format assisté automatiquement : <span class="font-mono">ADM - ENTITE - 00001 - 2026</span>
                </p>
            </div>

            {{-- Loader --}}
            <div id="verify-loading" class="hidden items-center gap-2 text-sm text-gray-500 py-2">
                <i class="fas fa-spinner fa-spin text-[#2453d6]"></i> Vérification en cours…
            </div>

            {{-- Résultat --}}
            <div id="verify-result" class="hidden"></div>
            <div id="verify-number-result" class="hidden"></div>
        </div>
    </div>

    {{-- Info --}}
    <div class="mt-5 px-5 py-4 bg-blue-50 border border-blue-200 rounded-2xl text-sm text-blue-700">
        <p class="font-semibold flex items-center gap-2 mb-1">
            <i class="fas fa-info-circle text-blue-400"></i> Comment trouver le token ?
        </p>
        <ul class="list-disc list-inside space-y-1 text-blue-600">
            <li>Le QR code est imprimé en <strong>pied de page</strong> de chaque document généré</li>
            <li>Scannez le QR code avec votre téléphone — vous serez redirigé automatiquement</li>
            <li>Le token alphanumérique est aussi lisible sous le QR code</li>
        </ul>
    </div>
</div>

@push('scripts')
<script>
// Vérification automatique si token présent dans l'URL
const _preToken = '{{ $token }}';
if (_preToken) { window.addEventListener('DOMContentLoaded', () => verifyToken()); }

async function verifyToken() {
    const tokenInput = document.getElementById('verify-token');
    const token = tokenInput.value.trim();
    if (!token) { tokenInput.focus(); return; }

    const result  = document.getElementById('verify-result');
    const loading = document.getElementById('verify-loading');

    result.classList.add('hidden');
    loading.classList.remove('hidden');
    loading.style.display = 'flex';

    try {
        const resp = await fetch('{{ route("qr-verification.verify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token }),
        });
        const data = await resp.json();

        if (resp.ok && data.valid) {
            const doc = data.document ?? {};
            const isSigned = !!(data.is_signed || data.type === 'signature');
            const rows = [
                doc.document_number ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium w-40">N° Document</th><td class="py-1 font-mono font-bold text-blue-700">${esc(doc.document_number)}</td></tr>` : '',
                doc.title           ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Titre</th><td class="py-1">${esc(doc.title)}</td></tr>` : '',
                doc.administration  ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Administration</th><td class="py-1">${esc(doc.administration)}</td></tr>` : '',
                doc.owner           ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Généré par</th><td class="py-1">${esc(doc.owner)}</td></tr>` : '',
                doc.created_at      ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Date de génération</th><td class="py-1">${esc(doc.created_at)}</td></tr>` : '',
                signersRow(data.signers),
                (data.signed_at || doc.signed_at) ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Signé le</th><td class="py-1 font-semibold text-emerald-700">${esc(data.signed_at || doc.signed_at)}</td></tr>` : '',
            ].filter(Boolean).join('');

            const signedBadge = isSigned
                ? `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200">
                    <i class="fas fa-file-signature text-[11px]"></i> Version PDF signée électroniquement
                   </span>`
                : `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-bold border border-amber-200">
                    <i class="fas fa-hourglass-half text-[11px]"></i> Non signé — en attente de signature
                   </span>`;

            const dlLabel = isSigned
                ? `<i class="fas fa-file-pdf"></i> Télécharger la version signée (PDF)`
                : `<i class="fas fa-download"></i> Télécharger le document`;

            result.innerHTML = `
                <div class="p-5 bg-green-50 border border-green-200 rounded-xl space-y-3">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <p class="font-bold text-green-800 flex items-center gap-2 text-base">
                            <i class="fas fa-check-circle text-green-500 text-lg"></i>
                            Document authentique
                        </p>
                        ${signedBadge}
                    </div>
                    <table class="w-full text-sm text-gray-700">${rows}</table>
                    <div class="pt-2 flex flex-wrap items-center gap-2">
                        ${data.download_url ? `<a href="${data.download_url}"
                           class="inline-flex items-center gap-2 px-5 py-2.5 ${isSigned ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-[#2453d6] hover:bg-[#1f47bb]'} text-white font-semibold rounded-xl text-sm transition">
                            ${dlLabel}
                        </a>` : ''}
                        ${data.is_owner && data.editor_url ? `<button type="button" onclick="openOnlyOfficeEditor('${(data.editor_url || '').replace(/'/g, "\\'")}')"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition">
                            <i class="fas fa-pen-to-square"></i> Ouvrir dans l'éditeur
                        </button>` : ''}
                    </div>
                    <p class="text-xs text-green-600 border-t border-green-200 pt-2">${esc(data.message ?? '')}</p>
                </div>`;
        } else {
            result.innerHTML = `
                <div class="p-5 bg-red-50 border border-red-200 rounded-xl">
                    <p class="font-bold text-red-800 flex items-center gap-2">
                        <i class="fas fa-times-circle text-red-500 text-lg"></i> Document non reconnu
                    </p>
                    <p class="text-sm text-red-600 mt-2">${esc(data.message ?? 'Ce code QR n\'est associé à aucun document dans le système.')}</p>
                </div>`;
        }
    } catch (e) {
        result.innerHTML = `
            <div class="p-5 bg-yellow-50 border border-yellow-200 rounded-xl">
                <p class="font-bold text-yellow-800 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i> Erreur de connexion
                </p>
                <p class="text-sm text-yellow-700 mt-1">Impossible de vérifier. Réessayez dans quelques instants.</p>
            </div>`;
    } finally {
        loading.style.display = 'none';
        loading.classList.add('hidden');
        result.classList.remove('hidden');
    }
}

// Ligne du tableau listant tous les signataires, avec la date de chacun s'ils sont plusieurs.
function signersRow(signers) {
    if (!signers || !signers.length) return '';
    const several = signers.length > 1;
    const items = signers.map(s => `<div>${esc(s.name)}${several && s.signed_at ? ` <span class="text-xs font-normal text-gray-500">— le ${esc(s.signed_at)}</span>` : ''}</div>`).join('');
    return `<tr><th class="text-left align-top py-1 pr-4 text-gray-500 font-medium">${several ? `Signataires (${signers.length})` : 'Signataire'}</th><td class="py-1 font-semibold text-emerald-700">${items}</td></tr>`;
}

async function verifyDocumentNumber() {
    const numberInput = document.getElementById('verify-number');
    const documentNumber = numberInput.value.trim();
    if (!documentNumber) { numberInput.focus(); return; }

    const result  = document.getElementById('verify-number-result');
    const loading = document.getElementById('verify-loading');

    result.classList.add('hidden');
    loading.classList.remove('hidden');
    loading.style.display = 'flex';

    try {
        const resp = await fetch('{{ route("qr-verification.verify-number") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ document_number: documentNumber }),
        });

        const data = await resp.json();
        if (resp.ok && data.valid) {
            const doc = data.document ?? {};
            const isSigned = !!data.is_signed;
            const signedBadge = isSigned
                ? `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200">
                    <i class="fas fa-file-signature text-[11px]"></i> Version PDF signée électroniquement
                   </span>`
                : `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-bold border border-amber-200">
                    <i class="fas fa-hourglass-half text-[11px]"></i> Non signé — en attente de signature
                   </span>`;

            const dlLabel = isSigned
                ? `<i class="fas fa-file-pdf"></i> Télécharger la version signée (PDF)`
                : `<i class="fas fa-download"></i> Télécharger le document`;

            const rows = [
                doc.document_number ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium w-40">N° Document</th><td class="py-1 font-mono font-bold text-blue-700">${esc(doc.document_number)}</td></tr>` : '',
                doc.title           ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Titre</th><td class="py-1">${esc(doc.title)}</td></tr>` : '',
                doc.administration  ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Administration</th><td class="py-1">${esc(doc.administration)}</td></tr>` : '',
                doc.owner           ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Généré par</th><td class="py-1">${esc(doc.owner)}</td></tr>` : '',
                doc.created_at      ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Date de génération</th><td class="py-1">${esc(doc.created_at)}</td></tr>` : '',
                signersRow(data.signers),
                (data.signed_at || doc.signed_at) ? `<tr><th class="text-left py-1 pr-4 text-gray-500 font-medium">Signé le</th><td class="py-1 font-semibold text-emerald-700">${esc(data.signed_at || doc.signed_at)}</td></tr>` : '',
            ].filter(Boolean).join('');

            result.innerHTML = `
                <div class="p-5 bg-green-50 border border-green-200 rounded-xl space-y-3">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <p class="font-bold text-green-800 flex items-center gap-2 text-base">
                            <i class="fas fa-check-circle text-green-500 text-lg"></i>
                            Numéro valide.
                        </p>
                        ${signedBadge}
                    </div>
                    <table class="w-full text-sm text-gray-700">${rows}</table>
                    <div class="pt-1 flex flex-wrap items-center gap-2">
                        ${data.download_url ? `<a href="${data.download_url}"
                           class="inline-flex items-center gap-2 px-5 py-2.5 ${isSigned ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-[#2453d6] hover:bg-[#1f47bb]'} text-white font-semibold rounded-xl text-sm transition">
                            ${dlLabel}
                        </a>` : ''}
                        ${data.is_owner && data.editor_url ? `<button type="button" onclick="openOnlyOfficeEditor('${(data.editor_url || '').replace(/'/g, "\\'")}')"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-700 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition">
                            <i class="fas fa-pen-to-square"></i> Ouvrir dans l'éditeur
                        </button>` : ''}
                    </div>
                </div>`;
        } else {
            result.innerHTML = `
                <div class="p-5 bg-red-50 border border-red-200 rounded-xl">
                    <p class="font-bold text-red-800 flex items-center gap-2">
                        <i class="fas fa-times-circle text-red-500 text-lg"></i> Numéro non reconnu
                    </p>
                    <p class="text-sm text-red-600 mt-2">${esc(data.message || 'Ce document ne fait pas partie des documents générés.')}</p>
                </div>`;
        }
    } catch (e) {
        result.innerHTML = `
            <div class="p-5 bg-yellow-50 border border-yellow-200 rounded-xl">
                <p class="font-bold text-yellow-800 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i> Erreur de connexion
                </p>
                <p class="text-sm text-yellow-700 mt-1">Impossible de vérifier le numéro. Réessayez dans quelques instants.</p>
            </div>`;
    } finally {
        loading.style.display = 'none';
        loading.classList.add('hidden');
        result.classList.remove('hidden');
    }
}

function openPreviewPopup(url) {
    const width = 1000;
    const height = 760;
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    window.open(url, 'docPreviewPopup', `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);
}

function openOnlyOfficeEditor(url) {
    if (!url) {
        return;
    }
    window.location.href = url;
}

function formatDocumentNumberInput(value) {
    const cleaned = String(value || '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, ' ')
        .trim();

    if (!cleaned) {
        return '';
    }

    const rawParts = cleaned.split(/\s+/).filter(Boolean);
    const parts = [];

    if (rawParts.length > 0) {
        parts.push(rawParts[0].slice(0, 12));
    }

    if (rawParts.length > 1) {
        parts.push(rawParts[1].slice(0, 24));
    }

    if (rawParts.length > 2) {
        parts.push(rawParts[2].replace(/[^0-9]/g, '').slice(0, 5));
    }

    if (rawParts.length > 3) {
        parts.push(rawParts[3].replace(/[^0-9]/g, '').slice(0, 4));
    }

    return parts.filter(Boolean).join(' - ');
}

function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.getElementById('verify-token').addEventListener('keydown', e => {
    if (e.key === 'Enter') verifyToken();
});

document.getElementById('verify-number').addEventListener('keydown', e => {
    if (e.key === 'Enter') verifyDocumentNumber();
});

document.getElementById('verify-number').addEventListener('input', e => {
    const formatted = formatDocumentNumberInput(e.target.value);
    if (formatted !== e.target.value) {
        e.target.value = formatted;
    }
});
</script>
@endpush
@endsection
