@extends('admin.layouts.app')
@section('title', __('admin.vendor.countries.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.countries.edit') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.countries.title'), 'url' => route('admin.countries.index')], ['label' => __('admin.vendor.common.edit')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.countries.update', $country) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.countries.form.name') }} *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $country->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.countries.form.code') }}</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $country->code) }}">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('admin.vendor.countries.form.tax_rate') }}</label>
                        <input type="number" name="tax_rate" class="form-control" value="{{ old('tax_rate', $country->tax_rate !== null ? $country->tax_rate * 100 : '') }}" min="0" max="100" step="0.01" placeholder="10">
                        <small class="text-muted">{{ __('admin.vendor.countries.form.tax_rate_help') }}</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('admin.vendor.countries.form.tax_name') }}</label>
                        <input type="text" name="tax_name" class="form-control" value="{{ old('tax_name', $country->tax_name) }}" placeholder="{{ __('admin.vendor.countries.form.tax_name_placeholder') }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.countries.form.image') }}</label>
                    @if($country->image)
                        <div class="mb-2"><img src="{{ asset('storage/'.$country->image) }}" alt="" class="rounded" style="max-height:120px"></div>
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small class="text-muted">Leave empty to keep current image.</small>
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.countries.form.update') }}</button>
                <a href="{{ route('admin.countries.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
