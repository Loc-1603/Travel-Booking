<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifyEmailController extends Controller
{
    /**
     * Handle the emailed signed verification link.
     *
     * id = "pending:{uuid}" → complete the signup (create the user account).
     * id = "user:{uuid}"    → mark an existing user's (new) email as verified.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $id = (string) $request->route('id');
        $hash = (string) $request->route('hash');

        if (str_starts_with($id, 'pending:')) {
            return $this->completePendingRegistration(substr($id, 8), $hash);
        }

        if (str_starts_with($id, 'user:')) {
            return $this->verifyExistingUser(substr($id, 5), $hash);
        }

        abort(403);
    }

    protected function completePendingRegistration(string $uuid, string $hash): RedirectResponse
    {
        $pending = PendingRegistration::where('uuid', $uuid)->first();

        if (! $pending || $pending->isExpired() || ! hash_equals(sha1($pending->email), $hash)) {
            return $this->redirectWithError('invalid');
        }

        // The email may have been taken since the link was sent.
        if (User::where('email', $pending->email)->exists()) {
            $pending->delete();

            return $this->redirectWithError('email_taken');
        }

        // All-or-nothing: if anything fails (e.g. the spatie role is not
        // seeded yet), the pending registration survives so the user can
        // retry the same link once the system is configured correctly.
        $user = DB::transaction(function () use ($pending) {
            $user = User::create([
                'name' => $pending->name,
                'email' => $pending->email,
                'password' => $pending->password,
                'role' => $pending->role,
                'status' => 'active',
            ]);
            $user->markEmailAsVerified();

            $user->assignRole($pending->role);

            if ($pending->role === Role::VENDOR->value) {
                VendorProfile::create([
                    'user_id' => $user->id,
                    'status' => VendorProfile::STATUS_PENDING,
                    'business_name' => $pending->business_name,
                    'business_details' => $pending->business_details,
                ]);
            }

            $pending->delete();

            return $user;
        });

        return $this->redirectVerified($user);
    }

    protected function verifyExistingUser(string $uuid, string $hash): RedirectResponse
    {
        $user = User::where('uuid', $uuid)->first();

        if (! $user || ! hash_equals(sha1($user->email), $hash)) {
            return $this->redirectWithError('invalid');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->redirectVerified($user);
    }

    protected function redirectVerified(User $user): RedirectResponse
    {
        if (in_array($user->role, [Role::VENDOR, Role::GUIDE], true)) {
            return redirect()
                ->route('register.verify', [
                    'portal' => $user->role === Role::GUIDE ? 'guide' : 'vendor',
                    'verified' => 1,
                ])
                ->with('verify_email', $user->email);
        }

        return redirect()->to(config('app.frontend_url').'/verify-email?verified=1');
    }

    protected function redirectWithError(string $error): RedirectResponse
    {
        return redirect()->to(config('app.frontend_url').'/verify-email?error='.$error);
    }
}