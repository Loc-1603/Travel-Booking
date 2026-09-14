@extends('admin.layouts.app')
@section('title', __('admin.vendor.support_tickets.detail.title', ['id' => $supportTicket->id]))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.support_tickets.detail.title', ['id' => $supportTicket->id]) }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => __('admin.vendor.support_tickets.title'), 'url' => route('admin.support-tickets.index')], ['label' => __('admin.vendor.common.view')]]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.support_tickets.detail.ticket') }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('admin.vendor.support_tickets.detail.subject') }}:</strong> {{ $supportTicket->subject }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.support_tickets.detail.category') }}:</strong> {{ \App\Enums\TicketCategory::tryFrom($supportTicket->category)?->label() ?? $supportTicket->category }}</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.support_tickets.detail.from') }}:</strong> {{ $supportTicket->user->name ?? '-' }} ({{ $supportTicket->user->email ?? '-' }})</p>
                    <p class="mb-1"><strong>{{ __('admin.vendor.support_tickets.detail.status') }}:</strong> {{ $supportTicket->status }} | <strong>{{ __('admin.vendor.support_tickets.detail.priority') }}:</strong> {{ $supportTicket->priority }}</p>
                    <p class="mb-0"><strong>{{ __('admin.vendor.support_tickets.detail.body') }}:</strong></p>
                    <div class="border rounded p-2 mt-1">{{ $supportTicket->body }}</div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.support_tickets.detail.replies') }}</h5></div>
                <div class="card-body">
                    @forelse($supportTicket->replies as $reply)
                    <div class="border rounded p-2 mb-2">
                        <small class="text-muted">{{ $reply->user->name ?? $reply->user->email }} — {{ $reply->created_at->format('Y-m-d H:i') }}</small>
                        <div class="mt-1">{{ $reply->body }}</div>
                    </div>
                    @empty
                    <p class="text-muted mb-0">{{ __('admin.vendor.support_tickets.detail.no_replies') }}</p>
                    @endforelse
                    <hr>
                    <form action="{{ route('admin.support-tickets.replies.store', $supportTicket) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">{{ __('admin.vendor.support_tickets.detail.add_reply') }}</label>
                            <textarea name="body" class="form-control" rows="3" required maxlength="10000">{{ old('body') }}</textarea>
                            @error('body')<span class="text-danger small">{{ $message }}</span>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.vendor.support_tickets.detail.send_reply') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ __('admin.vendor.support_tickets.detail.update_ticket') }}</h5></div>
                <div class="card-body">
                    <form action="{{ route('admin.support-tickets.update', $supportTicket) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.support_tickets.form.status') }}</label>
                            <select name="status" class="form-select">
                                @foreach(['open','assigned','in_progress','resolved','closed'] as $s)
                                <option value="{{ $s }}" {{ $supportTicket->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.support_tickets.detail.assigned_to') }}</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">{{ __('admin.vendor.common.all') }}</option>
                                @foreach($staff ?? [] as $u)
                                <option value="{{ $u->id }}" {{ $supportTicket->assigned_to == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.support_tickets.form.priority') }}</label>
                            <select name="priority" class="form-select">
                                @foreach(['low','normal','high'] as $p)
                                <option value="{{ $p }}" {{ $supportTicket->priority === $p ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('admin.vendor.support_tickets.detail.save') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
