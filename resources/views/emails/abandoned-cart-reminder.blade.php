@extends('emails.layouts.base')

@section('email_title', $sequence === 1 ? 'Vaše knjige još vas čekaju — Vremeplov' : 'Želite li dovršiti kupnju? — Vremeplov')
@section('preheader', 'Sigurno nastavite kupnju tamo gdje ste stali.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Nedovršena kupnja</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">{{ $sequence === 1 ? 'Vaše odabrane knjige još vas čekaju' : 'Još uvijek možete dovršiti kupnju' }}</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Pozdrav {{ $order->payment_fname }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Primijetili smo da kupnja nije dovršena. Poveznica ispod vraća vas na siguran checkout, gdje ćemo ponovno provjeriti cijene i dostupnost.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => 'Nedovršeno'])
    @include('emails.layouts.partials.order-price-table', ['order' => $order])

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:25px 0 16px;">
        <tr>
            <td bgcolor="#2d2224" style="border-radius:6px;">
                <a href="{{ $recoveryUrl }}" target="_blank" class="mail-button" style="display:inline-block;padding:14px 25px;border:1px solid #2d2224;border-radius:6px;background-color:#2d2224;color:#ffffff;font-size:14px;font-weight:bold;line-height:20px;text-decoration:none;">Nastavi sigurnu kupnju &nbsp;&rarr;</a>
            </td>
        </tr>
    </table>
    <p style="margin:0;color:#887d72;font-size:12px;line-height:19px;">Poveznica vrijedi {{ config('abandoned_cart.recovery_link_days', 7) }} dana. Artikli nisu rezervirani dok kupnja nije potvrđena.</p>
    <p style="margin:25px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Ako ste kupnju već dovršili, ovu poruku možete zanemariti.<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
