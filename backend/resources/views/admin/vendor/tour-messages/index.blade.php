@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_messages.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_messages.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.tour_messages.title')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tour_messages.table.booking') }}</th>
                        <th>{{ __('admin.vendor.tour_messages.table.tour') }}</th>
                        <th>{{ __('admin.vendor.tour_messages.table.customer') }}</th>
                        <th>{{ __('admin.vendor.tour_messages.table.messages') }}</th>
                        <th>{{ __('admin.vendor.tour_messages.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody data-tour-messages-index>
                    @forelse($bookings as $b)
                    @php
                        $unread = $b->messages
                            ->where('sender_id', '!=', auth()->id())
                            ->whereNull('read_at')
                            ->count();
                    @endphp
                    <tr data-booking-uuid="{{ $b->uuid }}" class="{{ $unread > 0 ? 'table-warning' : '' }}">
                        <td><code>{{ Str::limit($b->uuid, 8) }}</code></td>
                        <td>{{ $b->tour->title ?? '-' }}</td>
                        <td>{{ $b->customer->name ?? $b->customer->email ?? '-' }}</td>
                        <td>
                            <span data-row-messages>{{ $b->messages->count() }}</span>
                            @if($unread > 0)
                                <span data-row-unread data-count="{{ $unread }}" class="badge bg-danger rounded-pill">{{ $unread }} {{ __('admin.vendor.tour_messages.table.unread') }}</span>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.vendor.tour-messages.show', $b->uuid) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.tour_messages.open') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">{{ __('admin.vendor.tour_messages.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection
