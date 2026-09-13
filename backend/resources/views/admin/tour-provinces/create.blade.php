@extends('admin.layouts.app')
@section('title', 'Add tour province')
@section('content')
<div class="container-fluid">
    <x-page-title title="Add tour province" :breadcrumbs="[['label' => 'Admin', 'url' => route('admin.dashboard')], ['label' => 'Tour provinces', 'url' => route('admin.tour-provinces.index')], ['label' => 'Create']]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tour-provinces.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Country</label>
                        <select name="country_id" class="form-select">
                            <option value="">—</option>
                            @foreach($countries as $c)
                            <option value="{{ $c->id }}" {{ old('country_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">City</label>
                        <select name="city_id" class="form-select">
                            <option value="">—</option>
                            @foreach($cities as $c)
                            <option value="{{ $c->id }}" {{ old('city_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Sort order</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Featured</label>
                        <select name="is_featured" class="form-select">
                            <option value="0">No</option>
                            <option value="1" {{ old('is_featured') ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="{{ route('admin.tour-provinces.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
