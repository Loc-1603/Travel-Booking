@extends('admin.layouts.app')
@section('title', __('admin.vendor.tours.slots.title', ['title' => $tour->title]))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tours.slots.title', ['title' => $tour->title]) }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.tours.title'), 'url' => route('admin.vendor.tours.index')], ['label' => __('admin.vendor.tours.actions.slots')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.tours.slots.heading') }}</h5></div>
        <div class="card-body">
            <form action="{{ route('admin.vendor.tours.slots.store', $tour) }}" method="POST" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tours.slots.date') }}</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ old('date') }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tours.slots.start') }}</label>
                    <input type="time" name="start_time" class="form-control form-control-sm" value="{{ old('start_time', '08:00') }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tours.slots.end') }}</label>
                    <input type="time" name="end_time" class="form-control form-control-sm" value="{{ old('end_time', '12:00') }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tours.slots.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="available">available</option>
                        <option value="blocked">blocked</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tours.slots.price_override') }}</label>
                    <input type="number" name="price_override" class="form-control form-control-sm" min="0" step="1000" placeholder="—">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.tours.slots.save') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tours.slots.table.date') }}</th>
                        <th>{{ __('admin.vendor.tours.slots.table.start') }}</th>
                        <th>{{ __('admin.vendor.tours.slots.table.end') }}</th>
                        <th>{{ __('admin.vendor.tours.slots.table.status') }}</th>
                        <th>{{ __('admin.vendor.tours.slots.table.booking') }}</th>
                        <th>{{ __('admin.vendor.tours.slots.table.price_override') }}</th>
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
                            <form action="{{ route('admin.vendor.tours.slots.destroy', [$tour, $s]) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.tours.slots.remove_confirm') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.tours.slots.remove') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">{{ __('admin.vendor.tours.slots.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $slots->links() }}
        </div>
    </div>
</div>
@endsection
