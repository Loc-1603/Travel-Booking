@extends('admin.layouts.app')
@section('title', 'My tours')
@section('content')
<div class="container-fluid">
    <x-page-title title="My tours" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => 'My tours']]" />
    <x-alert />
    <a href="{{ route('admin.vendor.tours.create') }}" class="btn btn-primary mb-3">Add tour</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Provider</th>
                        <th>Province</th>
                        <th>Fixed</th>
                        <th>Hourly</th>
                        <th>Daily</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                            <a href="{{ route('admin.vendor.tours.slots', $t) }}" class="btn btn-sm btn-outline-secondary">Slots</a>
                            <a href="{{ route('admin.vendor.tours.edit', $t) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('admin.vendor.tours.destroy', $t) }}" method="POST" class="d-inline" onsubmit="return confirm('Suspend this tour?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Suspend</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted">No tours yet. Create your first 1vs1 tour.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $tours->links() }}
        </div>
    </div>
</div>
@endsection
