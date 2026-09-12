@extends('admin.layouts.app')
@section('title', 'Slots: '.$tour->title)
@section('content')
<div class="container-fluid">
    <x-page-title title="Slots: {{ $tour->title }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => 'My tours', 'url' => route('admin.vendor.tours.index')], ['label' => 'Slots']]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Add / update slot (capacity always 1)</h5></div>
        <div class="card-body">
            <form action="{{ route('admin.vendor.tours.slots.store', $tour) }}" method="POST" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label mb-0">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ old('date') }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">Start</label>
                    <input type="time" name="start_time" class="form-control form-control-sm" value="{{ old('start_time', '08:00') }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">End</label>
                    <input type="time" name="end_time" class="form-control form-control-sm" value="{{ old('end_time', '12:00') }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="available">available</option>
                        <option value="blocked">blocked</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">Price override (VND)</label>
                    <input type="number" name="price_override" class="form-control form-control-sm" min="0" step="1000" placeholder="—">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Save slot</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Booking</th>
                        <th>Price override</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($slots as $s)
                    <tr>
                        <td>{{ $s->date?->format('Y-m-d') }}</td>
                        <td>{{ substr($s->start_time, 0, 5) }}</td>
                        <td>{{ substr($s->end_time, 0, 5) }}</td>
                        <td><span class="badge bg-secondary">{{ $s->status }}</span></td>
                        <td>{{ $s->tour_booking_id ?? '—' }}</td>
                        <td>{{ $s->price_override ? format_vnd($s->price_override) : '—' }}</td>
                        <td>
                            @if($s->status !== 'booked')
                            <form action="{{ route('admin.vendor.tours.slots.destroy', [$tour, $s]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this slot?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">No slots yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $slots->links() }}
        </div>
    </div>
</div>
@endsection
