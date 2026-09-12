@extends('admin.layouts.app')
@section('title', __('admin.vendor.rooms.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.rooms.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.rooms.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.rooms.form.hotel') }}</label>
                    <select name="hotel_id" class="form-select form-select-sm">
                        <option value="">{{ __('admin.common.all') }}</option>
                        @foreach($hotels as $h)
                        <option value="{{ $h->id }}" {{ request('hotel_id') == $h->id ? 'selected' : '' }}>{{ $h->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.filter') }}</button></div>
            </form>
        </div>
    </div>
    <a href="{{ route('admin.vendor.rooms.create', request()->only('hotel_id')) }}" class="btn btn-primary mb-3">{{ __('admin.vendor.rooms.add') }}</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.rooms.table.room') }}</th>
                        <th>{{ __('admin.vendor.rooms.table.hotel') }}</th>
                        <th>{{ __('admin.vendor.rooms.table.images') }}</th>
                        <th>{{ __('admin.vendor.rooms.table.capacity') }}</th>
                        <th>{{ __('admin.vendor.rooms.table.base_price') }}</th>
                        <th>{{ __('admin.vendor.rooms.table.total_rooms') }}</th>
                        <th width="240">{{ __('admin.vendor.rooms.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rooms as $r)
                    <tr>
                        <td>{{ $r->name }}</td>
                        <td>{{ $r->hotel->name ?? '-' }}</td>
                        <td>
                            @if($r->images && $r->images->count() > 0)
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-info me-2">{{ $r->images->count() }} {{ __('admin.vendor.rooms.images_count', ['count' => $r->images->count()]) }}</span>
                                    @if($r->bannerImage)
                                    <span class="badge bg-warning">{{ __('admin.vendor.rooms.banner') }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">{{ __('admin.vendor.rooms.no_images') }}</span>
                            @endif
                        </td>
                        <td>{{ $r->capacity }}</td>
                        <td>{{ format_vnd($r->base_price) }}</td>
                        <td>{{ $r->total_rooms }}</td>
                        <td>
                            <a href="{{ route('admin.vendor.rooms.images.index', $r) }}" class="btn btn-sm btn-info me-1">{{ __('admin.vendor.rooms.images') }}</a>
                            <a href="{{ route('admin.vendor.rooms.availability', $r) }}" class="btn btn-sm btn-secondary me-1">{{ __('admin.vendor.rooms.availability_label') }}</a>
                            <a href="{{ route('admin.vendor.rooms.edit', $r) }}" class="btn btn-sm btn-warning me-1">{{ __('admin.vendor.rooms.edit') }}</a>
                            <form action="{{ route('admin.vendor.rooms.destroy', $r) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ __('admin.vendor.rooms.delete_confirm') }}')">{{ __('admin.vendor.rooms.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">{{ __('admin.vendor.rooms.empty', ['url' => route('admin.vendor.rooms.create')]) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $rooms->links() }}
        </div>
    </div>
</div>
@endsection
