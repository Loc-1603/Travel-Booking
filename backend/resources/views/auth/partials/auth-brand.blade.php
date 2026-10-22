@php
    $authSiteName = $authSiteName ?? \App\Services\WebsiteSettingsService::getSiteName();
    $authSiteLogo = $authSiteLogo ?? \App\Services\WebsiteSettingsService::getSiteLogo();
    $authSiteTagline = $authSiteTagline ?? \App\Services\WebsiteSettingsService::getSiteDescription();
@endphp
<div class="auth-brand">
    <a href="{{ url('/') }}" class="auth-brand-link">
        @if($authSiteLogo)
            <img src="{{ $authSiteLogo }}" alt="{{ $authSiteName }}" class="auth-brand-logo">
        @else
            <span class="auth-brand-mark" aria-hidden="true">
                <i class="mdi mdi-airplane"></i>
            </span>
        @endif
        <span class="auth-brand-name">{{ $authSiteName }}</span>
        @if($authSiteTagline)
            <span class="auth-brand-tagline">{{ $authSiteTagline }}</span>
        @endif
    </a>
</div>
