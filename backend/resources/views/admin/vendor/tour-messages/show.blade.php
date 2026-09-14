@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_messages.chat_title', ['title' => $booking->tour->title ?? 'Tour']))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_messages.chat_title', ['title' => $booking->tour->title ?? 'Tour']) }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.tour_messages.title'), 'url' => route('admin.vendor.tour-messages.index')], ['label' => __('admin.vendor.tour_messages.chat')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>{{ __('admin.vendor.tour_messages.detail.customer') }}:</strong> {{ $booking->customer->name ?? $booking->customer->email ?? '-' }}</p>
            <p class="mb-0"><strong>{{ __('admin.vendor.tour_messages.detail.slot') }}:</strong> {{ $booking->start_at?->format('Y-m-d H:i') }} – {{ $booking->end_at?->format('H:i') }} ({{ $booking->status }})</p>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body" style="max-height:420px;overflow-y:auto">
            @forelse($booking->messages as $m)
            <div class="mb-2 p-2 rounded {{ (int) $m->sender_id === (int) auth()->id() ? 'bg-primary text-white ms-5' : 'bg-light me-5' }}">
                <div class="small opacity-75">{{ $m->sender->name ?? 'User' }} · {{ $m->created_at?->format('Y-m-d H:i') }}</div>
                <div>{{ $m->body }}</div>
            </div>
            @empty
            <p class="text-muted">{{ __('admin.vendor.tour_messages.detail.no_messages') }}</p>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.tour-messages.reply', $booking->uuid) }}" method="POST">
                @csrf
                <div class="input-group">
                    <input type="text" name="body" class="form-control" placeholder="{{ __('admin.vendor.tour_messages.detail.reply_placeholder') }}" maxlength="2000" required>
                    <button type="submit" class="btn btn-primary">{{ __('admin.vendor.tour_messages.detail.send') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
