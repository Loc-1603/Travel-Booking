<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\City;

$updated = DB::table('cities')
    ->where('image','like','cities/%')
    ->update(['image' => DB::raw("REPLACE(image, 'cities/', 'locations/cities/')")]);

echo "Updated rows: $updated\n";
echo "Total cities with image: ".City::whereNotNull('image')->count()."\n";
