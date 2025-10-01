<?php
// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully!\n";
} else {
    echo "OPcache is not enabled.\n";
}

// Clear Laravel caches
echo "\nClearing Laravel caches...\n";
passthru('cd .. && php artisan optimize:clear');
echo "\nDone!";
