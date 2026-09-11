@extends('admin.layouts.app')
@section('title', __('admin.vendor.vendors.title'))
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.vendors.title') }}"
        :breadcrumbs="[
            ['label' => 'Admin', 'url' => route('admin.dashboard')],
            ['label' => __('admin.vendor.vendors.title')]
        ]"
    />
    <x-alert />

    <div class="mb-3">
        <a href="{{ route('admin.vendors.index', ['status' => '']) }}" class="btn btn-outline-secondary btn-sm">{{ __('admin.vendor.vendors.filters.all') }}</a>
        <a href="{{ route('admin.vendors.index', ['status' => 'pending']) }}" class="btn btn-outline-warning btn-sm">{{ __('admin.vendor.vendors.filters.pending') }}</a>
        <a href="{{ route('admin.vendors.index', ['status' => 'approved']) }}" class="btn btn-outline-success btn-sm">{{ __('admin.vendor.vendors.filters.approved') }}</a>
        <a href="{{ route('admin.vendors.index', ['status' => 'rejected']) }}" class="btn btn-outline-danger btn-sm">{{ __('admin.vendor.vendors.filters.rejected') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('admin.vendor.vendors.table.name') }}</th>
                            <th>{{ __('admin.vendor.vendors.table.email') }}</th>
                            <th>{{ __('admin.vendor.vendors.table.business') }}</th>
                            <th>{{ __('admin.vendor.vendors.table.approval') }}</th>
                            <th>{{ __('admin.vendor.vendors.table.account') }}</th>
                            <th width="280">{{ __('admin.vendor.vendors.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendors as $v)
                        @php
                            $profile = $v->vendorProfile;
                            $approvalStatus = $profile?->status ?? 'pending';
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.vendors.show', $v) }}">{{ $v->name }}</a>
                            </td>
                            <td>{{ $v->email }}</td>
                            <td>
                                {{ $profile?->business_name ?? '—' }}
                                @if($profile && ($profile->business_phone || $profile->business_address))
                                    <br><small class="text-muted">{{ $profile->business_phone ?? '' }}{{ $profile->business_phone && $profile->business_address ? ' · ' : '' }}{{ Str::limit($profile->business_address ?? '', 30) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($approvalStatus === 'approved')
                                    <span class="badge bg-success">{{ __('admin.vendor.vendors.approval_status.approved') }}</span>
                                @elseif($approvalStatus === 'rejected')
                                    <span class="badge bg-danger">{{ __('admin.vendor.vendors.approval_status.rejected') }}</span>
                                @else
                                    <span class="badge bg-warning">{{ __('admin.vendor.vendors.approval_status.pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $v->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $v->status }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.vendors.show', $v) }}" class="btn btn-sm btn-outline-primary me-1">{{ __('admin.vendor.vendors.actions.view') }}</a>
                                @if($approvalStatus === 'pending')
                                    <form action="{{ route('admin.vendors.approve', $v) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">{{ __('admin.vendor.vendors.actions.approve') }}</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $v->id }}">{{ __('admin.vendor.vendors.actions.reject') }}</button>
                                    <div class="modal fade" id="rejectModal{{ $v->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.vendors.reject', $v) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ __('admin.vendor.vendors.modal.reject_title') }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label">{{ __('admin.vendor.vendors.modal.reason') }}</label>
                                                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="{{ __('admin.vendor.vendors.modal.reason_placeholder') }}"></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('admin.vendor.vendors.modal.cancel') }}</button>
                                                        <button type="submit" class="btn btn-danger">{{ __('admin.vendor.vendors.modal.confirm_reject') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($approvalStatus === 'approved' && $v->status === 'active')
                                    <form action="{{ route('admin.vendors.update-status', $v) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="suspended">
                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('{{ __('admin.vendor.vendors.actions.confirm_suspend') }}')">{{ __('admin.vendor.vendors.actions.suspend') }}</button>
                                    </form>
                                @elseif($v->status === 'suspended')
                                    <form action="{{ route('admin.vendors.update-status', $v) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="btn btn-sm btn-success">{{ __('admin.vendor.vendors.actions.activate') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-muted">{{ __('admin.vendor.vendors.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $vendors->links() }}
        </div>
    </div>
</div>
@endsection
