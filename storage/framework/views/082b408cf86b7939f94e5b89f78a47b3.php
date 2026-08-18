<?php $__env->startSection('title', __('navigation.dashboard')); ?>
<?php $__env->startSection('page-title', __('navigation.dashboard')); ?>
<?php $__env->startSection('content'); ?>

<?php
    $user = auth()->user();
    $docCount   = \App\Models\Document::where('owner_id', $user->id)->count();
    $wfCount    = \App\Models\Workflow::where('created_by', $user->id)->count();
    $sigCount   = \App\Models\Signature::where('signer_id', $user->id)->count();
    $pendingSig = \App\Models\SignatureRequest::where('requested_to', $user->id)->where('status', 'pending')->count();
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 font-medium"><?php echo e(__('messages.my_documents')); ?></span>
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-blue-600"></i>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-800"><?php echo e($docCount); ?></div>
        <a href="<?php echo e(route('documents.index')); ?>" class="text-xs text-blue-600 hover:underline mt-1 block"><?php echo e(__('messages.see_all')); ?> →</a>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 font-medium"><?php echo e(__('messages.workflows')); ?></span>
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-project-diagram text-purple-600"></i>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-800"><?php echo e($wfCount); ?></div>
        <a href="<?php echo e(route('workflows.index')); ?>" class="text-xs text-purple-600 hover:underline mt-1 block"><?php echo e(__('messages.see_all')); ?> →</a>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 font-medium"><?php echo e(__('messages.signatures')); ?></span>
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-pen-nib text-green-600"></i>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-800"><?php echo e($sigCount); ?></div>
        <a href="<?php echo e(route('signatures.index')); ?>" class="text-xs text-green-600 hover:underline mt-1 block"><?php echo e(__('messages.see_all')); ?> →</a>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 font-medium"><?php echo e(__('messages.pending_signatures')); ?></span>
            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-orange-600"></i>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-800"><?php echo e($pendingSig); ?></div>
        <a href="<?php echo e(route('signatures.index')); ?>" class="text-xs text-orange-600 hover:underline mt-1 block"><?php echo e(__('messages.process')); ?> →</a>
    </div>
</div>

<!-- Recent Documents -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800"><?php echo e(__('messages.recent_documents')); ?></h2>
            <a href="<?php echo e(route('documents.create')); ?>" class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus mr-1"></i> <?php echo e(__('messages.new')); ?>

            </a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php $__empty_1 = true; $__currentLoopData = \App\Models\Document::where('owner_id', $user->id)->latest()->take(5)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="flex items-center gap-3 p-4">
                <div class="w-9 h-9 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-file-pdf text-red-500 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate"><?php echo e($doc->title); ?></p>
                    <p class="text-xs text-gray-400"><?php echo e($doc->created_at->diffForHumans()); ?></p>
                </div>
                <?php
                    $dClass = match($doc->status) {
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
                        default             => 'bg-yellow-100 text-yellow-700',
                    };
                    $dKey = 'documents.status_' . $doc->status;
                    $dLabel = __($dKey) !== $dKey ? __($dKey) : ucfirst(str_replace('_', ' ', $doc->status));
                ?>
                <span class="text-xs px-2 py-1 rounded-full <?php echo e($dClass); ?>"><?php echo e($dLabel); ?></span>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="p-8 text-center text-gray-400 text-sm">
                <i class="fas fa-folder-open text-3xl mb-2 block"></i>
                <?php echo e(__('messages.no_documents')); ?>

            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications récentes -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800"><?php echo e(__('messages.recent_notifications')); ?></h2>
            <a href="<?php echo e(route('notifications.index')); ?>" class="text-sm text-indigo-600 hover:underline"><?php echo e(__('messages.see_all')); ?></a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php $__empty_1 = true; $__currentLoopData = \App\Models\Notification::where('recipient_id', $user->id)->latest('created_at')->take(5)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notif): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="flex items-start gap-3 p-4 <?php echo e(!$notif->is_read ? 'bg-indigo-50' : ''); ?>">
                <div class="w-2 h-2 rounded-full mt-2 flex-shrink-0 <?php echo e(!$notif->is_read ? 'bg-indigo-500' : 'bg-gray-300'); ?>"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800"><?php echo e($notif->title); ?></p>
                    <p class="text-xs text-gray-500 mt-0.5"><?php echo e(Str::limit($notif->message, 60)); ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?php echo e($notif->created_at->diffForHumans()); ?></p>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="p-8 text-center text-gray-400 text-sm">
                <i class="fas fa-bell-slash text-3xl mb-2 block"></i>
                <?php echo e(__('messages.no_notifications')); ?>

            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\e-administration_laravel.worktrees\gestion-courrier-notification-email\resources\views/dashboard.blade.php ENDPATH**/ ?>