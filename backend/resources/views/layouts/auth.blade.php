@php
    $authPageTitle = $authPageTitle ?? __('auth.login.title');
    $authCardWidth = $authCardWidth ?? '480px';
    $authVariant = $authVariant ?? 'account';
    $authBackground = $authBackground ?? asset('images/auth/ha-long.jpg');
    $authSiteName = \App\Services\WebsiteSettingsService::getSiteName();
    $authSiteLogo = \App\Services\WebsiteSettingsService::getSiteLogo();
    $authSiteTagline = \App\Services\WebsiteSettingsService::getSiteDescription();
    $isAuthPage = true;
@endphp
@include('admin.layouts.css')
<div class="auth-page auth-page--{{ $authVariant }}">
    <div class="container-fluid p-0">
        <div class="row g-0 auth-layout">
            <div class="col-12 col-lg-6 col-xxl-5 auth-form-col">
                <div class="auth-form-panel">
                    <main class="auth-card" style="--auth-card-width: {{ $authCardWidth }};" aria-labelledby="auth-title">
                        @include('auth.partials.auth-brand')

                        @yield('content')
                    </main>
                </div>
            </div>
            <div class="col-lg-6 col-xxl-7 d-none d-lg-block auth-visual-col" aria-hidden="true">
                <div class="auth-visual" style="background-image: url('{{ $authBackground }}');">
                    <div class="auth-visual-overlay"></div>
                    <ul class="auth-visual-shapes" aria-hidden="true">
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
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('admin/dist/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script>
(function () {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var group = button.closest('.auth-pass-inputgroup');
            var input = group ? group.querySelector('input') : null;

            if (!input) {
                return;
            }

            var isHidden = input.getAttribute('type') === 'password';

            input.setAttribute('type', isHidden ? 'text' : 'password');
            button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            button.setAttribute('aria-label', isHidden ? button.dataset.hideLabel : button.dataset.showLabel);

            var icon = button.querySelector('i');

            if (icon) {
                icon.classList.toggle('mdi-eye-outline', !isHidden);
                icon.classList.toggle('mdi-eye-off-outline', isHidden);
            }
        });
    });
})();
</script>
@yield('scripts')
</body>
</html>
