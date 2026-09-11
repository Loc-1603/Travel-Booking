@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.users.edit') }}"
        :breadcrumbs="[
        ['label' => __('admin.sidebar.dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('admin.vendor.users.edit')]
    ]"
    />

    <form method="POST" action="{{ route('admin.users.update', $user->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="mb-3 d-flex align-items-center gap-3">
            <div class="flex-shrink-0">@include('admin.partials.user-avatar', ['user' => $user, 'size' => 64])</div>
            <div class="flex-grow-1">
                <label>{{ __('admin.vendor.users.form.photo') }}</label>
                <input type="file" name="avatar" class="form-control" accept="image/*">
                @if($user->avatar)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                        <label class="form-check-label" for="remove_avatar">{{ __('admin.vendor.users.form.remove_photo') }}</label>
                    </div>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label>{{ __('admin.vendor.users.form.name') }}</label>
            <input type="text" name="name"
                   value="{{ $user->name }}"
                   class="form-control" required>
        </div>

        <div class="mb-3">
            <label>{{ __('admin.vendor.users.form.email') }}</label>
            <input type="email" name="email"
                   value="{{ $user->email }}"
                   class="form-control" required>
        </div>

        <div class="mb-3">
            <label>{{ __('admin.vendor.users.form.new_password_optional') }}</label>
            <input type="password" name="password" class="form-control">
        </div>

        <div class="mb-3">
            <label>{{ __('admin.vendor.users.form.assign_roles') }}</label><br>
            @foreach($roles as $role)
                <label>
                    <input type="checkbox"
                           name="roles[]"
                           value="{{ $role->name }}"
                        {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                    {{ $role->name }}
                </label><br>
            @endforeach
        </div>

        <button class="btn btn-success">{{ __('admin.vendor.users.form.update') }}</button>
    </form>
</div>
@endsection
