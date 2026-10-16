<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class RegisterVerificationController extends Controller
{
    /**
     * Seconds the resend button stays disabled (countdown) after page load.
     */
    private const RESEND_COOLDOWN_SECONDS = 15;

    /**
     * "Verify your email to continue" notice shown right after a vendor or
     * guide registration. The continue button stays dimmed until the emailed
     * link sends the user back here with ?verified=1.
     */
    public function show(Request $request): View
    {
        $portal = $request->query('portal') === 'guide' ? 'guide' : 'vendor';

        return view('auth.register-verify', [
            'portal' => $portal,
            'verified' => $request->boolean('verified'),
            'email' => $request->session()->get('verify_email'),
            'loginRoute' => $portal === 'guide' ? 'login.guide' : 'login',
            'resendCooldown' => self::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    /**
     * Resend the verification email for a pending registration (or an existing
     * user waiting to verify a changed email). Always reports success so the
     * response does not leak which addresses exist.
     */
    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $portal = $request->input('portal') === 'guide' ? 'guide' : 'vendor';

        $pending = PendingRegistration::query()
            ->where('email', $validated['email'])
            ->active()
            ->latest()
            ->first();

        if ($pending) {
            Notification::route('mail', $pending->email)
                ->notify(new VerifyEmailNotification('pending:'.$pending->uuid, $pending->email));
        } else {
            $user = User::where('email', $validated['email'])
                ->whereNull('email_verified_at')
                ->first();

            if ($user) {
                $user->sendEmailVerificationNotification();
            }
        }

        return Redirect::route('register.verify', ['portal' => $portal])
            ->with('verify_email', $validated['email'])
            ->with('resent', true);
    }
}
