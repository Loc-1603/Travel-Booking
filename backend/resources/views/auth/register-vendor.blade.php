@extends('layouts.auth')

@php
    $authPageTitle = __('auth.register_vendor.title');
    $authCardWidth = '560px';
    $authVariant = 'vendor';
@endphp

@section('content')
    <div class="auth-heading">
        <span class="auth-eyebrow">
            <i class="mdi mdi-office-building" aria-hidden="true"></i>
            {{ __('auth.choose_role.vendor_name') }}
        </span>
        <h1 class="auth-title" id="auth-title">{{ __('auth.register_vendor.register_vendor') }}</h1>
        <p class="auth-subtitle">{{ __('auth.register_vendor.apply_list_properties') }}</p>
    </div>

    <form class="auth-form" method="POST" action="{{ route('register.vendor') }}">
        @csrf

        <div class="auth-field">
            <label class="form-label auth-label" for="name">{{ __('auth.register_vendor.name') }} <span class="auth-required" aria-hidden="true">*</span></label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name"
                   name="name"
                   placeholder="{{ __('auth.register_vendor.name_placeholder') }}"
                   value="{{ old('name') }}"
                   autocomplete="name"
                   required
                   autofocus>
            @error('name')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="form-label auth-label" for="email">{{ __('auth.register_vendor.email') }} <span class="auth-required" aria-hidden="true">*</span></label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   placeholder="{{ __('auth.register_vendor.email_placeholder') }}"
                   value="{{ old('email') }}"
                   autocomplete="email"
                   required>
            @error('email')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="form-label auth-label" for="password">{{ __('auth.register_vendor.password') }} <span class="auth-required" aria-hidden="true">*</span></label>
            <div class="input-group auth-pass-inputgroup">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       placeholder="{{ __('auth.register_vendor.password_placeholder') }}"
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
            <label class="form-label auth-label" for="password_confirmation">{{ __('auth.register_vendor.confirm_password') }} <span class="auth-required" aria-hidden="true">*</span></label>
            <div class="input-group auth-pass-inputgroup">
                <input type="password"
                       class="form-control"
                       id="password_confirmation"
                       name="password_confirmation"
                       placeholder="{{ __('auth.register_vendor.confirm_password_placeholder') }}"
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

        <div class="auth-section-title">
            <span>{{ __('auth.register_vendor.business_section') }}</span>
            <span>{{ __('auth.register_vendor.optional') }}</span>
        </div>

        <div class="auth-field">
            <label class="form-label auth-label" for="business_name">{{ __('auth.register_vendor.business_name') }}</label>
            <input type="text"
                   class="form-control @error('business_name') is-invalid @enderror"
                   id="business_name"
                   name="business_name"
                   placeholder="{{ __('auth.register_vendor.business_name_placeholder') }}"
                   value="{{ old('business_name') }}">
            @error('business_name')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="form-label auth-label" for="business_details">{{ __('auth.register_vendor.business_details') }}</label>
            <textarea class="form-control @error('business_details') is-invalid @enderror"
                      id="business_details"
                      name="business_details"
                      rows="3"
                      placeholder="{{ __('auth.register_vendor.business_details_placeholder') }}">{{ old('business_details') }}</textarea>
            @error('business_details')
                <div class="auth-field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 auth-submit">{{ __('auth.register_vendor.apply') }}</button>
    </form>

    <div class="auth-footnote">
        {{ __('auth.register_vendor.already_registered') }}
        <a href="{{ route('login') }}">{{ __('auth.login.log_in') }}</a>
    </div>
@endsection
