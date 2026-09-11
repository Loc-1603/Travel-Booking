@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.users.title') }}"
        :breadcrumbs="[
        ['label' => __('admin.sidebar.dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('admin.vendor.users.title')]
    ]"
    />
    @can('create users')
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.users.create') }}</a>
    @endcan
    <x-alert />

    <div class="table-responsive">
    <table class="table mt-3">
        <thead>
        <tr>
            <th width="56">{{ __('admin.vendor.users.table.photo') }}</th>
            <th>{{ __('admin.vendor.users.table.name') }}</th>
            <th>{{ __('admin.vendor.users.table.email') }}</th>
            <th>{{ __('admin.vendor.users.table.roles') }}</th>
            <th width="180">{{ __('admin.vendor.users.table.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($users as $user)
            <tr>
                <td class="align-middle">@include('admin.partials.user-avatar', ['user' => $user, 'size' => 40])</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    @foreach($user->roles as $role)
                        <span class="badge bg-info">{{ $role->name }}</span>
                    @endforeach
                </td>
                <td>
                    <a href="{{ route('admin.users.edit', $user->id) }}"
                       class="btn btn-sm btn-warning">{{ __('admin.vendor.common.edit') }}</a>

                    <form action="{{ route('admin.users.destroy', $user->id) }}"
                          method="POST"
                          style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger"
                                onclick="return confirm('{{ __('admin.vendor.users.confirm_delete') }}')">
                            {{ __('admin.vendor.common.delete') }}
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@endsection
