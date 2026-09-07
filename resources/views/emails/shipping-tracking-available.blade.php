@extends('emails.layouts.base')

@section('email_title', 'Vaša pošiljka je poslana — Antikvarijat Vremeplov')
@section('preheader', 'GLS pošiljka za narudžbu #' . $order->id . ' spremna je za praćenje.')

@section('content')
    @php
        $customerName = trim((string) $order->payment_fname);
        $trackingUrl = URL::temporarySignedRoute('order.tracking.public', now()->addDays(30), ['order' => $order->id]);
    @endphp

    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">✓ Narudžba je poslana</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Vaša pošiljka je na putu</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Poštovani{{ $customerName ? ' ' . $customerName : '' }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">GLS pošiljka za narudžbu <strong style="color:#2d2224;">#{{ $order->id }}</strong> kreirana je i spremna za praćenje.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;background-color:#f5ecd9;border:1px solid #ddc89f;border-radius:9px;">
        <tr>
            <td style="padding:19px 21px;color:#5f4b31;font-size:13px;line-height:21px;">
                <strong style="color:#2d2224;">Broj pošiljke:</strong> {{ $order->tracking_code }}<br>
                <strong style="color:#2d2224;">Način dostave:</strong> {{ $order->shipping_method }}
                @if($order->shipping_tracking_status)
                    <br><strong style="color:#2d2224;">Status:</strong> {{ $order->shipping_tracking_status }}
                @endif
            </td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 23px;">
        <tr><td bgcolor="#2d2224" style="border-radius:6px;"><a href="{{ $trackingUrl }}" target="_blank" class="mail-button" style="display:inline-block;padding:14px 25px;border:1px solid #2d2224;border-radius:6px;background-color:#2d2224;color:#ffffff;font-size:15px;font-weight:bold;line-height:20px;text-decoration:none;">Prati pošiljku &nbsp;&rarr;</a></td></tr>
    </table>

    <p style="margin:0 0 22px;color:#89796a;font-size:12px;line-height:19px;">Status se može pojaviti s kratkim odmakom nakon što GLS obradi pošiljku.</p>
    <p style="margin:0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Lijep pozdrav,<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
