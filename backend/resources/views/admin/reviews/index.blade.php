@extends('admin.layouts.app')
@section('title', __('admin.vendor.reviews.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.reviews.title') }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.reviews.title')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.reviews.filter.label') }}</label>
                    <select name="filter" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.reviews.filter.all') }}</option>
                        <option value="visible" {{ request('filter') === 'visible' ? 'selected' : '' }}>{{ __('admin.vendor.reviews.filter.visible') }}</option>
                        <option value="hidden" {{ request('filter') === 'hidden' ? 'selected' : '' }}>{{ __('admin.vendor.reviews.filter.hidden') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.reviews.filter.type_label') }}</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.reviews.filter.all') }}</option>
                        <option value="hotel" {{ request('type') === 'hotel' ? 'selected' : '' }}>{{ __('admin.vendor.reviews.type.hotel') }}</option>
                        <option value="tour" {{ request('type') === 'tour' ? 'selected' : '' }}>{{ __('admin.vendor.reviews.type.tour') }}</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.reviews.filter.apply') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.reviews.table.id') }}</th>
                        <th>{{ __('admin.vendor.reviews.table.type') }}</th>
                        <th>{{ __('admin.vendor.reviews.table.target') }}</th>
                        <th>{{ __('admin.vendor.reviews.table.rating') }}</th>
                        <th>{{ __('admin.vendor.reviews.table.comment') }}</th>
                        <th>{{ __('admin.vendor.reviews.table.hidden') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>{{ $r->tour_booking_id ? __('admin.vendor.reviews.type.tour') : __('admin.vendor.reviews.type.hotel') }}</td>
                        <td>{{ $r->booking->hotel->name ?? $r->tourBooking->tour->title ?? '-' }}</td>
                        <td>{{ $r->rating }}</td>
                        <td>{{ Str::limit($r->comment, 40) }}</td>
                        <td>{{ $r->hidden ? __('admin.vendor.reviews.yes') : __('admin.vendor.reviews.no') }}</td>
                        <td><a href="{{ route('admin.reviews.show', array_merge([$r], request()->query())) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.view') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">{{ __('admin.vendor.reviews.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $reviews->links() }}
        </div>
    </div>
</div>
@endsection
