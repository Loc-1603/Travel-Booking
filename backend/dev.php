<?php

$logCmd = PHP_OS_FAMILY === 'Windows'
    ? 'php tail-logs.php'
    : 'php artisan pail --timeout=0';

$cmd = 'npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" '
    . '"php artisan serve" '
    . '"php artisan queue:listen --tries=1" '
    . '"' . $logCmd . '" '
    . '"npm run dev" '
    . '--names=server,queue,logs,vite --kill-others';

passthru($cmd, $code);
exit($code);