@extends('admin.layouts.app')
@section('title', __('admin.vendor.cities.add'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.cities.add') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.cities.title'), 'url' => route('admin.cities.index')], ['label' => __('admin.vendor.common.create')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.cities.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.cities.form.country') }} *</label>
                    <select name="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                        <option value="">{{ __('admin.vendor.cities.form.select_country') }}</option>
                        @foreach($countries as $c)
                            <option value="{{ $c->id }}" {{ old('country_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.cities.form.name') }} *</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.cities.form.image') }}</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.cities.form.create') }}</button>
                <a href="{{ route('admin.cities.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
