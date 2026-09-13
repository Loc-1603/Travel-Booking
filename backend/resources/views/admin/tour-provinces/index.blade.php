@extends('admin.layouts.app')
@section('title', 'Tour provinces')
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour provinces" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour provinces']]" />
    <x-alert />
    <a href="{{ route('admin.tour-provinces.create') }}" class="btn btn-primary mb-3">Add province</a>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Country</th>
                        <th>Attractions</th>
                        <th>Tours</th>
                        <th>Featured</th>
                        <th>Actions</th>
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
                        <td>{{ $p->is_featured ? 'Yes' : 'No' }}</td>
                        <td>
                            <a href="{{ route('admin.tour-attractions.index', ['province_id' => $p->id]) }}" class="btn btn-sm btn-outline-secondary">Attractions</a>
                            <a href="{{ route('admin.tour-provinces.edit', $p) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('admin.tour-provinces.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this province?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted">No tour provinces yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $provinces->links() }}
        </div>
    </div>
</div>
@endsection
