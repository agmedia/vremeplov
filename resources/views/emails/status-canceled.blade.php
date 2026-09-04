@extends('emails.layouts.base')

@section('email_title', 'Narudžba #' . $order->id . ' je otkazana — Vremeplov')
@section('preheader', 'Obavijest o otkazivanju narudžbe #' . $order->id . '.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#9a5249;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Narudžba je otkazana</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Ova kupnja nije dovršena</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Pozdrav {{ $order->payment_fname }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Narudžba je otkazana ili plaćanje nije uspjelo. Ako mislite da je riječ o pogrešci, javite nam se i rado ćemo provjeriti.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => 'Otkazano'])
    @include('emails.layouts.partials.order-price-table', ['order' => $order])

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:25px 0 0;">
        <tr><td bgcolor="#2d2224" style="border-radius:6px;"><a href="{{ route('index') }}" target="_blank" class="mail-button" style="display:inline-block;padding:14px 25px;border:1px solid #2d2224;border-radius:6px;background-color:#2d2224;color:#ffffff;font-size:14px;font-weight:bold;line-height:20px;text-decoration:none;">Povratak u webshop &nbsp;&rarr;</a></td></tr>
    </table>

    <p style="margin:27px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Trebate pomoć? Nazovite 091 762 7441 ili odgovorite na ovaj email.<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
