@extends('admin.layouts.app')
@section('title', __('admin.vendor.tour_messages.chat_title', ['title' => $booking->tour->title ?? 'Tour']))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.tour_messages.chat_title', ['title' => $booking->tour->title ?? 'Tour']) }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.tour_messages.title'), 'url' => route('admin.vendor.tour-messages.index')], ['label' => __('admin.vendor.tour_messages.chat')]]" />
    <x-alert />
    <div id="tour-message-sent-alert" class="alert alert-success d-none" role="alert"></div>
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>{{ __('admin.vendor.tour_messages.detail.customer') }}:</strong> {{ $booking->customer->name ?? $booking->customer->email ?? '-' }}</p>
            <p class="mb-0"><strong>{{ __('admin.vendor.tour_messages.detail.slot') }}:</strong> {{ $booking->start_at?->format('Y-m-d H:i') }} – {{ $booking->end_at?->format('H:i') }} ({{ $booking->status }})</p>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body" data-tour-messages-thread data-booking-uuid="{{ $booking->uuid }}" style="max-height:420px;overflow-y:auto">
            @forelse($booking->messages as $m)
            @php $isMine = (int) $m->sender_id === (int) auth()->id(); @endphp
            <div data-message data-message-id="{{ $m->id }}" data-sender-id="{{ $m->sender_id }}" data-created-at="{{ $m->created_at?->toIso8601String() }}" data-read-at="{{ $m->read_at?->toIso8601String() }}" class="mb-2 p-2 rounded {{ $isMine ? 'bg-primary text-white ms-5' : 'bg-light me-5' }}">
                <div class="small opacity-75">{{ $m->sender->name ?? 'User' }} · {{ $m->created_at?->format('Y-m-d H:i') }}</div>
                <div>{{ $m->body }}</div>
                {{-- Read receipt only on the vendor's own messages (same as customer chat). --}}
                @if($isMine)
                <div class="small mt-1 message-read-label {{ $m->read_at ? 'opacity-75' : 'text-danger fw-semibold' }}">
                    {{ $m->read_at
                        ? __('admin.vendor.tour_messages.detail.read')
                        : __('admin.vendor.tour_messages.detail.unread') }}
                </div>
                @endif
            </div>
            @empty
            <p class="text-muted" data-empty-hint>{{ __('admin.vendor.tour_messages.detail.no_messages') }}</p>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form id="tour-message-reply-form" action="{{ route('admin.vendor.tour-messages.reply', $booking->uuid) }}" method="POST" data-booking-uuid="{{ $booking->uuid }}">
                @csrf
                <div class="input-group">
                    <input type="text" name="body" class="form-control" placeholder="{{ __('admin.vendor.tour_messages.detail.reply_placeholder') }}" maxlength="2000" required autocomplete="off">
                    <button type="submit" class="btn btn-primary">{{ __('admin.vendor.tour_messages.detail.send') }}</button>
                </div>
                <div class="text-danger small mt-1 d-none" data-reply-error></div>
            </form>
        </div>
    </div>
</div>
@endsection
