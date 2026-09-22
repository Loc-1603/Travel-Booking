@extends('admin.layouts.app')
@section('title', __('admin.vendor.bookings.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.bookings.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.bookings.title')]]" />
    <x-alert />
    <div class="mb-3">
        <a href="{{ route('admin.vendor.bookings.old') }}" class="btn btn-outline-secondary btn-sm">
            <i data-feather="archive" class="icon-sm me-1"></i>{{ __('admin.vendor.bookings.view_old') }}
        </a>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.bookings.filter.hotel') }}</label>
                    <select name="hotel_id" class="form-select form-select-sm">
                        <option value="">{{ __('admin.common.all') }}</option>
                        @foreach($hotels as $h)
                        <option value="{{ $h->id }}" {{ request('hotel_id') == $h->id ? 'selected' : '' }}>{{ $h->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.bookings.filter.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.common.all') }}</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('admin.status.pending') }}</option>
                        <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>{{ __('admin.status.confirmed') }}</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('admin.status.cancelled') }}</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('admin.status.completed') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.bookings.filter.from') }}</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.bookings.filter.to') }}</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.filter') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.bookings.table.booking') }}</th>
                        <th>{{ __('admin.vendor.bookings.table.hotel') }}</th>
                        <th>{{ __('admin.vendor.bookings.table.customer') }}</th>
                        <th>{{ __('admin.vendor.bookings.table.check_in') }}</th>
                        <th>{{ __('admin.vendor.bookings.table.check_out') }}</th>
                        <th>{{ __('admin.vendor.bookings.table.status') }}</th>
                        <th>{{ __('admin.vendor.bookings.table.total') }}</th>
                        <th>{{ __('admin.vendor.bookings.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $b)
                    <tr>
                        <td><code>{{ $b->uuid ?? $b->id }}</code></td>
                        <td>{{ $b->hotel->name ?? '-' }}</td>
                        <td>{{ $b->customer_id ? ($b->customer->name ?? $b->customer->email ?? '-') : ($b->guest_name ?? $b->guest_email ?? '-') }}</td>
                        <td>{{ $b->check_in ? $b->check_in->format('Y-m-d') : '-' }}</td>
                        <td>{{ $b->check_out ? $b->check_out->format('Y-m-d') : '-' }}</td>
                        <td>
                            @php
                                $statusColors = [
                                    'completed' => 'bg-primary',
                                    'confirmed' => 'bg-success',
                                    'pending_payment' => 'bg-warning',
                                    'cancelled' => 'bg-danger',
                                    'pending' => 'bg-secondary',
                                ];
                                $badgeClass = $statusColors[$b->status] ?? 'bg-secondary';
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ __('admin.status.' . $b->status) }}</span>
                        </td>
                        <td>{{ format_vnd($b->total_price ?? 0) }}</td>
                        <td>
                            <a href="{{ route('admin.vendor.bookings.invoice', $b->uuid) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="{{ __('admin.vendor.bookings.view_invoice') }}">{{ __('admin.vendor.bookings.invoice') }}</a>
                            @if($b->status === 'confirmed')
                            <form method="POST" action="{{ route('admin.vendor.bookings.complete', $b->uuid) }}" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.bookings.complete_confirm') }}');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('admin.vendor.bookings.complete') }}">{{ __('admin.vendor.bookings.complete') }}</button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('admin.vendor.bookings.mark-old', $b->uuid) }}" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.bookings.mark_as_old_confirm') }}');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('admin.vendor.bookings.mark_as_old') }}">{{ __('admin.vendor.bookings.mark_as_old') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted">{{ __('admin.vendor.bookings.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection
