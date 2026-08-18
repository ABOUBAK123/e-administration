<?php
    $currentRoute = Route::currentRouteName();
?>
<div class="flex gap-1 mb-5 border-b border-gray-200">
    <a href="<?php echo e(route('meetings.index')); ?>"
       class="px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 transition-colors
              <?php echo e(Str::startsWith($currentRoute, 'meetings.index') || ($currentRoute === 'meetings.show') ? 'bg-white border-gray-200 text-[#2453d6] -mb-px z-10' : 'border-transparent text-gray-500 hover:text-[#2453d6] hover:bg-gray-50'); ?>">
        Réunions
    </a>
    <a href="<?php echo e(route('meetings.calendar')); ?>"
       class="px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 transition-colors
              <?php echo e(Str::startsWith($currentRoute, 'meetings.calendar') ? 'bg-white border-gray-200 text-[#2453d6] -mb-px z-10' : 'border-transparent text-gray-500 hover:text-[#2453d6] hover:bg-gray-50'); ?>">
        Calendrier
    </a>
    <a href="<?php echo e(route('meetings.rooms.index')); ?>"
       class="px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 transition-colors
              <?php echo e(Str::startsWith($currentRoute, 'meetings.rooms') ? 'bg-white border-gray-200 text-[#2453d6] -mb-px z-10' : 'border-transparent text-gray-500 hover:text-[#2453d6] hover:bg-gray-50'); ?>">
        Salles
    </a>
    <a href="<?php echo e(route('meetings.create')); ?>"
       class="px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 transition-colors
              <?php echo e($currentRoute === 'meetings.create' ? 'bg-white border-gray-200 text-[#2453d6] -mb-px z-10' : 'border-transparent text-gray-500 hover:text-[#2453d6] hover:bg-gray-50'); ?>">
        + Nouvelle réunion
    </a>
    <a href="<?php echo e(route('meetings.reporting')); ?>"
       class="px-4 py-2 text-sm font-semibold rounded-t-lg border border-b-0 transition-colors
              <?php echo e(Str::startsWith($currentRoute, 'meetings.reporting') ? 'bg-white border-gray-200 text-[#2453d6] -mb-px z-10' : 'border-transparent text-gray-500 hover:text-[#2453d6] hover:bg-gray-50'); ?>">
        Reporting
    </a>
</div>
<?php /**PATH C:\wamp64\www\e-administration_laravel\resources\views/meetings/_nav.blade.php ENDPATH**/ ?>