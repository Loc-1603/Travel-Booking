@extends('admin.layouts.app')
@section('title', 'Edit attraction')
@section('content')
<div class="container-fluid">
    <x-page-title title="Edit attraction" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour attractions', 'url' => route('admin.tour-attractions.index')], ['label' => 'Edit']]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-attractions.update', $tourAttraction) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Province *</label>
                    <select name="province_id" class="form-select" required>
                        @foreach($provinces as $p)
                        <option value="{{ $p->id }}" {{ old('province_id', $tourAttraction->province_id) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $tourAttraction->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $tourAttraction->description) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        @if($tourAttraction->image)
                        <img src="{{ $tourAttraction->image }}" alt="" class="rounded mt-2" style="height:60px">
                        @endif
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude', $tourAttraction->latitude) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude', $tourAttraction->longitude) }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Famous</label>
                        <select name="is_famous" class="form-select">
                            <option value="0" {{ ! old('is_famous', $tourAttraction->is_famous) ? 'selected' : '' }}>No</option>
                            <option value="1" {{ old('is_famous', $tourAttraction->is_famous) ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.tour-attractions.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
