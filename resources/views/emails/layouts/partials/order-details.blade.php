<div style="margin:25px 0 10px;color:#a17436;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.2px;line-height:16px;text-transform:uppercase;">Podaci kupca</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="mail-card" style="width:100%;border:1px solid #e1d7c8;border-radius:9px;background-color:#fbf8f2;">
    <tr>
        <td style="padding:20px 22px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td class="mobile-block" width="50%" valign="top" style="width:50%;padding:0 18px 15px 0;">
                        <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Ime i prezime</div>
                        <div class="mail-value" style="color:#2d2224;font-size:14px;font-weight:bold;line-height:21px;">{{ trim($order->payment_fname . ' ' . $order->payment_lname) }}</div>
                    </td>
                    <td class="mobile-block" width="50%" valign="top" style="width:50%;padding:0 0 15px;">
                        <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Kontakt</div>
                        <div style="color:#453b35;font-size:13px;line-height:20px;">
                            <a href="mailto:{{ $order->payment_email }}" style="color:#76542f;">{{ $order->payment_email }}</a>
                            @if ($order->payment_phone)<br>{{ $order->payment_phone }}@endif
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="mobile-block mobile-last" width="50%" valign="top" style="width:50%;padding:0 18px 0 0;">
                        <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Adresa</div>
                        <div style="color:#453b35;font-size:13px;line-height:20px;">
                            {{ $order->payment_address }}<br>
                            {{ trim($order->payment_zip . ' ' . $order->payment_city) }}
                            @if ($order->payment_state && $order->payment_state !== 'Croatia')<br>{{ $order->payment_state }}@endif
                        </div>
                    </td>
                    @if (! empty($order->company) || ! empty($order->oib))
                        <td class="mobile-block mobile-last" width="50%" valign="top" style="width:50%;padding:0;">
                            <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Podaci za račun</div>
                            <div style="color:#453b35;font-size:13px;line-height:20px;">
                                {{ $order->company }}@if ($order->company && $order->oib)<br>@endif
                                @if ($order->oib)OIB: {{ $order->oib }}@endif
                            </div>
                        </td>
                    @else
                        <td class="mobile-block mobile-last" width="50%" valign="top" style="width:50%;padding:0;">
                            <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Dostava</div>
                            <div style="color:#453b35;font-size:13px;line-height:20px;">{{ $order->shipping_method ?: 'Prema odabiru kupca' }}</div>
                        </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
</table>
