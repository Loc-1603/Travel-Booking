@extends('admin.layouts.app')
@section('content')
    <div class="container-fluid">
        <x-page-title
            title="{{ __('admin.vendor.permissions.edit') }}"
            :breadcrumbs="[
        ['label' => __('admin.vendor.permissions.title'), 'url' => route('admin.permissions.index')],
        ['label' => __('admin.vendor.permissions.edit')]
    ]"
        />
    <form action="{{ route('admin.permissions.update', $permission->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label>{{ __('admin.vendor.permissions.form.name') }}</label>
            <input type="text" name="name" value="{{ $permission->name }}" class="form-control" required>
        </div>

        <button class="btn btn-success mt-3">{{ __('admin.vendor.permissions.form.update') }}</button>
    </form>
@endersection
