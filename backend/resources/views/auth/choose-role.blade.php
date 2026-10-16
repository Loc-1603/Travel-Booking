@php
    $authPageTitle = __('auth.choose_role.title');
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
                                    <h5 class="mb-0">{{ __('auth.choose_role.title') }}</h5>
                                    <p class="text-muted mt-2">{{ __('auth.choose_role.subtitle') }}</p>
                                </div>
                                <div class="mt-4">
                                    <div class="card border position-relative mb-3">
                                        <div class="card-body d-flex align-items-center p-3">
                                            <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width:44px;height:44px;">
                                                <i class="mdi mdi-shield-account font-size-20"></i>
                                            </span>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ __('auth.choose_role.admin_name') }}</h6>
                                                <p class="text-muted mb-0 font-size-13">{{ __('auth.choose_role.admin_desc') }}</p>
                                            </div>
                                            <a href="{{ route('login.admin') }}" class="btn btn-sm btn-primary stretched-link">{{ __('auth.choose_role.continue') }}</a>
                                        </div>
                                    </div>
                                    <div class="card border position-relative mb-3">
                                        <div class="card-body d-flex align-items-center p-3">
                                            <span class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width:44px;height:44px;">
                                                <i class="mdi mdi-office-building font-size-20"></i>
                                            </span>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ __('auth.choose_role.vendor_name') }}</h6>
                                                <p class="text-muted mb-0 font-size-13">{{ __('auth.choose_role.vendor_desc') }}</p>
                                            </div>
                                            <a href="{{ route('login') }}" class="btn btn-sm btn-primary stretched-link">{{ __('auth.choose_role.continue') }}</a>
                                        </div>
                                    </div>
                                    <div class="card border position-relative mb-0">
                                        <div class="card-body d-flex align-items-center p-3">
                                            <span class="rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width:44px;height:44px;">
                                                <i class="mdi mdi-map-marker-path font-size-20"></i>
                                            </span>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ __('auth.choose_role.guide_name') }}</h6>
                                                <p class="text-muted mb-0 font-size-13">{{ __('auth.choose_role.guide_desc') }}</p>
                                            </div>
                                            <a href="{{ route('login.guide') }}" class="btn btn-sm btn-primary stretched-link">{{ __('auth.choose_role.continue') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg auth-bg--hotel-booking position-relative pt-md-5 p-4 d-flex">
                    <div class="bg-overlay bg-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('admin.layouts.javascript')
