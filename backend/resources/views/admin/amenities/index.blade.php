@extends('admin.layouts.app')
@section('title', __('admin.vendor.amenities.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.amenities.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.amenities.title')]]" />
    <x-alert />
    <a href="{{ route('admin.amenities.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.amenities.add') }}</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.amenities.table.sort') }}</th>
                        <th>{{ __('admin.vendor.amenities.table.name') }}</th>
                        <th>{{ __('admin.vendor.amenities.table.slug') }}</th>
                        <th>{{ __('admin.vendor.amenities.table.icon') }}</th>
                        <th>{{ __('admin.vendor.amenities.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($amenities as $amenity)
                    <tr>
                        <td>{{ $amenity->sort_order }}</td>
                        <td>{{ $amenity->name }}</td>
                        <td><code>{{ $amenity->slug }}</code></td>
                        <td>{{ $amenity->icon ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.amenities.edit', $amenity) }}" class="btn btn-sm btn-outline-primary">{{ __('admin.vendor.common.edit') }}</a>
                            <form action="{{ route('admin.amenities.destroy', $amenity) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.amenities.confirm_delete') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">{{ __('admin.vendor.amenities.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $amenities->links() }}
        </div>
    </div>
</div>
@endsection
