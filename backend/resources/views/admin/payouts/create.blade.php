@extends('admin.layouts.app')
@section('title', __('admin.vendor.payouts_admin.create.title'))
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.payouts_admin.create.title') }}"
        :breadcrumbs="[
            ['label' => 'Admin', 'url' => route('admin.dashboard')],
            ['label' => __('admin.vendor.payouts_admin.title'), 'url' => route('admin.payouts.index')],
            ['label' => __('admin.vendor.payouts_admin.create.generate')]
        ]"
    />
    <x-alert />

    <div class="card">
        <div class="card-body">
            <p class="text-muted">{{ __('admin.vendor.payouts_admin.create.description') }}</p>
            <form method="POST" action="{{ route('admin.payouts.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label for="period_start" class="form-label">{{ __('admin.vendor.payouts_admin.create.period_start') }}</label>
                    <input type="date" name="period_start" id="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start') }}" required>
                    @error('period_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="period_end" class="form-label">{{ __('admin.vendor.payouts_admin.create.period_end') }}</label>
                    <input type="date" name="period_end" id="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end') }}" required>
                    @error('period_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">{{ __('admin.vendor.payouts_admin.create.generate') }}</button>
                    <a href="{{ route('admin.payouts.index') }}" class="btn btn-secondary">{{ __('admin.vendor.payouts_admin.create.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
