<?php

declare(strict_types=1);

return [
    // Enable crontab
    'enable' => env('CRONTAB_ENABLE', true),
    
    // Crontab jobs are auto-discovered via #[Crontab] annotation
    // No manual registration needed for Hyperf 3.x
];