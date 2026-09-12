@extends('admin.layouts.app')
@section('title', __('admin.vendor.hotels.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.hotels.edit') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.hotels.title'), 'url' => route('admin.vendor.hotels.index')], ['label' => __('admin.vendor.hotels.form.update')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.hotels.update', $hotel) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.name') }} *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $hotel->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.status') }} *</label>
                        <select name="status" class="form-select">
                            <option value="active" {{ old('status', $hotel->status) === 'active' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.status_active') }}</option>
                            <option value="inactive" {{ old('status', $hotel->status) === 'inactive' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.status_inactive') }}</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.hotels.form.description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $hotel->description) }}</textarea>
                </div>
                @include('admin.vendor.hotels.partials.location-autocomplete', ['hotel' => $hotel])
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.country') }}</label>
                        <select name="country_id" id="country_id" class="form-select">
                            <option value="">{{ __('admin.vendor.hotels.form.select_country') }}</option>
                            @foreach($countries as $c)
                                <option value="{{ $c->id }}" data-image="{{ $c->image ? asset('storage/'.$c->image) : '' }}" {{ old('country_id', $hotel->country_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <div id="country_image_preview" class="mt-2"></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.city') }}</label>
                        <select name="city_id" id="city_id" class="form-select">
                            <option value="">{{ __('admin.vendor.hotels.form.select_city') }}</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" data-country-id="{{ $city->country_id }}" data-image="{{ $city->image ? asset('storage/'.$city->image) : '' }}" {{ old('city_id', $hotel->city_id) == $city->id ? 'selected' : '' }}>{{ $city->name }} ({{ $city->country->name ?? '' }})</option>
                            @endforeach
                        </select>
                        <div id="city_image_preview" class="mt-2"></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.custom_city') }}</label>
                        <input type="text" name="city" class="form-control mb-1" value="{{ old('city', $hotel->city) }}" placeholder="{{ __('admin.vendor.hotels.form.custom_city_placeholder') }}">
                        <input type="text" name="country" class="form-control" value="{{ old('country', $hotel->country) }}" placeholder="{{ __('admin.vendor.hotels.form.custom_country_placeholder') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.check_in') }}</label>
                        <input type="time" name="check_in" class="form-control" value="{{ old('check_in', $hotel->check_in) }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.check_out') }}</label>
                        <input type="time" name="check_out" class="form-control" value="{{ old('check_out', $hotel->check_out) }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.late_checkout_price') }}</label>
                        <input type="number" name="late_checkout_price" class="form-control" value="{{ old('late_checkout_price', $hotel->late_checkout_price) }}" min="0" step="0.01" placeholder="0.00">
                        <small class="text-muted">{{ __('admin.vendor.hotels.form.late_checkout_price_help') }}</small>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.tax_rate') }}</label>
                        <input type="number" name="tax_rate" class="form-control" value="{{ old('tax_rate', $hotel->tax_rate !== null ? $hotel->tax_rate * 100 : '') }}" min="0" max="100" step="0.01" placeholder="10">
                        <small class="text-muted">{{ __('admin.vendor.hotels.form.tax_rate_help') }}</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.tax_name') }}</label>
                        <input type="text" name="tax_name" class="form-control" value="{{ old('tax_name', $hotel->tax_name) }}" placeholder="{{ __('admin.vendor.hotels.form.tax_name_placeholder') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('admin.vendor.hotels.form.tax_type') }}</label>
                        <select name="tax_inclusive" class="form-select">
                            <option value="0" {{ old('tax_inclusive', $hotel->tax_inclusive) == 0 ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.tax_exclusive') }}</option>
                            <option value="1" {{ old('tax_inclusive', $hotel->tax_inclusive) == 1 ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.tax_inclusive') }}</option>
                        </select>
                    </div>
                </div>
                @if(isset($amenities) && $amenities->isNotEmpty())
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.hotels.form.amenities') }}</label>
                    <div class="row">
                        @foreach($amenities as $a)
                        <div class="col-md-4 col-lg-3">
                            <div class="form-check">
                                <input type="checkbox" name="amenities[]" value="{{ $a->id }}" id="amenity_{{ $a->id }}" class="form-check-input" {{ in_array($a->id, old('amenities', $hotel->amenities->pluck('id')->toArray())) ? 'checked' : '' }}>
                                <label class="form-check-label" for="amenity_{{ $a->id }}">{{ $a->name }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @php
                    $cp = $hotel->cancellation_policy;
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
                    <label class="form-label">{{ __('admin.vendor.hotels.form.cancellation_policy') }}</label>
                    <select name="cancellation_policy_preset" id="cancellation_policy_preset" class="form-select">
                        <option value="none" {{ $cpPreset === 'none' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_none') }}</option>
                        <option value="non_refundable" {{ $cpPreset === 'non_refundable' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_non_refundable') }}</option>
                        <option value="free_24" {{ $cpPreset === 'free_24' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_free_24') }}</option>
                        <option value="free_48" {{ $cpPreset === 'free_48' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_free_48') }}</option>
                        <option value="free_168" {{ $cpPreset === 'free_168' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_free_168') }}</option>
                        <option value="custom" {{ $cpPreset === 'custom' ? 'selected' : '' }}>{{ __('admin.vendor.hotels.form.cancellation_custom') }}</option>
                    </select>
                    <div id="cancellation_policy_custom_wrap" class="mt-2" style="{{ $cpPreset !== 'custom' ? 'display:none' : '' }}">
                        <label class="form-label small">Custom policy JSON</label>
                        <textarea name="cancellation_policy_custom" class="form-control font-monospace small" rows="6" placeholder='{"type":"rules","rules":[{"hours_before":168,"refund_percent":100},{"hours_before":48,"refund_percent":50},{"hours_before":0,"refund_percent":0}]}'>{{ $cpCustom }}</textarea>
                        <small class="text-muted">{{ __('admin.vendor.hotels.form.custom_policy_help') }}</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.hotels.form.update') }}</button>
                <a href="{{ route('admin.vendor.hotels.index') }}" class="btn btn-secondary">{{ __('admin.vendor.hotels.form.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('cancellation_policy_preset').addEventListener('change', function() {
    document.getElementById('cancellation_policy_custom_wrap').style.display = this.value === 'custom' ? '' : 'none';
});
var countrySelect = document.getElementById('country_id');
var citySelect = document.getElementById('city_id');
function filterCities() {
    var cid = countrySelect.value;
    for (var i = 0; i < citySelect.options.length; i++) {
        var opt = citySelect.options[i];
        if (opt.value === '') { opt.style.display = ''; continue; }
        opt.style.display = (opt.dataset.countryId === cid || !cid) ? '' : 'none';
        if (cid && opt.dataset.countryId !== cid) opt.selected = false;
    }
    showCityImage(citySelect.value);
}
function showCountryImage(val) {
    var el = document.getElementById('country_image_preview');
    var opt = countrySelect.querySelector('option[value="'+val+'"]');
    if (opt && opt.dataset.image) {
        el.innerHTML = '<img src="'+opt.dataset.image+'" alt="" class="rounded" style="max-height:80px">';
    } else { el.innerHTML = ''; }
}
function showCityImage(val) {
    var el = document.getElementById('city_image_preview');
    var opt = citySelect.querySelector('option[value="'+val+'"]');
    if (opt && opt.dataset.image) {
        el.innerHTML = '<img src="'+opt.dataset.image+'" alt="" class="rounded" style="max-height:80px">';
    } else { el.innerHTML = ''; }
}
countrySelect.addEventListener('change', function() {
    filterCities();
    showCountryImage(this.value);
});
citySelect.addEventListener('change', function() {
    showCityImage(this.value);
    var opt = this.options[this.selectedIndex];
    if (opt && opt.dataset.countryId && !countrySelect.value) countrySelect.value = opt.dataset.countryId;
});
filterCities();
showCountryImage(countrySelect.value);
</script>
@include('admin.vendor.hotels.partials.location-autocomplete-js')
@endsection
