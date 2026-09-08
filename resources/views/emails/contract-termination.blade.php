@extends('emails.layouts.base')

@section('email_title', 'Izjava o jednostranom raskidu ugovora')
@section('preheader', 'Nova izjava poslana putem obrasca na web trgovini.')

@section('content')
    <div style="margin:0 0 10px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;">Administracija · raskid ugovora</div>
    <h1 style="margin:0 0 18px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:29px;font-weight:normal;">Nova izjava o raskidu</h1>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border:1px solid #e1d7c8;background:#fbf8f2;">
        @if(! empty($data['reference']))<tr><td><strong>Referenca</strong></td><td>{{ $data['reference'] }}</td></tr>@endif
        <tr><td><strong>Potrošač</strong></td><td>{{ $data['full_name'] }}</td></tr>
        <tr><td><strong>E-mail</strong></td><td><a href="mailto:{{ $data['email'] }}">{{ $data['email'] }}</a></td></tr>
        <tr><td><strong>Telefon</strong></td><td>{{ $data['phone'] ?: '—' }}</td></tr>
        <tr><td><strong>Adresa</strong></td><td>{{ $data['address'] }}, {{ $data['postal_code'] }} {{ $data['city'] }}, {{ $data['country'] }}</td></tr>
        <tr><td><strong>Broj narudžbe/računa</strong></td><td>{{ $data['order_number'] }}</td></tr>
        <tr><td><strong>Izjava poslana</strong></td><td>{{ $data['submitted_at']->format('d.m.Y. H:i') }}</td></tr>
        <tr><td><strong>Datum narudžbe</strong></td><td>{{ $data['order_date'] ?: '—' }}</td></tr>
        <tr><td><strong>Datum primitka</strong></td><td>{{ $data['received_date'] ?: '—' }}</td></tr>
        <tr><td><strong>IBAN</strong></td><td>{{ $data['iban'] ?: '—' }}</td></tr>
    </table>
    <h2 style="margin:24px 0 8px;color:#2d2224;font-size:17px;">Roba na koju se raskid odnosi</h2>
    <div style="padding:16px;border-left:4px solid #c7a361;background:#fff;white-space:pre-wrap;">{{ $data['items'] }}</div>
    <p style="margin:22px 0 0;color:#665b52;">Potrošač je potvrdio nedvosmislenu izjavu da jednostrano raskida navedeni ugovor.</p>
@endsection
