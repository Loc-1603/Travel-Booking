@php
    $authPageTitle = __('auth.register_verify.title');
    $authHotelBackground = asset('images/auth/login-bg.jpg');
@endphp
@include('admin.layouts.css')
@include('auth.partials.auth-hotel-bg-styles')
<div class="auth-page">
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-xxl-3 col-lg-4 col-md-5">
                <div class="auth-full-page-content d-flex p-sm-5 p-4">
                    <div class="w-100">
                        <div class="d-flex flex-column h-100">
                            @include('auth.partials.auth-brand')
                            <div class="auth-content my-auto">
                                <div class="text-center">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary mb-3" style="width: 56px; height: 56px;">
                                        <i class="mdi mdi-email-check-outline" style="font-size: 24px;"></i>
                                    </span>
                                    @if ($verified)
                                        <h5 class="mb-0">{{ __('auth.register_verify.verified_title') }}</h5>
                                        <p class="text-muted mt-2 mb-0">{{ __('auth.register_verify.verified_message') }}</p>
                                    @else
                                        <h5 class="mb-0">{{ __('auth.register_verify.title') }}</h5>
                                        <p class="text-muted mt-2 mb-0">
                                            {{ $email ? __('auth.register_verify.sent_to', ['email' => $email]) : __('auth.register_verify.check_inbox') }}
                                        </p>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    @if ($verified)
                                        <a href="{{ route($loginRoute) }}" class="btn btn-primary w-100 waves-effect waves-light">{{ __('auth.register_verify.continue') }}</a>
                                    @else
                                        <span class="btn btn-primary w-100 disabled" aria-disabled="true">{{ __('auth.register_verify.continue') }}</span>
                                    @endif
                                </div>

                                @if (! $verified && $email)
                                    <div class="mt-4">
                                        @if (session('resent'))
                                            <div class="alert alert-success mb-3" role="alert">{{ __('auth.register_verify.resent') }}</div>
                                        @endif
                                        <form method="POST" action="{{ route('register.verify.resend') }}">
                                            @csrf
                                            <input type="hidden" name="email" value="{{ $email }}">
                                            <input type="hidden" name="portal" value="{{ $portal }}">
                                            <button type="submit" id="resend-verification" class="btn btn-outline-primary w-100 waves-effect waves-light" disabled data-countdown="{{ $resendCooldown }}">
                                                {{ __('auth.register_verify.resend_wait', ['seconds' => $resendCooldown]) }}
                                            </button>
                                        </form>
                                    </div>
                                @endif

                                <div class="mt-4 text-center">
                                    <p class="text-muted mb-0">
                                        {{ __('auth.register_verify.already_verified') }}
                                        <a href="{{ route($loginRoute) }}" class="text-primary fw-semibold">{{ __('auth.register_verify.sign_in') }}</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg auth-bg--hotel-booking position-relative pt-md-5 p-4 d-flex">
                    <div class="bg-overlay bg-primary"></div>
                    <ul class="bg-bubbles">
                        @for($i = 0; $i < 10; $i++)<li></li>@endfor
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
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
@include('admin.layouts.javascript')
