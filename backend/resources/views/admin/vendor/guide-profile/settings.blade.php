@extends('admin.layouts.app')
@section('title', 'Cài đặt hoạt động & ngày nghỉ')
@section('content')
<div class="container-fluid">
    <x-page-title title="Cài đặt hoạt động & ngày nghỉ - {{ $provider->business_name }}" :breadcrumbs="[['label'=>'Vendor','url'=>route('admin.vendor.dashboard')],['label'=>'Guide Profile','url'=>route('admin.vendor.guide-profile.index')],['label'=>$provider->business_name,'url'=>route('admin.vendor.guide-profile.edit',$provider)],['label'=>'Cài đặt hoạt động']]" />
    <x-alert />
    
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Tỉnh hoạt động & Giá</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.vendor.guide-profile.settings.update', $provider) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tỉnh hoạt động</label>
                        <select name="province_id" class="form-control">
                            <option value="">-- Chọn tỉnh --</option>
                            @foreach($provinces as $p)
                                <option value="{{ $p->id }}" @selected(old('province_id', $provider->province_id) == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giá 1 ngày (VND)</label>
                        <input type="number" step="1000" min="0" name="price_daily" class="form-control" value="{{ old('price_daily', $provider->price_daily) }}">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary">Lưu cài đặt</button>
                    <a href="{{ route('admin.vendor.guide-profile.edit', $provider) }}" class="btn btn-secondary">Quay lại chỉnh sửa hồ sơ</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Ngày không làm việc</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.vendor.guide-profile.settings.blackouts.store', $provider) }}">
                @csrf
                <div class="row g-2 align-end">
                    <div class="col-md-3">
                        <label class="form-label">Từ ngày</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Đến ngày</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lý do</label>
                        <input type="text" name="reason" class="form-control" placeholder="Tùy chọn">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Thêm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Từ</th>
                        <th>Đến</th>
                        <th>Lý do</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($blackouts as $b)
                    <tr>
                        <td>{{ $b->start_date }}</td>
                        <td>{{ $b->end_date }}</td>
                        <td>{{ $b->reason }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.vendor.guide-profile.settings.blackouts.destroy', [$provider, $b]) }}" onsubmit="return confirm('Xóa?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Chưa có ngày nghỉ</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $blackouts->links() }}
        </div>
    </div>
</div>
@endsection
