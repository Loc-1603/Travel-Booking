@extends('admin.layouts.app')
@section('title', 'Tour disputes')
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour disputes" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour disputes']]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['open','in_review','resolved','closed'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Booking</th>
                        <th>Tour</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($disputes as $d)
                    <tr>
                        <td>{{ $d->id }}</td>
                        <td><a href="{{ route('admin.tour-disputes.show', $d) }}">{{ $d->booking->uuid ?? $d->tour_booking_id }}</a></td>
                        <td>{{ $d->booking->tour->title ?? '-' }}</td>
                        <td><span class="badge bg-secondary">{{ $d->status }}</span></td>
                        <td>{{ $d->created_at->format('Y-m-d') }}</td>
                        <td><a href="{{ route('admin.tour-disputes.show', $d) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">No tour disputes.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $disputes->links() }}
        </div>
    </div>
</div>
@endsection
