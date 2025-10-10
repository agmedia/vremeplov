<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <title> @yield('title') </title>
    <!-- SEO Meta Tags-->
    <meta name="description" content="@yield('description')">
    <meta name="author" content="AG media">
    @stack('meta_tags')
    <!-- Viewport-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=2.0" />
    <!-- Favicon and Touch Icons-->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'favicon-16x16.png' }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'apple-touch-icon.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'favicon-16x16.png' }}">
    <link rel="mask-icon" href="{{ config('settings.images_domain') . 'safari-pinned-tab.svg' }}" color="#2d2224">
    <meta name="msapplication-TileColor" content="#2d2224">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @livewireStyles

    <!-- Vendor Styles including: Font Icons, Plugins, etc.-->
    <link rel="preconnect" href="https://fonts.gstatic.com">

    <!-- Main Theme Styles + Bootstrap-->
    <link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/theme.css?v=1.91') }}">
    @if (config('app.env') == 'production')
        @yield('google_data_layer')
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-K4CB5GR');</script>
        <!-- End Google Tag Manager -->
    @endif

    @stack('css_after')

    @if (config('app.env') == 'production')

    @endif

    <style>
        [v-cloak] { display:none !important; }
    </style>

</head>
<!-- Body-->
<body class="bg-secondary">

@if (config('app.env') == 'production')
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K4CB5GR"
                      height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
@endif

<!--<div role="alert" class="alert alert-primary mb-0 text-center">
   <small> Poštovani, zbog povećanog broja narudžbi povodom Interlibera, molimo vas za razumijevanje i strpljenje tijekom isporuke. Zahvaljujemo na vašem strpljenju i povjerenju.</small>
</div>-->
<!-- Light topbar -->
<div class="topbar topbar-dark  bg-light position-relative" style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
    <div class="container">

        <div class="topbar-text text-nowrap  d-inline-block">
            <span class=" me-1">Podrška</span>
            <a class="topbar-link" href="tel:00385917627441">091 762 7441</a>
        </div>
        <div class="topbar-text  d-none  d-md-inline-block">Besplatna dostava U RH za narudžbe iznad 70 €</div>
        <div class="ms-3 text-nowrap ">
            <a class="topbar-link me-2 d-inline-block" aria-label="Follow us on facebook" href="https://www.facebook.com/antikavrijatvremeplov">
                <i class="ci-facebook"></i>
            </a>

            <a class="topbar-link me-2 d-inline-block" aria-label="Follow us on instagram" href="https://www.instagram.com/antiqueshopvremeplov/">
                <i class="ci-instagram"></i>
            </a>

            <a class="topbar-link me-0 d-inline-block" aria-label="Email us" href="mailto:info@antiqueshop.hr">
                <i class="ci-mail"></i>
            </a>

        </div>
    </div>

</div>




<div id="agapp">
    @include('front.layouts.partials.header')

    @yield('content')

    @include('front.layouts.partials.footer')

    @include('front.layouts.partials.handheld')
</div>

<!-- Back To Top Button-->
<a class="btn-scroll-top" href="#top" aria-label="Scroll to top" data-scroll><span class="btn-scroll-top-tooltip text-muted fs-sm me-2"></span><i class="btn-scroll-top-icon ci-arrow-up"></i></a>
<!-- Vendor Styles including: Font Icons, Plugins, etc.-->
<link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/tiny-slider.css?v=1.2') }}"/>
<!-- Vendor scrits: js libraries and plugins-->
<script src="{{ asset('js/jquery/jquery-2.1.1.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/bootstrap.bundle.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/tiny-slider.js?v=1.2') }}"></script>
<script src="{{ asset('js/smooth-scroll.polyfills.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
<script src="{{ asset('js/shufflejs/dist/shuffle.min.js') }}"></script>
<!-- Main theme script-->



<script src="{{ asset('js/cart.js?v=2.2.2') }}"></script>

<script src="{{ asset('js/theme.min.js') }}"></script>

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
