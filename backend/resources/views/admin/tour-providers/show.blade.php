@extends('admin.layouts.app')
@section('title', 'Tour provider #'.$tourProvider->id)
@section('content')
<div class="container-fluid">
    <x-page-title title="Tour provider #{{ $tourProvider->id }}" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour providers', 'url' => route('admin.tour-providers.index')], ['label' => 'View']]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Provider</h5></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        @if($tourProvider->resolvedAvatarUrl())
                            <img src="{{ $tourProvider->resolvedAvatarUrl() }}" alt="" class="rounded-circle" style="height:64px;width:64px;object-fit:cover">
                        @else
                            <span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:26px;">{{ mb_strtoupper(mb_substr(trim((string) $tourProvider->business_name) ?: '?', 0, 1)) }}</span>
                        @endif
                        <div>
                            <p class="mb-1"><strong>Business:</strong> {{ $tourProvider->business_name }}</p>
                            <p class="mb-0"><strong>Vendor:</strong> {{ $tourProvider->vendor->name ?? '-' }} ({{ $tourProvider->vendor->email ?? '-' }})</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.tour-providers.edit', $tourProvider) }}" class="btn btn-sm btn-primary">Edit profile + avatar</a>
                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-secondary">{{ $tourProvider->status }}</span></p>
                    <p class="mb-0"><strong>Bio:</strong> {{ $tourProvider->bio ?: '-' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Moderation</h5></div>
                <div class="card-body">
                    <form action="{{ route('admin.tour-providers.approve', $tourProvider) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success">Approve</button>
                    </form>
                    <form action="{{ route('admin.tour-providers.reject', $tourProvider) }}" method="POST" class="d-inline ms-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Reject</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Tours ({{ $tourProvider->tours->count() }})</h5></div>
                <div class="card-body">
                    <ul class="mb-0">
                        @forelse($tourProvider->tours as $t)
                        <li>{{ $t->title }} <span class="badge bg-secondary">{{ $t->status }}</span></li>
                        @empty
                        <li class="text-muted">No tours yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
