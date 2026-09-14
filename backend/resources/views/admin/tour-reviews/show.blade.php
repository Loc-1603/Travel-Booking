@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_reviews.detail_title', ['id' => $tourReview->id]))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_reviews.detail_title', ['id' => $tourReview->id]) }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.tour_reviews.title'), 'url' => route('admin.tour-reviews.index')], ['label' => __('admin.vendor.common.view')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>{{ __('admin.vendor.tour_reviews.detail.tour') }}:</strong> {{ $tourReview->booking->tour->title ?? '-' }}</p>
            <p class="mb-1"><strong>{{ __('admin.vendor.tour_reviews.detail.customer') }}:</strong> {{ $tourReview->booking->customer->name ?? $tourReview->booking->customer->email ?? '-' }}</p>
            <p class="mb-1"><strong>{{ __('admin.vendor.tour_reviews.detail.rating') }}:</strong> {{ $tourReview->rating }}/5</p>
            <p class="mb-1"><strong>{{ __('admin.vendor.tour_reviews.detail.comment') }}:</strong> {{ $tourReview->comment ?: '-' }}</p>
            <p class="mb-0"><strong>{{ __('admin.vendor.tour_reviews.detail.state') }}:</strong> approved={{ $tourReview->approved ? __('admin.vendor.common.yes') : __('admin.vendor.common.no') }}, hidden={{ $tourReview->hidden ? __('admin.vendor.common.yes') : __('admin.vendor.common.no') }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success btn-sm">{{ __('admin.vendor.tour_reviews.detail.approve') }}</button>
            </form>
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline ms-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('admin.vendor.tour_reviews.detail.reject') }}</button>
            </form>
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline ms-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="hide">
                <button type="submit" class="btn btn-outline-warning btn-sm">{{ __('admin.vendor.tour_reviews.detail.hide') }}</button>
            </form>
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline ms-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="unhide">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('admin.vendor.tour_reviews.detail.unhide') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
