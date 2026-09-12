@extends('admin.layouts.app')
@section('title', __('admin.vendor.profile.title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.profile.title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.profile.title')]]" />
    <x-alert />

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">{{ __('admin.vendor.profile.business_info') }}</h5>
            <p class="text-muted small mb-4">{{ __('admin.vendor.profile.business_info_help') }}</p>
            <form action="{{ route('admin.vendor.profile.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.business_name') }}</label>
                        <input type="text" name="business_name" class="form-control @error('business_name') is-invalid @enderror"
                               value="{{ old('business_name', $profile->business_name) }}" placeholder="{{ __('admin.vendor.profile.business_name_placeholder') }}">
                        @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.tax_id') }}</label>
                        <input type="text" name="tax_id" class="form-control @error('tax_id') is-invalid @enderror"
                               value="{{ old('tax_id', $profile->tax_id) }}" placeholder="{{ __('admin.vendor.profile.tax_id_placeholder') }}">
                        @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.profile.business_address') }}</label>
                    <input type="text" name="business_address" class="form-control @error('business_address') is-invalid @enderror"
                           value="{{ old('business_address', $profile->business_address) }}" placeholder="{{ __('admin.vendor.profile.business_address_placeholder') }}">
                    @error('business_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.business_phone') }}</label>
                        <input type="text" name="business_phone" class="form-control @error('business_phone') is-invalid @enderror"
                               value="{{ old('business_phone', $profile->business_phone) }}" placeholder="{{ __('admin.vendor.profile.business_phone_placeholder') }}">
                        @error('business_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.business_website') }}</label>
                        <input type="text" name="business_website" class="form-control @error('business_website') is-invalid @enderror"
                               value="{{ old('business_website', $profile->business_website) }}" placeholder="{{ __('admin.vendor.profile.business_website_placeholder') }}">
                        @error('business_website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.profile.business_details') }}</label>
                    <textarea name="business_details" class="form-control @error('business_details') is-invalid @enderror" rows="3"
                              placeholder="{{ __('admin.vendor.profile.business_details_placeholder') }}">{{ old('business_details', $profile->business_details) }}</textarea>
                    @error('business_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.profile.save_changes') }}</button>
                <a href="{{ route('admin.vendor.dashboard') }}" class="btn btn-secondary">{{ __('admin.vendor.profile.cancel') }}</a>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-2">{{ __('admin.vendor.profile.documents.title') }}</h5>
            <p class="text-muted small mb-4">{{ __('admin.vendor.profile.documents.help') }}</p>

            @if($businessDocuments && count($businessDocuments) > 0)
            <div class="table-responsive mb-4">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.vendor.profile.documents.table.file') }}</th>
                            <th>{{ __('admin.vendor.profile.documents.table.size') }}</th>
                            <th>{{ __('admin.vendor.profile.documents.table.uploaded') }}</th>
                            <th class="text-end" width="200">{{ __('admin.vendor.profile.documents.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($businessDocuments as $doc)
                        <tr>
                            <td>
                                <i class="mdi mdi-file-document-outline me-1 text-primary"></i>
                                <span class="text-break">{{ $doc['original_name'] ?? basename($doc['path'] ?? '') }}</span>
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
                                    {{ \Carbon\Carbon::parse($doc['uploaded_at'])->format('M j, Y g:i a') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.vendor.profile.documents.download', ['documentId' => $doc['id']]) }}" class="btn btn-sm btn-outline-primary me-1">{{ __('admin.vendor.profile.documents.download') }}</a>
                                <form action="{{ route('admin.vendor.profile.documents.destroy', ['documentId' => $doc['id']]) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.profile.documents.remove_confirm') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.profile.documents.remove') }}</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-muted small mb-4">{{ __('admin.vendor.profile.documents.empty') }}</p>
            @endif

            <h6 class="mb-3">{{ __('admin.vendor.profile.documents.upload') }}</h6>
            <form action="{{ route('admin.vendor.profile.documents.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <input type="file" name="document_files[]" class="form-control @error('document_files') is-invalid @enderror @error('document_files.*') is-invalid @enderror" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/*">
                    @error('document_files')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('document_files.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary" {{ count($businessDocuments ?? []) >= 25 ? 'disabled' : '' }}>{{ __('admin.vendor.profile.documents.upload') }}</button>
                @if(count($businessDocuments ?? []) >= 25)
                    <span class="text-muted small ms-2">{{ __('admin.vendor.profile.documents.max_reached') }}</span>
                @endif
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">{{ __('admin.vendor.profile.bank_accounts.title') }}</h5>
            <p class="text-muted small mb-4">{{ __('admin.vendor.profile.bank_accounts.help') }}</p>

            @forelse($bankAccounts ?? [] as $bank)
            <div class="border rounded p-3 mb-3 position-relative">
                @if($bank->is_default)
                <span class="badge bg-primary position-absolute top-0 end-0 m-2">{{ __('admin.vendor.profile.bank_accounts.default') }}</span>
                @endif
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>{{ $bank->account_holder_name }}</strong></p>
                        <p class="mb-1 text-muted small">{{ $bank->bank_name }} · {{ $bank->masked_account_number }} · {{ $bank->currency }}</p>
                        @if($bank->routing_number)<p class="mb-0 text-muted small">{{ __('admin.vendor.profile.bank_accounts.form.routing_number') }}: {{ $bank->routing_number }}</p>@endif
                        @if($bank->swift_code)<p class="mb-0 text-muted small">{{ __('admin.vendor.profile.bank_accounts.form.swift_code') }}: {{ $bank->swift_code }}</p>@endif
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBankModal{{ $bank->id }}">{{ __('admin.vendor.profile.bank_accounts.edit') }}</button>
                        <form method="POST" action="{{ route('admin.vendor.profile.bank-accounts.destroy', $bank) }}" class="d-inline" onsubmit="return confirm('{{ __('admin.vendor.profile.bank_accounts.remove_confirm') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.vendor.profile.bank_accounts.remove') }}</button>
                        </form>
                    </div>
                </div>
                <div class="modal fade" id="editBankModal{{ $bank->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.vendor.profile.bank-accounts.update', $bank) }}">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">{{ __('admin.vendor.profile.bank_accounts.edit') }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.account_holder_name') }}</label>
                                        <input type="text" name="account_holder_name" class="form-control" value="{{ old('account_holder_name', $bank->account_holder_name) }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.bank_name') }}</label>
                                        <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $bank->bank_name) }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.account_number') }}</label>
                                        <input type="text" name="account_number" class="form-control" value="{{ old('account_number', $bank->account_number) }}" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.routing_number') }}</label>
                                            <input type="text" name="routing_number" class="form-control" value="{{ old('routing_number', $bank->routing_number) }}" placeholder="{{ __('admin.vendor.profile.bank_accounts.form.routing_placeholder') }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.swift_code') }}</label>
                                            <input type="text" name="swift_code" class="form-control" value="{{ old('swift_code', $bank->swift_code) }}">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.currency') }}</label>
                                            <input type="text" name="currency" class="form-control" value="{{ old('currency', $bank->currency) }}" maxlength="3" placeholder="{{ __('admin.vendor.profile.bank_accounts.form.currency_placeholder') }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label d-block">{{ __('admin.vendor.profile.bank_accounts.form.is_default') }}</label>
                                            <div class="form-check mt-2">
                                                <input type="checkbox" name="is_default" value="1" class="form-check-input" {{ $bank->is_default ? 'checked' : '' }}>
                                                <label class="form-check-label">{{ __('admin.vendor.profile.bank_accounts.form.is_default') }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                                    <button type="submit" class="btn btn-primary">{{ __('admin.vendor.profile.bank_accounts.form.save') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <p class="text-muted mb-3">{{ __('admin.vendor.profile.bank_accounts.empty') }}</p>
            @endforelse

            <hr class="my-4">
            <h6 class="mb-3">{{ __('admin.vendor.profile.bank_accounts.add') }}</h6>
            <form action="{{ route('admin.vendor.profile.bank-accounts.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.account_holder_name') }}</label>
                        <input type="text" name="account_holder_name" class="form-control @error('account_holder_name') is-invalid @enderror" value="{{ old('account_holder_name') }}" required>
                        @error('account_holder_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.bank_name') }}</label>
                        <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror" value="{{ old('bank_name') }}" required>
                        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.account_number') }}</label>
                    <input type="text" name="account_number" class="form-control @error('account_number') is-invalid @enderror" value="{{ old('account_number') }}" required>
                    @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.routing_number') }}</label>
                        <input type="text" name="routing_number" class="form-control @error('routing_number') is-invalid @enderror" value="{{ old('routing_number') }}" placeholder="{{ __('admin.vendor.profile.bank_accounts.form.routing_placeholder') }}">
                        @error('routing_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.swift_code') }}</label>
                        <input type="text" name="swift_code" class="form-control @error('swift_code') is-invalid @enderror" value="{{ old('swift_code') }}">
                        @error('swift_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('admin.vendor.profile.bank_accounts.form.currency') }}</label>
                        <input type="text" name="currency" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', 'VND') }}" maxlength="3" placeholder="{{ __('admin.vendor.profile.bank_accounts.form.currency_placeholder') }}">
                        @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="is_default" value="1" class="form-check-input" id="bank_is_default" {{ empty($bankAccounts) ? 'checked' : '' }}>
                        <label class="form-check-label" for="bank_is_default">{{ __('admin.vendor.profile.bank_accounts.form.is_default') }}</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.profile.bank_accounts.form.add') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
