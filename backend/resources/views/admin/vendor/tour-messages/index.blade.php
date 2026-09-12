@extends('admin.layouts.app')
@section('title', 'Tour messages')
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour messages" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => 'Tour messages']]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Tour</th>
                        <th>Customer</th>
                        <th>Messages</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $b)
                    <tr>
                        <td><code>{{ Str::limit($b->uuid, 8) }}</code></td>
                        <td>{{ $b->tour->title ?? '-' }}</td>
                        <td>{{ $b->customer->name ?? $b->customer->email ?? '-' }}</td>
                        <td>{{ $b->messages->count() }}</td>
                        <td><a href="{{ route('admin.vendor.tour-messages.show', $b->uuid) }}" class="btn btn-sm btn-primary">Open</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">No conversations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection
