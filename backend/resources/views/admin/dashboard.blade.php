@extends('admin.layouts.app')
@section('title', __('admin.dashboard'))
@section('content')
@include('admin.partials.dashboard-styles')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.dashboard') }}" :breadcrumbs="[['label' => __('admin.dashboard')]]" />
    <x-alert />

    @php
        $dashUser = auth()->user();
        $dashRoleLabel = match ($dashUser->role) {
            \App\Enums\Role::SUPER_ADMIN => __('admin.role_labels.super_admin'),
            \App\Enums\Role::ADMIN => __('admin.role_labels.admin'),
            default => __('admin.role_labels.default'),
        };
    @endphp

    <div class="row g-4 mb-2">
        <div class="col-12">
            <div class="card dash-hero">
                <div class="card-body py-4 px-4">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        @include('admin.partials.user-avatar', ['user' => $dashUser, 'size' => 56])
                        <div class="flex-grow-1">
                            <h5 class="mb-1">{{ __('admin.welcome_back', ['name' => $dashUser->name]) }}</h5>
                            <p class="text-muted mb-0 small">{{ $dashRoleLabel }}</p>
                            @if($isSuperAdmin && isset($kpis['confirmed_bookings']))
                                <p class="mb-0 mt-2 small" style="opacity: 0.9;">
                                    <span class="me-3"><i class="mdi mdi-check-circle-outline me-1"></i>{{ number_format($kpis['confirmed_bookings']) }} {{ __('admin.confirmed_bookings') }}</span>
                                    <span><i class="mdi mdi-office-building-outline me-1"></i>{{ number_format($kpis['total_hotels']) }} {{ __('admin.properties') }}</span>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="row g-4 mb-2">
        <div class="col-xl-3 col-md-6">
            <div class="card dash-stat-card">
                <div class="card-body d-flex align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <div class="dash-stat-label">{{ __('admin.total_hotels') }}</div>
                        <div class="dash-stat-value">{{ number_format($kpis['total_hotels']) }}</div>
                    </div>
                    <div class="dash-stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="mdi mdi-office-building"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card dash-stat-card">
                <div class="card-body d-flex align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <div class="dash-stat-label">{{ __('admin.total_bookings') }}</div>
                        <div class="dash-stat-value">{{ number_format($kpis['total_bookings']) }}</div>
                    </div>
                    <div class="dash-stat-icon bg-info bg-opacity-10 text-info">
                        <i class="mdi mdi-calendar-check"></i>
                    </div>
                </div>
            </div>
        </div>
        @if($isSuperAdmin)
        <div class="col-xl-3 col-md-6">
            <div class="card dash-stat-card">
                <div class="card-body d-flex align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <div class="dash-stat-label">{{ __('admin.revenue_confirmed') }}</div>
                        <div class="dash-stat-value">{{ format_vnd($kpis['revenue']) }}</div>
                    </div>
                    <div class="dash-stat-icon bg-success bg-opacity-10 text-success">
                        <i class="mdi mdi-cash"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card dash-stat-card">
                <div class="card-body d-flex align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <div class="dash-stat-label">{{ __('admin.platform_commission') }}</div>
                        <div class="dash-stat-value">{{ format_vnd($kpis['commission']) }}</div>
                    </div>
                    <div class="dash-stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="mdi mdi-percent-outline"></i>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if($isSuperAdmin)
    <div class="row g-4 mb-2">
        <div class="col-12">
            <div class="card dash-chart-card dash-table-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">{{ __('admin.vendor_business_documents') }}</h5>
                        <p class="card-subtitle mb-0">{{ __('admin.recent_uploads') }}</p>
                    </div>
                    <a href="{{ route('admin.vendors.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="mdi mdi-account-group-outline me-1"></i> {{ __('admin.all_vendors') }}
                    </a>
                </div>
                <div class="card-body pt-0">
                    @if(!empty($recentVendorDocuments))
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('admin.vendor_label') }}</th>
                                    <th>{{ __('admin.file') }}</th>
                                    <th>{{ __('admin.uploaded') }}</th>
                                    <th width="120"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentVendorDocuments as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.vendors.show', $row['vendor_id']) }}" class="fw-medium">{{ $row['vendor_name'] }}</a>
                                    </td>
                                    <td class="text-muted small text-truncate" style="max-width: 220px;">{{ $row['file_name'] }}</td>
                                    <td class="text-muted small">
                                        {{ $row['uploaded_at'] ? $row['uploaded_at']->format('M j, Y g:i a') : '—' }}
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.vendors.documents.download', ['vendor' => $row['vendor_id'], 'documentId' => $row['document_id']]) }}" class="btn btn-sm btn-soft-primary">
                                            <i class="mdi mdi-download me-1"></i> {{ __('admin.download') }}
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted mb-0 small">{!! __('admin.no_documents') !!}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($isSuperAdmin && !empty($revenueChart['labels']))
    <div class="row g-4 mb-2">
        <div class="col-xl-8">
            <div class="card dash-chart-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.revenue_overview') }}</h5>
                    <p class="card-subtitle mb-0">{{ __('admin.revenue_subtitle') }}</p>
                </div>
                <div class="card-body pt-2">
                    <div style="height: 280px; position: relative;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card dash-commission-box h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-0" style="font-size: 1rem;">{{ __('admin.commission_rate') }}</h5>
                            <p class="text-muted small mb-0">{{ __('admin.commission_subtitle') }}</p>
                        </div>
                        <a href="{{ route('admin.commission.edit') }}" class="btn btn-sm btn-soft-primary">{{ __('admin.edit') }}</a>
                    </div>
                    <p class="text-muted small mb-1">{{ __('admin.current_rate') }}</p>
                    <p class="display-rate mb-3">{{ number_format($commissionRate * 100, 1) }}%</p>
                    <a href="{{ route('admin.commission.index') }}" class="btn btn-outline-primary btn-sm mt-auto align-self-start">
                        <i class="mdi mdi-chart-box-outline me-1"></i> {{ __('admin.view_commission_report') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-4 mb-2">
        <div class="col-xl-6">
            <div class="card dash-chart-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.bookings_by_status') }}</h5>
                    <p class="card-subtitle mb-0">{{ __('admin.bookings_status_subtitle') }}</p>
                </div>
                <div class="card-body">
                    <div style="height: 260px; position: relative;">
                        <canvas id="bookingsByStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card dash-chart-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.booking_volume') }}</h5>
                    <p class="card-subtitle mb-0">{{ __('admin.booking_volume_subtitle') }}</p>
                </div>
                <div class="card-body">
                    <div style="height: 260px; position: relative;">
                        <canvas id="bookingsTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($isSuperAdmin && !empty($topVendorsChart['labels']))
    <div class="row g-4 mb-2">
        <div class="col-xl-8 col-lg-12">
            <div class="card dash-chart-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('admin.top_vendors') }}</h5>
                    <p class="card-subtitle mb-0">{{ __('admin.top_vendors_subtitle') }}</p>
                </div>
                <div class="card-body">
                    <div style="height: 280px; position: relative;">
                        <canvas id="topVendorsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($isSuperAdmin && $vendors->isNotEmpty())
    <div class="row g-4">
        <div class="col-12">
            <div class="card dash-chart-card dash-table-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">{{ __('admin.recent_vendors') }}</h5>
                        <p class="card-subtitle mb-0">{{ __('admin.recent_vendors_subtitle') }}</p>
                    </div>
                    <a href="{{ route('admin.vendors.index') }}" class="btn btn-sm btn-primary">
                        <i class="mdi mdi-account-group-outline me-1"></i> {{ __('admin.view_all_vendors') }}
                    </a>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('admin.name') }}</th>
                                    <th>{{ __('admin.email') }}</th>
                                    <th>{{ __('admin.business') }}</th>
                                    <th>{{ __('admin.approval') }}</th>
                                    <th>{{ __('admin.account') }}</th>
                                    <th width="200">{{ __('admin.actions_label') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vendors->take(5) as $v)
                                @php $profile = $v->vendorProfile; $approval = $profile?->status ?? 'pending'; @endphp
                                <tr>
                                    <td><a href="{{ route('admin.vendors.show', $v) }}" class="fw-medium">{{ $v->name }}</a></td>
                                    <td class="text-muted small">{{ $v->email }}</td>
                                    <td>
                                        {{ $profile?->business_name ?? '—' }}
                                        @if($profile?->business_phone)
                                            <br><small class="text-muted">{{ $profile->business_phone }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($approval === 'approved')<span class="badge bg-success-subtle text-success">{{ __('admin.status_labels.approved') }}</span>
                                        @elseif($approval === 'rejected')<span class="badge bg-danger-subtle text-danger">{{ __('admin.status_labels.rejected') }}</span>
                                        @else<span class="badge bg-warning-subtle text-warning">{{ __('admin.status_labels.pending') }}</span>@endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $v->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $v->status }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.vendors.show', $v) }}" class="btn btn-sm btn-outline-primary me-1">{{ __('admin.actions.view') }}</a>
                                        @if($approval === 'pending')
                                        <form action="{{ route('admin.vendors.approve', $v) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">{{ __('admin.approve') }}</button>
                                        </form>
                                        @elseif($approval === 'approved' && $v->status === 'active')
                                        <form action="{{ route('admin.vendors.update-status', $v) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('{{ __('admin.actions.confirm_cancel') }}')">{{ __('admin.suspend') }}</button>
                                        </form>
                                        @elseif($v->status === 'suspended')
                                        <form action="{{ route('admin.vendors.update-status', $v) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="btn btn-sm btn-success">{{ __('admin.activate') }}</button>
                                        </form>
                                        @endif
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

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const grid = 'rgba(148, 163, 184, 0.22)';
    const tick = '#64748b';
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Segoe UI', system-ui, -apple-system, sans-serif";
        Chart.defaults.color = tick;
        Chart.defaults.scale.grid.color = grid;
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.92)';
        Chart.defaults.plugins.tooltip.titleFont = { weight: '600', size: 13 };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 16;
    }

    const colors = {
        indigo: { fill: 'rgba(79, 70, 229, 0.85)', stroke: 'rgb(67, 56, 202)', fillSoft: 'rgba(79, 70, 229, 0.12)' },
        emerald: { fill: 'rgba(5, 150, 105, 0.85)', stroke: 'rgb(4, 120, 87)' },
        amber: 'rgba(217, 119, 6, 0.85)',
        rose: 'rgba(225, 29, 72, 0.85)',
        blue: 'rgba(37, 99, 235, 0.85)',
        status: ['rgba(245, 158, 11, 0.9)', 'rgba(16, 185, 129, 0.9)', 'rgba(244, 63, 94, 0.9)', 'rgba(59, 130, 246, 0.9)'],
    };

    document.addEventListener('DOMContentLoaded', function() {
    @if($isSuperAdmin && !empty($revenueChart['labels']))
    var revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: @json($revenueChart['labels']),
                datasets: [{
                    label: '{{ __('admin.chart_labels.revenue') }}',
                    data: @json($revenueChart['data']),
                    backgroundColor: colors.indigo.fill,
                    borderColor: colors.indigo.stroke,
                    borderWidth: 0,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var v = ctx.parsed.y;
                                return Number(v).toLocaleString('vi-VN') + ' ₫';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0 } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return Number(v).toLocaleString('vi-VN') + ' ₫'; }
                        }
                    }
                }
            }
        });
    }
    @endif

    var statusCtx = document.getElementById('bookingsByStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: @json($bookingsByStatus['labels']),
                datasets: [{
                    data: @json($bookingsByStatus['data']),
                    backgroundColor: colors.status,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20, font: { size: 12 } }
                    }
                }
            }
        });
    }

    var trendCtx = document.getElementById('bookingsTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: @json($bookingsTrendChart['labels']),
                datasets: [{
                    label: '{{ __('admin.chart_labels.bookings') }}',
                    data: @json($bookingsTrendChart['data']),
                    borderColor: colors.indigo.stroke,
                    backgroundColor: colors.indigo.fillSoft,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: colors.indigo.stroke,
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true }
                }
            }
        });
    }

    var topVendorsCtx = document.getElementById('topVendorsChart');
    if (topVendorsCtx) {
        new Chart(topVendorsCtx, {
            type: 'bar',
            data: {
                labels: @json($topVendorsChart['labels'] ?? []),
                datasets: [{
                    label: '{{ __('admin.chart_labels.vendors_revenue') }}',
                    data: @json($topVendorsChart['data'] ?? []),
                    backgroundColor: colors.emerald.fill,
                    borderColor: colors.emerald.stroke,
                    borderWidth: 0,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 28,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var v = ctx.parsed.x;
                                return Number(v).toLocaleString('vi-VN') + ' ₫';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return Number(v).toLocaleString('vi-VN') + ' ₫'; }
                        }
                    },
                    y: { grid: { display: false } }
                }
            }
        });
    }
    });
})();
</script>
@endsection
