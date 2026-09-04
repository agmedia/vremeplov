@extends('emails.layouts.base')

@php($passwordResetUrl = $resetUrl ?? route('reset.password.get', ['token' => $token, 'email' => $email ?? null]))

@section('email_title', 'Resetiranje lozinke — Antikvarijat Vremeplov')
@section('preheader', 'Sigurna poveznica za postavljanje nove lozinke.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Sigurnost korisničkog računa</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Postavite novu lozinku</h1>
    <p style="margin:0 0 8px;color:#453b35;font-size:16px;line-height:25px;">Pozdrav{{ ! empty($user->name ?? null) ? ' ' . $user->name : '' }},</p>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Primili smo zahtjev za promjenu lozinke vašeg Vremeplov računa. Gumb ispod vodi na sigurnu stranicu za postavljanje nove lozinke.</p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px;">
        <tr><td bgcolor="#2d2224" style="border-radius:6px;"><a href="{{ $passwordResetUrl }}" target="_blank" class="mail-button" style="display:inline-block;padding:14px 25px;border:1px solid #2d2224;border-radius:6px;background-color:#2d2224;color:#ffffff;font-size:14px;font-weight:bold;line-height:20px;text-decoration:none;">Postavi novu lozinku &nbsp;&rarr;</a></td></tr>
    </table>

    <div style="padding:17px 19px;background-color:#f8f4ec;border-left:4px solid #c7a361;color:#665b52;font-size:12px;line-height:19px;">Ako niste zatražili promjenu lozinke, zanemarite ovu poruku. Vaša postojeća lozinka ostaje nepromijenjena.</div>
    <p style="margin:22px 0 0;color:#887d72;font-size:11px;line-height:18px;word-break:break-all;">Ako gumb ne radi, kopirajte ovu poveznicu u preglednik:<br><a href="{{ $passwordResetUrl }}" style="color:#76542f;">{{ $passwordResetUrl }}</a></p>
@endsection
