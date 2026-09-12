@extends('admin.layouts.app')
@section('title', __('admin.vendor.hotels.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.hotels.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.hotels.title')]]" />
    <x-alert />
    <a href="{{ route('admin.vendor.hotels.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.hotels.add') }}</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.hotels.table.name') }}</th>
                        <th>{{ __('admin.vendor.hotels.table.city') }}</th>
                        <th>{{ __('admin.vendor.hotels.table.country') }}</th>
                        <th>{{ __('admin.vendor.hotels.table.images') }}</th>
                        <th>{{ __('admin.vendor.hotels.table.status') }}</th>
                        <th width="220">{{ __('admin.vendor.hotels.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hotels as $h)
                    <tr>
                        <td>{{ $h->name }}</td>
                        <td>{{ $h->city }}</td>
                        <td>{{ $h->country }}</td>
                        <td>
                            @if($h->images && $h->images->count() > 0)
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-info me-2">{{ $h->images->count() }} {{ __('admin.vendor.hotels.images_count', ['count' => $h->images->count()]) }}</span>
                                    @if($h->bannerImage)
                                    <span class="badge bg-warning">{{ __('admin.vendor.hotels.banner') }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">{{ __('admin.vendor.hotels.no_images') }}</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $h->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $h->status }}</span></td>
                        <td>
                            <a href="{{ route('admin.vendor.hotels.images.index', $h) }}" class="btn btn-sm btn-info me-1">{{ __('admin.vendor.hotels.images') }}</a>
                            <a href="{{ route('admin.vendor.hotels.edit', $h) }}" class="btn btn-sm btn-warning me-1">{{ __('admin.vendor.hotels.edit') }}</a>
                            @if($h->status === 'active')
                            <form action="{{ route('admin.vendor.hotels.destroy', $h) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('{{ __('admin.vendor.hotels.deactivate_confirm') }}')">{{ __('admin.vendor.hotels.deactivate') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">{{ __('admin.vendor.hotels.empty', ['url' => route('admin.vendor.hotels.create')]) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $hotels->links() }}
        </div>
    </div>
</div>
@endsection
