@extends('emails.layouts.base')

@section('email_title', 'Novi upit s kontakt forme — Vremeplov')
@section('preheader', 'Nova poruka poslana putem kontakt forme.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Administracija · kontakt forma</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Stigao je novi upit</h1>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Odgovorite izravno na adresu pošiljatelja navedenu ispod.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="mail-card" style="width:100%;border:1px solid #e1d7c8;border-radius:9px;background-color:#fbf8f2;">
        <tr>
            <td style="padding:20px 22px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                    <tr><td style="padding:4px 12px 4px 0;color:#89796a;font-size:11px;line-height:18px;">Ime</td><td style="padding:4px 0;color:#2d2224;font-size:13px;font-weight:bold;line-height:19px;">{{ $data['name'] }}</td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#89796a;font-size:11px;line-height:18px;">Email</td><td style="padding:4px 0;font-size:13px;line-height:19px;"><a href="mailto:{{ $data['email'] }}" style="color:#76542f;font-weight:bold;">{{ $data['email'] }}</a></td></tr>
                    @if (! empty($data['phone']))
                        <tr><td style="padding:4px 12px 4px 0;color:#89796a;font-size:11px;line-height:18px;">Telefon</td><td style="padding:4px 0;color:#2d2224;font-size:13px;font-weight:bold;line-height:19px;">{{ $data['phone'] }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div style="margin:24px 0 0;padding:20px 22px;background-color:#ffffff;border:1px solid #e1d7c8;border-left:4px solid #c7a361;color:#453b35;font-size:14px;line-height:23px;white-space:pre-wrap;">{{ $data['message'] }}</div>
@endsection
