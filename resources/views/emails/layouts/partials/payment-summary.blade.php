@php
    $paymentLabels = [
        'bank' => 'Virman / internet bankarstvo',
        'cod' => 'Gotovina prilikom pouzeća',
        'wspay' => 'Kartično plaćanje putem WSPaya',
        'paypal' => 'PayPal',
        'pickup' => 'Plaćanje prilikom preuzimanja',
    ];
    $paymentLabel = $paymentLabels[$order->payment_code] ?? 'Plaćanje prilikom preuzimanja';
    $showBankInstructions = ($showBankInstructions ?? false) && $order->payment_code === 'bank';
@endphp

<div style="margin:25px 0 10px;color:#a17436;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.2px;line-height:16px;text-transform:uppercase;">Plaćanje i dostava</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="mail-card" style="width:100%;border:1px solid #e1d7c8;border-radius:9px;background-color:#fbf8f2;">
    <tr>
        <td style="padding:19px 21px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td class="mobile-block" width="50%" valign="top" style="width:50%;padding-right:18px;">
                        <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Plaćanje</div>
                        <div class="mail-value" style="color:#2d2224;font-size:13px;font-weight:bold;line-height:20px;">{{ $paymentLabel }}</div>
                    </td>
                    <td class="mobile-block mobile-last" width="50%" valign="top" style="width:50%;">
                        <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Dostava</div>
                        <div class="mail-value" style="color:#2d2224;font-size:13px;font-weight:bold;line-height:20px;">{{ $order->shipping_method ?: 'Prema odabiru kupca' }}</div>
                    </td>
                </tr>
            </table>

            @if ($showBankInstructions)
                <div style="margin-top:18px;padding-top:17px;border-top:1px solid #e2d8ca;">
                    <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Podaci za uplatu u roku od 48 sati</div>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:9px;">
                        <tr><td style="padding:2px 10px 2px 0;color:#756a60;font-size:12px;">Iznos</td><td align="right" style="padding:2px 0;color:#2d2224;font-size:13px;font-weight:bold;">{{ number_format((float) $order->total, 2, ',', '.') }} €</td></tr>
                        <tr><td style="padding:2px 10px 2px 0;color:#756a60;font-size:12px;">IBAN</td><td align="right" style="padding:2px 0;color:#2d2224;font-size:13px;font-weight:bold;">{{ config('services.bank_transfer.iban') }}</td></tr>
                        <tr><td style="padding:2px 10px 2px 0;color:#756a60;font-size:12px;">Model i poziv na broj</td><td align="right" style="padding:2px 0;color:#2d2224;font-size:13px;font-weight:bold;">00 &nbsp; {{ $order->id }}-{{ now()->format('ym') }}</td></tr>
                    </table>
                    <p style="margin:12px 0 0;color:#756a60;font-size:12px;line-height:19px;">Artikle čuvamo rezervirane 48 sati. Ako uplata ne stigne u tom roku, narudžba se može automatski otkazati.</p>
                    @if (Storage::disk('qr')->exists($order->id . '.jpg'))
                        <div style="padding-top:15px;text-align:center;">
                            <img src="{{ config('settings.images_domain') . 'media/img/qr/' . $order->id . '.jpg' }}" width="230" alt="2D barkod za plaćanje" style="display:inline-block;width:230px;max-width:80%;height:auto;border:1px solid #e1d7c8;">
                            <div style="padding-top:5px;color:#897d72;font-size:10px;line-height:16px;">Skenirajte barkod u aplikaciji mobilnog bankarstva.</div>
                        </div>
                    @endif
                </div>
            @endif
        </td>
    </tr>
</table>
