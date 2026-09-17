@extends('admin.layouts.app')
@section('title', __('admin.vendor.guide_profiles.edit'))
@section('content')
<div class="container-fluid">
    <x-page-title title="{{ __('admin.vendor.guide_profiles.edit') }}" :breadcrumbs="[['label' => 'Vendor', 'url' => route('admin.vendor.dashboard')], ['label' => __('admin.vendor.guide_profiles.title'), 'url' => route('admin.vendor.guide-profile.index')], ['label' => __('admin.vendor.common.edit')]]" />
    <x-alert />
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.vendor.guide-profile.update', $provider) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">{{ __('admin.vendor.guide_profiles.form.business_name') }} *</label>
                    <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $provider->business_name) }}" required>
                </div>
                <div class="mb-3">
                    <a href="{{ route('admin.vendor.guide-profile.settings.index', $provider) }}" class="btn btn-outline-primary btn-sm">Cài đặt hoạt động & ngày nghỉ</a>
                </div>
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
                            <button type="button" class="btn btn-outline-secondary" data-cmd="image" title="Chèn hình ảnh">🖼️</button>
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
                    <textarea name="bio" class="form-control" rows="2">{{ old('bio', $provider->bio) }}</textarea>
                    <div class="form-text">{{ __('admin.vendor.guide_profiles.form.bio_hint') }}</div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.guide_profiles.form.avatar') }}</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                        @if($provider->avatar)
                        <div class="mt-2">
                            <img src="{{ $provider->avatar }}" alt="" class="rounded-circle" style="height:64px;width:64px;object-fit:cover">
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                            <label class="form-check-label" for="remove_avatar">{{ __('admin.vendor.guide_profiles.form.remove_avatar') }}</label>
                        </div>
                        @else
                        <div class="form-text">{{ __('admin.vendor.guide_profiles.form.no_avatar') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('admin.vendor.guide_profiles.form.languages') }}</label>
                        <input type="text" name="languages" class="form-control" value="{{ old('languages', implode(', ', $provider->languages ?? [])) }}" placeholder="vi, en">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('admin.vendor.common.save') }}</button>
                <a href="{{ route('admin.vendor.guide-profile.index') }}" class="btn btn-secondary">{{ __('admin.vendor.common.cancel') }}</a>
            </form>
            <script type="importmap">
            {
                "imports": {
                    "@tiptap/core": "https://esm.sh/@tiptap/core@2.11.5",
                    "@tiptap/starter-kit": "https://esm.sh/@tiptap/starter-kit@2.11.5",
                    "@tiptap/underline": "https://esm.sh/@tiptap/underline@2.11.5",
                    "@tiptap/link": "https://esm.sh/@tiptap/link@2.11.5",
                    "@tiptap/image": "https://esm.sh/@tiptap/image@2.11.5",
                    "@tiptap/text-align": "https://esm.sh/@tiptap/text-align@2.11.5"
                }
            }
            </script>
            <script type="module">
            (async () => {
                const editorEl = document.getElementById('bio-editor');
                const form = editorEl?.closest('form');
                if (!editorEl || !form) return;
                const initialJson = @json(old('bio_json', $provider->bio_json));
                const plainBio = @json(old('bio', $provider->bio));
                const uploadUrl = @json(route('admin.vendor.guide-profile.content-image', $provider));
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                let editor = null;
                try {
                    const [{ Editor }, { default: StarterKit }, { default: Underline },
                        { default: Link }, { default: TiptapImage }, { default: TextAlign }] = await Promise.all([
                        import('@tiptap/core'), import('@tiptap/starter-kit'), import('@tiptap/underline'),
                        import('@tiptap/link'), import('@tiptap/image'), import('@tiptap/text-align'),
                    ]);
                    const validDoc = initialJson && typeof initialJson === 'object' && initialJson.type === 'doc'
                        ? initialJson
                        : (plainBio ? { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: plainBio }] }] } : null);
                    editor = new Editor({
                        element: editorEl,
                        extensions: [
                            StarterKit.configure({ heading: { levels: [2, 3] } }),
                            Underline,
                            Link.configure({ openOnClick: false }),
                            TiptapImage,
                            TextAlign.configure({ types: ['heading', 'paragraph'] }),
                        ],
                        content: validDoc || '',
                        editorProps: { attributes: { class: 'tiptap-bio' } },
                    });
                } catch (e) {
                    document.getElementById('bio-editor-fallback')?.classList.remove('d-none');
                    editorEl.style.display = 'none';
                    document.getElementById('bio-toolbar').style.display = 'none';
                    return;
                }
                const run = (fn) => { editor.chain().focus()[fn]().run(); };
                document.getElementById('bio-toolbar').addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-cmd]');
                    if (!btn) return;
                    const cmd = btn.dataset.cmd;
                    if (cmd === 'h2') editor.chain().focus().toggleHeading({ level: 2 }).run();
                    else if (cmd === 'h3') editor.chain().focus().toggleHeading({ level: 3 }).run();
                    else if (cmd === 'bold') run('toggleBold');
                    else if (cmd === 'italic') run('toggleItalic');
                    else if (cmd === 'underline') run('toggleUnderline');
                    else if (cmd === 'bullet') run('toggleBulletList');
                    else if (cmd === 'ordered') run('toggleOrderedList');
                    else if (cmd === 'quote') run('toggleBlockquote');
                    else if (cmd === 'undo') run('undo');
                    else if (cmd === 'clear') { editor.chain().focus().clearNodes().unsetAllMarks().run(); }
                    else if (cmd === 'link') {
                        const url = prompt('URL liên kết (https://...)');
                        if (url) editor.chain().focus().setLink({ href: url }).run();
                    } else if (cmd === 'image') {
                        document.getElementById('bio-image-input').click();
                    }
                });
                document.getElementById('bio-image-input').addEventListener('change', async (e) => {
                    const file = e.target.files?.[0];
                    if (!file) return;
                    const fd = new FormData();
                    fd.append('image', file);
                    try {
                        const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd });
                        const data = await res.json();
                        if (data.url) editor.chain().focus().setImage({ src: data.url }).run();
                        else alert('Upload ảnh thất bại.');
                    } catch { alert('Upload ảnh thất bại.'); }
                    e.target.value = '';
                });
                form.addEventListener('submit', () => {
                    document.getElementById('bio_json').value = JSON.stringify(editor.getJSON());
                    document.getElementById('bio_html').value = editor.getHTML();
                });
            })();
            </script>
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
</div>
@endsection
