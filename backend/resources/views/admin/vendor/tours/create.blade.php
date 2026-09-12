@extends('admin.layouts.app')
@section('title', 'Add tour')
@section('content')
<div class="container-fluid">
    <x-page-title title="Add tour" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => 'My tours', 'url' => route('admin.vendor.tours.index')], ['label' => 'Create']]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.tours.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Provider *</label>
                        <select name="provider_id" class="form-select" required>
                            @foreach($providers as $p)
                            <option value="{{ $p->id }}" {{ old('provider_id') == $p->id ? 'selected' : '' }}>{{ $p->business_name }} ({{ $p->status }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Province *</label>
                        <select name="province_id" class="form-select" required>
                            @foreach($provinces as $p)
                            <option value="{{ $p->id }}" {{ old('province_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Base fixed (VND) *</label>
                        <input type="number" name="base_fixed" class="form-control" value="{{ old('base_fixed') }}" min="0" step="1000" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Per hour (VND) *</label>
                        <input type="number" name="base_price_hourly" class="form-control" value="{{ old('base_price_hourly') }}" min="0" step="1000" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Per day (VND) *</label>
                        <input type="number" name="base_price_daily" class="form-control" value="{{ old('base_price_daily') }}" min="0" step="1000" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Transport fee (VND)</label>
                        <input type="number" name="transport_fee" class="form-control" value="{{ old('transport_fee') }}" min="0" step="1000">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Transport description</label>
                        <input type="text" name="transport_desc" class="form-control" value="{{ old('transport_desc') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Meeting point</label>
                        <input type="text" name="meeting_point" class="form-control" value="{{ old('meeting_point') }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create (draft)</button>
                <a href="{{ route('admin.vendor.tours.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
