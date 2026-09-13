@extends('admin.layouts.app')
@section('title', __('admin.vendor.cities.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.cities.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.cities.title')]]" />
    <x-alert />
    <a href="{{ route('admin.cities.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.cities.add') }}</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.cities.table.image') }}</th>
                        <th>{{ __('admin.vendor.cities.table.name') }}</th>
                        <th>{{ __('admin.vendor.cities.table.country') }}</th>
                        <th>{{ __('admin.vendor.cities.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cities as $city)
                    <tr>
                        <td>
                            @if($city->image)
                                <img src="{{ asset('storage/'.$city->image) }}" alt="" class="rounded" style="height:40px;width:60px;object-fit:cover">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $city->name }}</td>
                        <td>{{ $city->country->name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.cities.edit', $city) }}" class="btn btn-sm btn-outline-primary">{{ __('admin.vendor.common.edit') }}</a>
                            <form action="{{ route('admin.cities.destroy', $city) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.cities.confirm_delete') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted">{{ __('admin.vendor.cities.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $cities->links() }}
        </div>
    </div>
</div>
@endsection
