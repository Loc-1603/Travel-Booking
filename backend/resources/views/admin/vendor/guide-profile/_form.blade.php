@php
    /** @var \App\Models\TourProvider|null $provider */
    $provider = $provider ?? null;
    $method = $method ?? 'POST';
    // bio_json có thể là JSON string (từ old() khi validate lỗi, hoặc dữ liệu
    // cũ bị double-encode). Giải mã về mảng để TipTap luôn dựng đúng nội dung.
    $initialBioJson = old('bio_json');
    if (is_string($initialBioJson)) {
        $initialBioJson = json_decode($initialBioJson, true);
    }
    if (! is_array($initialBioJson)) {
        $initialBioJson = $provider?->bio_json;
        if (is_string($initialBioJson)) {
            $initialBioJson = json_decode($initialBioJson, true);
        }
    }
@endphp
<div class="card">
    <div class="card-body">
        <form action="{{ $action }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif
            <div class="mb-3">
                <label class="form-label">{{ __('admin.vendor.guide_profiles.form.bio_rich') }}</label>
                <div class="btn-toolbar mb-2" role="toolbar" aria-label="Rich text" id="bio-toolbar">
                    <div class="btn-group btn-group-sm me-2" role="group">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="h2" title="Tiêu đề lớn">H2</button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="h3" title="Tiêu đề nhỏ">H3</button>
                    </div>
                    <div class="btn-group btn-group-sm me-2" role="group">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="bold" title="In đậm"><strong>B</strong></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="italic" title="In nghiêng"><em>I</em></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="underline" title="Gạch chân"><u>U</u></button>
                    </div>
                    <div class="btn-group btn-group-sm me-2" role="group">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="bullet" title="Danh sách">• ≡</button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="ordered" title="Danh sách số">1. ≡</button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="quote" title="Trích dẫn">❝</button>
                    </div>
                    <div class="btn-group btn-group-sm me-2" role="group">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="link" title="Chèn liên kết">🔗</button>
                        @if($provider)
                        <button type="button" class="btn btn-outline-secondary" data-cmd="image" title="Chèn hình ảnh">🖼️</button>
                        @endif
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="undo" title="Hoàn tác">↩</button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="clear" title="Xoá định dạng">✕</button>
                    </div>
                </div>
                <div id="bio-editor" class="form-control" style="min-height: 240px; height: auto;"></div>
                <input type="hidden" name="bio_json" id="bio_json">
                <input type="hidden" name="bio_html" id="bio_html">
                <input type="file" id="bio-image-input" accept="image/*" class="d-none">
                <div class="form-text">{{ __('admin.vendor.guide_profiles.form.bio_rich_hint') }}</div>
                <div class="alert alert-warning d-none mt-2" id="bio-editor-fallback">
                    {{ __('admin.vendor.guide_profiles.form.bio_rich_offline') }}
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('admin.vendor.guide_profiles.form.bio') }}</label>
                <textarea name="bio" class="form-control" rows="2">{{ old('bio', $provider?->bio) }}</textarea>
                <div class="form-text">{{ __('admin.vendor.guide_profiles.form.bio_hint') }}</div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('admin.vendor.guide_profiles.form.languages') }}</label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach(__('admin.vendor.guide_profiles.form.languages_options') as $lang)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="languages[]" id="lang_{{ $loop->index }}" value="{{ $lang }}"
                                   @checked(in_array($lang, old('languages', $provider?->languages ?? []), true))>
                            <label class="form-check-label" for="lang_{{ $loop->index }}">{{ $lang }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('admin.vendor.common.save') }}</button>
            <a href="{{ route('admin.vendor.guide-profile.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
        </form>
        <script>
            window.GuideEditorConfig = {
                initialJson: @json($initialBioJson),
                plainBio: @json(old('bio', $provider?->bio)),
                uploadUrl: @json($provider ? route('admin.vendor.guide-profile.content-image', $provider) : null),
                csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        </script>
        @vite(['resources/js/guide-editor.js'])
        <style>
        .tiptap-bio { outline: none; }
        .tiptap-bio p { margin: .5rem 0; }
        .tiptap-bio h2 { font-size: 1.25rem; margin: .75rem 0 .5rem; }
        .tiptap-bio h3 { font-size: 1.1rem; margin: .75rem 0 .5rem; }
        .tiptap-bio ul, .tiptap-bio ol { padding-left: 1.5rem; }
        .tiptap-bio blockquote { border-left: 3px solid #b8860b; padding-left: .75rem; color: #5c5852; }
        .tiptap-bio img { max-width: 100%; border-radius: .75rem; }
        </style>
    </div>
</div>