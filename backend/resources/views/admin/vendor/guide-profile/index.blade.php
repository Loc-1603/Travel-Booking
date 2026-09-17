@extends('admin.layouts.app')
@section('title', __('admin.vendor.guide_profiles.my_title'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.guide_profiles.my_title') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.guide_profiles.title')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th></th>
                        <th>{{ __('admin.vendor.guide_profiles.table.business') }}</th>
                        <th>{{ __('admin.vendor.guide_profiles.table.languages') }}</th>
                        <th>{{ __('admin.vendor.guide_profiles.table.tours') }}</th>
                        <th>{{ __('admin.vendor.guide_profiles.table.status') }}</th>
                        <th>{{ __('admin.vendor.guide_profiles.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providers as $p)
                    <tr>
                        <td class="align-middle">
                            @if($p->resolvedAvatarUrl())
                                <img src="{{ $p->resolvedAvatarUrl() }}" alt="" class="rounded-circle" style="height:40px;width:40px;object-fit:cover">
                            @else
                                <span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;font-size:16px;">{{ mb_strtoupper(mb_substr(trim((string) $p->business_name) ?: '?', 0, 1)) }}</span>
                            @endif
                        </td>
                        <td>{{ $p->business_name }}</td>
                        <td>{{ implode(', ', $p->languages ?? []) }}</td>
                        <td>{{ $p->tours_count }}</td>
                        <td><span class="badge bg-secondary">{{ $p->status }}</span></td>
                        <td>
                            <a href="{{ route('admin.vendor.guide-profile.edit', $p) }}" class="btn btn-sm btn-primary">{{ __('admin.vendor.common.edit') }}</a>
                            <a href="{{ route('admin.vendor.guide-profile.settings.index', $p) }}" class="btn btn-sm btn-outline-primary">Cài đặt hoạt động</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted">{{ __('admin.vendor.guide_profiles.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
