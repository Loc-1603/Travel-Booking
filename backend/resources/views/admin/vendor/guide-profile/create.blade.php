@extends('admin.layouts.app')
@section('title', __('admin.vendor.guide_profiles.create'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.guide_profiles.create') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.guide_profiles.title'), 'url' => route('admin.vendor.guide-profile.index')], ['label' => __('admin.vendor.guide_profiles.create')]]" />
    <x-alert />
    @include('admin.vendor.guide-profile._form', [
        'provider' => null,
        'action' => route('admin.vendor.guide-profile.store'),
        'method' => 'POST',
    ])
</div>
@endsection