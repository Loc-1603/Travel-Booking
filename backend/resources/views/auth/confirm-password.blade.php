@extends('layouts.auth')

@php
    $authPageTitle = __('auth.confirm_password.title');
    $authCardWidth = '480px';
    $authVariant = 'account';
@endphp

@section('content')
    <div class="auth-heading">
        <span class="auth-status-icon" aria-hidden="true">
            <i class="mdi mdi-shield-lock-outline"></i>
        </span>
        <h1 class="auth-title" id="auth-title">{{ __('auth.confirm_password.title') }}</h1>
        <p class="auth-subtitle">{{ __('auth.confirm_password.secure_area') }}</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="auth-field">
            <label class="form-label auth-label" for="password">{{ __('auth.confirm_password.password') }}</label>
            <div class="input-group auth-pass-inputgroup">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       autocomplete="current-password"
                       required
                       autofocus>
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

        <button type="submit" class="btn btn-primary w-100 auth-submit">{{ __('auth.confirm_password.confirm') }}</button>
    </form>
@endsection
