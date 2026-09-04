@extends('emails.layouts.base')

@section('email_title', 'Dnevni izvještaj — Antikvarijat Vremeplov')
@section('preheader', 'Izvještaj za ' . now()->subDay()->format('d.m.Y.'))

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Automatski izvještaj · Akademska knjiga</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Dnevni izvještaj je spreman</h1>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">U privitku se nalazi Excel izvještaj za datum <strong style="color:#2d2224;">{{ now()->subDay()->format('d.m.Y.') }}</strong>. Datoteku možete preuzeti i putem gumba ispod.</p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0;">
        <tr><td bgcolor="#2d2224" style="border-radius:6px;"><a href="{{ url('akmk_report.xlsx') }}" target="_blank" class="mail-button" style="display:inline-block;padding:14px 25px;border:1px solid #2d2224;border-radius:6px;background-color:#2d2224;color:#ffffff;font-size:14px;font-weight:bold;line-height:20px;text-decoration:none;">Preuzmi Excel izvještaj &nbsp;&rarr;</a></td></tr>
    </table>

    <p style="margin:27px 0 0;padding-top:22px;border-top:1px solid #ece5da;color:#453b35;font-size:14px;line-height:22px;">Lijep pozdrav,<br><strong style="color:#2d2224;">Antikvarijat Vremeplov</strong></p>
@endsection
