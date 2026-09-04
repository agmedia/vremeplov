@extends('emails.layouts.base')

@section('email_title', 'Status narudžbe #' . $order->id . ' — Antikvarijat Vremeplov')
@section('preheader', 'Novi status narudžbe #' . $order->id . ': ' . $status->title)

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Novosti o vašoj narudžbi</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Status je promijenjen</h1>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Narudžba <strong style="color:#2d2224;">#{{ $order->id }}</strong> sada ima status <strong style="color:#2d2224;">{{ $status->title }}</strong>.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => $status->title])

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
                </td>
            </tr>
        </table>
    @endif

    @if (! empty(trim((string) $comment)))
        <div style="margin-top:24px;padding:17px 19px;background-color:#f8f4ec;border-left:4px solid #c7a361;color:#5f554d;font-size:13px;line-height:21px;"><strong style="color:#2d2224;">Poruka uz promjenu statusa</strong><br>{{ $comment }}</div>
    @endif

    @include('emails.layouts.partials.order-price-table', ['order' => $order])
    @include('emails.layouts.partials.payment-summary', ['order' => $order, 'showBankInstructions' => false])

    <p style="margin:27px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Lijep pozdrav,<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
