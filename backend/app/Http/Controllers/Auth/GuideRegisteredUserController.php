<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class GuideRegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register-guide');
    }

    /**
     * Create a pending guide registration and email the verification link.
     * The guide account is only created once the link is verified.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        PendingRegistration::where('email', $validated['email'])->delete();

        $pending = PendingRegistration::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => Role::GUIDE->value,
            'business_name' => null,
            'business_details' => null,
            'expires_at' => now()->addHours(24),
        ]);

        Notification::route('mail', $pending->email)
            ->notify(new VerifyEmailNotification('pending:'.$pending->uuid, $pending->email));

        return Redirect::route('register.verify', ['portal' => 'guide'])
            ->with('verify_email', $pending->email);
    }
}
