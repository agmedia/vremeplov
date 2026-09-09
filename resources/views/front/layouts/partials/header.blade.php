@php
    $mobileCatalogGroup = request()->routeIs('catalog.route') && empty($prod)
        ? (string) request()->route('group')
        : '';
    $mobileBooksActive = $mobileCatalogGroup === 'knjige';
    $mobileShopActive = $mobileCatalogGroup !== '' && ! $mobileBooksActive;
    $mobileShopRootActive = $mobileCatalogGroup === \App\Helpers\Helper::categoryGroupPath(true);
    $mobileCatalogCategoryId = isset($cat) && is_object($cat) ? (int) $cat->id : 0;
    $mobileCatalogSubcategoryId = isset($subcat) && is_object($subcat) ? (int) $subcat->id : 0;
@endphp

<header class="site-header bg-dark position-relative"
        style="background-image: url({{ config('settings.images_domain') . 'media/img/footer-vintage-bg.jpg' }});background-repeat: repeat;">
    <div class="navbar navbar-expand-lg navbar-dark site-header__main">
        <div class="container">
            <a class="navbar-brand site-header__brand d-none d-sm-block flex-shrink-0 me-4 order-lg-1 p-0" href="{{ route('index') }}">
                <img src="{{ config('settings.images_domain') . 'media/img/vremeplov-logo.svg' }}" width="160" height="110" alt="Web shop | Antikvarijat Vremeplov">
            </a>
            <a class="navbar-brand d-sm-none me-0 order-lg-1 p-0" href="{{ route('index') }}">
                <img src="{{ config('settings.images_domain') . 'media/img/vremeplov-logo.svg' }}" width="100" height="100" alt="Antikvarijat Vremeplov">
            </a>

            <div class="site-header__desktop-search d-none d-lg-block order-lg-2">
                <form action="{{ route('pretrazi') }}" id="search-form-desktop" method="get" role="search">
                    <label class="visually-hidden" for="search-input-desktop">Pretražite po nazivu, autoru ili šifri</label>
                    <div class="input-group position-relative site-header__search-control">
                        <i class="fa-regular fa-magnifying-glass site-header__search-leading" aria-hidden="true"></i>
                        <input class="form-control ps-5" id="search-input-desktop" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu, autoru ili šifri" autocomplete="off" aria-autocomplete="list" aria-controls="desktop-search-suggest" aria-expanded="false">
                        <button type="submit" class="btn btn-primary btn-lg fs-base" aria-label="Pretraži"><i class="fa-regular fa-magnifying-glass" aria-hidden="true"></i></button>
                        <div class="list-group desktop-search-suggest d-none" id="desktop-search-suggest" role="listbox"></div>
                    </div>
                </form>
            </div>

            <!-- Toolbar -->
            <div class="navbar-toolbar site-header__toolbar d-flex align-items-center order-lg-3">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" aria-label="Otvori glavni izbornik" aria-controls="mobileNavigation" data-bs-target="#mobileNavigation"><i class="fa-regular fa-bars" aria-hidden="true"></i></button>
                <a class="navbar-tool ms-2 me-1" aria-label="Prijava ili registracija" href="{{ route('login') }}" >
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon fa-regular fa-circle-user"></i></div>
                </a>
                <div>
                    <cart-nav-icon carturl="{{ route('kosarica') }}" checkouturl="{{ route('naplata') }}"></cart-nav-icon>
                </div>
            </div>
        </div>
    </div>

    <nav class="site-header__desktop-nav d-none d-lg-block" aria-label="Glavni izbornik">
        <div class="container">
            <ul class="navbar-nav flex-row justify-content-center">
                <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}"><i class="fa-regular fa-shop" aria-hidden="true"></i><span>Web shop</span></a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route', ['group' => '/knjige']) }}"><i class="fa-regular fa-books" aria-hidden="true"></i><span>Sve knjige</span></a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.author') }}"><i class="fa-regular fa-user-pen" aria-hidden="true"></i><span>Autori</span></a></li>
                @if ($hasCatalogActions ?? false)
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.actions') }}"><i class="fa-regular fa-badge-percent" aria-hidden="true"></i><span>Akcije</span></a></li>
                @endif
                <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.blog') }}"><i class="fa-regular fa-newspaper" aria-hidden="true"></i><span>Blog</span></a></li>
                <li class="nav-item"><a class="nav-link{{ request()->routeIs('book-purchase.*') ? ' active' : '' }}" href="{{ route('book-purchase.create') }}" @if (request()->routeIs('book-purchase.*')) aria-current="page" @endif><i class="fa-regular fa-book-open" aria-hidden="true"></i><span>Otkup knjiga</span></a></li>
                <li class="nav-item"><a class="nav-link{{ request()->routeIs('faq') ? ' active' : '' }}" href="{{ route('faq') }}" @if (request()->routeIs('faq')) aria-current="page" @endif><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span>Česta pitanja</span></a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('kontakt') }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>Kontakt</span></a></li>
            </ul>
        </div>
    </nav>

    <div class="offcanvas offcanvas-start mobile-main-navigation d-lg-none" tabindex="-1" id="mobileNavigation" aria-labelledby="mobileNavigationTitle">
        <div class="offcanvas-header mobile-main-navigation__header">
            <a href="{{ route('index') }}" aria-label="Antikvarijat Vremeplov">
                <img src="{{ config('settings.images_domain') . 'media/img/vremeplov-logo.svg' }}" width="116" height="82" alt="">
            </a>
            <h2 class="visually-hidden" id="mobileNavigationTitle">Glavni izbornik</h2>
            <button class="mobile-main-navigation__close" type="button" data-bs-dismiss="offcanvas" aria-label="Zatvori glavni izbornik">
                <i class="fa-regular fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="offcanvas-body p-0">
            <nav aria-label="Glavni izbornik">
                <ul class="mobile-main-navigation__list">
                    <li>
                        <details class="mobile-main-navigation__section" @if ($mobileShopActive) open @endif>
                            <summary class="mobile-main-navigation__row {{ $mobileShopActive ? 'is-active' : '' }}">
                                <i class="fa-duotone fa-shop" aria-hidden="true"></i>
                                <span>Web shop</span>
                                <i class="fa-regular fa-chevron-down mobile-main-navigation__chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="mobile-main-navigation__panel">
                                <a class="mobile-main-navigation__all {{ $mobileShopRootActive ? 'is-active' : '' }}" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}" @if ($mobileShopRootActive) aria-current="page" @endif>
                                    <span>Pregledaj cijeli web shop</span>
                                    <i class="fa-regular fa-arrow-right" aria-hidden="true"></i>
                                </a>
                                <ul class="mobile-main-navigation__submenu">
                                    @foreach ($mobileNavigationGroups as $navigationGroup)
                                        @php($mobileNavigationGroupActive = $mobileShopActive && ! $mobileShopRootActive && $mobileCatalogGroup === (string) $navigationGroup->slug)
                                        <li>
                                            <a class="{{ $mobileNavigationGroupActive ? 'is-active' : '' }}" href="{{ route('catalog.route', ['group' => $navigationGroup->slug]) }}" @if ($mobileNavigationGroupActive) aria-current="page" @endif>
                                                <span>{{ $navigationGroup->title }}</span>
                                                <i class="fa-regular fa-chevron-right" aria-hidden="true"></i>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    </li>
                    <li>
                        <details class="mobile-main-navigation__section" @if ($mobileBooksActive) open @endif>
                            <summary class="mobile-main-navigation__row {{ $mobileBooksActive ? 'is-active' : '' }}">
                                <i class="fa-duotone fa-books" aria-hidden="true"></i>
                                <span>Sve knjige</span>
                                <i class="fa-regular fa-chevron-down mobile-main-navigation__chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="mobile-main-navigation__panel">
                                <a class="mobile-main-navigation__all {{ $mobileBooksActive && ! $mobileCatalogCategoryId ? 'is-active' : '' }}" href="{{ route('catalog.route', ['group' => '/knjige']) }}" @if ($mobileBooksActive && ! $mobileCatalogCategoryId) aria-current="page" @endif>
                                    <span>Pregledaj sve knjige</span>
                                    <i class="fa-regular fa-arrow-right" aria-hidden="true"></i>
                                </a>
                                <ul class="mobile-main-navigation__submenu">
                                    @foreach ($mobileNavigationBookCategories as $navigationCategory)
                                        @php($mobileBookCategoryActive = $mobileBooksActive && $mobileCatalogCategoryId === (int) $navigationCategory->id)
                                        <li>
                                            @if ($navigationCategory->subcategories->isNotEmpty())
                                                <details class="mobile-main-navigation__subsection" @if ($mobileBookCategoryActive) open @endif>
                                                    <summary class="{{ $mobileBookCategoryActive ? 'is-active' : '' }}">
                                                        <span>{{ $navigationCategory->title }}</span>
                                                        <i class="fa-regular fa-chevron-down mobile-main-navigation__chevron" aria-hidden="true"></i>
                                                    </summary>
                                                    <div class="mobile-main-navigation__subpanel">
                                                        <a class="mobile-main-navigation__category-all {{ $mobileBookCategoryActive && ! $mobileCatalogSubcategoryId ? 'is-active' : '' }}" href="{{ $navigationCategory->url() }}" @if ($mobileBookCategoryActive && ! $mobileCatalogSubcategoryId) aria-current="page" @endif>
                                                            Sve iz kategorije
                                                        </a>
                                                        <ul>
                                                            @foreach ($navigationCategory->subcategories as $navigationSubcategory)
                                                                @php($mobileBookSubcategoryActive = $mobileBookCategoryActive && $mobileCatalogSubcategoryId === (int) $navigationSubcategory->id)
                                                                <li>
                                                                    <a class="{{ $mobileBookSubcategoryActive ? 'is-active' : '' }}" href="{{ $navigationCategory->url($navigationSubcategory) }}" @if ($mobileBookSubcategoryActive) aria-current="page" @endif>
                                                                        <span>{{ $navigationSubcategory->title }}</span>
                                                                        <i class="fa-regular fa-arrow-right" aria-hidden="true"></i>
                                                                    </a>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </details>
                                            @else
                                                <a class="{{ $mobileBookCategoryActive ? 'is-active' : '' }}" href="{{ $navigationCategory->url() }}" @if ($mobileBookCategoryActive) aria-current="page" @endif>
                                                    <span>{{ $navigationCategory->title }}</span>
                                                    <i class="fa-regular fa-chevron-right" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    </li>
                    <li><a class="mobile-main-navigation__row" href="{{ route('catalog.route.author') }}"><i class="fa-duotone fa-user-pen" aria-hidden="true"></i><span>Autori</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a></li>
                    <li><a class="mobile-main-navigation__row" href="{{ route('catalog.route.publisher') }}"><i class="fa-duotone fa-building" aria-hidden="true"></i><span>Izdavači</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a></li>
                    @if ($hasCatalogActions ?? false)
                        <li><a class="mobile-main-navigation__row" href="{{ route('catalog.route.actions') }}"><i class="fa-duotone fa-badge-percent" aria-hidden="true"></i><span>Akcije</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a></li>
                    @endif
                    <li><a class="mobile-main-navigation__row" href="{{ route('catalog.route.blog') }}"><i class="fa-duotone fa-newspaper" aria-hidden="true"></i><span>Blog</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a></li>
                    <li><a class="mobile-main-navigation__row{{ request()->routeIs('book-purchase.*') ? ' is-active' : '' }}" href="{{ route('book-purchase.create') }}" @if (request()->routeIs('book-purchase.*')) aria-current="page" @endif><i class="fa-duotone fa-book-open" aria-hidden="true"></i><span>Otkup knjiga</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a></li>
                    <li><a class="mobile-main-navigation__row{{ request()->routeIs('faq') ? ' is-active' : '' }}" href="{{ route('faq') }}" @if (request()->routeIs('faq')) aria-current="page" @endif><i class="fa-duotone fa-circle-question" aria-hidden="true"></i><span>Česta pitanja</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a></li>
                    <li><a class="mobile-main-navigation__row" href="{{ route('kontakt') }}"><i class="fa-duotone fa-envelope" aria-hidden="true"></i><span>Kontakt</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a></li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="mobile-header-search d-lg-none">
        <div class="container">
            <form action="{{ route('pretrazi') }}" id="search-form-mobile" method="get" role="search">
                <label class="visually-hidden" for="search-input-mobile">Pretražite po nazivu, autoru ili šifri</label>
                <div class="input-group position-relative">
                    <i class="fa-regular fa-magnifying-glass mobile-header-search__leading position-absolute top-50 start-0 translate-middle-y text-muted fs-base ms-3" aria-hidden="true"></i>
                    <input class="form-control rounded-start" id="search-input-mobile" type="search" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu, autoru ili šifri" autocomplete="off" aria-autocomplete="list" aria-controls="mobile-search-suggest" aria-expanded="false">
                    <button type="submit" class="btn btn-primary btn-lg fs-base rounded-end" aria-label="Pretraži"><i class="fa-regular fa-magnifying-glass" aria-hidden="true"></i></button>
                    <div class="list-group mobile-search-suggest d-none" id="mobile-search-suggest" role="listbox"></div>
                </div>
            </form>
        </div>
    </div>
    <div id="desktop-search-overlay" class="d-none" style="position:fixed;inset:0;background:rgba(18,14,10,.46);z-index:1090;"></div>
</header>

@push('js_after')
    <script>
        (function () {
            var overlay = document.getElementById('desktop-search-overlay');
            var suggestUrl = @json(route('pretrazi.suggest'));
            var searchUrl = @json(route('pretrazi'));
            var searchKey = @json(config('settings.search_keyword'));
            var cache = new Map();

            function initializeSearch(formId, inputId, suggestId) {
                var form = document.getElementById(formId);
                var input = document.getElementById(inputId);
                var suggestBox = document.getElementById(suggestId);

                if (!form || !input || !suggestBox) {
                    return;
                }

                var timer = null;
                var controller = null;
                var isMobileSuggest = suggestBox.classList.contains('mobile-search-suggest');
                var resizeFrame = null;

                function updateMobileSuggestHeight() {
                    if (!isMobileSuggest || suggestBox.classList.contains('d-none')) {
                        return;
                    }

                    window.cancelAnimationFrame(resizeFrame);
                    resizeFrame = window.requestAnimationFrame(function () {
                        var viewport = window.visualViewport;
                        var viewportBottom = viewport
                            ? viewport.offsetTop + viewport.height
                            : window.innerHeight;
                        var suggestTop = suggestBox.getBoundingClientRect().top;
                        var availableHeight = Math.max(0, Math.floor(viewportBottom - suggestTop));

                        suggestBox.style.setProperty('--mobile-search-suggest-height', availableHeight + 'px');
                    });
                }

                function closeSuggest() {
                    suggestBox.classList.add('d-none');
                    suggestBox.innerHTML = '';
                    input.setAttribute('aria-expanded', 'false');

                    if (isMobileSuggest) {
                        suggestBox.style.removeProperty('--mobile-search-suggest-height');
                    }

                    if (overlay && !document.querySelector('.mobile-search-suggest:not(.d-none), #desktop-search-suggest:not(.d-none)')) {
                        overlay.classList.add('d-none');
                    }
                }

                function normalizeUrl(item) {
                    var path = String(item.url || '').replace(/^\/+/, '');
                    return '/' + path;
                }

                function addSectionTitle(text) {
                    var section = document.createElement('div');
                    section.className = 'search-suggest__section';
                    section.textContent = text;
                    suggestBox.appendChild(section);
                }

                function formatPrice(value) {
                    var number = Number(value || 0);
                    return number.toLocaleString('hr-HR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' €';
                }

                function addRow(url, title, subtitle, meta) {
                    var link = document.createElement('a');
                    link.href = url;
                    link.className = 'list-group-item list-group-item-action search-suggest__item';

                    var row = document.createElement('div');
                    row.className = 'd-flex justify-content-between align-items-start gap-3 search-suggest__row';

                    if (meta && meta.image) {
                        var imageWrap = document.createElement('div');
                        imageWrap.className = 'flex-shrink-0 search-suggest__media';

                        var img = document.createElement('img');
                        img.src = meta.image;
                        img.alt = title || 'Proizvod';
                        img.width = 60;
                        img.height = 84;
                        img.loading = 'lazy';
                        img.className = 'search-suggest__image';
                        imageWrap.appendChild(img);

                        row.appendChild(imageWrap);
                    }

                    var left = document.createElement('div');
                    left.className = 'flex-grow-1 search-suggest__content';

                    var main = document.createElement('div');
                    main.className = 'fw-semibold text-dark search-suggest__title';
                    main.textContent = title || '';
                    left.appendChild(main);

                    if (subtitle) {
                        var sub = document.createElement('small');
                        sub.className = 'text-muted';
                        sub.textContent = subtitle;
                        left.appendChild(sub);
                    }

                    row.appendChild(left);

                    if (meta) {
                        var right = document.createElement('div');
                        right.className = 'text-end search-suggest__meta';

                        if (typeof meta.price !== 'undefined' && meta.price !== null) {
                            var price = document.createElement('div');
                            price.className = 'fw-semibold search-suggest__price';
                            price.textContent = formatPrice(meta.price);
                            right.appendChild(price);
                        }

                        if (meta.sold_out) {
                            var soldOut = document.createElement('small');
                            soldOut.className = 'd-inline-block mt-1 px-2 py-1 rounded search-suggest__sold-out';
                            soldOut.textContent = 'Rasprodano';
                            right.appendChild(soldOut);
                        }

                        row.appendChild(right);
                    }

                    link.appendChild(row);

                    suggestBox.appendChild(link);
                }

                function addSearchAll(query) {
                    var link = document.createElement('a');
                    link.className = 'list-group-item list-group-item-action text-center fw-semibold mobile-search-suggest__all search-suggest__all';
                    link.href = searchUrl + '?' + encodeURIComponent(searchKey) + '=' + encodeURIComponent(query);
                    link.textContent = 'Prikaži sve rezultate za “' + query + '”  →';
                    suggestBox.appendChild(link);
                }

                function render(payload, query) {
                    suggestBox.innerHTML = '';

                    var authors = (payload && payload.authors) ? payload.authors : [];
                    var products = (payload && payload.products) ? payload.products : [];

                    if (!authors.length && !products.length) {
                        closeSuggest();
                        return;
                    }

                    if (authors.length) {
                        addSectionTitle('Autori');
                        authors.forEach(function (item) {
                            addRow(normalizeUrl(item), item.title, null, null);
                        });
                    }

                    if (products.length) {
                        addSectionTitle('Artikli');
                        products.forEach(function (item) {
                            addRow(
                                normalizeUrl(item),
                                item.card_name || item.name,
                                item.author || null,
                                {
                                    price: item.price,
                                    sold_out: Number(item.quantity || 0) < 1,
                                    image: item.image || null
                                }
                            );
                        });
                    }

                    addSearchAll(query);

                    suggestBox.classList.remove('d-none');
                    input.setAttribute('aria-expanded', 'true');
                    updateMobileSuggestHeight();
                    if (overlay) {
                        overlay.classList.remove('d-none');
                    }
                }

                function fetchSuggest(query) {
                    if (cache.has(query)) {
                        render(cache.get(query), query);
                        return;
                    }

                    if (controller) {
                        controller.abort();
                    }

                    controller = new AbortController();

                    fetch(suggestUrl + '?q=' + encodeURIComponent(query), {
                        signal: controller.signal,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(function (response) {
                            return response.json();
                        })
                        .then(function (data) {
                            var payload = {
                                authors: (data && data.authors) ? data.authors : [],
                                products: (data && data.products) ? data.products : []
                            };
                            cache.set(query, payload);

                            if (input.value.trim() === query) {
                                render(payload, query);
                            }
                        })
                        .catch(function (error) {
                            if (error.name !== 'AbortError') {
                                closeSuggest();
                            }
                        });
                }

                input.addEventListener('input', function () {
                    var query = input.value.trim();

                    if (query.length < 2) {
                        closeSuggest();
                        return;
                    }

                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        fetchSuggest(query);
                    }, 220);
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        closeSuggest();
                    }
                });

                document.addEventListener('click', function (event) {
                    if (!form.contains(event.target) && !suggestBox.contains(event.target)) {
                        closeSuggest();
                    }
                });

                form.addEventListener('submit', function () {
                    closeSuggest();
                });

                if (overlay) {
                    overlay.addEventListener('click', function () {
                        closeSuggest();
                    });
                }

                if (isMobileSuggest) {
                    window.addEventListener('resize', updateMobileSuggestHeight);
                    window.addEventListener('orientationchange', updateMobileSuggestHeight);

                    if (window.visualViewport) {
                        window.visualViewport.addEventListener('resize', updateMobileSuggestHeight);
                        window.visualViewport.addEventListener('scroll', updateMobileSuggestHeight);
                    }
                }
            }

            initializeSearch('search-form-desktop', 'search-input-desktop', 'desktop-search-suggest');
            initializeSearch('search-form-mobile', 'search-input-mobile', 'mobile-search-suggest');

            var mobileNavigation = document.getElementById('mobileNavigation');

            if (mobileNavigation) {
                var activeNavigationScrollTimer = null;

                function revealActiveNavigationLink() {
                    var activeLink = mobileNavigation.querySelector('[aria-current="page"]');
                    var scrollArea = mobileNavigation.querySelector('.offcanvas-body');

                    if (!activeLink || !scrollArea) {
                        return;
                    }

                    var activeRect = activeLink.getBoundingClientRect();
                    var scrollRect = scrollArea.getBoundingClientRect();

                    if (activeRect.top >= scrollRect.top && activeRect.bottom <= scrollRect.bottom) {
                        return;
                    }

                    scrollArea.scrollTop += activeRect.top
                        - scrollRect.top
                        - Math.max(16, (scrollRect.height - activeRect.height) / 2);
                }

                function scheduleActiveNavigationScroll() {
                    window.clearTimeout(activeNavigationScrollTimer);
                    activeNavigationScrollTimer = window.setTimeout(revealActiveNavigationLink, 360);
                }

                document.querySelectorAll('[data-bs-target="#mobileNavigation"]').forEach(function (trigger) {
                    trigger.addEventListener('click', scheduleActiveNavigationScroll);
                });

                mobileNavigation.addEventListener('shown.bs.offcanvas', revealActiveNavigationLink);
                mobileNavigation.addEventListener('transitionend', function () {
                    if (mobileNavigation.classList.contains('show')) {
                        revealActiveNavigationLink();
                    }
                });
            }
        })();
    </script>
@endpush
