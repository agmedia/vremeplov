@extends('emails.layouts.base')

@section('email_title', 'Kako su vam se svidjele knjige iz narudžbe #' . $invitation->order_id . '?')
@section('preheader', 'Jedna minuta vama, velika pomoć sljedećem čitatelju.')

@section('content')
    @php
        $orderItems = $invitation->order
            ? $invitation->order->products->unique('product_id')->take(3)
            : collect();
    @endphp

    <div class="mail-eyebrow">Mala recenzija, velika pomoć</div>
    <h1 class="mail-title">Jesu li knjige pronašle svoje mjesto?</h1>

    <p style="margin:0 0 12px;font-size:16px;line-height:26px;color:#453b35;">
        Pozdrav {{ $invitation->recipient_name }},
    </p>
    <p style="margin:0 0 25px;font-size:16px;line-height:26px;color:#6f6258;">
        Knjige iz vaše narudžbe već su neko vrijeme s vama. Ostavite kratak, iskren dojam — pomoći ćete sljedećem čitatelju da pronađe pravi naslov.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="mail-card" style="margin:0 0 25px;background-color:#fbf8f2;border:1px solid #e1d7c8;border-radius:9px;">
        <tr>
            <td style="padding:20px 22px;font-family:Arial,Helvetica,sans-serif;">
                <div class="mail-label">Vaša narudžba</div>
                <div style="margin-top:4px;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:30px;color:#2d2224;">#{{ $invitation->order_id }}</div>
            </td>
            <td width="155" align="right" style="padding:20px 22px;color:#c7a361;font-family:Arial,Helvetica,sans-serif;font-size:17px;letter-spacing:2px;white-space:nowrap;">★ ★ ★ ★ ★</td>
        </tr>
    </table>

    @if ($orderItems->isNotEmpty())
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 25px;">
            @foreach ($orderItems as $item)
                <tr>
                    @if ($item->product)
                        <td width="58" valign="top" style="padding:0 14px 12px 0;">
                            <img src="{{ $item->product->thumb }}" width="58" alt="" style="display:block;width:58px;max-height:78px;border-radius:4px;object-fit:cover;">
                        </td>
                    @endif
                    <td valign="middle" style="padding:0 0 12px;color:#453b35;font-family:Georgia,'Times New Roman',serif;font-size:15px;line-height:21px;">
                        {{ $item->name ?: optional($item->product)->name }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 23px;">
        <tr>
            <td bgcolor="#2d2224" style="border-radius:6px;">
                <a href="{{ $reviewUrl }}" target="_blank" class="mail-button">Podijelite dojam &nbsp;→</a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 22px;font-size:12px;line-height:19px;color:#89796a;">
        Vaš e-mail ostaje privatan. Osobna poveznica potvrđuje da je recenziju ostavio stvarni kupac i vrijedi {{ config('reviews.request_link_days', 180) }} dana.
    </p>

    <p style="margin:0;padding-top:21px;border-top:1px solid #e4dacb;font-size:14px;line-height:22px;color:#453b35;">
        Srdačan pozdrav,<br>
        <strong>Antikvarijat Vremeplov</strong>
    </p>
@endsection
