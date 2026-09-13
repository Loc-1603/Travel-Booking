@extends('admin.layouts.app')
@section('title', __('admin.vendor.commission.title'))
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.commission.title') }}"
        :breadcrumbs="[
            ['label' => 'Admin', 'url' => route('admin.dashboard')],
            ['label' => __('admin.vendor.commission.title')]
        ]"
    />
    <x-alert />

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ __('admin.vendor.commission.current_rate') }}</h5>
                    <p class="display-6">{{ number_format($rate * 100, 1) }}%</p>
                    <a href="{{ route('admin.commission.edit') }}" class="btn btn-primary">{{ __('admin.vendor.commission.edit_rate') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.vendor.commission.by_vendor') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2"><a href="{{ route('admin.payouts.index') }}">{{ __('admin.vendor.commission.manage_payouts') }}</a> to process vendor payments.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.vendor.commission.table.vendor') }}</th>
                                    <th>{{ __('admin.vendor.commission.table.contact') }}</th>
                                    <th>{{ __('admin.vendor.commission.table.business') }}</th>
                                    <th>{{ __('admin.vendor.commission.table.payout_bank') }}</th>
                                    <th class="text-end">{{ __('admin.vendor.commission.table.id') }}</th>
                                    <th class="text-end">{{ __('admin.vendor.commission.table.bookings') }}</th>
                                    <th class="text-end">{{ __('admin.vendor.commission.table.gross') }}</th>
                                    <th class="text-end">{{ __('admin.vendor.commission.table.commission') }}</th>
                                    <th class="text-end">{{ __('admin.vendor.commission.table.net') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report as $row)
                                <tr>
                                    <td>
                                        @if(!empty($row['vendor_name']))
                                            <a href="{{ route('admin.vendors.show', $row['vendor_id']) }}">{{ $row['vendor_name'] }}</a>
                                            @if(!empty($row['vendor_profile_status']))
                                                <span class="badge bg-secondary ms-1">{{ $row['vendor_profile_status'] }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if(!empty($row['vendor_email']))
                                            <div><a href="mailto:{{ $row['vendor_email'] }}">{{ $row['vendor_email'] }}</a></div>
                                        @endif
                                        @if(!empty($row['business_phone']))
                                            <div class="text-muted">{{ $row['business_phone'] }}</div>
                                        @endif
                                        @if(empty($row['vendor_email']) && empty($row['business_phone']))
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if(!empty($row['business_name']))
                                            <div>{{ $row['business_name'] }}</div>
                                        @endif
                                        @if(!empty($row['tax_id']))
                                            <div class="text-muted">{{ __('admin.vendor.commission.tax_id') }}: {{ $row['tax_id'] }}</div>
                                        @endif
                                        @if(!empty($row['business_address']))
                                            <div class="text-muted">{{ \Illuminate\Support\Str::limit($row['business_address'], 80) }}</div>
                                        @endif
                                        @if(empty($row['business_name']) && empty($row['tax_id']) && empty($row['business_address']))
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if(!empty($row['bank_name']))
                                            <div>{{ $row['bank_name'] }}</div>
                                            @if(!empty($row['bank_account_masked']))
                                                <div class="text-muted">{{ $row['bank_account_masked'] }} @if(!empty($row['bank_currency']))<span class="text-uppercase">({{ $row['bank_currency'] }})</span>@endif</div>
                                            @endif
                                            @if(!empty($row['bank_account_holder']))
                                                <div class="text-muted">{{ $row['bank_account_holder'] }}</div>
                                            @endif
                                        @else
                                            <span class="text-muted">{{ __('admin.vendor.commission.no_bank_on_file') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-muted">{{ $row['vendor_id'] }}</td>
                                    <td class="text-end">{{ $row['booking_count'] }}</td>
                                    <td class="text-end">{{ format_vnd((float) $row['gross']) }}</td>
                                    <td class="text-end">{{ format_vnd((float) $row['commission']) }}</td>
                                    <td class="text-end"><strong>{{ format_vnd((float) $row['net']) }}</strong></td>
                                </tr>
                                @empty
                                <tr><td colspan="9" class="text-muted">{{ __('admin.vendor.common.no_data') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
