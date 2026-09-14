@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_bookings.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_bookings.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.tour_bookings.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tour_bookings.filter.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.tour_bookings.filter.all') }}</option>
                        @foreach(['pending_payment','confirmed','ongoing','completed','cancelled','disputed','refunded'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tour_bookings.filter.from') }}</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tour_bookings.filter.to') }}</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.tour_bookings.filter.apply') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tour_bookings.table.booking') }}</th>
                        <th>{{ __('admin.vendor.tour_bookings.table.tour') }}</th>
                        <th>{{ __('admin.vendor.tour_bookings.table.customer') }}</th>
                        <th>{{ __('admin.vendor.tour_bookings.table.start') }}</th>
                        <th>{{ __('admin.vendor.tour_bookings.table.status') }}</th>
                        <th>{{ __('admin.vendor.tour_bookings.table.total') }}</th>
                        <th>{{ __('admin.vendor.tour_bookings.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $b)
                    <tr>
                        <td><code>{{ Str::limit($b->uuid, 8) }}</code></td>
                        <td>{{ $b->tour->title ?? '-' }}</td>
                        <td>{{ $b->customer->name ?? $b->customer->email ?? '-' }}</td>
                        <td>{{ $b->start_at?->format('Y-m-d H:i') }}</td>
                        <td><span class="badge bg-secondary">{{ $b->status }}</span></td>
                        <td>{{ format_vnd($b->total_price ?? 0) }}</td>
                        <td>
                            <a href="{{ route('admin.vendor.tour-bookings.invoice', $b->uuid) }}" class="btn btn-sm btn-outline-primary" target="_blank">{{ __('admin.vendor.tour_bookings.invoice') }}</a>
                            <a href="{{ route('admin.vendor.tour-messages.show', $b->uuid) }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.vendor.tour_bookings.chat') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">{{ __('admin.vendor.tour_bookings.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection
