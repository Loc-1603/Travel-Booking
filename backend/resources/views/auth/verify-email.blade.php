@extends('layouts.auth')

@php
    $authPageTitle = __('auth.verify_email.title');
    $authCardWidth = '480px';
    $authVariant = 'account';
@endphp

@section('content')
    <div class="auth-heading">
        <span class="auth-status-icon" aria-hidden="true">
            <i class="mdi mdi-email-outline"></i>
        </span>
        <h1 class="auth-title" id="auth-title">{{ __('auth.verify_email.title') }}</h1>
        <p class="auth-subtitle">{{ __('auth.verify_email.thanks_signing_up') }}</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success auth-alert" role="status">
            {{ __('auth.verify_email.verification_link_sent') }}
        </div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-100 auth-submit">
            {{ __('auth.verify_email.resend_verification_email') }}
        </button>
    </form>

    <form class="mt-3" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary w-100">
            {{ __('auth.verify_email.log_out') }}
        </button>
    </form>
@endsection
