@extends('emails.layouts.base')

@section('email_title', 'Potvrda narudžbe #' . $order->id . ' — Antikvarijat Vremeplov')
@section('preheader', 'Zaprimili smo vašu narudžbu #' . $order->id . '.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">✓ Narudžba je zaprimljena</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Hvala vam na narudžbi</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Pozdrav {{ $order->payment_fname }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Vaša je narudžba sigurno zaprimljena. Javit ćemo vam čim prijeđe u sljedeću fazu obrade.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => 'Zaprimljeno'])
    @include('emails.layouts.partials.payment-summary', ['order' => $order, 'showBankInstructions' => true])
    @include('emails.layouts.partials.order-price-table', ['order' => $order])
    @include('emails.layouts.partials.order-details', ['order' => $order])

    @if (! empty(trim((string) $order->comment)))
        <div style="margin-top:24px;padding:17px 19px;background-color:#f8f4ec;border-left:4px solid #c7a361;color:#5f554d;font-size:13px;line-height:21px;">
            <strong style="color:#2d2224;">Napomena uz narudžbu</strong><br>{{ $order->comment }}
        </div>
    @endif

    <p style="margin:27px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Lijep pozdrav,<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
