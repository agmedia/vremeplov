@extends('front.layouts.app')

@section('content')

    <!-- Page Title (Light)-->
    <div class="bg-light pt-4 pb-3"  style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
        <div class="container d-lg-flex justify-content-between py-2 py-lg-3">
            <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark flex-lg-nowrap justify-content-center justify-content-lg-start">
                        <li class="breadcrumb-item"><a class="text-nowrap" href="/"><i class="ci-home"></i>Naslovnica</a></li>
                         <li class="breadcrumb-item text-nowrap active" aria-current="page">Kontakt</li>
                    </ol>
                </nav>
            </div>
            <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                <h1 class="h3 text-dark mb-0"> Kontaktirajte nas</h1>
            </div>

        </div>

    </div>


    <section class="spikesg" ></section>
    <!-- Contact detail cards-->
    <section class="container pt-grid-gutter">
        <div class="row">

            @include('front.layouts.partials.session')

            <div class="col-12 col-sm-6 mb-5">

                        <h3 class=" mb-2">Impressum</h3>
                        <p>
                        <br>
                           <strong> Vremeplov razglednica d.o.o.</strong></p>

                <p> Sjedište: Radoslava Lopašića br.11, 10000 Zagreb<br><br>

                            OIB: 34413434459<br>
                            MB: 2623196<br>

                    <br>
                            IBAN: HR4524020061100571694<br>
                            Banka: ERSTE & STEIERMÄRKISCHE BANK d.d. Rijeka<br>
                            Swift: ESBCHR22


                </p>
                <p>
                    Ljetno radno vrijeme<br>
                    (01.07 - 01.09.2024)<br>
                    Ponedjeljak - petak<br>
                    09 -14h i 16 - 19h<br>

                    Subota<br>
                    10 - 13h
                </p>

            </div>

            <div class="col-12 col-sm-6 mb-5 ">
                <h2 class="h4 mb-4">Pošaljite upit</h2>
                <form action="{{ route('poruka') }}" method="POST" class="mb-3">
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-12">
                            <label class="form-label" for="cf-name">Vaše ime:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <input class="form-control" type="text" name="name" id="cf-name" placeholder="">
                            @error('name')<div class="text-danger font-size-sm">Molimo upišite vaše ime!</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="cf-email">Email adresa:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <input class="form-control" type="email" id="cf-email" placeholder="" name="email">
                            @error('email')<div class="invalid-feedback">Molimo upišite ispravno email adresu!</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="cf-phone">Broj telefona:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <input class="form-control" type="text" id="cf-phone" placeholder="" name="phone">
                            @error('phone')<div class="invalid-feedback">Molimo upišite broj telefona!</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="cf-message">Upit:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <textarea class="form-control" id="cf-message" rows="6" placeholder="" name="message"></textarea>
                            @error('message')<div class="invalid-feedback">Molimo upišite poruku!</div>@enderror
                            <button class="btn btn-primary mt-4" type="submit">Pošaljite upit</button>
                        </div>
                    </div>
                    <input type="hidden" name="recaptcha" id="recaptcha">
                </form>
            </div>

        </div>
    </section>




    <!-- Split section: Map + Contact form-->
    <div class="container-fluid px-0" id="map">
        <div class="row g-0">
            <div class="col-lg-12 iframe-full-height-wrap">

                <iframe class="iframe-full-height" src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d11124.005570722158!2d15.988982!3d45.8112305!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4765d6549337de2d%3A0xeb2609abc24978d!2sAntikvarijat%20Vremeplov!5e0!3m2!1shr!2shr!4v1701073620720!5m2!1shr!2shr" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>


            </div>

        </div>
    </div>

@endsection

@push('js_after')
    @include('front.layouts.partials.recaptcha-js')
@endpush
