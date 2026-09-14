@extends('admin.layouts.app')
@section('title', __('admin.vendor.vendor_support_tickets.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.vendor_support_tickets.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.sidebar.support')]]" />
    <x-alert />
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <p class="mb-0">{{ __('admin.vendor.vendor_support_tickets.form.help') }}</p>
            <a href="{{ route('admin.vendor.support-tickets.create') }}" class="btn btn-primary">{{ __('admin.vendor.vendor_support_tickets.create') }}</a>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.vendor_support_tickets.filter.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.vendor_support_tickets.filter.all') }}</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.filter.status_open') }}</option>
                        <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.filter.status_assigned') }}</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.filter.status_in_progress') }}</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.filter.status_resolved') }}</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.filter.status_closed') }}</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.vendor_support_tickets.filter.apply') }}</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.vendor_support_tickets.table.id') }}</th>
                        <th>{{ __('admin.vendor.vendor_support_tickets.table.subject') }}</th>
                        <th>{{ __('admin.vendor.vendor_support_tickets.table.category') }}</th>
                        <th>{{ __('admin.vendor.vendor_support_tickets.table.status') }}</th>
                        <th>{{ __('admin.vendor.vendor_support_tickets.table.replies') }}</th>
                        <th>{{ __('admin.vendor.vendor_support_tickets.table.created') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $t)
                    <tr>
                        <td>{{ $t->id }}</td>
                        <td>{{ Str::limit($t->subject, 50) }}</td>
                        <td>{{ \App\Enums\TicketCategory::tryFrom($t->category)?->label() ?? $t->category }}</td>
                        <td><span class="badge bg-secondary">{{ $t->status }}</span></td>
                        <td>{{ $t->replies_count }}</td>
                        <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                        <td><a href="{{ route('admin.vendor.support-tickets.show', $t) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.vendor_support_tickets.view') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">{{ __('admin.vendor.vendor_support_tickets.empty', ['url' => route('admin.vendor.support-tickets.create')]) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $tickets->links() }}
        </div>
    </div>
</div>
@endsection
