@extends('admin.layouts.app')
@section('title', __('admin.vendor.disputes.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.disputes.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.disputes.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.disputes.filter.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.disputes.filter.all') }}</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>{{ __('admin.vendor.disputes.filter.open') }}</option>
                        <option value="in_review" {{ request('status') === 'in_review' ? 'selected' : '' }}>{{ __('admin.vendor.disputes.filter.in_review') }}</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>{{ __('admin.vendor.disputes.filter.resolved') }}</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('admin.vendor.disputes.filter.closed') }}</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.disputes.filter.apply') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.disputes.table.id') }}</th>
                        <th>{{ __('admin.vendor.disputes.table.booking') }}</th>
                        <th>{{ __('admin.vendor.disputes.table.hotel') }}</th>
                        <th>{{ __('admin.vendor.disputes.table.status') }}</th>
                        <th>{{ __('admin.vendor.disputes.table.created') }}</th>
                        <th>{{ __('admin.vendor.disputes.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($disputes as $d)
                    <tr>
                        <td>{{ $d->id }}</td>
                        <td><a href="{{ route('admin.disputes.show', $d) }}">{{ $d->booking->uuid ?? $d->booking_id }}</a></td>
                        <td>{{ $d->booking->hotel->name ?? '-' }}</td>
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
                            <span class="badge {{ $badgeClass }}">{{ __('admin.vendor.disputes.filter.' . $d->status) }}</span>
                        </td>
                        <td>{{ $d->created_at->format('Y-m-d') }}</td>
                        <td><a href="{{ route('admin.disputes.show', $d) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.view') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">{{ __('admin.vendor.disputes.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $disputes->links() }}
        </div>
    </div>
</div>
@endsection
