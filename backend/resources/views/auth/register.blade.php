@php
    $authPageTitle = __('auth.register.title');
    $authHotelBackground = asset('images/auth/register-bg.jpg');
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
                                    <h5 class="mb-0">{{ __('auth.register.create_account') }}</h5>
                                    <p class="text-muted mt-2">{{ __('auth.register.register_guest') }}</p>
                                </div>
                                <form class="mt-4 pt-2" method="POST" action="{{ route('register') }}">
                                    @csrf

                                    <!-- Name -->
                                    <div class="mb-3">
                                        <label class="form-label" for="name">{{ __('auth.register.name') }}</label>
                                        <input type="text"
                                               class="form-control @error('name') is-invalid @enderror"
                                               id="name"
                                               name="name"
                                               placeholder="{{ __('auth.register.name_placeholder') }}"
                                               value="{{ old('name') }}"
                                               required
                                               autofocus>
                                        @error('name')
                                        <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Email Address -->
                                    <div class="mb-3">
                                        <label class="form-label" for="email">{{ __('auth.register.email') }}</label>
                                        <input type="email"
                                               class="form-control @error('email') is-invalid @enderror"
                                               id="email"
                                               name="email"
                                               placeholder="{{ __('auth.register.email_placeholder') }}"
                                               value="{{ old('email') }}"
                                               required>
                                        @error('email')
                                        <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Password -->
                                    <div class="mb-3">
                                        <label class="form-label" for="password">{{ __('auth.register.password') }}</label>
                                        <div class="input-group auth-pass-inputgroup">
                                            <input type="password"
                                                   class="form-control @error('password') is-invalid @enderror"
                                                   id="password"
                                                   name="password"
                                                   placeholder="{{ __('auth.register.password_placeholder') }}"
                                                   aria-label="Password"
                                                   aria-describedby="password-addon"
                                                   required>
                                            <button class="btn btn-light shadow-none ms-0" type="button" id="password-addon">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </button>
                                        </div>
                                        @error('password')
                                        <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Confirm Password -->
                                    <div class="mb-3">
                                        <label class="form-label" for="password_confirmation">{{ __('auth.register.confirm_password') }}</label>
                                        <div class="input-group auth-pass-inputgroup">
                                            <input type="password"
                                                   class="form-control @error('password_confirmation') is-invalid @enderror"
                                                   id="password_confirmation"
                                                   name="password_confirmation"
                                                   placeholder="{{ __('auth.register.confirm_password_placeholder') }}"
                                                   aria-label="Confirm Password"
                                                   aria-describedby="password-confirm-addon"
                                                   required>
                                            <button class="btn btn-light shadow-none ms-0" type="button" id="password-confirm-addon">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </button>
                                        </div>
                                        @error('password_confirmation')
                                        <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Links and Submit -->
                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <a class="text-muted" href="{{ route('login') }}">
                                            {{ __('auth.register.already_registered') }}
                                        </a>
                                        <button type="submit" class="btn btn-primary waves-effect waves-light">
                                            {{ __('auth.register.register') }}
                                        </button>
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

