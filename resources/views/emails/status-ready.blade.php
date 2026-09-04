@extends('emails.layouts.base')

@section('email_title', 'Narudžba #' . $order->id . ' spremna je za preuzimanje — Vremeplov')
@section('preheader', 'Vaša narudžba spremna je za osobno preuzimanje u Vremeplovu.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#4f7654;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">✓ Spremno za preuzimanje</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Vaše knjige su spremne</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Pozdrav {{ $order->payment_fname }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Narudžbu možete preuzeti u Antikvarijatu Vremeplov, Zvonimirova 24, Zagreb, tijekom radnog vremena.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => 'Spremno'])

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;background-color:#f5ecd9;border:1px solid #ddc89f;border-radius:9px;">
        <tr><td style="padding:18px 21px;color:#5f4b31;font-size:13px;line-height:21px;"><strong style="color:#2d2224;">Adresa preuzimanja</strong><br>Zvonimirova 24, 10000 Zagreb<br>Pon–pet: 09–14 i 16–19 h · Sub: 10–13 h</td></tr>
    </table>

    @include('emails.layouts.partials.order-price-table', ['order' => $order])
    @include('emails.layouts.partials.payment-summary', ['order' => $order, 'showBankInstructions' => false])

    <p style="margin:27px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Vidimo se uskoro,<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
