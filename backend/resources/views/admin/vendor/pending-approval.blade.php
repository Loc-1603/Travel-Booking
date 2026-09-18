@extends('admin.layouts.app')
@section('title', __('admin.vendor.pending_approval'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.pending_approval') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.pending_approval')]]" />
    <x-alert />
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card text-center shadow-sm">
                <div class="card-body py-5 px-4">
                    <div class="display-4 text-warning mb-3">
                        <i data-feather="clock"></i>
                    </div>
                    <h4 class="mb-2">{{ __('admin.vendor.pending_approval') }}</h4>
                    <p class="text-muted mb-4">{{ __('admin.vendor.pending_approval_feature') }}</p>
                    <a href="{{ route('admin.vendor.dashboard') }}" class="btn btn-primary">
                        <i data-feather="home" class="icon-sm me-1"></i>{{ __('admin.vendor.pending_approval_go_dashboard') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection