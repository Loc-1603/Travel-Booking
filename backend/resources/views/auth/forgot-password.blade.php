@php
    $authPageTitle = __('auth.forgot_password.title');
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
                            <div class="mb-4 mb-md-5 text-center">
                                <a href="{{ url('/') }}" class="d-block auth-logo">
                                    @include('auth.partials.auth-brand')
                                </a>
                            </div>
                            <div class="auth-content my-auto">
                                <div class="text-center">
                                    <h5 class="mb-0">{{ __('auth.forgot_password.forgot_password') }}</h5>
                                </div>
                                <div class="mb-4 mt-2 text-sm text-gray-600">
                                    {{ __('auth.forgot_password.description') }}
                                </div>

                                @if (session('status'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{session('status')}}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                @endif

                                <form class="mt-4 pt-2" method="POST" action="{{ route('password.email') }}">
                                    @csrf

                                    <!-- Email Address -->
                                    <div class="mb-3">
                                        <label class="form-label" for="email">{{ __('auth.forgot_password.email') }}</label>
                                        <input type="email"
                                               class="form-control @error('email') is-invalid @enderror"
                                               id="email"
                                               name="email"
                                               placeholder="{{ __('auth.forgot_password.email_placeholder') }}"
                                               value="{{ old('email') }}"
                                               required
                                               autofocus>
                                        @error('email')
                                        <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary w-100 waves-effect waves-light">
                                            {{ __('auth.forgot_password.send_reset_link') }}
                                        </button>
                                    </div>
                                    <div class="mt-5 text-center">
                                        <p class="text-muted mb-0">{{ __('auth.forgot_password.back_to_login') }} <a href="{{route('login')}}" class="text-primary fw-semibold">{{ __('auth.forgot_password.back_to_login') }}</a> </p>
                                    </div>
                                </form>

                            </div>

                        </div>
                    </div>
                </div>
                <!-- end auth full page content -->
            </div>
            <!-- end col -->
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg auth-bg--hotel-booking position-relative pt-md-5 p-4 d-flex">
                    <div class="bg-overlay bg-primary"></div>
                    <ul class="bg-bubbles">
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                    </ul>
                    <!-- end bubble effect -->
                </div>
            </div>
            <!-- end col -->
        </div>
        <!-- end row -->
    </div>
    <!-- end container fluid -->
</div>
@include('admin.layouts.javascript')

