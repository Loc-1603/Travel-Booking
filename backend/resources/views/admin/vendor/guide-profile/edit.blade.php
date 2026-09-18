@extends('admin.layouts.app')
@section('title', __('admin.vendor.guide_profiles.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.guide_profiles.edit') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.guide_profiles.title'), 'url' => route('admin.vendor.guide-profile.index')], ['label' => __('admin.vendor.common.edit')]]" />
    <x-alert />
    @include('admin.vendor.guide-profile._form', [
        'provider' => $provider,
        'action' => route('admin.vendor.guide-profile.update', $provider),
        'method' => 'PUT',
    ])
</div>
@endsection