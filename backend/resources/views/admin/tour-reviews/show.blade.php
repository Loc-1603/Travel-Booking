@extends('admin.layouts.app')
@section('title', 'Tour review #'.$tourReview->id)
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour review #{{ $tourReview->id }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour reviews', 'url' => route('admin.tour-reviews.index')], ['label' => 'View']]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>Tour:</strong> {{ $tourReview->booking->tour->title ?? '-' }}</p>
            <p class="mb-1"><strong>Customer:</strong> {{ $tourReview->booking->customer->name ?? $tourReview->booking->customer->email ?? '-' }}</p>
            <p class="mb-1"><strong>Rating:</strong> {{ $tourReview->rating }}/5</p>
            <p class="mb-1"><strong>Comment:</strong> {{ $tourReview->comment ?: '-' }}</p>
            <p class="mb-0"><strong>State:</strong> approved={{ $tourReview->approved ? 'yes' : 'no' }}, hidden={{ $tourReview->hidden ? 'yes' : 'no' }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success btn-sm">Approve</button>
            </form>
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline ms-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-outline-secondary btn-sm">Reject</button>
            </form>
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline ms-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="hide">
                <button type="submit" class="btn btn-outline-warning btn-sm">Hide</button>
            </form>
            <form action="{{ route('admin.tour-reviews.update', $tourReview) }}" method="POST" class="d-inline ms-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="unhide">
                <button type="submit" class="btn btn-outline-primary btn-sm">Unhide</button>
            </form>
        </div>
    </div>
</div>
@endsection
