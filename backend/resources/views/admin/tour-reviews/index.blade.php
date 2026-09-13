@extends('admin.layouts.app')
@section('title', 'Tour reviews')
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour reviews" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour reviews']]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">Filter</label>
                    <select name="filter" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['pending','approved','rejected','hidden'] as $f)
                        <option value="{{ $f }}" {{ request('filter') === $f ? 'selected' : '' }}>{{ $f }}</option>
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
                        <th>Tour</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Approved</th>
                        <th>Hidden</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>{{ $r->booking->tour->title ?? '-' }}</td>
                        <td>{{ $r->rating }}</td>
                        <td>{{ Str::limit($r->comment, 40) }}</td>
                        <td>{{ $r->approved ? 'Yes' : 'No' }}</td>
                        <td>{{ $r->hidden ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('admin.tour-reviews.show', $r) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">No tour reviews.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $reviews->links() }}
        </div>
    </div>
</div>
@endsection
