<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Connexion'); ?> — E-Administration</title>
    <?php
        $useVite = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    ?>
    <?php if($useVite): ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php else: ?>
        <script src="<?php echo e(asset('vendor/tailwind/tailwind.js')); ?>"></script>
    <?php endif; ?>
    <?php
        $authMenuColor = '#173b9f';
        $authFavicon = null;
        $authHeaderLogo = null;
        $authAppName = 'E-Administration';
    ?>

    
    <?php if($authFavicon): ?>
        <link rel="icon" type="image/png" href="<?php echo e($authFavicon); ?>">
    <?php elseif($authHeaderLogo): ?>
        <link rel="icon" type="image/png" href="<?php echo e($authHeaderLogo); ?>">
    <?php else: ?>
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23173b9f'/%3E%3Ctext x='16' y='23' text-anchor='middle' font-family='Arial' font-weight='900' font-size='20' fill='white'%3EE%3C/text%3E%3C/svg%3E">
    <?php endif; ?>

    <style>
        :root { --auth-color: <?php echo e($authMenuColor); ?>; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, <?php echo e($authMenuColor); ?> 0%, #0d2566 100%);
        }
        .auth-wrapper { position: relative; z-index: 1; }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="auth-wrapper w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg overflow-hidden">
                <?php if($authHeaderLogo): ?>
                    <img src="<?php echo e($authHeaderLogo); ?>" alt="<?php echo e($authAppName); ?>" class="w-full h-full object-contain p-1">
                <?php else: ?>
                    <svg class="w-8 h-8" fill="currentColor" style="color:<?php echo e($authMenuColor); ?>" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl font-bold text-white"><?php echo e($authAppName); ?></h1>
            <p class="text-white/70 text-sm">Connect &amp; Sign</p>
        </div>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <?php echo $__env->yieldContent('content'); ?>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\wamp64\www\e-administration_laravel.worktrees\gestion-courrier-notification-email\resources\views/layouts/auth.blade.php ENDPATH**/ ?>