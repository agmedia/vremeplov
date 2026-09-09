
@extends('front.layouts.app')

@section('content')

    @if (isset($data['google_tag_manager']))
        @section('google_data_layer')
            <script>
                window.VremeplovAnalytics.track('purchase', {
                    ecommerce: @json($data['google_tag_manager']['ecommerce'])
                });
            </script>
        @endsection
    @endif

    <div class="container pb-5 mb-sm-4">
        <div class="pt-5">
            <div class="steps steps-dark checkout-steps-six pt-2 pb-3 mb-4" aria-label="Napredak kupnje">
                <a class="step-item active" href="{{ route('kosarica') }}">
                    <div class="step-progress"><span class="step-count">1</span></div>
                    <div class="step-label"><i class="fa-regular fa-cart-shopping"></i>Košarica</div>
                </a>
                <span class="step-item active">
                    <span class="step-progress"><span class="step-count">2</span></span>
                    <span class="step-label"><i class="fa-regular fa-circle-user"></i>Podaci</span>
                </span>
                <span class="step-item active">
                    <span class="step-progress"><span class="step-count">3</span></span>
                    <span class="step-label"><i class="fa-regular fa-box"></i>Dostava</span>
                </span>
                <span class="step-item active">
                    <span class="step-progress"><span class="step-count">4</span></span>
                    <span class="step-label"><i class="fa-regular fa-credit-card"></i>Plaćanje</span>
                </span>
                <span class="step-item active">
                    <span class="step-progress"><span class="step-count">5</span></span>
                    <span class="step-label"><i class="fa-regular fa-eye"></i>Pregledaj</span>
                </span>
                <span class="step-item current active" aria-current="step">
                    <span class="step-progress"><span class="step-count">6</span></span>
                    <span class="step-label"><i class="fa-regular fa-circle-check"></i>Uspješno</span>
                </span>
            </div>
            <div class="card py-3 mt-sm-3">
                <div class="card-body text-center">
                    <h2 class="h4 pb-3">Vaša narudžba je uspješno dovršena!</h2>

                    @if($data['order']['payment_code'] == 'bank')
                        <p>Uredno smo zaprimili Vašu narudžbu broj {{ $data['order']['id'] }} i zahvaljujemo Vam.</p><p>Molimo vas da izvršite uplatu po sljedećim uputama za plaćanje.</p>
                        <p> Rok za uplatu je maksimalno 48h tijekom koga robu koju ste naručili držimo rezerviranu za vas.</p>
                        <p> Ukoliko u tom roku ne zaprimimo uplatu, nažalost moramo poništiti ovu narudžbu.</p>
                        <p>MOLIMO IZVRŠITE UPLATU U IZNOSU OD  {{number_format($data['order']['total'], 2)}} €<br>
                           IBAN RAČUN: {{ config('services.bank_transfer.iban') }}<br>
                           MODEL: 00 POZIV NA BROJ: {{ $data['order']['id'] }}-{{date('ym')}}</p>
                        @if (Storage::disk('qr')->exists($data['order']['id'] . '.jpg'))
                            <p>ILI JEDNOSTAVNO POSKENIRAJTE 2D BARKOD</p>
                            <p><img src="{{ asset('media/img/qr/'.$data['order']['id']) }}.jpg" alt="2D barkod za plaćanje"></p>
                        @endif
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
