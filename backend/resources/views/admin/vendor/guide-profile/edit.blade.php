@extends('admin.layouts.app')
@section('title', 'Edit guide profile')
@section('content')
<div class="container-fluid">
    <x-page-title title="Edit guide profile" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => 'Guide profiles', 'url' => route('admin.vendor.guide-profile.index')], ['label' => 'Edit']]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.guide-profile.update', $provider) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Business name *</label>
                    <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $provider->business_name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-control" rows="3">{{ old('bio', $provider->bio) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Avatar</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                        @if($provider->avatar)
                        <div class="mt-2">
                            <img src="{{ $provider->avatar }}" alt="" class="rounded-circle" style="height:64px;width:64px;object-fit:cover">
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                            <label class="form-check-label" for="remove_avatar">Remove photo</label>
                        </div>
                        @else
                        <div class="form-text">No photo yet — shoppers see your account avatar, then an initial.</div>
                        @endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Languages (comma separated)</label>
                        <input type="text" name="languages" class="form-control" value="{{ old('languages', implode(', ', $provider->languages ?? [])) }}" placeholder="vi, en">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.vendor.guide-profile.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
