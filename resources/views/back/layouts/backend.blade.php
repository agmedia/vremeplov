<!doctype html>
<html lang="{{ config('app.locale') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

        <title>Antikvarijat Vremeplov</title>

        <meta name="description" content="Antikvarijat Vremeplov">
        <meta name="author" content="agmedia.hr">
        <meta name="robots" content="noindex, nofollow">

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

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

        <!-- Fonts and Styles -->
        @stack('css_before')
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/fontawesome.min.css?v=7.3.1') }}">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/solid.min.css?v=7.3.1') }}">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/regular.min.css?v=7.3.1') }}">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/duotone.min.css?v=7.3.1') }}">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome-pro/css/brands.min.css?v=7.3.1') }}">
        <link rel="stylesheet" id="css-main" href="{{ asset('css/dashmix.css?v=1.1') }}">
        <link rel="stylesheet" href="{{ asset('css/admin-vremeplov.css?v=20260909-3') }}">

        <!-- You can include a specific file from public/css/themes/ folder to alter the default color theme of the template. eg: -->
        <!-- <link rel="stylesheet" id="css-theme" href="{{ asset('css/themes/xwork.css') }}"> -->
        @stack('css_after')

        <!-- Scripts -->
        <script>window.Laravel = {!! json_encode(['csrfToken' => csrf_token(),]) !!};</script>

        @livewireStyles
    </head>
    <body class="admin-vremeplov">

        <div id="page-container" class="sidebar-o enable-page-overlay sidebar-dark side-scroll page-header-fixed">

            @include('back.layouts.partials.aside')

            @include('back.layouts.partials.sidebar')

            @include('back.layouts.partials.topbar')

            <!-- Main Container -->
            <main id="main-container">
                @yield('content')
            </main>
            <!-- END Main Container -->

            <!-- Footer -->
            <footer id="page-footer" class="admin-footer">
                <div class="content">
                    <div class="admin-footer-inner">
                        <a class="admin-footer-brand" href="{{ route('index') }}" target="_blank" rel="noopener">
                            <i class="fa fa-book-open" aria-hidden="true"></i>
                            <span>Antikvarijat Vremeplov</span>
                            <small>&copy; <span data-toggle="year-copy"></span></small>
                        </a>
                        <a class="admin-footer-credit" href="https://www.agmedia.hr" target="_blank" rel="noopener">
                            <span>Made with</span>
                            <i class="fa fa-heart" aria-hidden="true"></i>
                            <span>by</span>
                            <strong>AG media</strong>
                        </a>
                    </div>
                </div>
            </footer>
            <!-- END Footer -->
        </div>

        @stack('modals')

        @livewireScripts

        <!-- END Page Container -->
        <script src="{{ asset('js/dashmix.app.js') }}"></script>
        <script src="{{ asset('/js/laravel.app.js') }}"></script>

        <script>
            const confirmPopUp = Swal.mixin({
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-success m-5',
                    cancelButton: 'btn btn-danger m-5',
                    input: 'form-control'
                }
            })

            const successToast = Swal.mixin({
                position: 'top-end',
                icon: 'success',
                width: 270,
                showConfirmButton: false,
                timer: 1500
            })

            const errorToast = Swal.mixin({
                type: 'error',
                timer: 3000,
                position: 'top-end',
                showConfirmButton:false,
                toast: true,
            })

        </script>

        <script>
            function slugify(string) {
                const a = 'àáâäæãåāăąçćčđďèéêëēėęěğǵḧîïíīįìłḿñńǹňôöòóœøōõőṕŕřßśšşșťțûüùúūǘůűųẃẍÿýžźż·/_,:;'
                const b = 'aaaaaaaaaacccddeeeeeeeegghiiiiiilmnnnnoooooooooprrsssssttuuuuuuuuuwxyyzzz------'
                const p = new RegExp(a.split('').join('|'), 'g')

                return string.toString().toLowerCase()
                .replace(/\s+/g, '-') // Replace spaces with -
                .replace(p, c => b.charAt(a.indexOf(c))) // Replace special characters
                .replace(/&/g, '-and-') // Replace & with 'and'
                .replace(/[^\w\-]+/g, '') // Remove all non-word characters
                .replace(/\-\-+/g, '-') // Replace multiple - with single -
                .replace(/^-+/, '') // Trim - from start of text
                .replace(/-+$/, '') // Trim - from end of text
            }
        </script>

        <script>
            $(document).on('select2:open', function () {
                $('.select2-container--open .select2-search__field')
                    .attr('placeholder', 'Pretraži opcije…')
                    .attr('aria-label', 'Pretraži opcije');
            });

            $(function () {
                $('[data-admin-filter-memory]').each(function () {
                    const panel = this;
                    const panelKey = panel.dataset.adminFilterMemory;
                    const storageKey = 'vremeplov.admin.filters.' + panelKey;
                    const $panel = $(panel);
                    const $toggle = $('[data-admin-filter-toggle="' + panelKey + '"]');
                    let savedState = null;

                    try {
                        savedState = window.localStorage.getItem(storageKey);
                    } catch (error) {
                        savedState = null;
                    }

                    const open = savedState !== 'closed';
                    $panel.toggleClass('show', open);
                    $toggle.toggleClass('collapsed', ! open).attr('aria-expanded', open ? 'true' : 'false');

                    $panel.on('shown.bs.collapse', function () {
                        try {
                            window.localStorage.setItem(storageKey, 'open');
                        } catch (error) {
                            // Filtri i dalje rade ako preglednik blokira lokalnu pohranu.
                        }
                    });

                    $panel.on('hidden.bs.collapse', function () {
                        try {
                            window.localStorage.setItem(storageKey, 'closed');
                        } catch (error) {
                            // Filtri i dalje rade ako preglednik blokira lokalnu pohranu.
                        }
                    });
                });
            });

            /**
             *
             */
            function deleteItem(id, url) {
                Swal.fire({
                    title: 'Obriši..!',
                    text: "Jeste li sigurni da želite obrisati stavak?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Da, obriši!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        axios.post(url, {id: id})
                        .then(response => {
                            if (response.data.success) {
                                successToast.fire({
                                    timer: 2160
                                });

                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            } else {
                                return errorToast.fire(response.data.message);
                            }
                        });


                    }
                });
            }

            /**
             *
             */
            function confirmDeleteItem(id, url) {

            }
        </script>

        @stack('js_after')
    </body>
</html>
