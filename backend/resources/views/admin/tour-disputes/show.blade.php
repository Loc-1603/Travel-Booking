@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_disputes.detail_title', ['id' => $tourDispute->id]))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_disputes.detail_title', ['id' => $tourDispute->id]) }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_disputes.title'), 'url' => route('admin.tour-disputes.index')], ['label' => __('admin.vendor.common.view')]]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.tour_disputes.detail.booking') }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('admin.vendor.tour_disputes.detail.uuid') }}:</strong> {{ $tourDispute->booking->uuid ?? $tourDispute->tour_booking_id }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.tour_disputes.detail.tour') }}:</strong> {{ $tourDispute->booking->tour->title ?? '-' }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.tour_disputes.detail.customer') }}:</strong> {{ $tourDispute->booking->customer->name ?? $tourDispute->booking->customer->email ?? '-' }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.tour_disputes.detail.total') }}:</strong> {{ format_vnd($tourDispute->booking->total_price ?? 0) }}</p>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.tour_disputes.detail.contact') }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('admin.vendor.tour_disputes.detail.name') }}:</strong> {{ $tourDispute->contact_name ?: '-' }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.tour_disputes.detail.email') }}:</strong> {{ $tourDispute->contact_email ?: '-' }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.tour_disputes.detail.phone') }}:</strong> {{ $tourDispute->contact_phone ?: '-' }}</p>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.tour_disputes.detail.customer_notes') }}</h5></div>
                <div class="card-body">{{ $tourDispute->customer_notes ?: '-' }}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.tour_disputes.detail.update') }}</h5></div>
                <div class="card-body">
                    <form action="{{ route('admin.tour-disputes.update', $tourDispute) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.tour_disputes.detail.status') }}</label>
                            <select name="status" class="form-select" required>
                                @foreach(['open','in_review','resolved','closed'] as $s)
                                <option value="{{ $s }}" {{ $tourDispute->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.tour_disputes.detail.internal_notes') }}</label>
                            <textarea name="internal_notes" class="form-control" rows="4">{{ old('internal_notes', $tourDispute->internal_notes) }}</textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="refund_payments" value="1" class="form-check-input" id="refund_payments">
                            <label class="form-check-label" for="refund_payments">{{ __('admin.vendor.tour_disputes.detail.refund_hint') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('admin.vendor.tour_disputes.detail.save') }}</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="mb-0"><strong>{{ __('admin.vendor.tour_disputes.detail.resolved') }}:</strong> {{ $tourDispute->resolved_at ? $tourDispute->resolved_at->format('Y-m-d H:i') : '-' }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.tour_disputes.detail.by') }}:</strong> {{ $tourDispute->resolvedBy->name ?? '-' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
