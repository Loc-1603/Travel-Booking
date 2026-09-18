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

class VendorRegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register-vendor');
    }

    /**
     * Create a pending registration and email the verification link.
     * The vendor account is only created once the link is verified.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_details' => ['nullable', 'string', 'max:2000'],
        ]);

        PendingRegistration::where('email', $validated['email'])->delete();

        $pending = PendingRegistration::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => Role::VENDOR->value,
            'business_name' => $validated['business_name'] ?? null,
            'business_details' => $validated['business_details'] ?? null,
            'expires_at' => now()->addHours(24),
        ]);

        Notification::route('mail', $pending->email)
            ->notify(new VerifyEmailNotification('pending:'.$pending->uuid, $pending->email));

        return Redirect::route('login')
            ->with('status', 'verification-sent')
            ->with('info', __('auth.registration.check_email'));
    }
}