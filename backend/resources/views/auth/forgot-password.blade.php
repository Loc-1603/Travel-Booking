@extends('layouts.auth')

@php
    $authPageTitle = __('auth.forgot_password.title');
    $authCardWidth = '480px';
    $authVariant = 'account';
@endphp

@section('content')
    <div class="auth-heading">
        <span class="auth-status-icon" aria-hidden="true">
            <i class="mdi mdi-lock-reset"></i>
        </span>
        <h1 class="auth-title" id="auth-title">{{ __('auth.forgot_password.forgot_password') }}</h1>
        <p class="auth-subtitle">{{ __('auth.forgot_password.description') }}</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show auth-alert" role="status">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="auth-field">
            <label class="form-label auth-label" for="email">{{ __('auth.forgot_password.email') }}</label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   placeholder="{{ __('auth.forgot_password.email_placeholder') }}"
                   value="{{ old('email') }}"
                   autocomplete="username"
                   required
                   autofocus>
            @error('email')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 auth-submit">
            {{ __('auth.forgot_password.send_reset_link') }}
        </button>
    </form>

    <div class="auth-footnote">
        <a href="{{ route('login') }}" class="auth-back-link">
            <i class="mdi mdi-arrow-left" aria-hidden="true"></i>
            {{ __('auth.forgot_password.back_to_login') }}
        </a>
    </div>
@endsection
