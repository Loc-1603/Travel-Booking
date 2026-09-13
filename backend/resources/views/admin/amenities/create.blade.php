@extends('admin.layouts.app')
@section('title', __('admin.vendor.amenities.add'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.amenities.add') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.amenities.title'), 'url' => route('admin.amenities.index')], ['label' => __('admin.vendor.common.create')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.amenities.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.name') }} *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="{{ __('admin.vendor.amenities.form.name_placeholder') }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.slug') }}</label>
                        <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" placeholder="{{ __('admin.vendor.amenities.form.slug_placeholder') }}">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.icon') }}</label>
                        <input type="text" name="icon" class="form-control" value="{{ old('icon') }}" placeholder="{{ __('admin.vendor.amenities.form.icon_placeholder') }}">
                        <small class="text-muted">{{ __('admin.vendor.amenities.form.icon_help') }}</small>
                        @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.amenities.form.sort_order') }}</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order') }}" min="0" placeholder="{{ __('admin.vendor.amenities.form.sort_order_placeholder') }}">
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.amenities.form.create') }}</button>
                <a href="{{ route('admin.amenities.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
