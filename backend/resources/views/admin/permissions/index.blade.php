
@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">
    <x-page-title
        title="{{ __('admin.vendor.permissions.title') }}"
        :breadcrumbs="[
        ['label' => __('admin.sidebar.dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('admin.vendor.permissions.title')]
    ]"
    />
    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary mb-3">{{ __('admin.vendor.permissions.create') }}</a>
    @if(session('success'))
       <x-alert />
    @endif

    <table class="table mt-3">
        <thead>
        <tr>
            <th>{{ __('admin.vendor.permissions.table.name') }}</th>
            <th>{{ __('admin.vendor.permissions.table.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($permissions as $permission)
            <tr>
                <td>{{ $permission->name }}</td>
                <td>
                    <a href="{{ route('admin.permissions.edit', $permission->id) }}" class="btn btn-sm btn-warning">{{ __('admin.vendor.common.edit') }}</a>
                    <form action="{{ route('admin.permissions.destroy', $permission->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger" onclick="return confirm('{{ __('admin.vendor.permissions.confirm_delete') }}')">{{ __('admin.vendor.common.delete') }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{$permissions->links()}}
</div>
@endsection
