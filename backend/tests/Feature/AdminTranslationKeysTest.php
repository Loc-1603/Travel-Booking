<?php

/*
 * Regression tests for: TypeError htmlspecialchars(): Argument #1 ($string)
 * must be of type string, array given on /admin/dashboard and
 * /admin/vendor/dashboard.
 *
 * Root cause was duplicate translation keys in lang/{en,vi}/admin.php where a
 * later array definition silently overwrote an earlier string (PHP arrays
 * keep the last value for a duplicated key), e.g.:
 *   'vendor' => 'Vendor'      (string, shadowed)
 *   'vendor' => [...]         (array wins -> __('admin.vendor') returns array)
 * Rendering such a key with {{ ... }} (e() -> htmlspecialchars) throws.
 */

function adminLangKeyKinds(string $locale): array
{
    $path = base_path("lang/{$locale}/admin.php");
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    // Stack of [indent, key]; path => ['scalar' => bool, 'array' => bool].
    $kinds = [];
    $stack = [];

    foreach ($lines as $line) {
        if (! preg_match("/^(\\s*)'([A-Za-z_]+)'\\s*=>\\s*(\\[?)/", $line, $m)) {
            continue;
        }

        $indent = strlen($m[1]);
        $key = $m[2];
        $isArray = $m[3] === '[';

        while ($stack !== [] && $stack[count($stack) - 1][0] >= $indent) {
            array_pop($stack);
        }

        $fullPath = implode('.', [...array_column($stack, 1), $key]);
        $kinds[$fullPath][$isArray ? 'array' : 'scalar'] = true;

        if ($isArray) {
            $stack[] = [$indent, $key];
        }
    }

    return $kinds;
}

it('has no translation key shadowed by a different value kind (string vs array)', function (string $locale) {
    $kinds = adminLangKeyKinds($locale);

    $mixed = array_keys(array_filter($kinds, fn (array $k) => isset($k['scalar']) && isset($k['array'])));

    expect($mixed)->toBeEmpty();
})->with(['en', 'vi']);

it('resolves every bare single-segment translation echo in admin blades to a non-array value', function (string $locale) {
    app()->setLocale($locale);

    $bladeFiles = array_merge(
        glob(resource_path('views/admin/**/*.blade.php')),
        glob(resource_path('views/components/*.blade.php')),
    );

    $failures = [];

    foreach ($bladeFiles as $file) {
        $content = file_get_contents($file);

        // Bare keys only: {{ __('admin.foo') }} or {{ __('admin.vendor.bar') }}
        // (nested paths with further dots are arrays by design and must use @json).
        preg_match_all("/__\\(\\s*'(admin\\.[A-Za-z_]+|admin\\.vendor\\.[A-Za-z_]+)'\\s*[,)]/", $content, $matches);

        foreach (array_unique($matches[1]) as $key) {
            if (is_array(__($key))) {
                $failures[] = "{$file}: {$key}";
            }
        }
    }

    expect($failures)->toBeEmpty();
})->with(['en', 'vi']);

it('resolves the previously crashing dashboard keys to strings', function (string $locale) {
    app()->setLocale($locale);

    $keys = [
        // /admin/dashboard crash (compiled 9e2bb0...:155) + latent second crash (:300).
        'admin.vendor_label',
        'admin.actions_label',
        // /admin/vendor/dashboard crash (compiled f47c60...:109).
        'admin.vendor.commission_label',
        // Vendor rooms pages (same crash class via rooms.availability array).
        'admin.vendor.rooms.availability_label',
        // Adjacent wrong-key labels fixed in the same change.
        'admin.suspend',
        'admin.activate',
        'admin.sidebar.menu',
        'admin.vendor.my_hotels',
        'admin.vendor.my_rooms',
        'admin.chart_labels.revenue',
        'admin.chart_labels.bookings',
        'admin.chart_labels.vendors_revenue',
    ];

    foreach ($keys as $key) {
        expect(__($key))->toBeString("{$locale}: {$key}");
    }
})->with(['en', 'vi']);
