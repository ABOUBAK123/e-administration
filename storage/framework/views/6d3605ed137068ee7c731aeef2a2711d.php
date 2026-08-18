

<?php $__env->startSection('title', 'Nouveau modèle d’acte'); ?>
<?php $__env->startSection('page-title', 'Nouveau modèle d’acte'); ?>
<?php $__env->startSection('page-subtitle', 'Définir un modèle de document et ses variables'); ?>

<?php $__env->startSection('content'); ?>
    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <form action="<?php echo e(route('act-templates.store')); ?>" method="POST" class="space-y-5">
            <?php echo csrf_field(); ?>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nom du modèle</label>
                    <input id="name" name="name" value="<?php echo e(old('name')); ?>" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100" required>
                    <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="file_type" class="mb-1 block text-sm font-medium text-gray-700">Type de fichier</label>
                    <select id="file_type" name="file_type" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100" required>
                        <?php $__currentLoopData = ['docx','xlsx','pptx','pdf']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($type); ?>" <?php echo e(old('file_type', 'docx') === $type ? 'selected' : ''); ?>><?php echo e(strtoupper($type)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div>
                    <label for="administration_id" class="mb-1 block text-sm font-medium text-gray-700">Administration</label>
                    <select id="administration_id" name="administration_id" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">— Non spécifique —</option>
                        <?php $__currentLoopData = $administrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $administration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($administration->id); ?>" <?php echo e(old('administration_id') == $administration->id ? 'selected' : ''); ?>><?php echo e($administration->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="content" class="mb-1 block text-sm font-medium text-gray-700">Contenu du modèle</label>
                    <textarea id="content" name="content" rows="12" placeholder="Exemple : Bonjour {{ nom }}, ..." class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100"><?php echo e(old('content')); ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Utilisez le format <code><?php echo e('{'); ?>{ variable }}</code> pour déclarer les champs à remplir.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="<?php echo e(route('act-templates.index')); ?>" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Annuler</a>
                <button type="submit" class="rounded-xl bg-[#173b9f] px-4 py-2 text-sm font-semibold text-white hover:opacity-95">Enregistrer</button>
            </div>
        </form>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\e-administration_laravel\resources\views/act-templates/create.blade.php ENDPATH**/ ?>