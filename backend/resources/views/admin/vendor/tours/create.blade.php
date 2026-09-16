@extends('admin.layouts.app')
@section('title', __('admin.vendor.tours.add'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tours.add') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.tours.title'), 'url' => route('admin.vendor.tours.index')], ['label' => __('admin.vendor.common.create')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.tours.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tours.form.provider') }} *</label>
                        <select name="provider_id" class="form-select" required>
                            @foreach($providers as $p)
                            <option value="{{ $p->id }}" {{ old('provider_id') == $p->id ? 'selected' : '' }}>{{ $p->business_name }} ({{ $p->status }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tours.form.province') }} *</label>
                        <select name="province_id" class="form-select" required>
                            @foreach($provinces as $p)
                            <option value="{{ $p->id }}" {{ old('province_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tours.form.title') }} *</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.tours.form.description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3" style="display:none">
                        <input type="hidden" name="base_fixed" value="{{ old('base_fixed', 0) }}">
                        <input type="hidden" name="base_price_hourly" value="{{ old('base_price_hourly', 0) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tours.form.per_day') }} *</label>
                        <input type="number" name="base_price_daily" class="form-control" value="{{ old('base_price_daily') }}" min="0" step="1000" required>
                        <div class="form-text">{{ __('admin.vendor.tours.form.per_day_hint') }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tours.form.transport_fee') }}</label>
                        <input type="number" name="transport_fee" class="form-control" value="{{ old('transport_fee') }}" min="0" step="1000">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tours.form.transport_desc') }}</label>
                        <input type="text" name="transport_desc" class="form-control" value="{{ old('transport_desc') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.tours.form.meeting_point') }}</label>
                        <input type="text" name="meeting_point" class="form-control" value="{{ old('meeting_point') }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.tours.form.create_draft') }}</button>
                <a href="{{ route('admin.vendor.tours.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
