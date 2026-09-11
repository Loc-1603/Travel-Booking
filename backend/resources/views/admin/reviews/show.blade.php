@extends('admin.layouts.app')
@section('title', 'Review')
@section('content')
<div class="container-fluid">
    <x-page-title title="Review #{{ $review->id }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.reviews.title'), 'url' => route('admin.reviews.index')], ['label' => __('admin.vendor.common.view')]]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.reviews.detail.title', ['id' => $review->id]) }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.rating') }}:</strong> {{ $review->rating }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.comment') }}:</strong></p>
                    <p class="mb-2">{{ $review->comment ?: '-' }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.hotel') }}:</strong> {{ $review->booking->hotel->name ?? '-' }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.reviews.detail.customer') }}:</strong> {{ $review->booking->customer->name ?? $review->booking->customer->email ?? '-' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.reviews.detail.moderate') }}</h5></div>
                <div class="card-body">
                    <p class="mb-2">Status: {{ $review->approved ? __('admin.vendor.reviews.detail.status_approved') : __('admin.vendor.reviews.detail.status_not_approved') }} | Hidden: {{ $review->hidden ? __('admin.vendor.reviews.yes') : __('admin.vendor.reviews.no') }}</p>
                    <form action="{{ route('admin.reviews.update', $review) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success btn-sm">{{ __('admin.vendor.reviews.detail.actions.approve') }}</button>
                    </form>
                    <form action="{{ route('admin.reviews.update', $review) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-warning btn-sm">{{ __('admin.vendor.reviews.detail.actions.reject') }}</button>
                    </form>
                    @if($review->hidden)
                    <form action="{{ route('admin.reviews.update', $review) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="unhide">
                        <button type="submit" class="btn btn-info btn-sm">{{ __('admin.vendor.reviews.detail.actions.unhide') }}</button>
                    </form>
                    @else
                    <form action="{{ route('admin.reviews.update', $review) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="hide">
                        <button type="submit" class="btn btn-secondary btn-sm">{{ __('admin.vendor.reviews.detail.actions.hide') }}</button>
                    </form>
                    @endif
                </div>
            </div>
            @if($review->moderated_at)
            <p class="text-muted small">{{ __('admin.vendor.reviews.detail.moderated', ['date' => $review->moderated_at->format('Y-m-d H:i'), 'name' => $review->moderatedBy->name ?? '-']) }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
