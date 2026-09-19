@extends('admin.layouts.app')
@section('title', __('admin.vendor.payouts_admin.detail.title', ['id' => $payout->id]))
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.payouts_admin.detail.title', ['id' => $payout->id]) }}"
        :breadcrumbs="[
            ['label' => 'Admin', 'url' => route('admin.dashboard')],
            ['label' => __('admin.vendor.payouts_admin.title'), 'url' => route('admin.payouts.index')],
            ['label' => '#' . $payout->id]
        ]"
    />
    <x-alert />

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.vendor.payouts_admin.detail.title', ['id' => $payout->id]) }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.vendor') }}</th><td><a href="{{ route('admin.vendors.show', $payout->vendor_id) }}">{{ $payout->vendor->name ?? $payout->vendor_id }}</a> ({{ $payout->vendor->email ?? '' }})</td></tr>
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.period') }}</th><td>{{ $payout->period_start->format('d/m/Y') }} – {{ $payout->period_end->format('d/m/Y') }}</td></tr>
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.gross') }}</th><td>{{ format_vnd($payout->amount) }}</td></tr>
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.commission') }}</th><td>{{ format_vnd($payout->commission) }}</td></tr>
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.net') }}</th><td><strong>{{ format_vnd($payout->net) }}</strong></td></tr>
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.status') }}</th><td>
                            @if($payout->status === 'pending')
                                <span class="badge bg-warning">{{ __('admin.vendor.payouts_admin.status.pending') }}</span>
                            @elseif($payout->status === 'processing')
                                <span class="badge bg-info">{{ __('admin.vendor.payouts_admin.status.processing') }}</span>
                            @else
                                <span class="badge bg-success">{{ __('admin.vendor.payouts_admin.status.paid') }}</span>
                            @endif
                        </td></tr>
                        @if($payout->reference)
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.reference') }}</th><td>{{ $payout->reference }}</td></tr>
                        @endif
                        @if($payout->paid_at)
                        <tr><th>{{ __('admin.vendor.payouts_admin.detail.paid_at') }}</th><td>{{ $payout->paid_at->format('d/m/Y H:i') }}</td></tr>
                        @endif
                    </table>
                    @if(!$payout->isPaid())
                    <form method="POST" action="{{ route('admin.payouts.mark-paid', $payout) }}" class="mt-3">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="reference" class="form-control" placeholder="{{ __('admin.vendor.payouts_admin.detail.reference_placeholder') }}">
                            <button type="submit" class="btn btn-success">{{ __('admin.vendor.payouts_admin.detail.mark_as_paid') }}</button>
                        </div>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ __('admin.vendor.payouts_admin.detail.included_bookings', ['count' => $payout->bookings->count()]) }}</h5>
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.payouts_admin.detail.bookings_table.booking') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.detail.bookings_table.hotel') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.detail.bookings_table.check_in') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.detail.bookings_table.check_out') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.detail.bookings_table.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payout->bookings as $b)
                    <tr>
                        <td><code>{{ $b->uuid ?? $b->id }}</code></td>
                        <td>{{ $b->hotel->name ?? '-' }}</td>
                        <td>{{ $b->check_in?->format('Y-m-d') ?? '-' }}</td>
                        <td>{{ $b->check_out?->format('Y-m-d') ?? '-' }}</td>
                        <td>{{ format_vnd($b->total_price ?? 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
