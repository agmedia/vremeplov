<header class="bg-light shadow-sm navbar-sticky" style="background-color: #f2f5fc;">
    <div class="navbar navbar-expand-lg navbar-light">
        <div class="container"><a class="navbar-brand d-none d-sm-block flex-shrink-0 me-4 order-lg-1 p-0" href="{{ route('index') }}"><img src="{{ asset('media/img/zuzi-logo.png') }}" width="110"  alt="Web shop | ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop"></a><a class="navbar-brand d-sm-none me-0 order-lg-1 p-0" href="{{ route('index') }}"><img src="{{ asset('media/img/zuzi-logo.png') }}" width="70" alt="Žuži Shop"></a>

            <!-- Toolbar -->
            <div class="navbar-toolbar d-flex align-items-center order-lg-3">
                @if (isset($group) && $group && ! isset($prod))
                    <button class="navbar-toggler" type="button" data-bs-target="#shop-sidebar" data-bs-toggle="collapse" aria-expanded="false"><i class="ci-filter-alt"></i></button>
                @endif
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse"><span class="navbar-toggler-icon"></span></button>
                <a class="navbar-tool d-none d-lg-flex" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#searchBox" role="button" aria-expanded="false" aria-controls="searchBox"><span class="navbar-tool-tooltip">Pretraži</span>
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-search"></i></div>
                </a>
                <a class="navbar-tool ms-12" href="{{ route('login') }}" ><span class="navbar-tool-tooltip">Korisnički račun</span>
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-user-circle"></i></div>
                </a>
                <div style="width:46px">
                    <cart-nav-icon carturl="{{ route('kosarica') }}" checkouturl="{{ route('naplata') }}"></cart-nav-icon>
                </div>
            </div>

            <div class="collapse navbar-collapse me-auto mx-auto order-lg-2 justify-content-center" id="navbarCollapse">
                <form action="{{ route('pretrazi') }}" id="search-form-mobile" method="get">
                    <div class="input-group d-lg-none my-3"><i class="ci-search position-absolute top-50 start-0 translate-middle-y text-muted fs-base ms-3"></i>
                        <input class="form-control rounded-start" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu ili autoru">
                        <button type="submit" class="btn btn-primary btn-lg fs-base"><i class="ci-search"></i></button>
                    </div>
                </form>

                <!-- Navbar -->
                <ul class="navbar-nav justify-content-centerpe-lg-2 me-lg-2">
                    <li class="nav-item "><a class="nav-link" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}"><span>Web shop</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.author') }}"><span>Autori</span></a>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true) . '/rijetke-knjige']) }}"><span>Rijetke knjige</span></a>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.actions') }}"><span>Akcije</span></a>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true) . '/outlet']) }}"><span>Outlet</span></a>

                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.blog') }}"><span>Blog</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('kontakt') }}"><span>Kontakt</span></a></li>
                </ul>

            </div>
        </div>
    </div>
    <!-- Search collapse-->
    <div class="search-box collapse" id="searchBox">
        <div class="card bg-white pt-3 pb-3 border-0 rounded-0">
            <div class="container">
                <form action="{{ route('pretrazi') }}" id="search-form" method="get">
                    <div class="input-group">
                        <input class="form-control rounded-start" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu ili autoru">
                        <button type="submit" class="btn btn-primary btn-lg fs-base"><i class="ci-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <section class="spikesw"></section>
</header>


