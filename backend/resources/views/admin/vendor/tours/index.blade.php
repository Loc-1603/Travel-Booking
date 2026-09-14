@extends('admin.layouts.app')
@section('title', __('admin.vendor.tours.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tours.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.tours.title')]]" />
    <x-alert />
    <a href="{{ route('admin.vendor.tours.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.tours.add') }}</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tours.table.title') }}</th>
                        <th>{{ __('admin.vendor.tours.table.provider') }}</th>
                        <th>{{ __('admin.vendor.tours.table.province') }}</th>
                        <th>{{ __('admin.vendor.tours.table.fixed') }}</th>
                        <th>{{ __('admin.vendor.tours.table.hourly') }}</th>
                        <th>{{ __('admin.vendor.tours.table.daily') }}</th>
                        <th>{{ __('admin.vendor.tours.table.status') }}</th>
                        <th>{{ __('admin.vendor.tours.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tours as $t)
                    <tr>
                        <td>{{ $t->title }}</td>
                        <td>{{ $t->provider->business_name ?? '—' }}</td>
                        <td>{{ $t->province->name ?? '—' }}</td>
                        <td>{{ format_vnd($t->base_fixed) }}</td>
                        <td>{{ format_vnd($t->base_price_hourly) }}</td>
                        <td>{{ format_vnd($t->base_price_daily) }}</td>
                        <td><span class="badge bg-secondary">{{ $t->status }}</span></td>
                        <td>
                            <a href="{{ route('admin.vendor.tours.slots', $t) }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.vendor.tours.actions.slots') }}</a>
                            <a href="{{ route('admin.vendor.tours.edit', $t) }}" class="btn btn-sm btn-outline-primary">{{ __('admin.vendor.tours.actions.edit') }}</a>
                            <form action="{{ route('admin.vendor.tours.destroy', $t) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.tours.actions.suspend_confirm') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.tours.actions.suspend') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted">{{ __('admin.vendor.tours.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $tours->links() }}
        </div>
    </div>
</div>
@endsection
