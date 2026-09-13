@extends('admin.layouts.app')
@section('title', 'Edit tour provider #'.$tourProvider->id)
@section('content')
<div class="container-fluid">
    <x-page-title title="Edit tour provider #{{ $tourProvider->id }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour providers', 'url' => route('admin.tour-providers.index')], ['label' => 'Edit']]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-providers.update', $tourProvider) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Business name *</label>
                    <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $tourProvider->business_name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-control" rows="3">{{ old('bio', $tourProvider->bio) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Avatar</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                        @if($tourProvider->avatar)
                        <div class="mt-2">
                            <img src="{{ $tourProvider->avatar }}" alt="" class="rounded-circle" style="height:64px;width:64px;object-fit:cover">
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                            <label class="form-check-label" for="remove_avatar">Remove photo (falls back to vendor account avatar)</label>
                        </div>
                        @else
                        <div class="form-text">No photo yet — the storefront falls back to the vendor account avatar, then to an initial.</div>
                        @endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Languages (comma separated)</label>
                        <input type="text" name="languages" class="form-control" value="{{ old('languages', implode(', ', $tourProvider->languages ?? [])) }}" placeholder="vi, en">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.tour-providers.show', $tourProvider) }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
