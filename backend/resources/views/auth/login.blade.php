@extends('layouts.auth')

@php
    $portal = $portal ?? 'vendor';
    $loginAction = $portal === 'admin' ? route('login.admin') : ($portal === 'guide' ? route('login.guide') : route('login'));
    $loginWelcome = 'auth.login.' . $portal . '_welcome';
    $loginSubtitle = 'auth.login.' . $portal . '_subtitle';
    $portalIcon = match ($portal) {
        'admin' => 'mdi-shield-account',
        'guide' => 'mdi-map-marker-path',
        default => 'mdi-office-building',
    };
    $authPageTitle = __('auth.login.title');
    $authCardWidth = '480px';
    $authVariant = $portal;
@endphp

@section('content')
    <div class="auth-heading">
        <span class="auth-eyebrow">
            <i class="mdi {{ $portalIcon }}" aria-hidden="true"></i>
            {{ __('auth.login.portal_' . $portal) }}
        </span>
        <h1 class="auth-title" id="auth-title">{{ __($loginWelcome) }}</h1>
        <p class="auth-subtitle">{{ __($loginSubtitle) }}</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success auth-alert" role="status">{{ session('status') }}</div>
    @endif

    <form class="auth-form" method="POST" action="{{ $loginAction }}">
        @csrf

        <div class="auth-field">
            <label class="form-label auth-label" for="email">{{ __('auth.login.email') }}</label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   placeholder="{{ __('auth.login.email_placeholder') }}"
                   value="{{ old('email') }}"
                   autocomplete="username"
                   required
                   autofocus>
            @error('email')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <div class="auth-label-row">
                <label class="form-label auth-label" for="password">{{ __('auth.login.password') }}</label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="auth-inline-link">{{ __('auth.login.forgot_password') }}</a>
                @endif
            </div>

            <div class="input-group auth-pass-inputgroup">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       placeholder="{{ __('auth.login.password_placeholder') }}"
                       autocomplete="current-password"
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

        <div class="auth-row">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember-check" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember-check">{{ __('auth.login.remember_me') }}</label>
            </div>
        </div>

        <button class="btn btn-primary w-100 auth-submit" type="submit">{{ __('auth.login.log_in') }}</button>
    </form>

    @if($portal !== 'admin')
        <div class="auth-footnote">
            @if($portal === 'guide')
                {{ __('auth.login.no_account') }}
                <a href="{{ route('register.guide') }}">{{ __('auth.register_guide.register_guide') }}</a>
            @else
                {{ __('auth.login.register_vendor_label') }}
                <a href="{{ route('register.vendor') }}">{{ __('auth.login.register_vendor_link_text') }}</a>
            @endif
        </div>
    @endif

    <div class="auth-footnote auth-footnote--muted">
        <a href="{{ route('login.choose') }}" class="auth-back-link">
            <i class="mdi mdi-arrow-left" aria-hidden="true"></i>
            {{ __('auth.login.change_portal') }}
        </a>
    </div>
@endsection
