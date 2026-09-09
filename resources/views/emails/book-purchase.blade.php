@extends('emails.layouts.base')

@section('email_title', 'Nova prijava za otkup knjiga — Vremeplov')
@section('preheader', 'Nova ponuda knjiga čeka pregled u administraciji.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Administracija · otkup knjiga</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Stigla je nova prijava</h1>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Prijava <strong>{{ $purchase->reference }}</strong> sadrži {{ count($purchase->photos ?: []) }} {{ count($purchase->photos ?: []) === 1 ? 'fotografiju' : 'fotografija' }}.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="mail-card" style="width:100%;border:1px solid #e1d7c8;border-radius:9px;background-color:#fbf8f2;">
        <tr>
            <td style="padding:20px 22px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                    <tr><td style="padding:4px 12px 4px 0;color:#89796a;font-size:11px;line-height:18px;">Ime</td><td style="padding:4px 0;color:#2d2224;font-size:13px;font-weight:bold;line-height:19px;">{{ $purchase->full_name }}</td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#89796a;font-size:11px;line-height:18px;">E-mail</td><td style="padding:4px 0;font-size:13px;line-height:19px;"><a href="mailto:{{ $purchase->email }}" style="color:#76542f;font-weight:bold;">{{ $purchase->email }}</a></td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#89796a;font-size:11px;line-height:18px;">Telefon</td><td style="padding:4px 0;color:#2d2224;font-size:13px;font-weight:bold;line-height:19px;">{{ $purchase->phone }}</td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#89796a;font-size:11px;line-height:18px;">Poštanski broj</td><td style="padding:4px 0;color:#2d2224;font-size:13px;font-weight:bold;line-height:19px;">{{ $purchase->postal_code }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;">
        <a href="{{ route('book-purchases.show', $purchase) }}" style="display:inline-block;padding:12px 18px;border-radius:7px;background:#2d2224;color:#fff;text-decoration:none;font-size:14px;font-weight:bold;">Otvori prijavu i fotografije</a>
    </p>
@endsection
