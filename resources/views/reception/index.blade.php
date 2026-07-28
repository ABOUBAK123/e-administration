@extends('layouts.app')
@section('title', 'Réception')
@section('page-title', 'Réception')
@section('page-subtitle', 'Documents reçus depuis d\'autres administrations')
@section('content')

{{-- Barre de recherche --}}
<div class="flex items-center gap-4 mb-6">
    <form method="GET" action="{{ route('reception.index') }}" class="flex-1 max-w-md flex gap-2">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Rechercher un document reçu…"
                class="w-full border border-gray-300 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6] bg-white">
        </div>
        <button type="submit" class="px-4 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold hover:bg-[#1f47bb] transition">
            Chercher
        </button>
        @if($search)
            <a href="{{ route('reception.index') }}" class="px-4 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-100 transition">
                <i class="fas fa-times"></i>
            </a>
        @endif
    </form>
</div>

@if($documents->isEmpty())
    <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-5">
            <i class="fas fa-inbox text-4xl text-gray-300"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-1">Aucun document reçu</h3>
        <p class="text-sm text-gray-400 max-w-sm">
            Les documents partagés avec vous par d'autres administrations apparaîtront ici.
        </p>
    </div>
@else
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-inbox text-[#2453d6]"></i> Documents reçus
            </h2>
            <span class="text-sm text-gray-500">{{ $documents->total() }} document(s)</span>
        </div>

            <div class="overflow-x-scroll pb-2">
            <table class="w-full min-w-[980px] text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Document</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Expéditeur</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Demandeur</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Téléphone</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">RIB</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Statut</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Reçu le</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Statut réception</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($documents as $doc)
                @php $shareInfo = $sharesInfo[$doc->id] ?? null; @endphp
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-file-alt text-blue-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 truncate max-w-xs">{{ $doc->title }}</p>
                                <p class="text-xs text-gray-400">{{ $doc->file_size ? number_format($doc->file_size / 1024, 0) . ' KB' : '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-gray-600">{{ $doc->owner?->name ?? '—' }}</td>
                    <td class="px-5 py-4">
                        @if($shareInfo?->applicant_full_name)
                            <div class="font-medium text-gray-800 text-xs">{{ $shareInfo->applicant_full_name }}</div>
                            @if($shareInfo->applicant_email)
                                <div class="text-xs text-gray-400">{{ $shareInfo->applicant_email }}</div>
                            @endif
                            @if($shareInfo->tracking_number)
                                <div class="text-xs text-indigo-500 font-mono">{{ $shareInfo->tracking_number }}</div>
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-gray-600 text-xs">
                        {{ $shareInfo?->applicant_phone ?? '—' }}
                    </td>
                    <td class="px-5 py-4 text-gray-700 text-xs font-mono">
                        {{ $shareInfo?->applicant_rib ?? '—' }}
                    </td>
                    <td class="px-5 py-4">
                        @php
                            $statusClass = match($doc->status) {
                                'signed'            => 'bg-green-100 text-green-700',
                                'approved'          => 'bg-emerald-100 text-emerald-700',
                                'completed'         => 'bg-teal-100 text-teal-700',
                                'sent'              => 'bg-blue-100 text-blue-700',
                                'active'            => 'bg-indigo-100 text-indigo-700',
                                'pending_signature' => 'bg-amber-100 text-amber-700',
                                'processing'        => 'bg-orange-100 text-orange-700',
                                'draft'             => 'bg-gray-100 text-gray-600',
                                'archived'          => 'bg-slate-100 text-slate-600',
                                'rejected'          => 'bg-red-100 text-red-700',
                                default             => 'bg-blue-100 text-blue-700',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
                            {{ __('documents.status_' . $doc->status, [], app()->getLocale()) !== 'documents.status_' . $doc->status
                                ? __('documents.status_' . $doc->status)
                                : ucfirst(str_replace('_', ' ', $doc->status)) }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-gray-500 text-xs">{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                    @php
                        $recStatus = $receptionStatuses[$doc->id]->reception_status ?? null;
                        $isTransmis = $recStatus === 'transmis';
                        $isRecu     = $recStatus === 'recu';
                        $recLabel   = $recStatus ? __('documents.reception_status_' . $recStatus) : null;
                    @endphp
                    <td id="rec-status-cell-{{ $doc->id }}" class="px-5 py-4">
                        @if($recLabel)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                            {{ $isTransmis ? 'bg-green-100 text-green-700' : 'bg-sky-100 text-sky-700' }}">
                            <i class="fas {{ $isTransmis ? 'fa-share' : 'fa-check' }} mr-1 text-[10px]"></i>
                            {{ $recLabel }}
                        </span>
                        @else
                        <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                        @if($doc->file_path)
                        <a href="{{ route('documents.download', $doc) }}"
                            onclick="markDocReceived('{{ $doc->id }}')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition">
                            <i class="fas fa-download text-xs"></i> Télécharger
                        </a>
                        @endif
                        @if($subEntities->isNotEmpty())
                        <button type="button"
                            id="forward-btn-{{ $doc->id }}"
                            onclick="openForwardModal('{{ $doc->id }}', '{{ addslashes($doc->title) }}')"
                            title="{{ $isTransmis && isset($transmissionInfo[$doc->id]) ? 'Transmis à : ' . $transmissionInfo[$doc->id] : '' }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition
                                {{ $isTransmis
                                    ? 'bg-green-100 hover:bg-green-200 text-green-700 border border-green-200'
                                    : 'bg-indigo-100 hover:bg-indigo-200 text-indigo-700' }}">
                            <i class="fas fa-share text-xs"></i>
                            {{ $isTransmis ? __('documents.reception_status_transmis') : __('buttons.forward') }}
                        </button>
                        @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
            </div>

        @if($documents->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $documents->links() }}
        </div>
        @endif
    </div>
@endif

@endsection

@push('scripts')
{{-- Modal Transmettre aux entités sous tutelle --}}
<div id="forwardModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-share text-indigo-500"></i> Transmettre le document
            </h3>
            <button type="button" onclick="closeForwardModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-500">Document : <span id="forwardDocTitle" class="font-semibold text-gray-700"></span></p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Entité sous tutelle <span class="text-red-500">*</span></label>
                <select id="forwardSubEntityCode" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Sélectionner une entité --</option>
                    @foreach($subEntities as $se)
                    <option value="{{ $se->code }}">{{ $se->name }} ({{ $se->code }})</option>
                    @endforeach
                </select>
            </div>
            <div id="forwardMsg" class="hidden text-sm rounded-lg px-3 py-2"></div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
            <button type="button" onclick="closeForwardModal()"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
                Annuler
            </button>
            <button type="button" id="forwardSubmitBtn" onclick="submitForward()"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2">
                <i class="fas fa-share"></i> Transmettre
            </button>
        </div>
    </div>
</div>

<script>
const _recLabels = {
    recu:     '{{ __("documents.reception_status_recu") }}',
    transmis: '{{ __("documents.reception_status_transmis") }}',
    forward:  '{{ __("buttons.forward") }}',
};

let _forwardDocId = null;

function setRecStatusBadge(docId, status) {
    const cell = document.getElementById('rec-status-cell-' + docId);
    if (!cell) return;
    const isTransmis = status === 'transmis';
    const label = isTransmis ? _recLabels.transmis : _recLabels.recu;
    const icon  = isTransmis ? 'fa-share' : 'fa-check';
    const cls   = isTransmis ? 'bg-green-100 text-green-700' : 'bg-sky-100 text-sky-700';
    cell.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${cls}"><i class="fas ${icon} mr-1 text-[10px]"></i>${label}</span>`;
}

async function markDocReceived(docId) {
    try {
        const res = await fetch(`/reception/${docId}/mark-received`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            keepalive: true,
        });
        const data = await res.json();
        if (data.ok && data.reception_status) {
            setRecStatusBadge(docId, data.reception_status);
        }
    } catch (e) { /* silent */ }
}

function openForwardModal(docId, docTitle) {
    _forwardDocId = docId;
    document.getElementById('forwardDocTitle').textContent = docTitle;
    document.getElementById('forwardSubEntityCode').value = '';
    const msg = document.getElementById('forwardMsg');
    msg.className = 'hidden text-sm rounded-lg px-3 py-2';
    msg.textContent = '';
    const modal = document.getElementById('forwardModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeForwardModal() {
    const modal = document.getElementById('forwardModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    _forwardDocId = null;
}

async function submitForward() {
    const subEntityCode = document.getElementById('forwardSubEntityCode').value;
    const btn = document.getElementById('forwardSubmitBtn');

    if (!subEntityCode) {
        showForwardMsg('Veuillez sélectionner une entité sous tutelle.', false);
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

    try {
        const res = await fetch(`/reception/${_forwardDocId}/forward`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ sub_entity_code: subEntityCode }),
        });
        const data = await res.json();
        if (data.ok) {
            showForwardMsg(data.message, true);
            setRecStatusBadge(_forwardDocId, 'transmis');
            const fwdBtn = document.getElementById('forward-btn-' + _forwardDocId);
            if (fwdBtn) {
                fwdBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition bg-green-100 hover:bg-green-200 text-green-700 border border-green-200';
                fwdBtn.innerHTML = `<i class="fas fa-share text-xs"></i> ${_recLabels.transmis}`;
            }
            setTimeout(closeForwardModal, 1800);
        } else {
            showForwardMsg(data.message ?? 'Erreur lors de la transmission.', false);
        }
    } catch (e) {
        showForwardMsg('Erreur réseau. Veuillez réessayer.', false);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-share"></i> ${_recLabels.forward}`;
    }
}

function showForwardMsg(text, success) {
    const msg = document.getElementById('forwardMsg');
    msg.textContent = text;
    msg.className = 'text-sm rounded-lg px-3 py-2 ' + (success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600');
}

document.getElementById('forwardModal').addEventListener('click', function(e) {
    if (e.target === this) closeForwardModal();
});
</script>
@endpush
