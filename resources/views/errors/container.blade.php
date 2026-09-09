<!DOCTYPE html>
<html dir="ltr" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="author" content="Skladišna Logistika" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>  @yield ('title' ) - {{ config('app.name') }}</title>
    <!-- Favicon and Touch Icons-->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'media/img/favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'media/img/favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'media/img/favicon-16x16.png' }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'apple-touch-icon.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'favicon-16x16.png' }}">
    <link rel="mask-icon" href="{{ config('settings.images_domain') . 'safari-pinned-tab.svg' }}" color="#314837">
    <meta name="msapplication-TileColor" content="#314837">
    <meta name="theme-color" content="#ffffff">

    <!-- Vendor Styles including: Font Icons, Plugins, etc.-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <!-- Main Theme Styles + Bootstrap-->
    <link rel="stylesheet" media="screen" href="{{ config('settings.images_domain') . 'css/theme.min.css?v=1.6' }}">
    <!-- Fire the plugin -->
    <script>
        document.addEventListener(
            "DOMContentLoaded", () => {
                new Mmenu( "#menu", {
                    "extensions": [
                        "pagedim-black",
                        "border-full"
                    ],
                    "navbars": [
                        {
                            "position": "bottom",
                            "content": [
                                "<a class='fa fa-envelope' href='#/'></a>",
                                "<a class='fa fa-twitter' href='#/'></a>",
                                "<a class='fa fa-facebook' href='#/'></a>"
                            ]
                        }
                    ]
                });
            }
        );
    </script>
    @stack('css_before')
    @stack('css')
</head>
<body class="stretched side-panel-left" data-loader="9" data-loader-color="#C11226" data-animation-in="fadeIn" data-speed-in="1500" data-animation-out="fadeOut" data-speed-out="800"
>
<!-- Document Wrapper -->
<div id="wrapper" class="clearfix">
    <!-- Topbar -->
@include('front.layouts.partials.header')
<!-- Slider -->
{{-- @include('front.layouts.partials.slider') --}}
<!-- Content -->
@yield('content')
<!-- Footer -->
    @include('front.layouts.partials.footer')
</div><!-- #wrapper end -->

<link rel="stylesheet" media="screen" href="{{ config('settings.images_domain') . 'css/tiny-slider.css?v=1.2' }}"/>
<!-- Vendor scrits: js libraries and plugins-->
<script src="{{ asset('js/jquery/jquery-2.1.1.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/bootstrap.bundle.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/tiny-slider.js?v=1.2') }}"></script>
<script src="{{ asset('js/smooth-scroll.polyfills.min.js?v=1.2') }}"></script>
<!-- Main theme script-->

<script src="{{ asset('js/cart.js?v=2.3.5') }}"></script>

<script src="{{ asset('js/theme.min.js') }}"></script>
@stack('js')
<script>
    jQuery(function() {
        jQuery( "#side-navigation" ).tabs({ show: { effect: "fade", duration: 400 } });
    });
</script>

</body>
</html>
