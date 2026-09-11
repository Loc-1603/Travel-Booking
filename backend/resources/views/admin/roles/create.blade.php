@extends('admin.layouts.app')
@section('content')
    <div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.roles.create') }}"
        :breadcrumbs="[
        ['label' => __('admin.vendor.roles.title'), 'url' => route('admin.roles.index')],
        ['label' => __('admin.vendor.roles.create')]
    ]"
    />

    <form action="{{ route('admin.roles.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label>{{ __('admin.vendor.roles.form.name') }}</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <h5 class="mt-3">{{ __('admin.vendor.roles.form.assign_permissions') }}</h5>
        <div class="row">
            @foreach($permissions->groupBy(fn($p) => explode(' ', $p->name)[1]) as $group => $perms)
                <div class="col-md-3">
                    <h6 class="text-capitalize">{{ $group }}</h6>
                    @foreach($perms as $permission)
                        <label>
                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}">
                            {{ $permission->name }}
                        </label><br>
                    @endforeach
                </div>
            @endforeach
        </div>

        <button class="btn btn-primary mt-3">{{ __('admin.vendor.roles.form.create') }}</button>
    </form>
    </div>
@endsection
