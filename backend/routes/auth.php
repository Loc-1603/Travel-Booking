<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login/choose', function () {
        if (auth()->check()) {
            return redirect()->to(auth()->user()->dashboardRoute());
        }

        return view('auth.choose-role');
    })->name('login.choose');

    Route::get('register/vendor', [\App\Http\Controllers\Auth\VendorRegisteredUserController::class, 'create'])
        ->name('register.vendor');

    Route::post('register/vendor', [\App\Http\Controllers\Auth\VendorRegisteredUserController::class, 'store']);

    Route::get('register/guide', [\App\Http\Controllers\Auth\GuideRegisteredUserController::class, 'create'])
        ->name('register.guide');

    Route::post('register/guide', [\App\Http\Controllers\Auth\GuideRegisteredUserController::class, 'store']);

    Route::get('register/verify', [\App\Http\Controllers\Auth\RegisterVerificationController::class, 'show'])
        ->name('register.verify');

    Route::post('register/verify/resend', [\App\Http\Controllers\Auth\RegisterVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('register.verify.resend');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('portal', 'vendor')
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('portal', 'vendor');

    Route::get('login/admin', [AuthenticatedSessionController::class, 'create'])
        ->defaults('portal', 'admin')
        ->name('login.admin');

    Route::post('login/admin', [AuthenticatedSessionController::class, 'store'])
        ->defaults('portal', 'admin');

    Route::get('login/guide', [AuthenticatedSessionController::class, 'create'])
        ->defaults('portal', 'guide')
        ->name('login.guide');

    Route::post('login/guide', [AuthenticatedSessionController::class, 'store'])
        ->defaults('portal', 'guide');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
