<?php

use App\Support\RichTextSanitizer;

test('sanitizer keeps formatting but strips scripts and unsafe attributes', function (): void {
    $html = '<h2>Tour 1 ngày</h2><p onclick="evil()">Chào <strong>bạn</strong></p>'
        .'<script>alert(1)</script>'
        .'<a href="javascript:alert(1)">x</a> <a href="https://example.com">ok</a>'
        .'<img src="https://example.com/a.jpg" alt="view" onerror="evil()"><ul><li>Ăn sáng</li></ul>';

    $out = RichTextSanitizer::sanitize($html);

    expect($out)->toContain('<h2>Tour 1 ngày</h2>')
        ->and($out)->toContain('<strong>bạn</strong>')
        ->and($out)->not->toContain('<script')
        ->and($out)->not->toContain('onclick')
        ->and($out)->not->toContain('onerror')
        ->and($out)->not->toContain('javascript:')
        ->and($out)->toContain('href="https://example.com"')
        ->and($out)->toContain('<img src="https://example.com/a.jpg" alt="view">')
        ->and($out)->toContain('<li>Ăn sáng</li>');
});

test('sanitizer unwraps disallowed tags but keeps their text', function (): void {
    $out = RichTextSanitizer::sanitize('<div>Hello <span>world</span></div>');

    expect($out)->toContain('Hello')->and($out)->toContain('world')
        ->and($out)->not->toContain('<div')->and($out)->not->toContain('<span');
});
