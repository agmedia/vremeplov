@extends('emails.layouts.base')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset">{!! __('Pozdrav ' . $order->payment_fname. ', hvala vam na vašoj narudžbi.') !!}</td>
        </tr>
        <tr>
            <td class="ag-mail-tableset"> <h3 style="line-height:0px">Narudžba broj: {{ $order->id }} </h3></td>
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
                <b> {{ __('Način plaćanja') }}:</b>
                @if ($order->payment_code == 'bank')
                    <b>{{ __('Općom uplatnicom / Virmanom / Internet bankarstvom') }}</b>

                    <p style="font-size:12px">Uredno smo zaprimili Vašu narudžbu broj {{ $order->id }} i zahvaljujemo Vam.</p><p style="font-size:12px">Molimo vas da izvršite uplatu po sljedećim uputama za plaćanje.</p>

                    <p style="font-size:12px"> Rok za uplatu je maksimalno 48h tijekom koga robu koju ste naručili držimo rezerviranu za vas.</p>

                    <p style="font-size:12px"> Ukoliko u tom roku ne zaprimimo uplatu, nažalost moramo poništiti ovu narudžbu.</p>

                    <p style="font-size:12px">MOLIMO IZVRŠITE UPLATU U IZNOSU OD € {{number_format($order->total, 2)}}</p>


                    <p style="font-size:12px"> IBAN RAČUN: HR98 2402 0061 1011 2296 1<br>
                        MODEL: 00 POZIV NA BROJ: {{ $order->id }}-{{date('ym')}}</p>


                    <p style="font-size:12px">ILI JEDNOSTAVNO POSKENIRAJTE 2D BARKOD</p>

                    <p><img src="{{ asset('media/img/qr/'.$order->id) }}.jpg" style="max-width:80%; border:1px solid #ccc; height:auto"></p>

                @elseif ($order->payment_code == 'cod')
                    <b>{{ __('Gotovinom prilikom pouzeća') }}</b>
                    <p style="font-size:12px">Uredno smo zaprimili Vašu narudžbu broj {{ $order->id }} i zahvaljujemo Vam.</p>
                @elseif ($order->payment_code == 'corvus')
                    <b>{{ __('CorvusPay') }}</b>
                    <p style="font-size:12px">Uredno smo zaprimili Vašu narudžbu broj {{ $order->id }} i zahvaljujemo Vam.</p>
                @else
                    <b>{{ __('Plaćanje prilikom preuzimanja') }}</b>
                    <p style="font-size:12px">Uredno smo zaprimili Vašu narudžbu broj {{ $order->id }} i zahvaljujemo Vam.</p>
                @endif
                <br><br>

                Lijep pozdrav,<br>Plava Krava
            </td>
        </tr>

    </table>
@endsection
