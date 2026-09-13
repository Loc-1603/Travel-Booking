<?php

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

function isAdmin()
{
    return auth()->check() && auth()->user()->hasRole('admin|super-admin');
}

/**
 * Format amount as VND currency with Vietnamese formatting.
 */
function format_vnd(?float $amount = 0): string
{
    if ($amount === null) {
        $amount = 0;
    }
    // Use NumberFormatter if available for locale vi_VN
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter('vi_VN', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);
        $formatted = $formatter->formatCurrency($amount, 'VND');
        // NumberFormatter may output non-breaking spaces, normalize
        return str_replace("\xc2\xa0", ' ', $formatted);
    }
    // Fallback
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Generate a QR code data URI for embedding in HTML.
 */
function qrCodeDataUri(string $data, int $size = 120): string
{
    $builder = new Builder(
        writer: new PngWriter(),
        data: $data,
        size: $size,
        margin: 5
    );

    return $builder->build()->getDataUri();
}
