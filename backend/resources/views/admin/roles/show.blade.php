@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ $role->name }}"
        :breadcrumbs="[
        ['label' => __('admin.vendor.roles.title'), 'url' => route('admin.roles.index')],
        ['label' => $role->name]
    ]"
    />
    <h5>{{ __('admin.vendor.roles.table.permissions') }}:</h5>
    <ul>
        @foreach($role->permissions as $permission)
            <li>{{ $permission->name }}</li>
        @endforeach
    </ul>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.back') }}</a>
</div>
@endsection
