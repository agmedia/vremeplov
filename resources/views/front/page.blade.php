@extends('front.layouts.app')
@if (request()->routeIs(['index']))
    @section ( 'title', 'Antikvarijat Vremeplov | Prodaja knjiga | Otkup knjiga | Webshop' )
@section ( 'description', 'Dobro došli na stranice antikvarijata Vremeplov. Specijalizirani smo za stare razglednice, pisma, knjige, plakate,časopise te vršimo otkup i prodaju navedenih.' )


@push('meta_tags')

    <link rel="canonical" href="{{ env('APP_URL')}}" />
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="product" />
    <meta property="og:title" content="Antikvarijat Vremeplov | Prodaja knjiga | Otkup knjiga | Webshop" />
    <meta property="og:description" content="Dobro došli na stranice antikvarijata Vremeplov. Specijalizirani smo za stare razglednice, pisma, knjige, plakate,časopise te vršimo otkup i prodaju navedenih." />
    <meta property="og:url" content="{{ env('APP_URL')}}"  />
    <meta property="og:site_name" content="Antikvarijat Vremeplov | Prodaja knjiga | Otkup knjiga | Webshop" />
    <meta property="og:image" content="{{ asset('media/img/cover-vremeplov.jpg') }}" />
    <meta property="og:image:secure_url" content="{{ asset('media/img/cover-vremeplov.jpg') }}" />
    <meta property="og:image:width" content="1920" />
    <meta property="og:image:height" content="720" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="Antikvarijat Vremeplov | Prodaja knjiga | Otkup knjiga | Webshop" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Antikvarijat Vremeplov | Prodaja knjiga | Otkup knjiga | Webshop" />
    <meta name="twitter:description" content="Dobro došli na stranice antikvarijata Vremeplov. Specijalizirani smo za stare razglednice, pisma, knjige, plakate,časopise te vršimo otkup i prodaju navedenih." />
    <meta name="twitter:image" content="{{ asset('media/img/cover-vremeplov.jpg') }}" />

@endpush

@else
    @section ( 'title', $page->title. ' - Antikvarijat Vremeplov' )
@section ( 'description', $page->meta_description )

@endif

@section('content')

    @if (request()->routeIs(['index']))

        <section class="container">
            <div class="row mt-2">
                <div class="col-sm-12 ">
                    <div class="alert alert-danger" role="alert" style="font-size: 0.875rem;">
                        Zatvoreni od 25.6.do 3.7.2025. zbog selidbe u novi prostor (Zvonimirova 24).
                        <strong>Online narudžbe su moguće</strong>, preuzimanje po dogovoru: 097 7820 935 (Tamara).
                    </div>
                </div>
            </div>
        </section>


        {!! $page->description !!}





    @else

        <div class="bg-light pt-4 pb-3"  style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
            <div class="container d-lg-flex justify-content-between py-2 py-lg-3">
                <div class="order-lg-2 mb-1 mb-lg-0 pt-lg-2">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-dark flex-lg-nowrap justify-content-center justify-content-lg-start">
                            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                            <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $page->title }}</li>
                        </ol>
                    </nav>
                </div>
                <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                    <h1 class="h2 text-dark">{{ $page->title }}</h1>
                </div>
            </div>
        </div>
        <section class="spikesg" ></section>
        <div class="container">

            <div class="mt-5 mb-5">
                {!! $page->description !!}
            </div>
        </div>

    @endif

@endsection
