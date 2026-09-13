@extends('admin.layouts.app')
@section('title', 'Tour attractions')
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour attractions" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour attractions']]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">Province</label>
                    <select name="province_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($provinces as $p)
                        <option value="{{ $p->id }}" {{ request('province_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div>
                <div class="col-auto"><a href="{{ route('admin.tour-attractions.create', request('province_id') ? ['province_id' => request('province_id')] : []) }}" class="btn btn-sm btn-success">Add attraction</a></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Province</th>
                        <th>Famous</th>
                        <th>Actions</th>
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
                            <a href="{{ route('admin.tour-attractions.edit', $a) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('admin.tour-attractions.destroy', $a) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this attraction?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">No attractions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $attractions->links() }}
        </div>
    </div>
</div>
@endsection
