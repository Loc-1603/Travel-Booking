@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_provinces.add'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_provinces.add') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_provinces.title'), 'url' => route('admin.tour-provinces.index')], ['label' => __('admin.vendor.common.create')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-provinces.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_provinces.form.name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.country') }}</label>
                        <select name="country_id" class="form-select">
                            <option value="">—</option>
                            @foreach($countries as $c)
                            <option value="{{ $c->id }}" {{ old('country_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.city') }}</label>
                        <select name="city_id" class="form-select">
                            <option value="">—</option>
                            @foreach($cities as $c)
                            <option value="{{ $c->id }}" {{ old('city_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_provinces.form.description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.image') }}</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.sort_order') }}</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.featured') }}</label>
                        <select name="is_featured" class="form-select">
                            <option value="0">{{ __('admin.vendor.common.no') }}</option>
                            <option value="1" {{ old('is_featured') ? 'selected' : '' }}>{{ __('admin.vendor.common.yes') }}</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.tour_provinces.form.create') }}</button>
                <a href="{{ route('admin.tour-provinces.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
