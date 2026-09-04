@extends('emails.layouts.base')

@section('email_title', 'Narudžba #' . $order->id . ' je poslana — Vremeplov')
@section('preheader', 'Vaša pošiljka je na putu. Otvorite praćenje dostave.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#4f7654;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">✓ Predano dostavnoj službi</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Vaša pošiljka je na putu</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Pozdrav {{ $order->payment_fname }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Narudžbu smo pažljivo zapakirali i predali odabranoj dostavnoj službi.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => 'Poslano'])

    @if ($order->tracking_code || $order->shipping_tracking_url)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;background-color:#f5ecd9;border:1px solid #ddc89f;border-radius:9px;">
            <tr>
                <td style="padding:19px 21px;color:#5f4b31;font-size:13px;line-height:21px;">
                    @if ($order->tracking_code)<strong style="color:#2d2224;">Broj pošiljke:</strong> {{ $order->tracking_code }}@endif
                    @if ($order->shipping_tracking_url)
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:14px;">
                            <tr><td bgcolor="#2d2224" style="border-radius:6px;"><a href="{{ URL::temporarySignedRoute('order.tracking.public', now()->addDays(30), ['order' => $order->id]) }}" target="_blank" class="mail-button" style="display:inline-block;padding:13px 22px;border:1px solid #2d2224;border-radius:6px;background-color:#2d2224;color:#ffffff;font-size:14px;font-weight:bold;line-height:20px;text-decoration:none;">Prati pošiljku &nbsp;&rarr;</a></td></tr>
                        </table>
                    @endif
                    <p style="margin:12px 0 0;color:#7b6b57;font-size:11px;line-height:17px;">Prvi status može se pojaviti tek nakon što dostavna služba obradi paket.</p>
                </td>
            </tr>
        </table>
    @endif

    @include('emails.layouts.partials.order-price-table', ['order' => $order])
    @include('emails.layouts.partials.payment-summary', ['order' => $order, 'showBankInstructions' => false])

    <p style="margin:27px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Lijep pozdrav,<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
