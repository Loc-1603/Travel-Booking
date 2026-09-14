@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_providers.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_providers.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_providers.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tour_providers.filter.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.tour_providers.filter.all') }}</option>
                        @foreach(['pending','approved','rejected'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.tour_providers.filter.apply') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tour_providers.table.id') }}</th>
                        <th>{{ __('admin.vendor.tour_providers.table.business') }}</th>
                        <th>{{ __('admin.vendor.tour_providers.table.vendor') }}</th>
                        <th>{{ __('admin.vendor.tour_providers.table.tours') }}</th>
                        <th>{{ __('admin.vendor.tour_providers.table.status') }}</th>
                        <th>{{ __('admin.vendor.tour_providers.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providers as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td>{{ $p->business_name }}</td>
                        <td>{{ $p->vendor->name ?? '-' }} ({{ $p->vendor->email ?? '-' }})</td>
                        <td>{{ $p->tours->count() }}</td>
                        <td><span class="badge bg-secondary">{{ $p->status }}</span></td>
                        <td><a href="{{ route('admin.tour-providers.show', $p) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.view') }}</a> <a href="{{ route('admin.tour-providers.edit', $p) }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.vendor.common.edit') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">{{ __('admin.vendor.tour_providers.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $providers->links() }}
        </div>
    </div>
</div>
@endsection
