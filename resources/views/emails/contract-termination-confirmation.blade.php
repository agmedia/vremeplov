@extends('emails.layouts.base')

@section('email_title', 'Potvrda primitka izjave o raskidu')
@section('preheader', 'Zaprimili smo vašu izjavu o jednostranom raskidu ugovora.')

@section('content')
    <div style="margin:0 0 10px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;">Antikvarijat Vremeplov</div>
    <h1 style="margin:0 0 18px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:29px;font-weight:normal;">Izjava je zaprimljena</h1>
    <p style="color:#665b52;line-height:1.65;">Poštovani/Poštovana {{ $data['full_name'] }}, potvrđujemo primitak vaše izjave o jednostranom raskidu za narudžbu ili račun <strong>{{ $data['order_number'] }}</strong>.</p>
    @if(! empty($data['reference']))<p style="color:#665b52;line-height:1.65;">Referentna oznaka: <strong>{{ $data['reference'] }}</strong></p>@endif
    <p style="color:#665b52;line-height:1.65;">Vrijeme primitka: <strong>{{ $data['submitted_at']->format('d.m.Y. H:i') }}</strong></p>
    <div style="margin:18px 0;padding:16px;border-left:4px solid #c7a361;background:#fbf8f2;white-space:pre-wrap;">{{ $data['items'] }}</div>
    <p style="color:#665b52;line-height:1.65;">Javit ćemo vam se na ovu e-mail adresu ako budu potrebni dodatni podaci. Sačuvajte ovu poruku kao dokaz primitka.</p>
@endsection
