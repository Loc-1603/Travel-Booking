@extends('layouts.auth')

@php
    $authPageTitle = __('auth.reset_password.title');
    $authCardWidth = '480px';
    $authVariant = 'account';
@endphp

@section('content')
    <div class="auth-heading">
        <span class="auth-status-icon" aria-hidden="true">
            <i class="mdi mdi-lock-reset"></i>
        </span>
        <h1 class="auth-title" id="auth-title">{{ __('auth.reset_password.title') }}</h1>
        <p class="auth-subtitle">{{ __('auth.reset_password.description') }}</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="auth-field">
            <label class="form-label auth-label" for="email">{{ __('auth.reset_password.email') }}</label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   value="{{ old('email', $request->email) }}"
                   autocomplete="username"
                   required
                   autofocus>
            @error('email')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="form-label auth-label" for="password">{{ __('auth.reset_password.password') }}</label>
            <div class="input-group auth-pass-inputgroup">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       autocomplete="new-password"
                       required>
                <button class="btn auth-pass-toggle"
                        type="button"
                        data-password-toggle
                        aria-controls="password"
                        aria-pressed="false"
                        aria-label="{{ __('auth.login.show_password') }}"
                        data-show-label="{{ __('auth.login.show_password') }}"
                        data-hide-label="{{ __('auth.login.hide_password') }}">
                    <i class="mdi mdi-eye-outline" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="form-label auth-label" for="password_confirmation">{{ __('auth.reset_password.confirm_password') }}</label>
            <div class="input-group auth-pass-inputgroup">
                <input type="password"
                       class="form-control @error('password_confirmation') is-invalid @enderror"
                       id="password_confirmation"
                       name="password_confirmation"
                       autocomplete="new-password"
                       required>
                <button class="btn auth-pass-toggle"
                        type="button"
                        data-password-toggle
                        aria-controls="password_confirmation"
                        aria-pressed="false"
                        aria-label="{{ __('auth.login.show_password') }}"
                        data-show-label="{{ __('auth.login.show_password') }}"
                        data-hide-label="{{ __('auth.login.hide_password') }}">
                    <i class="mdi mdi-eye-outline" aria-hidden="true"></i>
                </button>
            </div>
            @error('password_confirmation')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 auth-submit">{{ __('auth.reset_password.reset_password') }}</button>
    </form>

    <div class="auth-footnote">
        <a href="{{ route('login') }}" class="auth-back-link">
            <i class="mdi mdi-arrow-left" aria-hidden="true"></i>
            {{ __('auth.forgot_password.back_to_login') }}
        </a>
    </div>
@endsection
