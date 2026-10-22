@extends('layouts.auth')

@php
    $authPageTitle = __('auth.register_verify.title');
    $authCardWidth = '480px';
    $authVariant = $portal;
@endphp

@section('content')
    <div class="auth-heading">
        <span class="auth-status-icon" aria-hidden="true">
            <i class="mdi mdi-email-check-outline"></i>
        </span>

        @if ($verified)
            <h1 class="auth-title" id="auth-title">{{ __('auth.register_verify.verified_title') }}</h1>
            <p class="auth-subtitle">{{ __('auth.register_verify.verified_message') }}</p>
        @else
            <h1 class="auth-title" id="auth-title">{{ __('auth.register_verify.title') }}</h1>
            <p class="auth-subtitle">
                {{ $email ? __('auth.register_verify.sent_to', ['email' => $email]) : __('auth.register_verify.check_inbox') }}
            </p>
        @endif
    </div>

    <div class="mt-4">
        @if ($verified)
            <a href="{{ route($loginRoute) }}" class="btn btn-primary w-100 auth-submit">{{ __('auth.register_verify.continue') }}</a>
        @else
            <button type="button" class="btn btn-primary w-100 auth-submit" disabled aria-disabled="true">{{ __('auth.register_verify.continue') }}</button>
        @endif
    </div>

    @if (! $verified && $email)
        <div class="mt-4">
            @if (session('resent'))
                <div class="alert alert-success auth-alert" role="status">{{ __('auth.register_verify.resent') }}</div>
            @endif

            <form method="POST" action="{{ route('register.verify.resend') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="portal" value="{{ $portal }}">
                <button type="submit"
                        id="resend-verification"
                        class="btn btn-outline-primary w-100 waves-effect waves-light"
                        disabled
                        data-countdown="{{ $resendCooldown }}">
                    {{ __('auth.register_verify.resend_wait', ['seconds' => $resendCooldown]) }}
                </button>
            </form>
        </div>
    @endif

    <div class="auth-footnote">
        {{ __('auth.register_verify.already_verified') }}
        <a href="{{ route($loginRoute) }}">{{ __('auth.register_verify.sign_in') }}</a>
    </div>
@endsection

@section('scripts')
    @if (! $verified && $email)
        <script>
            (function () {
                var button = document.getElementById('resend-verification');

                if (!button) {
                    return;
                }

                var remaining = parseInt(button.dataset.countdown, 10) || 15;
                var waitLabel = @json(__('auth.register_verify.resend_wait', ['seconds' => ':seconds']));
                var readyLabel = @json(__('auth.register_verify.resend'));

                button.textContent = waitLabel.replace(':seconds', remaining);

                var timer = setInterval(function () {
                    remaining -= 1;

                    if (remaining <= 0) {
                        clearInterval(timer);
                        button.disabled = false;
                        button.textContent = readyLabel;
                        return;
                    }

                    button.textContent = waitLabel.replace(':seconds', remaining);
                }, 1000);
            })();
        </script>
    @endif
@endsection
