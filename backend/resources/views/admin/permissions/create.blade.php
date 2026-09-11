@extends('admin.layouts.app')
@section('content')
    <div class="container-fluid">
        <x-page-title
            title="{{ __('admin.vendor.permissions.create') }}"
            :breadcrumbs="[
        ['label' => __('admin.vendor.permissions.title'), 'url' => route('admin.permissions.index')],
        ['label' => __('admin.vendor.permissions.create')]
    ]"
        />
        <form action="{{ route('admin.permissions.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>{{ __('admin.vendor.permissions.form.name') }}</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <button class="btn btn-primary mt-3">{{ __('admin.vendor.permissions.form.create') }}</button>
        </form>
    </div>
@endsection
