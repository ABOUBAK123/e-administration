@extends('layouts.app')
@section('title', 'Corbeille')
@section('page-title', 'Corbeille')
@section('content')

@php $trashCount = $documents->count(); @endphp

{{-- Barre d'actions --}}
<div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
    <a href="{{ route('documents.index') }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-[#2453d6] font-medium">
        <i class="fas fa-arrow-left"></i> Retour à Mes Documents
    </a>

    @if($trashCount > 0)
    <button onclick="confirmEmptyTrash()"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
        <i class="fas fa-trash"></i> Vider la corbeille
    </button>
    @endif
</div>

{{-- Messages flash --}}
@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
</div>
@endif

{{-- Bannière info --}}
<div class="mb-4 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
    <i class="fas fa-info-circle"></i>
    Les documents dans la corbeille sont conservés jusqu'à votre confirmation de suppression définitive.
    Vous pouvez les restaurer à tout moment.
</div>

{{-- Barre de sélection --}}
@if($trashCount > 0)
<div id="selectionBar" class="mb-3 bg-blue-50 border border-gray-200 rounded-2xl px-4 py-2.5 flex-wrap items-center justify-between gap-3" style="display:none">
    <span id="selectionCount" class="text-xs font-semibold text-gray-700" aria-live="polite"></span>
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" data-bulk-action onclick="bulkRestore()" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 flex items-center gap-1.5">
            <i class="fas fa-undo-alt"></i> Restaurer
        </button>
        <button type="button" data-bulk-action onclick="confirmBulkForceDelete()" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-600 text-white hover:bg-red-700 flex items-center gap-1.5">
            <i class="fas fa-trash-alt"></i> Supprimer définitivement
        </button>
        <button type="button" onclick="toggleSelectAll(false)" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
            Tout désélectionner
        </button>
    </div>
</div>
@endif

{{-- Contenu --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

    @if($trashCount === 0)
    <div class="p-16 text-center text-gray-400">
        <i class="fas fa-trash text-5xl mb-4 block text-gray-200"></i>
        <p class="text-base font-medium text-gray-500">La corbeille est vide</p>
        <p class="text-sm mt-1">Les documents supprimés apparaîtront ici.</p>
        <a href="{{ route('documents.index') }}" class="mt-4 inline-flex items-center gap-2 text-[#2453d6] text-sm font-medium hover:underline">
            <i class="fas fa-arrow-left"></i> Retour à Mes Documents
        </a>
    </div>
    @else

    <table class="w-full text-sm">
        <thead class="border-b bg-gray-50/80">
            <tr>
                <th class="py-3 pl-4 text-left" style="width:2.5rem">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)" autocomplete="off"
                           title="Tout sélectionner" aria-label="Sélectionner tous les documents de la corbeille"
                           class="h-4 w-4 cursor-pointer align-middle" style="accent-color:#2453d6">
                </th>
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Nom</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Type</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Taille</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Supprimé le</th>
                <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody id="trashTableBody">
            @foreach($documents as $doc)
            @php
                $ext = strtolower(pathinfo($doc->file_path ?? '', PATHINFO_EXTENSION));
                $iconMap = [
                    'pdf'  => ['fas fa-file-pdf',         'text-red-500'],
                    'doc'  => ['fas fa-file-word',         'text-blue-600'],
                    'docx' => ['fas fa-file-word',         'text-blue-600'],
                    'xls'  => ['fas fa-file-excel',        'text-green-600'],
                    'xlsx' => ['fas fa-file-excel',        'text-green-600'],
                    'ppt'  => ['fas fa-file-powerpoint',   'text-orange-500'],
                    'pptx' => ['fas fa-file-powerpoint',   'text-orange-500'],
                    'zip'  => ['fas fa-file-archive',      'text-yellow-600'],
                    'csv'  => ['fas fa-file-csv',          'text-teal-600'],
                ];
                [$icon, $color] = $iconMap[$ext] ?? ['fas fa-file', 'text-gray-400'];
                $size = $doc->file_size
                    ? ($doc->file_size >= 1048576
                        ? round($doc->file_size / 1048576, 1) . ' Mo'
                        : round($doc->file_size / 1024, 0) . ' Ko')
                    : '—';
            @endphp
            <tr class="border-b border-gray-50 hover:bg-gray-50/60 transition-colors" id="row-{{ $doc->id }}">
                <td class="py-3 pl-4">
                    <input type="checkbox" data-select-id="{{ $doc->id }}" onchange="updateSelectionUi()" autocomplete="off"
                           aria-label="Sélectionner {{ $doc->title }}"
                           class="h-4 w-4 cursor-pointer align-middle" style="accent-color:#2453d6">
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                        <i class="{{ $icon }} {{ $color }} text-lg w-5 flex-shrink-0"></i>
                        <span class="font-medium text-gray-800 truncate max-w-xs" title="{{ $doc->title }}">
                            {{ $doc->title }}
                        </span>
                    </div>
                </td>
                <td class="py-3 px-4 text-gray-500 uppercase text-xs font-mono">{{ $ext ?: '—' }}</td>
                <td class="py-3 px-4 text-gray-500 text-xs">{{ $size }}</td>
                <td class="py-3 px-4 text-gray-500 text-xs">
                    {{ $doc->deleted_at?->format('d/m/Y à H:i') }}
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center justify-end gap-2">
                        {{-- Restaurer --}}
                        <button onclick="restoreDocument('{{ $doc->id }}', '{{ addslashes($doc->title) }}')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors">
                            <i class="fas fa-undo-alt"></i> Restaurer
                        </button>
                        {{-- Supprimer définitivement --}}
                        <button onclick="confirmForceDelete('{{ $doc->id }}', '{{ addslashes($doc->title) }}')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-600 hover:bg-red-100 transition-colors">
                            <i class="fas fa-trash-alt"></i> Supprimer
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div data-trash-count class="px-4 py-3 border-t border-gray-100 text-xs text-gray-400">
        {{ $trashCount }} document{{ $trashCount > 1 ? 's' : '' }} dans la corbeille
    </div>
    @endif
</div>

{{-- Modal confirmation suppression définitive --}}
<div id="forceDeleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trash-alt text-red-600 text-lg"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-base">Suppression définitive</h3>
                <p class="text-xs text-gray-500">Cette action est irréversible</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-5">
            Voulez-vous supprimer définitivement <strong id="forceDeleteDocName" class="text-gray-900"></strong> ?
            Le contenu sera effacé et ne pourra plus être récupéré.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeForceDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                Annuler
            </button>
            <button id="forceDeleteConfirmBtn" onclick="executeForceDelete()"
                    class="px-4 py-2 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center gap-2">
                <i class="fas fa-trash-alt"></i> Supprimer définitivement
            </button>
        </div>
    </div>
</div>

{{-- Modal confirmation vider corbeille --}}
<div id="emptyTrashModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trash text-red-600 text-lg"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-base">Vider la corbeille</h3>
                <p class="text-xs text-gray-500">{{ $trashCount }} document{{ $trashCount > 1 ? 's' : '' }} seront supprimés</p>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-5">
            Tous les <strong>{{ $trashCount }} document{{ $trashCount > 1 ? 's' : '' }}</strong> de la corbeille seront
            supprimés définitivement. Cette action est irréversible.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeEmptyTrashModal()" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                Annuler
            </button>
            <button onclick="executeEmptyTrash()"
                    class="px-4 py-2 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center gap-2" id="emptyTrashBtn">
                <i class="fas fa-trash"></i> Vider la corbeille
            </button>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let pendingForceDeleteId = null;
let pendingBulkIds = null;

function restoreDocument(id, name) {
    const row = document.getElementById('row-' + id);
    if (row) row.style.opacity = '0.5';

    fetch(`/documents/${id}/restore`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            if (row) row.remove();
            updateCount(-1);
            showFlash('success', `« ${name} » a été restauré.`);
        }
    })
    .catch(() => {
        if (row) row.style.opacity = '1';
        showFlash('error', 'Erreur lors de la restauration.');
    });
}

function confirmForceDelete(id, name) {
    pendingBulkIds = null;
    pendingForceDeleteId = id;
    document.getElementById('forceDeleteDocName').textContent = name;
    document.getElementById('forceDeleteModal').classList.remove('hidden');
}

function closeForceDeleteModal() {
    pendingForceDeleteId = null;
    pendingBulkIds = null;
    document.getElementById('forceDeleteModal').classList.add('hidden');
}

function executeForceDelete() {
    if (pendingBulkIds) { executeBulkForceDelete(); return; }
    if (!pendingForceDeleteId) return;
    const id = pendingForceDeleteId;
    const btn = document.getElementById('forceDeleteConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression…';

    fetch(`/documents/${id}/force-delete`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        closeForceDeleteModal();
        if (data.ok) {
            const row = document.getElementById('row-' + id);
            if (row) row.remove();
            updateCount(-1);
            showFlash('success', 'Document supprimé définitivement.');
        }
    })
    .catch(() => {
        closeForceDeleteModal();
        showFlash('error', 'Erreur lors de la suppression.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash-alt"></i> Supprimer définitivement';
    });
}

function confirmEmptyTrash() {
    document.getElementById('emptyTrashModal').classList.remove('hidden');
}

function closeEmptyTrashModal() {
    document.getElementById('emptyTrashModal').classList.add('hidden');
}

function executeEmptyTrash() {
    const btn = document.getElementById('emptyTrashBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression…';

    const rows = [...document.querySelectorAll('#trashTableBody tr[id^="row-"]')];
    const ids  = rows.map(r => r.id.replace('row-', ''));

    Promise.all(ids.map(id =>
        fetch(`/documents/${id}/force-delete`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        }).then(r => r.json()).catch(() => ({ ok: false }))
    )).then(results => {
        closeEmptyTrashModal();
        results.forEach((data, i) => {
            if (data.ok) {
                const row = document.getElementById('row-' + ids[i]);
                if (row) row.remove();
            }
        });
        updateCount(0, true);
        updateSelectionUi();
        showFlash('success', 'Corbeille vidée avec succès.');
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Vider la corbeille';
    });
}

function updateCount(delta, forceZero = false) {
    const footer = document.querySelector('[data-trash-count]');
    const rows = document.querySelectorAll('#trashTableBody tr[id^="row-"]');
    const count = forceZero ? 0 : rows.length;

    if (footer) footer.textContent = `${count} document${count > 1 ? 's' : ''} dans la corbeille`;
    updateSelectionUi();

    if (count === 0) {
        const table = document.querySelector('table');
        if (table) {
            table.closest('.bg-white').innerHTML =
                `<div class="p-16 text-center text-gray-400">
                    <i class="fas fa-trash text-5xl mb-4 block text-gray-200"></i>
                    <p class="text-base font-medium text-gray-500">La corbeille est vide</p>
                    <a href="{{ route('documents.index') }}" class="mt-4 inline-flex items-center gap-2 text-[#2453d6] text-sm font-medium hover:underline">
                        <i class="fas fa-arrow-left"></i> Retour à Mes Documents
                    </a>
                </div>`;
        }
        const emptyBtn = document.querySelector('button[onclick="confirmEmptyTrash()"]');
        if (emptyBtn) emptyBtn.remove();
    }
}

// ---- Sélection et actions groupées ----
const selectionBoxes = () => [...document.querySelectorAll('input[data-select-id]')];
const selectedIds = () => selectionBoxes().filter(b => b.checked).map(b => b.dataset.selectId);
const plural = (n, word) => `${n} ${word}${n > 1 ? 's' : ''}`;

function toggleSelectAll(checked) {
    selectionBoxes().forEach(box => box.checked = checked);
    updateSelectionUi();
}

function updateSelectionUi() {
    const bar = document.getElementById('selectionBar');
    if (!bar) return;
    const boxes = selectionBoxes();
    const count = selectedIds().length;
    boxes.forEach(box => box.closest('tr').classList.toggle('bg-blue-50', box.checked));
    bar.style.display = count ? 'flex' : 'none';
    document.getElementById('selectionCount').textContent =
        count > 1 ? `${count} documents sélectionnés` : `${count} document sélectionné`;
    const all = document.getElementById('selectAll');
    if (all) {
        all.checked = count > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    }
}

let bulkBusy = false;
async function runBulk(url, ids, done) {
    if (bulkBusy || !ids.length) return;
    bulkBusy = true;
    const buttons = document.querySelectorAll('[data-bulk-action]');
    buttons.forEach(b => b.disabled = true);
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ ids }),
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.ok) {
            showFlash('error', resp.status === 419 ? 'La page a expiré. Rechargez-la puis réessayez.' : (data.message || 'Opération impossible.'));
            return;
        }
        data.ids.forEach(id => document.getElementById('row-' + id)?.remove());
        updateCount(0);
        done(data);
    } catch (e) {
        showFlash('error', 'Opération impossible.');
    } finally {
        bulkBusy = false;
        buttons.forEach(b => b.disabled = false);
    }
}

function bulkRestore() {
    runBulk(@json(route('documents.bulk.restore')), selectedIds(), data => {
        showFlash('success', `${plural(data.ids.length, 'document')} restauré${data.ids.length > 1 ? 's' : ''}.`);
    });
}

function confirmBulkForceDelete() {
    const ids = selectedIds();
    if (!ids.length || bulkBusy) return;
    pendingForceDeleteId = null;
    pendingBulkIds = ids;
    document.getElementById('forceDeleteDocName').textContent = plural(ids.length, 'document');
    document.getElementById('forceDeleteModal').classList.remove('hidden');
}

function executeBulkForceDelete() {
    const ids = pendingBulkIds;
    closeForceDeleteModal();
    runBulk(@json(route('documents.bulk.forceDelete')), ids, data => {
        const done = `${plural(data.ids.length, 'document')} supprimé${data.ids.length > 1 ? 's' : ''} définitivement.`;
        if (data.failed) showFlash('error', `${done} ${plural(data.failed, 'document')} n'a pas pu être supprimé.`);
        else showFlash('success', done);
    });
}

let flashTimeout;
function showFlash(type, msg) {
    clearTimeout(flashTimeout);
    let el = document.getElementById('flashMsg');
    if (!el) {
        el = document.createElement('div');
        el.id = 'flashMsg';
        document.querySelector('.bg-amber-50').insertAdjacentElement('afterend', el);
    }
    const isSuccess = type === 'success';
    el.className = `mb-4 px-4 py-3 rounded-xl text-sm flex items-center gap-2 ${
        isSuccess ? 'bg-emerald-50 border border-emerald-200 text-emerald-700'
                  : 'bg-red-50 border border-red-200 text-red-700'
    }`;
    el.innerHTML = `<i class="fas fa-${isSuccess ? 'check-circle' : 'exclamation-circle'}"></i> ${msg}`;
    flashTimeout = setTimeout(() => el.remove(), 4000);
}

document.getElementById('forceDeleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeForceDeleteModal();
});
document.getElementById('emptyTrashModal').addEventListener('click', function(e) {
    if (e.target === this) closeEmptyTrashModal();
});
</script>

@endsection
