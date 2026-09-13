@extends('admin.layouts.app')
@section('title', __('admin.vendor.countries.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.countries.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.countries.title')]]" />
    <x-alert />
    <a href="{{ route('admin.countries.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.countries.add') }}</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.countries.table.image') }}</th>
                        <th>{{ __('admin.vendor.countries.table.name') }}</th>
                        <th>{{ __('admin.vendor.countries.table.code') }}</th>
                        <th>{{ __('admin.vendor.countries.table.cities') }}</th>
                        <th>{{ __('admin.vendor.countries.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($countries as $country)
                    <tr>
                        <td>
                            @if($country->image)
                                <img src="{{ asset('storage/'.$country->image) }}" alt="" class="rounded" style="height:40px;width:60px;object-fit:cover">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $country->name }}</td>
                        <td>{{ $country->code ?? '—' }}</td>
                        <td>{{ $country->cities_count }}</td>
                        <td>
                            <a href="{{ route('admin.countries.edit', $country) }}" class="btn btn-sm btn-outline-primary">{{ __('admin.vendor.common.edit') }}</a>
                            <form action="{{ route('admin.countries.destroy', $country) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.countries.confirm_delete') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">{{ __('admin.vendor.countries.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $countries->links() }}
        </div>
    </div>
</div>
@endsection
