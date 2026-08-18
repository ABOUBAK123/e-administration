<?php $__env->startSection('title', 'Réception'); ?>
<?php $__env->startSection('page-title', 'Réception'); ?>
<?php $__env->startSection('page-subtitle', 'Documents reçus depuis d\'autres administrations'); ?>
<?php $__env->startSection('content'); ?>

<?php
    $activeSubtab = $subtab ?? 'inbox';
?>

<div class="mb-4 flex flex-wrap gap-2 bg-white border border-gray-200 rounded-xl p-2 shadow-sm w-fit">
    <a href="<?php echo e(route('reception.index', array_merge(request()->except(['page', 'subtab']), ['subtab' => 'inbox']))); ?>"
        class="px-3 py-2 rounded-lg text-xs font-semibold transition <?php echo e($activeSubtab === 'inbox' ? 'bg-[#2453d6] text-white' : 'text-gray-600 hover:bg-gray-100'); ?>">
        <i class="fas fa-inbox mr-1"></i> Réception
    </a>
    <a href="<?php echo e(route('reception.index', array_merge(request()->except(['page', 'subtab']), ['subtab' => 'archives']))); ?>"
        class="px-3 py-2 rounded-lg text-xs font-semibold transition <?php echo e($activeSubtab === 'archives' ? 'bg-[#2453d6] text-white' : 'text-gray-600 hover:bg-gray-100'); ?>">
        <i class="fas fa-archive mr-1"></i> Archives
    </a>
</div>

<?php if($activeSubtab === 'archives'): ?>
<div class="mb-5 p-3 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-700">
    <i class="fas fa-info-circle mr-1"></i>
    Les documents reçus plus anciens que <strong><?php echo e((int)($receptionArchivalDays ?? 0) > 0 ? (int)$receptionArchivalDays . ' jour(s)' : 'la période configurée'); ?></strong>
    apparaissent ici.
</div>
<?php endif; ?>


<div class="flex items-center gap-4 mb-6">
    <form method="GET" action="<?php echo e(route('reception.index')); ?>" class="flex-1 max-w-md flex gap-2">
        <input type="hidden" name="subtab" value="<?php echo e($activeSubtab); ?>">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="<?php echo e($activeSubtab === 'archives' ? 'Rechercher un document archivé…' : 'Rechercher un document reçu…'); ?>"
                class="w-full border border-gray-300 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6] bg-white">
        </div>
        <button type="submit" class="px-4 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold hover:bg-[#1f47bb] transition">
            Chercher
        </button>
        <?php if($search): ?>
            <a href="<?php echo e(route('reception.index', ['subtab' => $activeSubtab])); ?>" class="px-4 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-100 transition">
                <i class="fas fa-times"></i>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if($documents->isEmpty()): ?>
    <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-5">
            <i class="fas fa-inbox text-4xl text-gray-300"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-1"><?php echo e($activeSubtab === 'archives' ? 'Aucun document archivé' : 'Aucun document reçu'); ?></h3>
        <p class="text-sm text-gray-400 max-w-sm">
            <?php echo e($activeSubtab === 'archives'
                ? 'Les documents reçus archivés apparaîtront ici dès qu\'ils dépassent le délai configuré.'
                : 'Les documents partagés avec vous par d\'autres administrations apparaîtront ici.'); ?>

        </p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <i class="fas <?php echo e($activeSubtab === 'archives' ? 'fa-archive' : 'fa-inbox'); ?> text-[#2453d6]"></i>
                <?php echo e($activeSubtab === 'archives' ? 'Archives de réception' : 'Documents reçus'); ?>

            </h2>
            <span class="text-sm text-gray-500"><?php echo e($documents->total()); ?> document(s)</span>
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
                <?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $shareInfo = $sharesInfo[$doc->id] ?? null; ?>
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-file-alt text-blue-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 truncate max-w-xs"><?php echo e($doc->title); ?></p>
                                <p class="text-xs text-gray-400"><?php echo e($doc->file_size ? number_format($doc->file_size / 1024, 0) . ' KB' : '—'); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-gray-600"><?php echo e($doc->owner?->name ?? '—'); ?></td>
                    <td class="px-5 py-4">
                        <?php if($shareInfo?->applicant_full_name): ?>
                            <div class="font-medium text-gray-800 text-xs"><?php echo e($shareInfo->applicant_full_name); ?></div>
                            <?php if($shareInfo->applicant_email): ?>
                                <div class="text-xs text-gray-400"><?php echo e($shareInfo->applicant_email); ?></div>
                            <?php endif; ?>
                            <?php if($shareInfo->tracking_number): ?>
                                <div class="text-xs text-indigo-500 font-mono"><?php echo e($shareInfo->tracking_number); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-gray-600 text-xs">
                        <?php echo e($shareInfo?->applicant_phone ?? '—'); ?>

                    </td>
                    <td class="px-5 py-4 text-gray-700 text-xs font-mono">
                        <?php echo e($shareInfo?->applicant_rib ?? '—'); ?>

                    </td>
                    <td class="px-5 py-4">
                        <?php
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
                        ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo e($statusClass); ?>">
                            <?php echo e(__('documents.status_' . $doc->status, [], app()->getLocale()) !== 'documents.status_' . $doc->status
                                ? __('documents.status_' . $doc->status)
                                : ucfirst(str_replace('_', ' ', $doc->status))); ?>

                        </span>
                    </td>
                    <td class="px-5 py-4 text-gray-500 text-xs"><?php echo e($doc->created_at?->format('d/m/Y H:i')); ?></td>
                    <?php
                        $recStatus = $receptionStatuses[$doc->id]->reception_status ?? null;
                        $isTransmis = $recStatus === 'transmis';
                        $isRecu     = $recStatus === 'recu';
                        $recLabel   = $recStatus ? __('documents.reception_status_' . $recStatus) : null;
                    ?>
                    <td id="rec-status-cell-<?php echo e($doc->id); ?>" class="px-5 py-4">
                        <?php if($recLabel): ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                            <?php echo e($isTransmis ? 'bg-green-100 text-green-700' : 'bg-sky-100 text-sky-700'); ?>">
                            <i class="fas <?php echo e($isTransmis ? 'fa-share' : 'fa-check'); ?> mr-1 text-[10px]"></i>
                            <?php echo e($recLabel); ?>

                        </span>
                        <?php else: ?>
                        <span class="text-gray-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                        <?php if($doc->file_path): ?>
                        <a href="<?php echo e(route('documents.download', ['document' => $doc, 'from_reception' => 1])); ?>"
                            <?php if($activeSubtab === 'inbox'): ?> onclick="markDocReceived('<?php echo e($doc->id); ?>')" <?php endif; ?>
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition">
                            <i class="fas fa-download text-xs"></i> Télécharger
                        </a>
                        <?php endif; ?>
                        <?php if($activeSubtab === 'inbox' && $subEntities->isNotEmpty()): ?>
                        <button type="button"
                            id="forward-btn-<?php echo e($doc->id); ?>"
                            onclick="openForwardModal('<?php echo e($doc->id); ?>', '<?php echo e(addslashes($doc->title)); ?>')"
                            title="<?php echo e($isTransmis && isset($transmissionInfo[$doc->id]) ? 'Transmis à : ' . $transmissionInfo[$doc->id] : ''); ?>"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition
                                <?php echo e($isTransmis
                                    ? 'bg-green-100 hover:bg-green-200 text-green-700 border border-green-200'
                                    : 'bg-indigo-100 hover:bg-indigo-200 text-indigo-700'); ?>">
                            <i class="fas fa-share text-xs"></i>
                            <?php echo e($isTransmis ? __('documents.reception_status_transmis') : __('buttons.forward')); ?>

                        </button>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
            </div>

        <?php if($documents->hasPages()): ?>
        <div class="px-5 py-4 border-t border-gray-100">
            <?php echo e($documents->links()); ?>

        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>

<?php if($activeSubtab === 'inbox'): ?>
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
                    <?php $__currentLoopData = $subEntities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $se): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($se->code); ?>"><?php echo e($se->name); ?> (<?php echo e($se->code); ?>)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
    recu:     '<?php echo e(__("documents.reception_status_recu")); ?>',
    transmis: '<?php echo e(__("documents.reception_status_transmis")); ?>',
    forward:  '<?php echo e(__("buttons.forward")); ?>',
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
<?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\e-administration_laravel.worktrees\gestion-courrier-notification-email\resources\views/reception/index.blade.php ENDPATH**/ ?>