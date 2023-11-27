
@extends('front.layouts.app')

@section('content')

    @if (isset($data['google_tag_manager']))
        @section('google_data_layer')
            <script>
                window.dataLayer = window.dataLayer || [];
                dataLayer.push(<?php echo json_encode($data['google_tag_manager']); ?>);
            </script>
        @endsection
    @endif

    <div class="container pb-5 mb-sm-4">
        <div class="pt-5">
            <div class="card py-3 mt-sm-3">
                <div class="card-body text-center">
                    <h2 class="h4 pb-3">Vaša narudžba je uspješno dovršena!</h2>

                    @if($data['order']['payment_code'] == 'bank')
                        <p>Uredno smo zaprimili Vašu narudžbu broj {{ $data['order']['id'] }} i zahvaljujemo Vam.</p><p>Molimo vas da izvršite uplatu po sljedećim uputama za plaćanje.</p>
                        <p> Rok za uplatu je maksimalno 48h tijekom koga robu koju ste naručili držimo rezerviranu za vas.</p>
                        <p> Ukoliko u tom roku ne zaprimimo uplatu, nažalost moramo poništiti ovu narudžbu.</p>
                        <p>MOLIMO IZVRŠITE UPLATU U IZNOSU OD  {{number_format($data['order']['total'], 2)}} €<br>
                           IBAN RAČUN: HR4524020061100571694<br>
                           MODEL: 00 POZIV NA BROJ: {{ $data['order']['id'] }}-{{date('ym')}}</p>
                        <p>ILI JEDNOSTAVNO POSKENIRAJTE 2D BARKOD</p>
                        <p><img src="{{ asset('media/img/qr/'.$data['order']['id']) }}.jpg"></p>
                    @else
                        <p class="fs-sm mb-2">Vaša je narudžba poslana i bit će obrađena u najkraćem mogućem roku.</p>
                        <p class="fs-sm">Uskoro ćete primiti e-poštu s potvrdom narudžbe.</p>
                    @endif

                    <a class="btn btn-secondary mt-3 me-3" href="{{ route('index') }}">Nastavite pregled stranice</a>
                </div>
            </div>
        </div>
    </div>



@endsection
