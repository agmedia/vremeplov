@extends('front.layouts.app')
@if (request()->routeIs(['index']))
    @php
        $homeTitle = 'Antikvarijat Vremeplov Zagreb | Rabljene i antikvarne knjige';
        $homeDescription = 'Kupite rabljene i antikvarne knjige, stare razglednice, plakate i časopise online ili u Zagrebu. Antikvarijat Vremeplov nudi prodaju i otkup.';
    @endphp
    @section('title', $homeTitle)
    @section('description', $homeDescription)
    @section('canonical', url('/'))


    @push('meta_tags')

    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $homeTitle }}" />
    <meta property="og:description" content="{{ $homeDescription }}" />
    <meta property="og:url" content="{{ url('/') }}"  />
    <meta property="og:site_name" content="Antikvarijat Vremeplov" />
    <meta property="og:image" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />
    <meta property="og:image:secure_url" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />
    <meta property="og:image:width" content="1920" />
    <meta property="og:image:height" content="720" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="Antikvarijat Vremeplov u Zagrebu" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $homeTitle }}" />
    <meta name="twitter:description" content="{{ $homeDescription }}" />
    <meta name="twitter:image" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />

    @endpush

@else
    @php
        $pageTitle = trim((string) ($page->meta_title ?: $page->title));
        $pageDescription = trim(strip_tags((string) $page->meta_description)) ?: 'Informacije za kupce Antikvarijata Vremeplov.';
        $pageCanonical = route('catalog.route.page', ['page' => $page]);
    @endphp
    @section('title', $pageTitle . ' - Antikvarijat Vremeplov')
    @section('description', $pageDescription)
    @section('canonical', $pageCanonical)
    @push('meta_tags')
        <meta property="og:locale" content="hr_HR" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $pageTitle }} - Antikvarijat Vremeplov" />
        <meta property="og:description" content="{{ $pageDescription }}" />
        <meta property="og:url" content="{{ $pageCanonical }}" />
        <meta property="og:site_name" content="Antikvarijat Vremeplov" />
        <meta property="og:image" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $pageTitle }} - Antikvarijat Vremeplov" />
        <meta name="twitter:description" content="{{ $pageDescription }}" />
        <meta name="twitter:image" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />
    @endpush

@endif

@section('content')

    @if (request()->routeIs(['index']))

        <header class="visually-hidden">
            <h1 class="h2 mb-2">Antikvarijat Vremeplov</h1>
            <p class="text-muted mb-0">Antikvarne i rabljene knjige, stare razglednice, plakati, časopisi i kolekcionarski predmeti.</p>
        </header>

        {!! $page->description !!}





    @else

        @include('front.layouts.partials.page-heading', ['title' => $page->title])
        <section class="spikesg" ></section>
        <div class="container">

            <div class="mt-5 mb-5">
                {!! $page->description !!}
            </div>
        </div>

    @endif

@endsection
