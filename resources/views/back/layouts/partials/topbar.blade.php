<header id="page-header">
    <!-- Header Content -->
    <div class="content-header">
        <!-- Left Section -->
        <div>
            <!-- Toggle Sidebar -->
            <!-- Layout API, functionality initialized in Template._uiApiLayout()-->
            <button type="button" class="btn btn-dual" data-toggle="layout" data-action="sidebar_toggle">
                <i class="fa fa-fw fa-bars"></i>
            </button>
            <!-- END Toggle Sidebar -->

            <!-- Open Search Section -->
            <!-- Layout API, functionality initialized in Template._uiApiLayout() -->

            <!-- END Open Search Section -->
        </div>
        <!-- END Left Section -->

        <!-- Right Section -->
        <div>
            <a class="btn btn-dual mr-1" href="{{ route('wishlists', ['tab' => 'wishlists', 'stock' => 'ready']) }}"
               title="Wishlist prijave spremne za slanje" aria-label="Wishlist prijave spremne za slanje: {{ $wishlistReadyCount }}">
                <i class="fa fa-fw fa-heart text-warning"></i>
                <span class="font-w600">{{ $wishlistReadyCount }}</span>
            </a>
            <a class="btn btn-dual mr-1" href="{{ route('reviews', ['status' => 'pending']) }}"
               title="Komentari koji čekaju pregled" aria-label="Komentari koji čekaju pregled: {{ $pendingCommentCount }}">
                <i class="fa fa-fw fa-comments text-warning"></i>
                <span class="font-w600">{{ $pendingCommentCount }}</span>
            </a>

            <!-- User Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-dual" id="page-header-user-dropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fa fa-fw fa-user d-sm-none"></i>
                    <span class="d-none d-sm-inline-block">Admin</span>
                    <i class="fa fa-fw fa-angle-down ml-1 d-none d-sm-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right p-0" aria-labelledby="page-header-user-dropdown">
                    <div class="bg-primary-darker rounded-top font-w600 text-white text-center p-3">
                        Brzi Linkovi
                    </div>
                    <div class="p-2">
                        <a class="dropdown-item text-info" href="{{ route('index') }}" target="_blank">
                            <i class="si si-screen-desktop mr-1"></i> Početna stranica
                        </a>
                        <a class="dropdown-item text-warning" href="{{ route('cache') }}">
                            <span><i class="si si-magic-wand mr-1"></i> Očisti Cache</span>
                        </a>

                        <div role="separator" class="dropdown-divider"></div>

                        <a class="dropdown-item text-danger" href="{{ route('maintenance.on') }}">
                            <i class="si si-ban mr-1"></i> Održavanje ON
                        </a>
                        <a class="dropdown-item text-success" href="{{ route('maintenance.off') }}">
                            <i class="si si-control-play mr-1"></i> Održavanje OFF
                        </a>

                        <div role="separator" class="dropdown-divider"></div>

                        <a class="dropdown-item text-danger-light" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="si si-logout mr-1"></i> Odjava
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>

        </div>
        <!-- END Right Section -->
    </div>
    <!-- END Header Content -->

    <!-- Header Search -->
    <div id="page-header-search" class="overlay-header bg-primary">
        <div class="content-header">
            <form class="w-100" action="/dashboard" method="POST">
                @csrf
                <div class="input-group">
                    <div class="input-group-prepend">
                        <!-- Layout API, functionality initialized in Template._uiApiLayout() -->
                        <button type="button" class="btn btn-primary" data-toggle="layout" data-action="header_search_off">
                            <i class="fa fa-fw fa-times-circle"></i>
                        </button>
                    </div>
                    <input type="text" class="form-control border-0" placeholder="Search or hit ESC.." id="page-header-search-input" name="page-header-search-input">
                </div>
            </form>
        </div>
    </div>
    <!-- END Header Search -->

    <!-- Header Loader -->
    <!-- Please check out the Loaders page under Components category to see examples of showing/hiding it -->
    <div id="page-header-loader" class="overlay-header bg-primary-darker">
        <div class="content-header">
            <div class="w-100 text-center">
                <i class="fa fa-fw fa-2x fa-sun fa-spin text-white"></i>
            </div>
        </div>
    </div>
    <!-- END Header Loader -->
</header>
