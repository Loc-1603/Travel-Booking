@extends('admin.layouts.app')
@section('title', $vendor->name)
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.vendors.detail.title') }}"
        :breadcrumbs="[
            ['label' => 'Admin', 'url' => route('admin.dashboard')],
            ['label' => __('admin.vendor.vendors.title'), 'url' => route('admin.vendors.index')],
            ['label' => $vendor->name]
        ]"
    />
    <x-alert />

    @php $profile = $vendor->vendorProfile; @endphp

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.vendor.vendors.detail.account') }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <th width="140" class="text-muted">{{ __('admin.vendor.vendors.detail.name') }}</th>
                            <td>{{ $vendor->name }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('admin.vendor.vendors.detail.email') }}</th>
                            <td>{{ $vendor->email }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('admin.vendor.vendors.detail.approval') }}</th>
                            <td>
                                @if($profile?->status === 'approved')
                                    <span class="badge bg-success">{{ __('admin.vendor.vendors.approval_status.approved') }}</span>
                                    @if($profile->approved_at)
                                        <small class="text-muted">{{ __('admin.vendor.vendors.detail.approved_at', ['date' => $profile->approved_at->format('M j, Y')]) }}</small>
                                    @endif
                                @elseif($profile?->status === 'rejected')
                                    <span class="badge bg-danger">{{ __('admin.vendor.vendors.approval_status.rejected') }}</span>
                                    @if($profile?->rejection_reason)
                                        <div class="text-muted small mt-1">{{ $profile->rejection_reason }}</div>
                                    @endif
                                @else
                                    <span class="badge bg-warning">{{ __('admin.vendor.vendors.approval_status.pending') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">{{ __('admin.vendor.vendors.detail.account_status') }}</th>
                            <td>
                                <span class="badge {{ $vendor->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $vendor->status }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.vendor.vendors.detail.business_details') }}</h5>
                </div>
                <div class="card-body">
                    @if($profile && ($profile->business_name || $profile->business_address || $profile->business_phone || $profile->business_website || $profile->tax_id || $profile->business_details))
                        <table class="table table-borderless table-sm mb-0">
                            @if($profile->business_name)
                            <tr>
                                <th width="140" class="text-muted">{{ __('admin.vendor.vendors.detail.business_name') }}</th>
                                <td>{{ $profile->business_name }}</td>
                            </tr>
                            @endif
                            @if($profile->business_address)
                            <tr>
                                <th class="text-muted">{{ __('admin.vendor.vendors.detail.address') }}</th>
                                <td>{{ $profile->business_address }}</td>
                            </tr>
                            @endif
                            @if($profile->business_phone)
                            <tr>
                                <th class="text-muted">{{ __('admin.vendor.vendors.detail.phone') }}</th>
                                <td>{{ $profile->business_phone }}</td>
                            </tr>
                            @endif
                            @if($profile->business_website)
                            <tr>
                                <th class="text-muted">{{ __('admin.vendor.vendors.detail.website') }}</th>
                                <td>
                                    @php $url = $profile->business_website; $href = (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) ? $url : 'https://' . $url; @endphp
                                    <a href="{{ $href }}" target="_blank" rel="noopener">{{ $profile->business_website }}</a>
                                </td>
                            </tr>
                            @endif
                            @if($profile->tax_id)
                            <tr>
                                <th class="text-muted">{{ __('admin.vendor.vendors.detail.tax_id') }}</th>
                                <td>{{ $profile->tax_id }}</td>
                            </tr>
                            @endif
                            @if($profile->business_details)
                            <tr>
                                <th class="text-muted">{{ __('admin.vendor.vendors.detail.additional_details') }}</th>
                                <td>{{ $profile->business_details }}</td>
                            </tr>
                            @endif
                        </table>
                    @else
                        <p class="text-muted mb-0">No business details provided yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @php
        $businessDocs = [];
        if ($profile && is_array($profile->documents)) {
            $businessDocs = array_values(array_filter($profile->documents, fn ($d) => is_array($d) && !empty($d['path']) && !empty($d['id'])));
        }
    @endphp
    @if(count($businessDocs) > 0)
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.vendor.vendors.detail.business_documents') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Files uploaded by the vendor for verification or records.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.vendor.vendors.detail.documents.file') }}</th>
                                    <th>{{ __('admin.vendor.vendors.detail.documents.size') }}</th>
                                    <th>{{ __('admin.vendor.vendors.detail.documents.uploaded') }}</th>
                                    <th width="140">{{ __('admin.vendor.vendors.detail.documents.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($businessDocs as $doc)
                                <tr>
                                    <td>
                                        <i class="mdi mdi-file-document-outline me-1 text-muted"></i>
                                        {{ $doc['original_name'] ?? basename($doc['path'] ?? '') }}
                                    </td>
                                    <td class="text-muted small">
                                        @if(!empty($doc['size']))
                                            {{ number_format($doc['size'] / 1024, 1) }} KB
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-muted small">
                                        @if(!empty($doc['uploaded_at']))
                                            {{ \Carbon\Carbon::parse($doc['uploaded_at'])->format('M j, Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.vendors.documents.download', ['vendor' => $vendor->id, 'documentId' => $doc['id']]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="mdi mdi-download"></i> {{ __('admin.vendor.vendors.detail.download') }}
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ __('admin.vendor.vendors.detail.actions') }}</h5>
            <a href="{{ route('admin.vendors.index') }}" class="btn btn-sm btn-secondary">{{ __('admin.vendor.vendors.detail.back_to_list') }}</a>
        </div>
        <div class="card-body">
            @if($profile?->status === 'pending')
                <form action="{{ route('admin.vendors.approve', $vendor) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success">{{ __('admin.vendor.vendors.actions.approve') }}</button>
                </form>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">{{ __('admin.vendor.vendors.actions.reject') }}</button>
                <div class="modal fade" id="rejectModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.vendors.reject', $vendor) }}" method="POST">
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
            @elseif($profile?->status === 'approved' && $vendor->status === 'active')
                <form action="{{ route('admin.vendors.update-status', $vendor) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="suspended">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('admin.vendor.vendors.actions.confirm_suspend') }}')">{{ __('admin.vendor.vendors.actions.suspend') }}</button>
                </form>
            @elseif($vendor->status === 'suspended')
                <form action="{{ route('admin.vendors.update-status', $vendor) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-success">{{ __('admin.vendor.vendors.actions.activate') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
