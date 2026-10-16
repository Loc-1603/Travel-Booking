<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Hotel;
use App\Models\TourProvider;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class SplitStaffRoles extends Command
{
    protected $signature = 'staff:split-roles {--dry-run : Report the planned changes without writing to the database}';

    protected $description = 'Split vendor accounts that own tour providers into hotel vendors and tour guides (strict portal split)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (['vendor', 'guide'] as $roleName) {
            if (! SpatieRole::where('name', $roleName)->where('guard_name', 'web')->exists()) {
                $this->error("Spatie role [{$roleName}] is missing. Run: php artisan db:seed --class=RoleAndPermissionSeeder");

                return self::FAILURE;
            }
        }

        $hotelCounts = Hotel::query()
            ->selectRaw('vendor_id, count(*) as aggregate')
            ->groupBy('vendor_id')
            ->pluck('aggregate', 'vendor_id');
        $providerCounts = TourProvider::query()
            ->selectRaw('vendor_id, count(*) as aggregate')
            ->groupBy('vendor_id')
            ->pluck('aggregate', 'vendor_id');

        $plan = [];
        $rows = [];

        foreach (User::where('role', Role::VENDOR->value)->orderBy('email')->get() as $vendor) {
            $hotels = (int) ($hotelCounts[$vendor->id] ?? 0);
            $providers = (int) ($providerCounts[$vendor->id] ?? 0);

            if ($providers === 0) {
                $rows[] = [$vendor->email, $hotels, 0, 'keep vendor'];

                continue;
            }

            if ($hotels === 0) {
                $rows[] = [$vendor->email, 0, $providers, 'promote to guide'];
                $plan[] = ['action' => 'promote', 'user' => $vendor, 'providers' => $providers];

                continue;
            }

            $companionEmail = $this->companionEmail($vendor->email);
            $rows[] = [$vendor->email, $hotels, $providers, "companion guide {$companionEmail}"];
            $plan[] = ['action' => 'companion', 'user' => $vendor, 'providers' => $providers, 'companion_email' => $companionEmail];
        }

        $this->info($dryRun ? '[DRY RUN] Planned staff role split:' : 'Applying staff role split...');
        $this->table(['Email', 'Hotels', 'Tour providers', 'Action'], $rows);

        if ($dryRun) {
            $this->line('No database changes were made.');

            return self::SUCCESS;
        }

        $promoted = 0;
        $companions = 0;
        $repointed = 0;

        DB::transaction(function () use ($plan, &$promoted, &$companions, &$repointed): void {
            foreach ($plan as $item) {
                $vendor = $item['user'];

                if ($item['action'] === 'promote') {
                    $vendor->update(['role' => Role::GUIDE]);
                    if (! $vendor->hasRole('guide')) {
                        $vendor->assignRole('guide');
                    }
                    if ($vendor->hasRole('vendor')) {
                        $vendor->removeRole('vendor');
                    }
                    $promoted++;
                    Log::info("staff:split-roles: promoted {$vendor->email} (vendor -> guide), {$item['providers']} tour provider(s) kept");
                } else {
                    $companion = User::create([
                        'name' => $vendor->name,
                        'email' => $item['companion_email'],
                        'password' => Str::random(40),
                        'role' => Role::GUIDE,
                        'status' => 'active',
                    ]);
                    DB::table('users')->where('id', $companion->id)->update([
                        'password' => $vendor->getRawOriginal('password'),
                        'email_verified_at' => now(),
                    ]);
                    $companion->assignRole('guide');

                    $moved = TourProvider::where('vendor_id', $vendor->id)->update(['vendor_id' => $companion->id]);
                    $repointed += $moved;
                    $companions++;
                    Log::info("staff:split-roles: {$vendor->email} kept as vendor; companion guide {$item['companion_email']} created; {$moved} tour provider(s) re-pointed");
                }
            }
        });

        $this->info("Done. Promoted: {$promoted}; companion guides: {$companions}; tour providers re-pointed: {$repointed}.");
        Log::info("staff:split-roles: completed - promoted={$promoted}, companions={$companions}, repointed={$repointed}");

        return self::SUCCESS;
    }

    private function companionEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, 'example.com');

        $candidate = $local.'+guide@'.$domain;

        while (User::where('email', $candidate)->exists()) {
            $candidate = $local.'+guide'.Str::lower(Str::random(6)).'@'.$domain;
        }

        return $candidate;
    }
}
