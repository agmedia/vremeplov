@extends('emails.layouts.base')

@section('email_title', 'Uplata potvrđena za narudžbu #' . $order->id . ' — Vremeplov')
@section('preheader', 'Uplata za vašu narudžbu #' . $order->id . ' uspješno je potvrđena.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#4f7654;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">✓ Uplata je potvrđena</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Hvala, uplata je uspješna</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Pozdrav {{ $order->payment_fname }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Evidentirali smo uplatu i vaša narudžba ide u pripremu. Poslat ćemo novu obavijest kada bude spremna ili predana dostavnoj službi.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => 'Plaćeno'])
    @include('emails.layouts.partials.order-price-table', ['order' => $order])
    @include('emails.layouts.partials.payment-summary', ['order' => $order, 'showBankInstructions' => false])

    <p style="margin:27px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Lijep pozdrav,<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
