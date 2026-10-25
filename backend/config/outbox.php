<?php

return [
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 3),
    'backoff' => [10, 30, 90],
    'lease_seconds' => (int) env('OUTBOX_LEASE_SECONDS', 90),
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),
];
