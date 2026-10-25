<?php

use Illuminate\Support\Facades\Schema;

test('outbox migration can roll back and recreate', function (): void {
    $migration = require database_path('migrations/2026_10_24_100000_create_outbox_events_table.php');

    $migration->down();
    expect(Schema::hasTable('outbox_events'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('outbox_events'))->toBeTrue();
});
