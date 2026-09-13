@extends('admin.layouts.app')
@section('title', 'Add attraction')
@section('content')
<div class="container-fluid">
    <x-page-title title="Add attraction" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour attractions', 'url' => route('admin.tour-attractions.index')], ['label' => 'Create']]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-attractions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Province *</label>
                    <select name="province_id" class="form-select" required>
                        @foreach($provinces as $p)
                        <option value="{{ $p->id }}" {{ ($selectedProvinceId ?? old('province_id')) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude') }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Famous</label>
                        <select name="is_famous" class="form-select">
                            <option value="0">No</option>
                            <option value="1" {{ old('is_famous') ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="{{ route('admin.tour-attractions.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
