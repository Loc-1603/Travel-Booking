@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_disputes.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_disputes.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_disputes.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tour_disputes.filter.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.tour_disputes.filter.all') }}</option>
                        @foreach(['open','in_review','resolved','closed'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ __('admin.vendor.tour_disputes.filter.' . $s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.tour_disputes.filter.apply') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tour_disputes.table.id') }}</th>
                        <th>{{ __('admin.vendor.tour_disputes.table.booking') }}</th>
                        <th>{{ __('admin.vendor.tour_disputes.table.tour') }}</th>
                        <th>{{ __('admin.vendor.tour_disputes.table.status') }}</th>
                        <th>{{ __('admin.vendor.tour_disputes.table.created') }}</th>
                        <th>{{ __('admin.vendor.tour_disputes.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($disputes as $d)
                    <tr>
                        <td>{{ $d->id }}</td>
                        <td><a href="{{ route('admin.tour-disputes.show', $d) }}">{{ $d->booking->uuid ?? $d->tour_booking_id }}</a></td>
                        <td>{{ $d->booking->tour->title ?? '-' }}</td>
                        <td>
                            @php
                                $statusColors = [
                                    'open' => 'bg-warning',
                                    'in_review' => 'bg-info',
                                    'resolved' => 'bg-success',
                                    'closed' => 'bg-secondary',
                                ];
                                $badgeClass = $statusColors[$d->status] ?? 'bg-secondary';
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ __('admin.vendor.tour_disputes.filter.' . $d->status) }}</span>
                        </td>
                        <td>{{ $d->created_at->format('Y-m-d') }}</td>
                        <td><a href="{{ route('admin.tour-disputes.show', $d) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.view') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">{{ __('admin.vendor.tour_disputes.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $disputes->links() }}
        </div>
    </div>
</div>
@endsection
