@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_provinces.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_provinces.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_provinces.title')]]" />
    <x-alert />
    <a href="{{ route('admin.tour-provinces.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.tour_provinces.add') }}</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tour_provinces.table.image') }}</th>
                        <th>{{ __('admin.vendor.tour_provinces.table.name') }}</th>
                        <th>{{ __('admin.vendor.tour_provinces.table.slug') }}</th>
                        <th>{{ __('admin.vendor.tour_provinces.table.country') }}</th>
                        <th>{{ __('admin.vendor.tour_provinces.table.attractions') }}</th>
                        <th>{{ __('admin.vendor.tour_provinces.table.tours') }}</th>
                        <th>{{ __('admin.vendor.tour_provinces.table.featured') }}</th>
                        <th>{{ __('admin.vendor.tour_provinces.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($provinces as $p)
                    <tr>
                        <td>
                            @if($p->image)
                                <img src="{{ $p->image }}" alt="" class="rounded" style="height:40px;width:60px;object-fit:cover">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $p->name }}</td>
                        <td><code>{{ $p->slug }}</code></td>
                        <td>{{ $p->country->name ?? '—' }}</td>
                        <td>{{ $p->attractions_count }}</td>
                        <td>{{ $p->tours_count }}</td>
                        <td>{{ $p->is_featured ? __('admin.vendor.common.yes') : __('admin.vendor.common.no') }}</td>
                        <td>
                            <a href="{{ route('admin.tour-attractions.index', ['province_id' => $p->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.vendor.tour_provinces.attractions') }}</a>
                            <a href="{{ route('admin.tour-provinces.edit', $p) }}" class="btn btn-sm btn-outline-primary">{{ __('admin.vendor.common.edit') }}</a>
                            <form action="{{ route('admin.tour-provinces.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.tour_provinces.confirm_delete') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted">{{ __('admin.vendor.tour_provinces.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $provinces->links() }}
        </div>
    </div>
</div>
@endsection
