@extends('emails.layouts.base')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td class="ag-mail-tableset"><h3>Narudžba je otkazana</h3></td></tr>

        <tr>
            <td class="ag-mail-tableset">
                Broj narudžbe: <strong>{{ $order->id }}</strong><br>
                Datum: <strong>{{ now()->format('d.m.Y') }}</strong><br>
                Status: <strong>OTKAZANO</strong> (Narudžba je otkazana / nije uspjelo plaćanje)
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset">
                @include('emails.layouts.partials.order-details', ['order' => $order])
            </td>
        </tr>
        <tr>
            <td class="ag-mail-tableset">
                @include('emails.layouts.partials.order-price-table', ['order' => $order])
            </td>
        </tr>
        <tr>
            <td class="ag-mail-tableset">
                {{ __('Način plaćanja') }}:
                @if ($order->payment_code == 'bank')
                    <b>{{ __('Općom uplatnicom / Virmanom / Internet bankarstvom') }}</b>
                @elseif ($order->payment_code == 'cod')
                    <b>{{ __('Gotovinom prilikom pouzeća') }}</b>
                @elseif ($order->payment_code == 'corvus')
                    <b>{{ __('CorvusPay') }}</b>
                @else
                    <b>{{ __('Plaćanje prilikom preuzimanja') }}</b>
                @endif
                <br><br>Lijep pozdrav,<br>Plava Krava
            </td>
        </tr>

    </table>
@endsection
