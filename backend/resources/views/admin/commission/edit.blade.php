@extends('admin.layouts.app')
@section('title', __('admin.vendor.commission.edit.title'))
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.commission.edit.title') }}"
        :breadcrumbs="[
            ['label' => 'Admin', 'url' => route('admin.dashboard')],
            ['label' => __('admin.vendor.commission.title'), 'url' => route('admin.commission.index')],
            ['label' => __('admin.vendor.commission.edit.title')]
        ]"
    />
    <x-alert />

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.commission.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="commission_rate" class="form-label">{{ __('admin.vendor.commission.edit.rate_label') }}</label>
                            <input type="number" step="0.1" min="0" max="100" name="commission_rate" id="commission_rate"
                                   class="form-control @error('commission_rate') is-invalid @enderror"
                                   value="{{ old('commission_rate', $rate * 100) }}" required>
                            @error('commission_rate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('admin.vendor.commission.edit.save') }}</button>
                        <a href="{{ route('admin.commission.index') }}" class="btn btn-secondary">{{ __('admin.vendor.commission.edit.cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
