<?php

$log = __DIR__ . '/storage/logs/laravel.log';

if (! file_exists($log)) {
    touch($log);
}

$offset = filesize($log);

while (true) {
    $size = filesize($log);

    if ($size < $offset) {
        // Log đã bị rotate/truncate.
        $offset = 0;
    }

    if ($size > $offset) {
        $fh = fopen($log, 'r');
        fseek($fh, $offset);
        fpassthru($fh);
        fclose($fh);
        $offset = $size;
    }

    usleep(250000);
}