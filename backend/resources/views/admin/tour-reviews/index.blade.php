@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_reviews.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_reviews.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_reviews.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.tour_reviews.filter.label') }}</label>
                    <select name="filter" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.tour_reviews.filter.all') }}</option>
                        @foreach(['pending','approved','rejected','hidden'] as $f)
                        <option value="{{ $f }}" {{ request('filter') === $f ? 'selected' : '' }}>{{ $f }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.tour_reviews.filter.apply') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.tour_reviews.table.id') }}</th>
                        <th>{{ __('admin.vendor.tour_reviews.table.tour') }}</th>
                        <th>{{ __('admin.vendor.tour_reviews.table.rating') }}</th>
                        <th>{{ __('admin.vendor.tour_reviews.table.comment') }}</th>
                        <th>{{ __('admin.vendor.tour_reviews.table.approved') }}</th>
                        <th>{{ __('admin.vendor.tour_reviews.table.hidden') }}</th>
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
                        <td>{{ $r->approved ? __('admin.vendor.common.yes') : __('admin.vendor.common.no') }}</td>
                        <td>{{ $r->hidden ? __('admin.vendor.common.yes') : __('admin.vendor.common.no') }}</td>
                        <td><a href="{{ route('admin.tour-reviews.show', $r) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.view') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">{{ __('admin.vendor.tour_reviews.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $reviews->links() }}
        </div>
    </div>
</div>
@endsection
