@extends('admin.layouts.app')
@section('title', __('admin.vendor.rooms.availability.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.rooms.availability.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.rooms.title'), 'url' => route('admin.vendor.rooms.index')], ['label' => __('admin.vendor.rooms.availability.title')]]" />
    <x-alert />
    <p class="text-muted">{{ __('admin.vendor.rooms.availability.room_info', ['room' => $room->name, 'hotel' => $room->hotel->name ?? '-', 'price' => format_vnd($room->base_price)]) }}</p>
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.rooms.availability.add_date') }}</h5></div>
        <div class="card-body">
            <form action="{{ route('admin.vendor.rooms.availability.store', $room) }}" method="POST" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.rooms.availability.date') }}</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.rooms.availability.available_rooms') }}</label>
                    <input type="number" name="available_rooms" class="form-control" min="0" value="0" required>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.rooms.availability.price_override') }}</label>
                    <input type="number" name="price_override" class="form-control" step="0.01" min="0" placeholder="{{ __('admin.vendor.rooms.availability.price_override_help') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ __('admin.vendor.rooms.availability.save') }}</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.rooms.availability.table.date') }}</th>
                        <th>{{ __('admin.vendor.rooms.availability.table.available_rooms') }}</th>
                        <th>{{ __('admin.vendor.rooms.availability.table.price_override') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($availability as $a)
                    <tr>
                        <td>{{ $a->date->format('Y-m-d') }}</td>
                        <td>{{ $a->available_rooms }}</td>
                        <td>{{ $a->price_override ? format_vnd($a->price_override) : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-muted">{{ __('admin.vendor.rooms.availability.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $availability->links() }}
        </div>
    </div>
    <a href="{{ route('admin.vendor.rooms.index') }}" class="btn btn-secondary mt-2">{{ __('admin.vendor.rooms.availability.back_to_rooms') }}</a>
</div>
@endsection
