<?php $__env->startSection('title', __('auth.login')); ?>
<?php $__env->startSection('content'); ?>
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold text-gray-800"><?php echo e(__('auth.login')); ?></h2>
    <form method="POST" action="<?php echo e(route('lang.switch', app()->getLocale())); ?>" id="lang-form-login">
        <?php echo csrf_field(); ?>
        <select onchange="document.getElementById('lang-form-login').action='<?php echo e(url('/lang')); ?>/'+this.value; document.getElementById('lang-form-login').submit();"
                class="text-sm border border-gray-300 rounded-lg px-2 py-1.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white cursor-pointer">
            <option value="fr" <?php echo e(app()->getLocale() === 'fr' ? 'selected' : ''); ?>>🇫🇷 FR</option>
            <option value="en" <?php echo e(app()->getLocale() === 'en' ? 'selected' : ''); ?>>🇬🇧 EN</option>
            <option value="es" <?php echo e(app()->getLocale() === 'es' ? 'selected' : ''); ?>>🇪🇸 ES</option>
            <option value="pt" <?php echo e(app()->getLocale() === 'pt' ? 'selected' : ''); ?>>🇵🇹 PT</option>
            <option value="ar" <?php echo e(app()->getLocale() === 'ar' ? 'selected' : ''); ?>>🇸🇦 AR</option>
        </select>
    </form>
</div>

<?php if($errors->any()): ?>
<div class="mb-4 bg-red-50 border border-red-200 text-red-600 rounded-lg p-3 text-sm">
    <?php echo e($errors->first()); ?>

</div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('login')); ?>" class="space-y-4">
    <?php echo csrf_field(); ?>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('auth.email')); ?></label>
        <input type="email" name="email" value="<?php echo e(old('email')); ?>" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('auth.password')); ?></label>
        <input type="password" name="password" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded"> <?php echo e(__('auth.remember_me')); ?>

        </label>
        <a href="<?php echo e(route('password.request')); ?>" class="text-sm text-indigo-600 hover:underline"><?php echo e(__('auth.forgot_password')); ?></a>
    </div>
    <button type="submit"
            class="w-full bg-indigo-600 text-white py-2.5 rounded-lg font-medium hover:bg-indigo-700 transition">
        <?php echo e(__('auth.login_button')); ?>

    </button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\e-administration_laravel.worktrees\gestion-courrier-notification-email\resources\views/auth/login.blade.php ENDPATH**/ ?>