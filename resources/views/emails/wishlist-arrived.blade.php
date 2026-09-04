@extends('emails.layouts.base')

@php
    $wishlistName = data_get($product, 'name', 'Odabrani artikl');
    $wishlistUrl = url(data_get($product, 'url', '/'));
    $wishlistImage = data_get($product, 'image');
    if ($wishlistImage && ! preg_match('#^https?://#i', $wishlistImage)) {
        $wishlistImage = rtrim((string) config('settings.images_domain'), '/') . '/' . ltrim($wishlistImage, '/');
    }
@endphp

@section('email_title', 'Artikl s vaše liste želja ponovno je dostupan — Vremeplov')
@section('preheader', $wishlistName . ' ponovno je dostupan.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#4f7654;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Dobra vijest · ponovno dostupno</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Pronašli smo artikl koji ste čekali</h1>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Artikl s vaše liste želja ponovno je dostupan u Vremeplovu. Budući da često imamo samo jedan primjerak, preporučujemo da ga pogledate dok je još na stanju.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="mail-card" style="width:100%;border:1px solid #e1d7c8;border-radius:9px;background-color:#fbf8f2;">
        <tr>
            @if ($wishlistImage)
                <td class="mobile-block" width="118" valign="middle" align="center" style="width:118px;padding:20px 0 20px 20px;">
                    <img src="{{ $wishlistImage }}" width="88" alt="{{ $wishlistName }}" style="display:block;width:88px;max-width:88px;height:auto;border-radius:4px;">
                </td>
            @endif
            <td class="mobile-block mobile-last" valign="middle" style="padding:20px 22px;">
                <div class="mail-label" style="color:#89796a;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Ponovno dostupno</div>
                <div style="margin-top:4px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:20px;line-height:27px;">{{ $wishlistName }}</div>
            </td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 0;">
        <tr><td bgcolor="#2d2224" style="border-radius:6px;"><a href="{{ $wishlistUrl }}" target="_blank" class="mail-button" style="display:inline-block;padding:14px 25px;border:1px solid #2d2224;border-radius:6px;background-color:#2d2224;color:#ffffff;font-size:14px;font-weight:bold;line-height:20px;text-decoration:none;">Pogledaj artikl &nbsp;&rarr;</a></td></tr>
    </table>
@endsection
