@extends('admin.layouts.app')
@section('title', __('admin.vendor.guide_profiles.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.guide_profiles.edit') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.guide_profiles.title'), 'url' => route('admin.vendor.guide-profile.index')], ['label' => __('admin.vendor.common.edit')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.guide-profile.update', $provider) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.guide_profiles.form.business_name') }} *</label>
                    <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $provider->business_name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.guide_profiles.form.bio') }}</label>
                    <textarea name="bio" class="form-control" rows="3">{{ old('bio', $provider->bio) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.guide_profiles.form.avatar') }}</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                        @if($provider->avatar)
                        <div class="mt-2">
                            <img src="{{ $provider->avatar }}" alt="" class="rounded-circle" style="height:64px;width:64px;object-fit:cover">
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                            <label class="form-check-label" for="remove_avatar">{{ __('admin.vendor.guide_profiles.form.remove_avatar') }}</label>
                        </div>
                        @else
                        <div class="form-text">{{ __('admin.vendor.guide_profiles.form.no_avatar') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.guide_profiles.form.languages') }}</label>
                        <input type="text" name="languages" class="form-control" value="{{ old('languages', implode(', ', $provider->languages ?? [])) }}" placeholder="vi, en">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.common.save') }}</button>
                <a href="{{ route('admin.vendor.guide-profile.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
