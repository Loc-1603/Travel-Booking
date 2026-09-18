import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TiptapImage from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';

document.addEventListener('DOMContentLoaded', () => {
    const editorEl = document.getElementById('bio-editor');
    const form = editorEl?.closest('form');
    if (!editorEl || !form) return;

    const cfg = window.GuideEditorConfig || {};
    const initialJson = cfg.initialJson;
    const plainBio = cfg.plainBio;
    const uploadUrl = cfg.uploadUrl;
    const csrf = cfg.csrf;

    let editor = null;
    try {
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

    const imageInput = document.getElementById('bio-image-input');
    imageInput.addEventListener('change', async (e) => {
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
});