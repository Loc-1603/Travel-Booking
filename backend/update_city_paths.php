<?php
require __DIR__.'/bootstrap/app.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\City;
use Illuminate\Support\Facades\DB;

$updated = City::where('image','like','cities/%')->update(['image' => DB::raw("REPLACE(image,'cities/','locations/cities/')")]);
echo "Updated rows: $updated\n";
