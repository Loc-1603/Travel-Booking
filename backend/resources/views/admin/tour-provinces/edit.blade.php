@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_provinces.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_provinces.edit') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_provinces.title'), 'url' => route('admin.tour-provinces.index')], ['label' => __('admin.vendor.common.edit')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-provinces.update', $tourProvince) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_provinces.form.name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $tourProvince->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_provinces.form.slug') }}</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $tourProvince->slug) }}">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.country') }}</label>
                        <select name="country_id" class="form-select">
                            <option value="">—</option>
                            @foreach($countries as $c)
                            <option value="{{ $c->id }}" {{ old('country_id', $tourProvince->country_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.city') }}</label>
                        <select name="city_id" class="form-select">
                            <option value="">—</option>
                            @foreach($cities as $c)
                            <option value="{{ $c->id }}" {{ old('city_id', $tourProvince->city_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tour_provinces.form.description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $tourProvince->description) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.image') }}</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        @if($tourProvince->image)
                        <img src="{{ $tourProvince->image }}" alt="" class="rounded mt-2" style="height:60px">
                        @endif
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.sort_order') }}</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $tourProvince->sort_order) }}" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tour_provinces.form.featured') }}</label>
                        <select name="is_featured" class="form-select">
                            <option value="0" {{ ! old('is_featured', $tourProvince->is_featured) ? 'selected' : '' }}>{{ __('admin.vendor.common.no') }}</option>
                            <option value="1" {{ old('is_featured', $tourProvince->is_featured) ? 'selected' : '' }}>{{ __('admin.vendor.common.yes') }}</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.tour_provinces.form.update') }}</button>
                <a href="{{ route('admin.tour-provinces.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
