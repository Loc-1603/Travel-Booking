@extends('admin.layouts.app')
@section('title', __('admin.vendor.vendor_support_tickets.detail.title', ['id' => $supportTicket->id]))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.vendor_support_tickets.detail.title', ['id' => $supportTicket->id]) }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.sidebar.support'), 'url' => route('admin.vendor.support-tickets.index')], ['label' => __('admin.vendor.common.view')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $supportTicket->subject }}</h5>
            <span class="badge bg-secondary">{{ $supportTicket->status }}</span>
        </div>
        <div class="card-body">
            <p class="mb-1"><strong>{{ __('admin.vendor.vendor_support_tickets.detail.category') }}:</strong> {{ \App\Enums\TicketCategory::tryFrom($supportTicket->category)?->label() ?? $supportTicket->category }} | <strong>{{ __('admin.vendor.vendor_support_tickets.detail.priority') }}:</strong> {{ $supportTicket->priority }}</p>
            <p class="mb-2"><strong>{{ __('admin.vendor.vendor_support_tickets.detail.created') }}:</strong> {{ $supportTicket->created_at->format('Y-m-d H:i') }}</p>
            <div class="border rounded p-2 bg-light">{{ $supportTicket->body }}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.vendor_support_tickets.detail.replies') }}</h5></div>
        <div class="card-body">
            @forelse($supportTicket->replies as $reply)
            <div class="border rounded p-2 mb-2 {{ $reply->user_id === $supportTicket->user_id ? 'bg-light' : '' }}">
                <small class="text-muted">{{ $reply->user->name ?? $reply->user->email }} — {{ $reply->created_at->format('Y-m-d H:i') }}</small>
                <div class="mt-1">{{ $reply->body }}</div>
            </div>
            @empty
            <p class="text-muted mb-0">{{ __('admin.vendor.vendor_support_tickets.detail.no_replies') }}</p>
            @endforelse
        </div>
    </div>
    <div class="mt-2">
        <a href="{{ route('admin.vendor.support-tickets.index') }}" class="btn btn-outline-secondary">{{ __('admin.vendor.vendor_support_tickets.back_to_list') }}</a>
    </div>
</div>
@endsection
