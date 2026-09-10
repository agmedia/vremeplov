<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <title>@hasSection('title')@yield('title')@else Antikvarijat Vremeplov | Prodaja i otkup knjiga @endif</title>
    <!-- SEO Meta Tags-->
    <meta name="description" content="@hasSection('description')@yield('description')@else Antikvarijat Vremeplov u Zagrebu: antikvarne i rabljene knjige, stare razglednice, plakati, časopisi i kolekcionarski predmeti. @endif">
    <meta name="author" content="AG media">
    <link rel="canonical" href="@hasSection('canonical')@yield('canonical')@else{{ url()->current() }}@endif">
    @if (request()->routeIs('kosarica', 'naplata', 'pregled', 'checkout*') || request()->is('moj-racun*'))
        <meta name="robots" content="noindex,nofollow">
    @endif
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => ['BookStore', 'Organization'],
                '@id' => url('/#organization'),
                'name' => 'Antikvarijat Vremeplov',
                'legalName' => 'Vremeplov razglednica d.o.o.',
                'url' => url('/'),
                'logo' => config('settings.images_domain') . 'media/img/vremeplov-logo.png',
                'telephone' => '+385 91 762 7441',
                'email' => config('mail.from.address'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Zvonimirova 24',
                    'postalCode' => '10000',
                    'addressLocality' => 'Zagreb',
                    'addressCountry' => 'HR',
                ],
                'openingHoursSpecification' => [
                    [
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                        'opens' => '09:00',
                        'closes' => '14:00',
                    ],
                    [
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                        'opens' => '16:00',
                        'closes' => '19:00',
                    ],
                    [
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => 'Saturday',
                        'opens' => '10:00',
                        'closes' => '13:00',
                    ],
                ],
                'sameAs' => [
                    'https://www.facebook.com/antikavrijatvremeplov',
                    'https://www.instagram.com/antikvarijatvremeplov',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/#website'),
                'url' => url('/'),
                'name' => 'Antikvarijat Vremeplov',
                'inLanguage' => 'hr-HR',
                'publisher' => ['@id' => url('/#organization')],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @stack('meta_tags')
    <!-- Viewport-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=2.0" />
    <!-- Favicon and Touch Icons-->
    <link rel="icon" href="/favicon.ico?v=20260909" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260909">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=20260909">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=20260909">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=20260909">
    <link rel="manifest" href="/site.webmanifest?v=20260909">
    <link rel="mask-icon" href="/safari-pinned-tab.svg?v=20260909" color="#2d2224">
    <meta name="application-name" content="Vremeplov">
    <meta name="apple-mobile-web-app-title" content="Vremeplov">
    <meta name="msapplication-config" content="/browserconfig.xml?v=20260909">
    <meta name="msapplication-TileColor" content="#2d2224">
    <meta name="theme-color" content="#2d2224">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @livewireStyles

    <!-- Vendor Styles including: Font Icons, Plugins, etc.-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" href="/vendor/fontawesome-pro/css/fontawesome.min.css?v=7.3.1">
    <link rel="stylesheet" href="/vendor/fontawesome-pro/css/solid.min.css?v=7.3.1">
    <link rel="stylesheet" href="/vendor/fontawesome-pro/css/regular.min.css?v=7.3.1">
    <link rel="stylesheet" href="/vendor/fontawesome-pro/css/duotone.min.css?v=7.3.1">
    <link rel="stylesheet" href="/vendor/fontawesome-pro/css/brands.min.css?v=7.3.1">

    <!-- Main Theme Styles + Bootstrap-->
    <link rel="stylesheet" media="screen" href="/css/theme.css?v=1.91">
    <link rel="stylesheet" media="screen" href="/css/front-vremeplov.css?v=1.0.44">
    @include('front.layouts.partials.analytics')

    @stack('css_after')

    @if (config('app.env') == 'production')

    @endif

    <style>
        [v-cloak] { display:none !important; }
    </style>

</head>
<!-- Body-->
<body class="bg-secondary" id="top">

<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light" href="#main-content">Preskoči na glavni sadržaj</a>

<!--<div role="alert" class="alert alert-primary mb-0 text-center">
   <small> Poštovani, zbog povećanog broja narudžbi povodom Interlibera, molimo vas za razumijevanje i strpljenje tijekom isporuke. Zahvaljujemo na vašem strpljenju i povjerenju.</small>
</div>-->
<!-- Light topbar -->
<div class="topbar topbar-dark site-topbar bg-light position-relative" style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
    <div class="container">

        <div class="topbar-text site-topbar__contacts text-nowrap d-inline-flex align-items-center gap-3">
            <a class="topbar-link d-inline-flex align-items-center" href="tel:00385917627441">
                <i class="fa-regular fa-phone me-1 text-primary" aria-hidden="true"></i>
                <span>091 762 7441</span>
            </a>
            <a class="topbar-link d-inline-flex align-items-center" href="mailto:{{ config('mail.admin') }}">
                <i class="fa-regular fa-envelope me-2 text-primary" aria-hidden="true"></i>{{ config('mail.admin') }}
            </a>
        </div>
        <div class="topbar-text  d-none  d-md-inline-block">Besplatna dostava U RH za narudžbe iznad 70 €</div>
        <div class="site-topbar__social ms-3 text-nowrap d-none d-md-flex">
            <a class="topbar-link me-2 d-inline-block" aria-label="Pratite nas na Facebooku" href="https://www.facebook.com/antikavrijatvremeplov">
                <i class="fa-brands fa-facebook-f"></i>
            </a>

            <a class="topbar-link me-2 d-inline-block" aria-label="Pratite nas na Instagramu" href="https://www.instagram.com/antikvarijatvremeplov">
                <i class="fa-brands fa-instagram"></i>
            </a>

        </div>
    </div>

</div>




<div id="agapp">
    @include('front.layouts.partials.header')

    <main id="main-content">
        @yield('content')
    </main>

    @unless (request()->routeIs('kosarica', 'naplata', 'pregled', 'checkout*'))
        @include('front.layouts.partials.newsletter')
    @endunless

    @include('front.layouts.partials.footer')

    @include('front.layouts.partials.handheld')
</div>

<!-- Back To Top Button-->
<a class="btn-scroll-top" href="#top" aria-label="Povratak na vrh stranice" data-scroll><span class="btn-scroll-top-tooltip text-muted fs-sm me-2"></span><i class="btn-scroll-top-icon fa-regular fa-arrow-up"></i></a>
<!-- Vendor Styles including: Font Icons, Plugins, etc.-->
<link rel="stylesheet" media="screen" href="/css/tiny-slider.css?v=1.2"/>
<!-- Vendor scrits: js libraries and plugins-->
<script src="/js/jquery/jquery-2.1.1.min.js?v=1.2"></script>
<script src="/js/bootstrap.bundle.min.js?v=1.2"></script>
<script src="/js/tiny-slider.js?v=1.2"></script>
<script src="/js/smooth-scroll.polyfills.min.js?v=1.2"></script>
<script src="/js/imagesloaded/imagesloaded.pkgd.min.js"></script>
<script src="/js/shufflejs/dist/shuffle.min.js"></script>
<!-- Main theme script-->



<script src="/js/cart.js?v=2.3.8"></script>

<script src="/js/theme.min.js?v=1.2"></script>

<script>
    $(() => {
        $('#search-input').on('keyup', (e) => {
            if (e.keyCode == 13) {
                e.preventDefault();
                $('search-form').submit();
            }
        })
    });
</script>


@stack('js_after')


@livewireScripts

@include('front.layouts.partials.cookie-consent')


<script>

    document.addEventListener('livewire:load', () => console.log('✅ Livewire loaded'));
    // Ako Livewire nije inicijaliziran nakon prvog painta, napravi jedan reload.
    window.addEventListener('load', function () {
        if (!window.livewire && !window.Livewire) {
            // Izbjegni beskonačnu petlju
            if (!sessionStorage.getItem('lw-reloaded')) {
                sessionStorage.setItem('lw-reloaded', '1');
                location.reload();
            }
        } else {
            sessionStorage.removeItem('lw-reloaded');
        }
    });
</script>
</body>
</html>
