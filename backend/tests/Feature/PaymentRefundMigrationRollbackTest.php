<?php

use Illuminate\Support\Facades\Schema;

test('payment refunds migration can roll back and recreate', function (): void {
    $migration = require database_path('migrations/2026_10_25_100000_create_payment_refunds_table.php');

    $migration->down();
    expect(Schema::hasTable('payment_refunds'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('payment_refunds'))->toBeTrue();
});
