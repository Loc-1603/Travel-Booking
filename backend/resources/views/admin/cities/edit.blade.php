@extends('admin.layouts.app')
@section('title', __('admin.vendor.cities.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.cities.edit') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.cities.title'), 'url' => route('admin.cities.index')], ['label' => __('admin.vendor.common.edit')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.cities.update', $city) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.cities.form.country') }} *</label>
                    <select name="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                        @foreach($countries as $c)
                            <option value="{{ $c->id }}" {{ old('country_id', $city->country_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.cities.form.name') }} *</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $city->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.cities.form.image') }}</label>
                    @if($city->image)
                        <div class="mb-2"><img src="{{ asset('storage/'.$city->image) }}" alt="" class="rounded" style="max-height:120px"></div>
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small class="text-muted">Leave empty to keep current image.</small>
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.cities.form.update') }}</button>
                <a href="{{ route('admin.cities.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
