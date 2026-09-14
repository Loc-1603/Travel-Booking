@extends('admin.layouts.app')
@section('title', __('admin.vendor.vendor_support_tickets.new'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.vendor_support_tickets.new') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.sidebar.support'), 'url' => route('admin.vendor.support-tickets.index')], ['label' => __('admin.vendor.common.create')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.support-tickets.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.vendor_support_tickets.form.category') }}</label>
                    <select name="category" class="form-select" required>
                        @foreach(\App\Enums\TicketCategory::cases() as $c)
                        <option value="{{ $c->value }}" {{ old('category') === $c->value ? 'selected' : '' }}>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.vendor_support_tickets.form.subject') }}</label>
                    <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required maxlength="255">
                    @error('subject')<span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.vendor_support_tickets.form.message') }}</label>
                    <textarea name="body" class="form-control" rows="5" required maxlength="10000">{{ old('body') }}</textarea>
                    @error('body')<span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.vendor_support_tickets.form.priority') }}</label>
                    <select name="priority" class="form-select">
                        <option value="low" {{ old('priority', 'normal') === 'low' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.form.priority_low') }}</option>
                        <option value="normal" {{ old('priority', 'normal') === 'normal' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.form.priority_normal') }}</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('admin.vendor.vendor_support_tickets.form.priority_high') }}</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.vendor_support_tickets.form.submit') }}</button>
                <a href="{{ route('admin.vendor.support-tickets.index') }}" class="btn btn-outline-secondary">{{ __('admin.vendor.vendor_support_tickets.form.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
