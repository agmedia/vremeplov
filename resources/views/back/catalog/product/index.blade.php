@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">

    <!-- Page JS Plugins CSS -->
    <link rel="stylesheet" href="{{ asset('js/plugins/magnific-popup/magnific-popup.css') }}">
@endpush

@section('content')

    @php
        $activeFilterCount = collect(['search', 'category', 'author', 'publisher', 'status', 'sort'])
            ->filter(fn ($key) => filled(request()->input($key)))
            ->count();
    @endphp

    <div class="bg-body-light admin-page-hero">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <span class="admin-page-kicker"><i class="fa fa-layer-group mr-1" aria-hidden="true"></i>Katalog</span>
                    <h1>Artikli</h1>
                    <p class="admin-page-subtitle">Pretraživanje, brze izmjene i upravljanje zalihom na jednom mjestu.</p>
                </div>
                <a class="btn btn-primary my-2" href="{{ route('products.create') }}">
                    <i class="far fa-fw fa-plus-square" aria-hidden="true"></i><span class="ml-1">Novi artikl</span>
                </a>
            </div>
        </div>
    </div>

    <div class="content">
    @include('back.layouts.partials.session')

    <!-- All Products -->
        <div class="block block-rounded admin-list-block">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">Svi artikli <span class="admin-count">{{ number_format($products->total(), 0, ',', '.') }}</span></h2>
                    <small class="text-muted">Kliknite cijenu, godinu ili policu za brzu izmjenu.</small>
                </div>
                <div class="admin-toolbar-actions">
                    <button class="btn btn-outline-primary" type="button" data-toggle="collapse" data-target="#productFilters" aria-expanded="{{ $activeFilterCount ? 'true' : 'false' }}" aria-controls="productFilters">
                        <i class="fa fa-filter mr-1" aria-hidden="true"></i> Filtri
                        @if($activeFilterCount)
                            <span class="admin-count">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    @if($activeFilterCount)
                        <a class="btn btn-light" href="{{ route('products') }}"><i class="fa fa-times mr-1" aria-hidden="true"></i>Očisti</a>
                    @endif
                </div>
            </div>
            <div class="collapse {{ $activeFilterCount ? 'show' : '' }}" id="productFilters">
                <div class="block-content admin-filter-panel">
                    <form action="{{ route('products') }}" method="get">

                        <div class="form-group row items-push mb-0">
                            <div class="col-md-9 mb-0">
                                <div class="form-group">
                                    <label class="admin-filter-label" for="search-input">Pretraživanje</label>
                                    <div class="input-group flex-nowrap">
                                        <input type="search" class="form-control" name="search" id="search-input" value="{{ request()->input('search') }}" placeholder="Naziv, šifra, godina ili polica">
                                        <button type="submit" class="btn btn-primary" aria-label="Pretraži"><i class="fa fa-search" aria-hidden="true"></i><span class="d-none d-sm-inline ml-2">Traži</span></button>
                                    </div>
                                    <div class="form-text small">Možete upisati puni pojam ili samo njegov dio.</div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="admin-filter-label" for="category-select">Kategorija</label>
                                    <select class="js-select2 form-control" id="category-select" name="category" style="width: 100%;" data-placeholder="Odaberi kategoriju">
                                        <option></option>
                                        @foreach ($categories as $group => $cats)
                                            <optgroup label="{{ $group }}">
                                                @foreach ($cats as $id => $category)
                                                    <option value="{{ $id }}" {{ $id == request()->input('category') ? 'selected' : '' }}>{{ $category['title'] }}</option>
                                                    @if ( ! empty($category['subs']))
                                                        @foreach ($category['subs'] as $sub_id => $subcategory)
                                                            <option value="{{ $sub_id }}" {{ $sub_id == request()->input('category') ? 'selected' : '' }}>{{ $category['title'] }} › {{ $subcategory['title'] }}</option>
                                                        @endforeach
                                                    @endif
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>
                        <div class="form-group row items-push mb-0">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="admin-filter-label">Autor</label>
                                    @livewire('back.layout.search.author-search', ['author_id' => request()->input('author') ?: '', 'list' => true])
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="admin-filter-label">Izdavač</label>
                                    @livewire('back.layout.search.publisher-search', ['publisher_id' => request()->input('publisher') ?: '', 'list' => true])
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="admin-filter-label" for="status-select">Status i zaliha</label>
                                    <select class="js-select2 form-control" id="status-select" name="status" style="width: 100%;" data-placeholder="Odaberi status">
                                        <option></option>
                                        <option value="all" {{ 'all' == request()->input('status') ? 'selected' : '' }}>Svi artikli</option>
                                        <option value="active" {{ 'active' == request()->input('status') ? 'selected' : '' }}>Aktivni</option>
                                        <option value="inactive" {{ 'inactive' == request()->input('status') ? 'selected' : '' }}>Neaktivni</option>
                                        <option value="kolicina" {{ 'kolicina' == request()->input('status') ? 'selected' : '' }}>Rasprodano</option>
                                        <option value="with_action" {{ 'with_action' == request()->input('status') ? 'selected' : '' }}>Sa akcijama</option>
                                        <option value="without_action" {{ 'without_action' == request()->input('status') ? 'selected' : '' }}>Bez akcija</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="admin-filter-label" for="sort-select">Sortiranje</label>
                                    <select class="js-select2 form-control" id="sort-select" name="sort" style="width: 100%;" data-placeholder="Sortiraj artikle">
                                        <option></option>
                                        <option value="new" {{ 'new' == request()->input('sort') ? 'selected' : '' }}>Najnovije</option>
                                        <option value="old" {{ 'old' == request()->input('sort') ? 'selected' : '' }}>Najstarije</option>
                                        <option value="price_up" {{ 'price_up' == request()->input('sort') ? 'selected' : '' }}>Cijena: niža prvo</option>
                                        <option value="price_down" {{ 'price_down' == request()->input('sort') ? 'selected' : '' }}>Cijena: viša prvo</option>
                                        <option value="az" {{ 'az' == request()->input('sort') ? 'selected' : '' }}>Naziv: A–Ž</option>
                                        <option value="za" {{ 'za' == request()->input('sort') ? 'selected' : '' }}>Naziv: Ž–A</option>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
            <div class="block-content">
                <div class="table-responsive admin-products-table-wrap">
                    <table class="table table-borderless table-striped table-vcenter admin-products-table">
                        <thead>
                        <tr>
                            <th class="text-center">Slika</th>
                            <th>Naziv</th>
                            <th>Šifra</th>
                            <th class="text-right">Cijena</th>
                            <th class="text-center">God.</th>
                           <th class="text-center">Polica</th>
                         <!--   <th class="text-center">Dimenzija</th> -->
                            <th class="text-center">Kol.</th>
                            <th>Dodano</th>
                            <th>Izmjena</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Radnje</th>
                        </tr>
                        </thead>
                        <tbody id="ag-table-with-input-fields" class="js-gallery" >
                        @forelse ($products as $product)
                            <tr>
                                <td class="text-center font-size-sm" data-label="Slika">
                                    <a class="img-link img-link-zoom-in img-lightbox"
                                       href="{{ $product->image_url }}"
                                       data-fallback-src="{{ $product->thumb }}"
                                       title="{{ $product->name }}"
                                       aria-label="Povećaj sliku artikla {{ $product->name }}">
                                        <img class="admin-product-thumb" src="{{ $product->thumb }}" alt="{{ $product->name }}" loading="lazy"/>
                                    </a>
                                </td>
                                <td class="font-size-sm" data-label="Naziv">
                                    <a class="admin-product-name" href="{{ route('products.edit', ['product' => $product]) }}" title="{{ $product->name }}">{{ $product->name }}</a>
                                    <div class="admin-product-categories">
                                    @if ($product->categories)
                                        @foreach ($product->categories as $cat)
                                            <span class="badge badge-secondary">{{ $cat->title }}</span>
                                        @endforeach
                                    @endif
                                    @if ($product->subcategory())
                                        <span class="badge badge-secondary">{{ $product->subcategory()->title }}</span>
                                    @endif
                                    </div>
                                </td>
                                <td class="font-size-sm" data-label="Šifra"><span class="admin-mono">{{ $product->sku }}</span></td>
                                <td class="font-size-sm text-right" data-label="Cijena">
                                    <ag-input-field item="{{ $product }}" target="price"></ag-input-field>
                                </td>
                                <td class="font-size-sm text-center" data-label="Godina">
                                    <ag-input-field item="{{ $product }}" target="year"></ag-input-field>
                                </td>
                               <td class="font-size-sm text-center" data-label="Polica"><ag-input-field item="{{ $product }}" target="polica"></ag-input-field></td>
{{--                                <td class="font-size-sm text-center">  <ag-input-field item="{{ $product }}" target="dimensions"></ag-input-field></td>--}}
                                <td class="font-size-sm text-center" data-label="Količina"><strong>{{ $product->quantity }}</strong></td>
                                <td class="font-size-sm" data-label="Dodano">{{ \Illuminate\Support\Carbon::make($product->created_at)->format('d.m.Y') }}</td>
                                <td class="font-size-sm" data-label="Izmjena">{{ \Illuminate\Support\Carbon::make($product->updated_at)->format('d.m.Y') }}</td>
                                <td class="text-center font-size-sm" data-label="Status">
                                    <div class="custom-control custom-switch custom-control-success mb-1">
                                        <input type="checkbox" class="custom-control-input" id="status-{{ $product->id }}" onclick="setStatus({{ $product->id }})" name="status" @if ($product->status) checked="" @endif>
                                        <label class="custom-control-label" for="status-{{ $product->id }}"></label>
                                    </div>
                                </td>
                                <td class="text-right font-size-sm" data-label="Radnje">
                                    <span class="admin-row-actions">
                                    <a class="btn btn-sm btn-alt-secondary" target="_blank" href="{{ url($product->url) }}" title="Otvori artikl" aria-label="Otvori {{ $product->name }}">
                                        <i class="fa fa-fw fa-eye"></i>
                                    </a>
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ route('products.edit', ['product' => $product]) }}" title="Uredi artikl" aria-label="Uredi {{ $product->name }}">
                                        <i class="fa fa-fw fa-pencil-alt"></i>
                                    </a>
                                    <a class="btn btn-sm btn-alt-warning" href="{{ route('products.duplicate', ['product' => $product]) }}" title="Dupliciraj artikl" aria-label="Dupliciraj {{ $product->name }}">
                                        <i class="fa fa-fw fa-copy"></i>
                                    </a>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-empty-state" colspan="12">
                                    <i class="fa fa-search" aria-hidden="true"></i>
                                    <strong>Nema pronađenih artikala.</strong>
                                    <div class="mt-1">Promijenite ili očistite filtre pa pokušajte ponovno.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                {{ $products->links() }}
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/ag-input-field.js') }}"></script>

    <!-- Page JS Plugins -->
    <script src="{{ asset('js/plugins/magnific-popup/jquery.magnific-popup.min.js') }}"></script>

    <script>
        jQuery(function ($) {
            const attachImageFallback = function (popup, item) {
                if (!item || !item.img || !item.el) {
                    return;
                }

                const fallbackSrc = item.el.data('fallback-src');
                const $image = item.img;

                $image.off('.productImageFallback').one('error.productImageFallback', function () {
                    if (!fallbackSrc || this.src === fallbackSrc) {
                        return;
                    }

                    item.src = fallbackSrc;
                    item.loadError = false;
                    item.loaded = false;
                    item.hasSize = false;
                    popup.updateStatus('loading');

                    $image
                        .one('load.productImageFallback', function () {
                            item.loadError = false;
                            item.loaded = true;
                            item.hasSize = true;
                            popup._onImageHasSize(item);
                            popup.updateStatus('ready');
                        })
                        .attr('src', fallbackSrc);
                });
            };

            $('#ag-table-with-input-fields').magnificPopup({
                delegate: 'a.img-lightbox',
                type: 'image',
                gallery: {
                    enabled: true
                },
                callbacks: {
                    change: function (item) {
                        attachImageFallback(this, item);
                    }
                }
            });
        });
    </script>

    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {
            $('#category-select').select2({
                placeholder: 'Odaberite kategoriju',
                allowClear: true
            });
            $('#status-select').select2({
                placeholder: 'Odaberite status',
                allowClear: true
            });
            $('#sort-select').select2({
                placeholder: 'Sortiraj artikle',
                allowClear: true
            });

            //
            $('#category-select').on('change', (e) => {
                setURL('category', e.currentTarget.selectedOptions[0]);
            });
            $('#status-select').on('change', (e) => {
                setURL('status', e.currentTarget.selectedOptions[0]);
            });
            $('#sort-select').on('change', (e) => {
                setURL('sort', e.currentTarget.selectedOptions[0]);
            });

            //
            Livewire.on('authorSelect', (e) => {
                setURL('author', e.author.id, true);
            });
            Livewire.on('publisherSelect', (e) => {
                setURL('publisher', e.publisher.id, true);
            });

            /*$('#btn-inactive').on('click', () => {
                setRegularURL('active', false);
            });
            $('#btn-today').on('click', () => {
                setRegularURL('today', true);
            });
            $('#btn-week').on('click', () => {
                setRegularURL('week', true);
            });*/

        });

        /**
         *
         * @param type
         * @param search
         */
        function setURL(type, search, isValue = false) {
            let url = new URL(location.href);
            let params = new URLSearchParams(url.search);
            let keys = [];

            for(var key of params.keys()) {
                if (key === type) {
                    keys.push(key);
                }
            }

            keys.forEach((value) => {
                if (params.has(value)) {
                    params.delete(value);
                }
            })

            if (search.value) {
                params.append(type, search.value);
            }

            if (isValue && search) {
                params.append(type, search);
            }

            url.search = params;
            location.href = url;
        }

        /**
         *
         * @param type
         * @param search
         */
        function setRegularURL(type, search) {
            let searches = ['active', 'today', 'week'];
            let url = new URL(location.href);
            let params = new URLSearchParams(url.search);
            let keys = [];

            for(var key of params.keys()) {
                if (key === type) {
                    keys.push(key);
                }
            }

            keys.forEach((value) => {
                if (params.has(value)) {
                    params.delete(value);
                }
            })

            params.append(type, search);

            url.search = params;
            location.href = url;
        }

        /**
         *
         * @param id
         */
        function setStatus(id) {
            let val = $('#status-' + id)[0].checked;

            axios.post("{{ route('products.change.status') }}", { id: id, value: val })
            .then((response) => {
                successToast.fire()
            })
            .catch((error) => {
                errorToast.fire()
            });
        }
    </script>

@endpush
