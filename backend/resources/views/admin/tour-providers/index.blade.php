@extends('admin.layouts.app')
@section('title', 'Tour providers')
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour providers" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour providers']]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['pending','approved','rejected'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Business</th>
                        <th>Vendor</th>
                        <th>Tours</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                        <td><a href="{{ route('admin.tour-providers.show', $p) }}" class="btn btn-sm btn-primary">View</a> <a href="{{ route('admin.tour-providers.edit', $p) }}" class="btn btn-sm btn-outline-secondary">Edit</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">No tour providers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $providers->links() }}
        </div>
    </div>
</div>
@endsection
