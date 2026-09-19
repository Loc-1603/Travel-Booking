<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice – {{ $booking->uuid }}</title>
    <style>
        body { font-family: system-ui, sans-serif; font-size: 14px; line-height: 1.5; color: #1c1917; max-width: 700px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 1.5rem; margin: 0 0 8px 0; }
        h2 { font-size: 1.125rem; margin: 0 0 8px 0; }
        .meta { color: #78716c; font-size: 0.875rem; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e7e5e4; }
        th { font-weight: 600; background: #fafaf9; }
        .text-right { text-align: right; }
        .totals { margin-top: 24px; margin-left: auto; width: 260px; }
        .totals td { padding: 6px 0; }
        .totals .total-row { font-weight: 700; font-size: 1.125rem; border-top: 2px solid #1c1917; padding-top: 10px; margin-top: 8px; }
        .two-col { display: flex; gap: 32px; flex-wrap: wrap; margin-bottom: 24px; }
        .two-col > div { flex: 1; min-width: 200px; }
        .payment-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.875rem; font-weight: 500; }
        .payment-paid { background: #dcfce7; color: #166534; }
        .payment-pending { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <h1>Invoice / Receipt — Tour 1vs1</h1>
    <p class="meta">Booking reference: <strong>{{ $booking->uuid }}</strong> · Issued: {{ $booking->created_at->format('d/m/Y') }} · Status: {{ $booking->status }}</p>

    <div class="two-col">
        <div>
            <strong>Customer</strong><br>
            {{ $booking->customer->name ?? 'Guest' }}<br>
            {{ $booking->customer->email ?? '' }}
        </div>
        <div>
            <strong>Tour</strong><br>
            {{ $booking->tour->title ?? '—' }}<br>
            @if($booking->tour?->provider)Guide: {{ $booking->tour->provider->business_name }}<br>@endif
            @if($booking->tour?->province){{ $booking->tour->province->name }}@endif
        </div>
    </div>

    <p><strong>Start:</strong> {{ $booking->start_at->format('d/m/Y H:i') }}
        &nbsp; <strong>End:</strong> {{ $booking->end_at->format('d/m/Y H:i') }}
        &nbsp; <strong>Mode:</strong> {{ $booking->pricing_mode }} × {{ $booking->duration_value }}</p>
    @if($booking->meeting_point)<p><strong>Meeting point:</strong> {{ $booking->meeting_point }}</p>@endif

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Base fixed fee</td>
                <td class="text-right">{{ $booking->currency }} {{ number_format((float) $booking->base_fixed, 2) }}</td>
            </tr>
            <tr>
                <td>Duration ({{ $booking->pricing_mode }} × {{ $booking->duration_value }} @ {{ number_format((float) $booking->unit_price, 2) }})</td>
                <td class="text-right">{{ $booking->currency }} {{ number_format((float) $booking->unit_price * (int) $booking->duration_value, 2) }}</td>
            </tr>
            @if((float) ($booking->transport_fee ?? 0) > 0)
            <tr>
                <td>Transport fee</td>
                <td class="text-right">{{ $booking->currency }} {{ number_format((float) $booking->transport_fee, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="text-right">{{ $booking->currency }} {{ number_format((float) $booking->subtotal, 2) }}</td>
        </tr>
        @if((float) ($booking->discount_amount ?? 0) > 0)
        <tr>
            <td>Discount</td>
            <td class="text-right">−{{ $booking->currency }} {{ number_format((float) $booking->discount_amount, 2) }}</td>
        </tr>
        @endif
        @if((float) ($booking->tax_amount ?? 0) > 0)
        <tr>
            <td>{{ config('booking.default_tax_name', 'VAT') }}</td>
            <td class="text-right">{{ $booking->currency }} {{ number_format((float) $booking->tax_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td>Total</td>
            <td class="text-right">{{ $booking->currency }} {{ number_format((float) $booking->total_price, 2) }}</td>
        </tr>
    </table>

    @php
        $payment = $booking->payments()->where('status', 'completed')->first();
    @endphp
    <p><strong>Payment:</strong>
        @if($payment)
            <span class="payment-badge payment-paid">Paid</span> {{ $booking->currency }} {{ number_format((float) $payment->amount, 2) }} @if($payment->provider)({{ $payment->provider }})@endif
        @else
            <span class="payment-badge payment-pending">Pending</span>
        @endif
    </p>

    <p class="meta" style="margin-top: 32px;">Thank you for your booking. This document serves as your receipt.</p>
</body>
</html>
