<?php

namespace App\Support;

/**
 * Sanitize rich HTML (từ TipTap editor của guide) theo allowlist,
 * không cần thêm dependency ngoài.
 *
 * - Chỉ giữ thẻ trình bày cơ bản: p, headings, bold/italic/underline,
 *   lists, quote, link, image, hr.
 * - Chỉ giữ attribute an toàn (href http(s)/mailto, src http(s) hoặc
 *   /storage tương đối, alt). Bỏ mọi event handler, style, script.
 */
class RichTextSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'h2', 'h3', 'strong', 'em', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'hr',
    ];

    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'title'],
    ];

    public static function sanitize(?string $html, int $maxLength = 60000): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $html = mb_substr($html, 0, $maxLength);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if (! $root) {
            return '';
        }

        self::cleanNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanNode(\DOMNode $node): void
    {
        $remove = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Bỏ thẻ script/style hoàn toàn, thẻ khác giữ lại text con.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                        $remove[] = $child;
                    } else {
                        self::cleanNode($child);
                        $fragment = $child->ownerDocument->createDocumentFragment();
                        while ($child->firstChild) {
                            $fragment->appendChild($child->firstChild);
                        }
                        $node->replaceChild($fragment, $child);
                    }
                    continue;
                }

                // Lọc attributes.
                $allowed = self::ALLOWED_ATTRS[$tag] ?? [];
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (! in_array($name, $allowed, true)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                    $value = trim($attr->value);
                    if ($name === 'href' && ! preg_match('#^(https?://|mailto:)#i', $value)) {
                        $child->removeAttribute($attr->name);
                    }
                    if ($name === 'src' && ! preg_match('#^(https?://|/storage/)#i', $value)) {
                        $child->removeAttribute($attr->name);
                    }
                    if ($name === 'target' && ! in_array(strtolower($value), ['_blank'], true)) {
                        $child->removeAttribute($attr->name);
                    }
                }

                self::cleanNode($child);
            } elseif (! $child instanceof \DOMText && ! $child instanceof \DOMComment) {
                $remove[] = $child;
            } elseif ($child instanceof \DOMComment) {
                $remove[] = $child;
            }
        }

        foreach ($remove as $dead) {
            $node->removeChild($dead);
        }
    }

    /**
     * Plain-text excerpt từ HTML đã sanitize (dùng cho meta/card).
     */
    public static function excerpt(string $html, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }
}
