<!doctype html>
<html lang="{{ config('app.locale') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

        <title>Plava Krava</title>

        <meta name="description" content="Plava Krava">
        <meta name="author" content="pixelcave">
        <meta name="robots" content="noindex, nofollow">

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Favicon and Touch Icons-->
        <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'favicon-16x16.png' }}">

        <link rel="mask-icon" href="{{ config('settings.images_domain') . 'safari-pinned-tab.svg' }}" color="#18326d">
        <meta name="msapplication-TileColor" content="#ffffff">
        <meta name="theme-color" content="#ffffff">

        <!-- Fonts and Styles -->
        @stack('css_before')
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
        <link rel="stylesheet" id="css-main" href="{{ asset('css/dashmix.css') }}">

        <!-- You can include a specific file from public/css/themes/ folder to alter the default color theme of the template. eg: -->
        <!-- <link rel="stylesheet" id="css-theme" href="{{ asset('css/themes/xwork.css') }}"> -->
        @stack('css_after')

        <!-- Scripts -->
        <script>window.Laravel = {!! json_encode(['csrfToken' => csrf_token(),]) !!};</script>

        @livewireStyles
    </head>
    <body>

        <div id="page-container" class="sidebar-o enable-page-overlay sidebar-dark side-scroll page-header-fixed main-content-narrow">

            @include('back.layouts.partials.aside')

            @include('back.layouts.partials.sidebar')

            @include('back.layouts.partials.topbar')

            <!-- Main Container -->
            <main id="main-container">
                @yield('content')
            </main>
            <!-- END Main Container -->

            <!-- Footer -->
            <footer id="page-footer" class="bg-body-light">
                <div class="content py-0">
                    <div class="row font-size-sm">
                        <div class="col-sm-6 order-sm-2 mb-1 mb-sm-0 text-center text-sm-right">
                            Crafted with <i class="fa fa-heart text-danger"></i> by <a class="font-w600" href="https://www.agmedia.hr" target="_blank">AG media</a>
                        </div>
                        <div class="col-sm-6 order-sm-1 text-center text-sm-left">
                            <a class="font-w600" href="https://www.plavakrava.hr" target="_blank">Plava Krava</a> &copy; <span data-toggle="year-copy"></span>
                        </div>
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
