@extends('admin.layouts.app')
@section('title', __('admin.vendor.payouts_admin.title'))
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.payouts_admin.title') }}"
        :breadcrumbs="[
            ['label' => 'Admin', 'url' => route('admin.dashboard')],
            ['label' => __('admin.vendor.payouts_admin.title')]
        ]"
    />
    <x-alert />

    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="{{ route('admin.payouts.create') }}" class="btn btn-primary">
            <i data-feather="plus" class="icon-sm me-1"></i>{{ __('admin.vendor.payouts_admin.generate') }}
        </a>
        <a href="{{ route('admin.payouts.export') }}?{{ request()->getQueryString() }}" class="btn btn-outline-secondary">
            <i data-feather="download" class="icon-sm me-1"></i>{{ __('admin.vendor.payouts_admin.export_csv') }}
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.payouts_admin.filter.status') }}</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.payouts_admin.filter.all') }}</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('admin.vendor.payouts_admin.filter.pending') }}</option>
                        <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>{{ __('admin.vendor.payouts_admin.filter.processing') }}</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>{{ __('admin.vendor.payouts_admin.filter.paid') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">{{ __('admin.vendor.payouts_admin.filter.vendor') }}</label>
                    <select name="vendor_id" class="form-select form-select-sm">
                        <option value="">{{ __('admin.vendor.payouts_admin.filter.all') }}</option>
                        @foreach($vendors as $v)
                        <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }} ({{ $v->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('admin.vendor.payouts_admin.filter.apply') }}</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin.vendor.payouts_admin.table.id') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.vendor') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.period') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.gross') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.commission') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.net') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.status') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.reference') }}</th>
                        <th>{{ __('admin.vendor.payouts_admin.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payouts as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td>
                            <a href="{{ route('admin.vendors.show', $p->vendor_id) }}">{{ $p->vendor->name ?? $p->vendor_id }}</a>
                            <br><small class="text-muted">{{ $p->vendor->email ?? '' }}</small>
                        </td>
                        <td>{{ $p->period_start->format('M d, Y') }} – {{ $p->period_end->format('M d, Y') }}</td>
                        <td>{{ format_vnd($p->amount) }}</td>
                        <td>{{ format_vnd($p->commission) }}</td>
                        <td><strong>{{ format_vnd($p->net) }}</strong></td>
                        <td>
                            @if($p->status === 'pending')
                                <span class="badge bg-warning">{{ __('admin.vendor.payouts_admin.status.pending') }}</span>
                            @elseif($p->status === 'processing')
                                <span class="badge bg-info">{{ __('admin.vendor.payouts_admin.status.processing') }}</span>
                            @else
                                <span class="badge bg-success">{{ __('admin.vendor.payouts_admin.status.paid') }}</span>
                            @endif
                        </td>
                        <td>{{ $p->reference ?? '-' }}</td>
                        <td>
                            <a href="{{ route('admin.payouts.show', $p) }}" class="btn btn-sm btn-outline-primary">{{ __('admin.vendor.payouts_admin.actions.view') }}</a>
                            @if(!$p->isPaid())
                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#markPaidModal{{ $p->id }}">{{ __('admin.vendor.payouts_admin.actions.mark_paid') }}</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-muted">{{ __('admin.vendor.payouts_admin.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $payouts->links() }}
        </div>
    </div>

    @foreach($payouts as $p)
    @if(!$p->isPaid())
    <div class="modal fade" id="markPaidModal{{ $p->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.payouts.mark-paid', $p) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.vendor.payouts_admin.detail.mark_as_paid', ['id' => $p->id]) }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.vendor.payouts_admin.detail.reference_placeholder') }}</label>
                            <input type="text" name="reference" class="form-control" placeholder="{{ __('admin.vendor.payouts_admin.detail.reference_placeholder') }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('admin.vendor.common.cancel') }}</button>
                        <button type="submit" class="btn btn-success">{{ __('admin.vendor.payouts_admin.detail.mark_as_paid') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    @endforeach
</div>
@endsection
