@extends('admin.layouts.app')
@section('title', __('admin.vendor.amenities.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.amenities.edit') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.amenities.title'), 'url' => route('admin.amenities.index')], ['label' => __('admin.vendor.common.edit')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.amenities.update', $amenity) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.name') }} *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $amenity->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.slug') }}</label>
                        <input type="text" name="slug" class="form-control" value="{{ old('slug', $amenity->slug) }}">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.icon') }}</label>
                        <input type="text" name="icon" class="form-control" value="{{ old('icon', $amenity->icon) }}">
                        @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.sort_order') }}</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $amenity->sort_order) }}" min="0">
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.amenities.form.update') }}</button>
                <a href="{{ route('admin.amenities.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
