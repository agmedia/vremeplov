<header class="bg-dark shadow-sm  position-relative"
        style="background-image: url({{ config('settings.images_domain') . 'media/img/footer-vintage-bg.jpg' }});background-repeat: repeat;">
    <div class="navbar navbar-expand-lg navbar-dark">
        <div class="container"><a class="navbar-brand d-none d-sm-block flex-shrink-0 me-4 order-lg-1 p-0" href="{{ route('index') }}"><img src="{{ asset('media/img/vremeplov-logo.svg') }}" width="180" height="123" alt="Web shop | Antikvarijat Vremeplov"></a><a class="navbar-brand d-sm-none me-0 order-lg-1 p-0" href="{{ route('index') }}"><img src="{{ asset('media/img/vremeplov-logo.svg') }}" width="100" height="100" alt="Antikvarijat Vremeplov"></a>

            <!-- Toolbar -->
            <div class="navbar-toolbar d-flex align-items-center order-lg-3">
                @if (isset($group) && $group && ! isset($prod))
                    <button class="navbar-toggler" type="button" data-bs-target="#shop-sidebar" aria-label="Filter" data-bs-toggle="collapse" aria-expanded="false"><i class="ci-filter-alt"></i></button>
                @endif
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" aria-label="Navbar" data-bs-target="#navbarCollapse"><span class="navbar-toggler-icon"></span></button>
                <a class="navbar-tool ms-2 me-1" aria-label="Login ir Register" href="{{ route('login') }}" >
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-user-circle"></i></div>
                </a>
                <div>
                    <cart-nav-icon carturl="{{ route('kosarica') }}" checkouturl="{{ route('naplata') }}"></cart-nav-icon>
                </div>
            </div>

            <div class="collapse navbar-collapse me-auto mx-auto order-lg-2 justify-content-center" id="navbarCollapse">
                <form action="{{ route('pretrazi') }}" id="search-form-mobile" method="get">
                    <div class="input-group d-lg-none my-3"><i class="ci-search position-absolute top-50 start-0 translate-middle-y text-muted fs-base ms-3"></i>
                        <input class="form-control rounded-start" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu ili autoru">
                        <button type="submit" class="btn btn-primary btn-lg fs-base rounded-end"><i class="ci-search"></i></button>
                    </div>
                </form>

                <!-- Navbar -->
                <ul class="navbar-nav justify-content-centerpe-lg-2 me-lg-2">
                    <li class="nav-item "><a class="nav-link" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}"><span>Web shop</span></a></li>
                    <li class="nav-item "><a class="nav-link" href="{{ route('catalog.route', ['group' => '/knjige']) }}"><span>Sve knjige</span></a>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.author') }}"><span>Autori</span></a>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.actions') }}"><span>Akcije</span></a>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.blog') }}"><span>Blog</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('kontakt') }}"><span>Kontakt</span></a></li>
                </ul>

            </div>
        </div>
    </div>
    <div class="search-box d-none d-lg-block">
        <div class="card pt-3 pb-3 border-0 rounded-0" style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
            <div class="container">
                <form action="{{ route('pretrazi') }}" id="search-form-desktop" method="get">
                    <div class="input-group position-relative">
                        <input class="form-control rounded-start" id="search-input-desktop" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu, autoru ili šifri" autocomplete="off">
                        <button type="submit" class="btn btn-primary btn-lg fs-base rounded-end"><i class="ci-search"></i></button>
                        <div class="list-group d-none"
                             id="desktop-search-suggest"
                             style="position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:1091;max-height:420px;overflow-y:auto;border:1px solid #e3dfd5;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.08);"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="desktop-search-overlay" class="d-none" style="position:fixed;inset:0;background:rgba(18,14,10,.46);z-index:1090;"></div>
    <!-- Search collapse-->
    <div class="search-box collapse d-lg-none" id="searchBox" >
        <div class="card  pt-3 pb-3 border-0 rounded-0" style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
            <div class="container">
                <form action="{{ route('pretrazi') }}" id="search-form" method="get">
                    <div class="input-group">
                        <input class="form-control rounded-start" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu ili autoru">
                        <button type="submit" class="btn btn-primary btn-lg fs-base rounded-end"><i class="ci-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>




</header>

@push('js_after')
    <script>
        (function () {
            var form = document.getElementById('search-form-desktop');
            var input = document.getElementById('search-input-desktop');
            var suggestBox = document.getElementById('desktop-search-suggest');
            var overlay = document.getElementById('desktop-search-overlay');
            var suggestUrl = @json(route('pretrazi.suggest'));
            var searchUrl = @json(route('pretrazi'));
            var searchKey = @json(config('settings.search_keyword'));

            if (!form || !input || !suggestBox) {
                return;
            }

            var timer = null;
            var controller = null;
            var cache = new Map();

            function closeSuggest() {
                suggestBox.classList.add('d-none');
                suggestBox.innerHTML = '';
                if (overlay) {
                    overlay.classList.add('d-none');
                }
            }

            function normalizeUrl(item) {
                var path = String(item.url || '').replace(/^\/+/, '');
                return '/' + path;
            }

            function addSectionTitle(text) {
                var section = document.createElement('div');
                section.className = 'px-3 py-2 border-bottom text-muted text-uppercase';
                section.style.fontSize = '11px';
                section.style.letterSpacing = '.06em';
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
                link.className = 'list-group-item list-group-item-action';
                link.style.padding = '10px 14px';

                var row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-start gap-3';

                if (meta && meta.image) {
                    var imageWrap = document.createElement('div');
                    imageWrap.className = 'flex-shrink-0';
                    imageWrap.style.width = '60px';

                    var img = document.createElement('img');
                    img.src = meta.image;
                    img.alt = title || 'Proizvod';
                    img.width = 60;
                    img.height = 84;
                    img.loading = 'lazy';
                    img.style.width = '60px';
                    img.style.height = '84px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '4px';
                    img.style.border = '1px solid #e8e2d4';
                    imageWrap.appendChild(img);

                    row.appendChild(imageWrap);
                }

                var left = document.createElement('div');
                left.className = 'flex-grow-1';

                var main = document.createElement('div');
                main.className = 'fw-semibold text-dark';
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
                    right.className = 'text-end';

                    if (typeof meta.price !== 'undefined' && meta.price !== null) {
                        var price = document.createElement('div');
                        price.className = 'fw-semibold';
                        price.style.color = '#2d2821';
                        price.textContent = formatPrice(meta.price);
                        right.appendChild(price);
                    }

                    if (meta.sold_out) {
                        var soldOut = document.createElement('small');
                        soldOut.className = 'd-inline-block mt-1 px-2 py-1 rounded';
                        soldOut.style.background = '#f4e7bf';
                        soldOut.style.color = '#6a4f0f';
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
                link.className = 'list-group-item list-group-item-action text-center fw-semibold';
                link.style.padding = '12px 14px';
                link.style.background = '#f8f6f1';
                link.href = searchUrl + '?' + encodeURIComponent(searchKey) + '=' + encodeURIComponent(query);
                link.textContent = 'Pretraži sve rezultate';
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
                            item.name,
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
        })();
    </script>
@endpush
