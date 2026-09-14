@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_attractions.add'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_attractions.add') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_attractions.title'), 'url' => route('admin.tour-attractions.index')], ['label' => __('admin.vendor.common.create')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-attractions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_attractions.form.province') }} *</label>
                    <select name="province_id" class="form-select" required>
                        @foreach($provinces as $p)
                        <option value="{{ $p->id }}" {{ ($selectedProvinceId ?? old('province_id')) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_attractions.form.name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_attractions.form.description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_attractions.form.image') }}</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_attractions.form.latitude') }}</label>
                        <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_attractions.form.longitude') }}</label>
                        <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude') }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_attractions.form.famous') }}</label>
                        <select name="is_famous" class="form-select">
                            <option value="0">{{ __('admin.vendor.common.no') }}</option>
                            <option value="1" {{ old('is_famous') ? 'selected' : '' }}>{{ __('admin.vendor.common.yes') }}</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.tour_attractions.form.create') }}</button>
                <a href="{{ route('admin.tour-attractions.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
