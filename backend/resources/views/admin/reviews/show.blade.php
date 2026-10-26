@extends('admin.layouts.app')
@section('title', __('admin.vendor.reviews.detail.title', ['id' => $review->id]))
@section('content')
@php($isTour = $review->tour_booking_id !== null)
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.reviews.detail.title', ['id' => $review->id]) }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.reviews.title'), 'url' => route('admin.reviews.index', request()->query())], ['label' => __('admin.vendor.common.view')]]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.reviews.detail.title', ['id' => $review->id]) }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.type') }}:</strong> {{ $isTour ? __('admin.vendor.reviews.type.tour') : __('admin.vendor.reviews.type.hotel') }}</p>
                    @if($isTour)
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.tour') }}:</strong> {{ $review->tourBooking->tour->title ?? '-' }}</p>
                    @else
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.hotel') }}:</strong> {{ $review->booking->hotel->name ?? '-' }}</p>
                    @endif
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.customer') }}:</strong> {{ $review->booking?->customer?->name ?? $review->tourBooking?->customer?->name ?? '-' }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.rating') }}:</strong> {{ $review->rating }}/5</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.comment') }}:</strong></p>
                    <p class="mb-2">{{ $review->comment ?: '-' }}</p>
                    @if($review->images->isNotEmpty())
                    <p class="mb-1"><strong>{{ __('admin.vendor.reviews.detail.images') }}:</strong></p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($review->images as $image)
                        <a href="{{ $image->url }}" target="_blank" rel="noopener">
                            <img src="{{ $image->url }}" alt="" style="width: 80px; height: 80px; object-fit: cover;" class="rounded border">
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.reviews.detail.moderate') }}</h5></div>
                <div class="card-body">
                    <p class="mb-2">{{ __('admin.vendor.reviews.detail.hidden') }}: {{ $review->hidden ? __('admin.vendor.reviews.yes') : __('admin.vendor.reviews.no') }}</p>
                    @if($review->hidden)
                    <form action="{{ route('admin.reviews.update', array_merge([$review], request()->query())) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="unhide">
                        <button type="submit" class="btn btn-info btn-sm">{{ __('admin.vendor.reviews.detail.actions.unhide') }}</button>
                    </form>
                    @else
                    <form action="{{ route('admin.reviews.update', array_merge([$review], request()->query())) }}" method="POST" class="d-inline">
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
