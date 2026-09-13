@extends('admin.layouts.app')
@section('title', 'Tour dispute #'.$tourDispute->id)
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour dispute #{{ $tourDispute->id }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour disputes', 'url' => route('admin.tour-disputes.index')], ['label' => 'View']]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Booking</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>UUID:</strong> {{ $tourDispute->booking->uuid ?? $tourDispute->tour_booking_id }}</p>
                    <p class="mb-1"><strong>Tour:</strong> {{ $tourDispute->booking->tour->title ?? '-' }}</p>
                    <p class="mb-1"><strong>Customer:</strong> {{ $tourDispute->booking->customer->name ?? $tourDispute->booking->customer->email ?? '-' }}</p>
                    <p class="mb-0"><strong>Total:</strong> {{ format_vnd($tourDispute->booking->total_price ?? 0) }}</p>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Contact</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>Name:</strong> {{ $tourDispute->contact_name ?: '-' }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ $tourDispute->contact_email ?: '-' }}</p>
                    <p class="mb-0"><strong>Phone:</strong> {{ $tourDispute->contact_phone ?: '-' }}</p>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Customer notes</h5></div>
                <div class="card-body">{{ $tourDispute->customer_notes ?: '-' }}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Update</h5></div>
                <div class="card-body">
                    <form action="{{ route('admin.tour-disputes.update', $tourDispute) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach(['open','in_review','resolved','closed'] as $s)
                                <option value="{{ $s }}" {{ $tourDispute->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Internal notes</label>
                            <textarea name="internal_notes" class="form-control" rows="4">{{ old('internal_notes', $tourDispute->internal_notes) }}</textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="refund_payments" value="1" class="form-check-input" id="refund_payments">
                            <label class="form-check-label" for="refund_payments">Full-refund completed tour payments on save (settled in VNPay portal; local accounting only)</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="mb-0"><strong>Resolved:</strong> {{ $tourDispute->resolved_at ? $tourDispute->resolved_at->format('Y-m-d H:i') : '-' }}</p>
                    <p class="mb-0"><strong>By:</strong> {{ $tourDispute->resolvedBy->name ?? '-' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
