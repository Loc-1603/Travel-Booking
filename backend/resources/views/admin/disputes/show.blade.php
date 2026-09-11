@extends('admin.layouts.app')
@section('title', __('admin.vendor.disputes.detail.title', ['id' => $dispute->id]))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.disputes.detail.title', ['id' => $dispute->id]) }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.disputes.title'), 'url' => route('admin.disputes.index')], ['label' => __('admin.vendor.common.view')]]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.disputes.detail.booking') }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('admin.vendor.disputes.detail.uuid') }}:</strong> {{ $dispute->booking->uuid ?? $dispute->booking->id }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.disputes.detail.hotel') }}:</strong> {{ $dispute->booking->hotel->name ?? '-' }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.disputes.detail.customer') }}:</strong> {{ $dispute->booking->customer->name ?? $dispute->booking->customer->email ?? '-' }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.disputes.detail.check_in_out') }}:</strong> {{ $dispute->booking->check_in?->format('Y-m-d') }} – {{ $dispute->booking->check_out?->format('Y-m-d') }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.disputes.detail.total') }}:</strong> {{ format_vnd($dispute->booking->total_price ?? 0) }}</p>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.disputes.detail.contact') }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('admin.vendor.disputes.detail.name') }}:</strong> {{ $dispute->contact_name ?: '-' }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.disputes.detail.email') }}:</strong> {{ $dispute->contact_email ?: '-' }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.disputes.detail.phone') }}:</strong> {{ $dispute->contact_phone ?: '-' }}</p>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.disputes.detail.customer_notes') }}</h5></div>
                <div class="card-body">{{ $dispute->customer_notes ?: '-' }}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.disputes.detail.update') }}</h5></div>
                <div class="card-body">
                    <form action="{{ route('admin.disputes.update', $dispute) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.disputes.detail.status') }}</label>
                            <select name="status" class="form-select" required>
                                @foreach(['open','in_review','resolved','closed'] as $s)
                                <option value="{{ $s }}" {{ $dispute->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.disputes.detail.internal_notes') }}</label>
                            <textarea name="internal_notes" class="form-control" rows="4">{{ old('internal_notes', $dispute->internal_notes) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('admin.vendor.disputes.detail.save') }}</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="mb-0"><strong>{{ __('admin.vendor.disputes.detail.resolved') }}:</strong> {{ $dispute->resolved_at ? $dispute->resolved_at->format('Y-m-d H:i') : '-' }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.disputes.detail.by') }}:</strong> {{ $dispute->resolvedBy->name ?? '-' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
