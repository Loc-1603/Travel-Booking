@extends('admin.layouts.app')
@section('title', __('admin.vendor.payouts.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.payouts.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.payouts.title')]]" />
    <x-alert />
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">{{ __('admin.vendor.payouts.summary.title') }}</h5>
            <table class="table table-bordered w-auto">
                <tr><th>{{ __('admin.vendor.payouts.summary.bookings') }}</th><td>{{ number_format($totals['booking_count']) }}</td></tr>
                <tr><th>{{ __('admin.vendor.payouts.summary.gross_revenue') }}</th><td>{{ format_vnd($totals['gross']) }}</td></tr>
                <tr><th>{{ __('admin.vendor.payouts.summary.commission') }}</th><td>{{ format_vnd($totals['commission']) }}</td></tr>
                <tr><th>{{ __('admin.vendor.payouts.summary.net') }}</th><td>{{ format_vnd($totals['net']) }}</td></tr>
            </table>
            <p class="text-muted small mb-0">{{ __('admin.vendor.payouts.summary.help') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ __('admin.vendor.payouts.history.title') }}</h5>
        </div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.payouts.history.table.period') }}</th>
                        <th>{{ __('admin.vendor.payouts.history.table.gross') }}</th>
                        <th>{{ __('admin.vendor.payouts.history.table.commission') }}</th>
                        <th>{{ __('admin.vendor.payouts.history.table.net') }}</th>
                        <th>{{ __('admin.vendor.payouts.history.table.status') }}</th>
                        <th>{{ __('admin.vendor.payouts.history.table.paid_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payouts as $p)
                    <tr>
                        <td>{{ $p->period_start->format('d/m/Y') }} – {{ $p->period_end->format('d/m/Y') }}</td>
                        <td>{{ format_vnd($p->amount) }}</td>
                        <td>{{ format_vnd($p->commission) }}</td>
                        <td><strong>{{ format_vnd($p->net) }}</strong></td>
                        <td>
                            @if($p->status === 'pending')
                                <span class="badge bg-warning">{{ __('admin.vendor.payouts.history.status.pending') }}</span>
                            @elseif($p->status === 'processing')
                                <span class="badge bg-info">{{ __('admin.vendor.payouts.history.status.processing') }}</span>
                            @else
                                <span class="badge bg-success">{{ __('admin.vendor.payouts.history.status.paid') }}</span>
                            @endif
                        </td>
                        <td>{{ $p->paid_at?->format('d/m/Y') ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">{{ __('admin.vendor.payouts.history.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $payouts->links() }}
        </div>
    </div>
</div>
@endsection
