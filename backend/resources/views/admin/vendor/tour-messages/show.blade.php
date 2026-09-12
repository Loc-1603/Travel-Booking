@extends('admin.layouts.app')
@section('title', 'Chat: '.$booking->tour->title)
@section('content')
<div class="container-fluid">
    <x-page-title title="Chat: {{ $booking->tour->title ?? 'Tour' }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => 'Tour messages', 'url' => route('admin.vendor.tour-messages.index')], ['label' => 'Chat']]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>Customer:</strong> {{ $booking->customer->name ?? $booking->customer->email ?? '-' }}</p>
            <p class="mb-0"><strong>Slot:</strong> {{ $booking->start_at?->format('Y-m-d H:i') }} – {{ $booking->end_at?->format('H:i') }} ({{ $booking->status }})</p>
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
            <p class="text-muted">No messages yet.</p>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.tour-messages.reply', $booking->uuid) }}" method="POST">
                @csrf
                <div class="input-group">
                    <input type="text" name="body" class="form-control" placeholder="Type a reply..." maxlength="2000" required>
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
