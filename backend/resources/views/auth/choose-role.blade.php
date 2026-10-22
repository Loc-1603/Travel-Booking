@extends('layouts.auth')

@php
    $authPageTitle = __('auth.choose_role.title');
    $authCardWidth = '520px';
    $authVariant = 'account';
@endphp

@section('content')
    <div class="auth-heading">
        <h1 class="auth-title" id="auth-title">{{ __('auth.choose_role.title') }}</h1>
        <p class="auth-subtitle">{{ __('auth.choose_role.subtitle') }}</p>
    </div>

    <div class="auth-role-list">
        <a href="{{ route('login.admin') }}" class="auth-role-card auth-role-card--admin">
            <span class="auth-role-icon" aria-hidden="true">
                <i class="mdi mdi-shield-account"></i>
            </span>
            <span class="auth-role-body">
                <span class="auth-role-title-row">
                    <span class="auth-role-title">{{ __('auth.choose_role.admin_name') }}</span>
                    <span class="auth-role-badge">{{ __('auth.choose_role.admin_badge') }}</span>
                </span>
                <span class="auth-role-desc">{{ __('auth.choose_role.admin_desc') }}</span>
            </span>
            <span class="auth-role-arrow" aria-hidden="true">
                <i class="mdi mdi-arrow-right"></i>
            </span>
        </a>

        <a href="{{ route('login') }}" class="auth-role-card auth-role-card--vendor">
            <span class="auth-role-icon" aria-hidden="true">
                <i class="mdi mdi-office-building"></i>
            </span>
            <span class="auth-role-body">
                <span class="auth-role-title-row">
                    <span class="auth-role-title">{{ __('auth.choose_role.vendor_name') }}</span>
                </span>
                <span class="auth-role-desc">{{ __('auth.choose_role.vendor_desc') }}</span>
            </span>
            <span class="auth-role-arrow" aria-hidden="true">
                <i class="mdi mdi-arrow-right"></i>
            </span>
        </a>

        <a href="{{ route('login.guide') }}" class="auth-role-card auth-role-card--guide">
            <span class="auth-role-icon" aria-hidden="true">
                <i class="mdi mdi-map-marker-path"></i>
            </span>
            <span class="auth-role-body">
                <span class="auth-role-title-row">
                    <span class="auth-role-title">{{ __('auth.choose_role.guide_name') }}</span>
                </span>
                <span class="auth-role-desc">{{ __('auth.choose_role.guide_desc') }}</span>
            </span>
            <span class="auth-role-arrow" aria-hidden="true">
                <i class="mdi mdi-arrow-right"></i>
            </span>
        </a>
    </div>
@endsection
