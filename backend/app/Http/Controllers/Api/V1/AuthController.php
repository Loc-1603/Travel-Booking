<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login: email + password. Returns Sanctum token and user (customer only).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => ['Account is not active.']]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.verify_email.blocked'),
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        if ($user->role !== Role::CUSTOMER) {
            throw ValidationException::withMessages(['email' => ['Only customer accounts can sign in here.']]);
        }

        $user->tokens()->where('name', 'spa')->delete();
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userApiPayload($user),
            ],
        ]);
    }

    /**
     * Register as vendor: name, email, password, optional business info.
     * Creates a PENDING registration — the vendor account is only created once
     * the emailed link is verified. Vendor cannot add hotels until approved.
     */
    public function registerVendor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'business_name' => 'nullable|string|max:255',
            'business_details' => 'nullable|string|max:2000',
        ]);

        $pending = $this->createPendingRegistration(
            $validated['name'],
            $validated['email'],
            $validated['password'],
            Role::VENDOR->value,
            $validated['business_name'] ?? null,
            $validated['business_details'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $pending->email,
            ],
        ], 201);
    }

    /**
     * Register: name, email, password.
     * Creates a PENDING registration — the customer account is only created once
     * the emailed link is verified.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $pending = $this->createPendingRegistration(
            $validated['name'],
            $validated['email'],
            $validated['password'],
            Role::CUSTOMER->value,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $pending->email,
            ],
        ], 201);
    }

    /**
     * Logout: revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Resend the email verification link for a pending registration.
     * Always returns success to avoid leaking which emails exist.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $pending = PendingRegistration::query()
            ->where('email', $validated['email'])
            ->active()
            ->latest()
            ->first();

        if ($pending) {
            $this->sendVerificationEmail($pending);
        } else {
            // Existing user waiting to verify a changed email address.
            $user = User::where('email', $validated['email'])
                ->whereNull('email_verified_at')
                ->first();

            if ($user) {
                $user->sendEmailVerificationNotification();
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.verify_email.resend_sent'),
        ]);
    }

    /**
     * Current user (auth:sanctum).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->userApiPayload($request->user())]);
    }

    /**
     * Update current user profile (name, email, optional password, optional avatar).
     * Send JSON as usual, or multipart/form-data to upload/remove a profile photo.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|max:2048',
            'remove_avatar' => 'nullable|boolean',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        }

        $validated = $request->validate($rules);

        if ($request->boolean('remove_avatar') && $user->avatar) {
            if (Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->name = $validated['name'] ?? $user->name;
        $emailChanged = false;
        if (array_key_exists('email', $validated)) {
            $emailChanged = $validated['email'] !== $user->email;
            $user->email = $validated['email'];
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
        }
        if (! empty($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['success' => true, 'data' => $this->userApiPayload($user->fresh())]);
    }

    /**
     * Create a pending registration (no users row yet) and email the verification link.
     *
     * @return PendingRegistration
     */
    protected function createPendingRegistration(
        string $name,
        string $email,
        string $password,
        string $role,
        ?string $businessName = null,
        ?string $businessDetails = null,
    ): PendingRegistration {
        // Clear stale pendings for this address so a re-registration works.
        PendingRegistration::where('email', $email)->delete();

        $pending = PendingRegistration::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'business_name' => $businessName,
            'business_details' => $businessDetails,
            'expires_at' => now()->addHours(24),
        ]);

        $this->sendVerificationEmail($pending);

        return $pending;
    }

    protected function sendVerificationEmail(PendingRegistration $pending): void
    {
        Notification::route('mail', $pending->email)
            ->notify(new VerifyEmailNotification('pending:'.$pending->uuid, $pending->email));
    }

    /**
     * @return array<string, mixed>
     */
    protected function userApiPayload(User $user): array
    {
        $data = [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'avatar_url' => $user->avatarUrl(),
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'email_verified' => $user->hasVerifiedEmail(),
        ];
        if ($user->role === Role::VENDOR) {
            $data['vendor_approved'] = $user->isVendorApproved();
        }

        return $data;
    }
}
