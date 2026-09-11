@extends('admin.layouts.app')
@section('content')

    <div class="container-fluid">
        <x-page-title
            title="{{ __('admin.vendor.roles.title') }}"
            :breadcrumbs="[
        ['label' => __('admin.sidebar.dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('admin.vendor.roles.title')]
    ]"
        />
    @can('create roles')
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.roles.create') }}</a>
    @endcan
    @if(session('success'))
        <div class="alert alert-success my-2">{{session('status')}} {{ session('success') }}</div>
    @endif
            <table class="table mt-3">
                <thead>
                <tr>
                    <th>{{ __('admin.vendor.roles.table.name') }}</th>
                    <th>{{ __('admin.vendor.roles.table.permissions') }}</th>
                    <th>{{ __('admin.vendor.roles.table.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td>{{ $role->name }}</td>
                        <td>
                            @foreach($role->permissions as $permission)
                                <span class="badge bg-primary">{{ $permission->name }}</span>
                            @endforeach
                        </td>
                        <td>
                            <a href="{{ route('admin.roles.edit', $role->id) }}" class="btn btn-sm btn-warning">{{ __('admin.vendor.common.edit') }}</a>
                            <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" onclick="return confirm('{{ __('admin.vendor.roles.confirm_delete') }}')">{{ __('admin.vendor.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{$roles->links()}}
    </div>
@endsection
