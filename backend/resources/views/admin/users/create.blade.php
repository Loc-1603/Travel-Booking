@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">

    <x-page-title
        title="{{ __('admin.vendor.users.create') }}"
        :breadcrumbs="[
        ['label' => __('admin.sidebar.dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('admin.vendor.users.create')]
    ]"
    />

    <form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data">
    @csrf

    <div class="mb-3">
        <label>{{ __('admin.vendor.users.form.photo_optional') }}</label>
        <input type="file" name="avatar" class="form-control" accept="image/*">
    </div>

    <div class="mb-3">
        <label>{{ __('admin.vendor.users.form.name') }}</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>{{ __('admin.vendor.users.form.email') }}</label>
        <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>{{ __('admin.vendor.users.form.password') }}</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>{{ __('admin.vendor.users.form.confirm_password') }}</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>{{ __('admin.vendor.users.form.assign_roles') }}</label><br>
        @foreach($roles as $role)
            <label>
                <input type="checkbox" name="roles[]" value="{{ $role->name }}">
                {{ $role->name }}
            </label><br>
        @endforeach
    </div>

    <button class="btn btn-primary">{{ __('admin.vendor.users.form.create') }}</button>
    </form>
    </div>
@endsection
