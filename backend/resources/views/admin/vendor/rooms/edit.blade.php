@extends('admin.layouts.app')
@section('title', __('admin.vendor.rooms.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.rooms.edit') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.rooms.title'), 'url' => route('admin.vendor.rooms.index')], ['label' => __('admin.vendor.rooms.form.update')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.rooms.update', $room) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.rooms.form.name') }} *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $room->name) }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.rooms.form.capacity') }} *</label>
                        <input type="number" name="capacity" class="form-control" value="{{ old('capacity', $room->capacity) }}" min="1" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('admin.vendor.rooms.form.total_rooms') }} *</label>
                        <input type="number" name="total_rooms" class="form-control" value="{{ old('total_rooms', $room->total_rooms) }}" min="1" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.rooms.form.base_price') }} *</label>
                    <input type="number" name="base_price" class="form-control" step="0.01" value="{{ old('base_price', $room->base_price) }}" min="0" required>
                </div>
                @if(isset($amenities) && $amenities->isNotEmpty())
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.rooms.form.amenities') }}</label>
                    <div class="row">
                        @foreach($amenities as $a)
                        <div class="col-md-4 col-lg-3">
                            <div class="form-check">
                                <input type="checkbox" name="amenities[]" value="{{ $a->id }}" id="amenity_{{ $a->id }}" class="form-check-input" {{ in_array($a->id, old('amenities', $room->amenities->pluck('id')->toArray())) ? 'checked' : '' }}>
                                <label class="form-check-label" for="amenity_{{ $a->id }}">{{ $a->name }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @php
                    $cp = $room->cancellation_policy;
                    $cpPreset = old('cancellation_policy_preset');
                    if ($cpPreset === null && $cp) {
                        if (($cp['type'] ?? '') === 'non_refundable') { $cpPreset = 'non_refundable'; }
                        elseif (($cp['type'] ?? '') === 'free_until_hours') {
                            $h = (int)($cp['hours'] ?? 0);
                            $cpPreset = $h === 24 ? 'free_24' : ($h === 48 ? 'free_48' : ($h === 168 ? 'free_168' : 'custom'));
                        } else { $cpPreset = 'custom'; }
                    } elseif ($cpPreset === null) { $cpPreset = 'none'; }
                    $cpCustom = old('cancellation_policy_custom', $cpPreset === 'custom' && is_array($cp) ? json_encode($cp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
                @endphp
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.rooms.form.cancellation_policy') }}</label>
                    <select name="cancellation_policy_preset" id="cancellation_policy_preset" class="form-select">
                        <option value="none" {{ $cpPreset === 'none' ? 'selected' : '' }}>{{ __('admin.vendor.rooms.form.use_hotel_default') }}</option>
                        <option value="non_refundable" {{ $cpPreset === 'non_refundable' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_non_refundable') }}</option>
                        <option value="free_24" {{ $cpPreset === 'free_24' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_free_24') }}</option>
                        <option value="free_48" {{ $cpPreset === 'free_48' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_free_48') }}</option>
                        <option value="free_168" {{ $cpPreset === 'free_168' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_free_168') }}</option>
                        <option value="custom" {{ $cpPreset === 'custom' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_custom') }}</option>
                    </select>
                    <div id="cancellation_policy_custom_wrap" class="mt-2" style="{{ $cpPreset !== 'custom' ? 'display:none' : '' }}">
                        <label class="form-label small">Custom policy JSON</label>
                        <textarea name="cancellation_policy_custom" class="form-control font-monospace small" rows="6" placeholder='{"type":"rules","rules":[...]}'>{{ $cpCustom }}</textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.rooms.form.update') }}</button>
                <a href="{{ route('admin.vendor.rooms.availability', $room) }}" class="btn btn-info">{{ __('admin.vendor.rooms.availability_label') }}</a>
                <a href="{{ route('admin.vendor.rooms.index') }}" class="btn btn-secondary">{{ __('admin.vendor.rooms.form.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('cancellation_policy_preset').addEventListener('change', function() {
    document.getElementById('cancellation_policy_custom_wrap').style.display = this.value === 'custom' ? '' : 'none';
});
</script>
@endsection
