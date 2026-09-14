@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_attractions.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_attractions.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_attractions.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tour_attractions.filter.province') }}</label>
                    <select name="province_id" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.tour_attractions.filter.all') }}</option>
                        @foreach($provinces as $p)
                        <option value="{{ $p->id }}" {{ request('province_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.tour_attractions.filter.apply') }}</button></div>
                <div class="col-auto"><a href="{{ route('admin.tour-attractions.create', request('province_id') ? ['province_id' => request('province_id')] : []) }}" class="btn btn-sm btn-success">{{ __('admin.vendor.tour_attractions.add') }}</a></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tour_attractions.table.image') }}</th>
                        <th>{{ __('admin.vendor.tour_attractions.table.name') }}</th>
                        <th>{{ __('admin.vendor.tour_attractions.table.province') }}</th>
                        <th>{{ __('admin.vendor.tour_attractions.table.famous') }}</th>
                        <th>{{ __('admin.vendor.tour_attractions.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attractions as $a)
                    <tr>
                        <td>
                            @if($a->image)
                                <img src="{{ $a->image }}" alt="" class="rounded" style="height:40px;width:60px;object-fit:cover">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $a->name }}</td>
                        <td>{{ $a->province->name ?? '—' }}</td>
                        <td>{{ $a->is_famous ? '★' : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.tour-attractions.edit', $a) }}" class="btn btn-sm btn-outline-primary">{{ __('admin.vendor.common.edit') }}</a>
                            <form action="{{ route('admin.tour-attractions.destroy', $a) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.tour_attractions.confirm_delete') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">{{ __('admin.vendor.tour_attractions.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $attractions->links() }}
        </div>
    </div>
</div>
@endsection
