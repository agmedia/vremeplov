@extends('front.layouts.app')

@if (isset($meta) && ! empty($meta))
    @php
        $canonicalUrl = $meta['canonical'];
        $pageNumber = max(1, (int) request()->input('page', 1));
        $hasFacetParameters = request()->hasAny(['start', 'end', 'autor', 'nakladnik', 'pismo', 'stanje', 'uvez', 'jezik', 'sort']);

        if ($pageNumber > 1 && ! $hasFacetParameters && ! request()->routeIs('pretrazi', 'tag')) {
            $canonicalUrl .= (str_contains($canonicalUrl, '?') ? '&' : '?') . 'page=' . $pageNumber;
        }
    @endphp
    @section('title', $meta['title'] . ' - Antikvarijat Vremeplov')
    @section('description', $meta['description'])
    @section('canonical', $canonicalUrl)
    @push('meta_tags')
        <meta property="og:locale" content="hr_HR" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $meta['title'] }} - Antikvarijat Vremeplov" />
        <meta property="og:description" content="{{ $meta['description'] }}" />
        <meta property="og:url" content="{{ $meta['canonical'] }}" />
        <meta property="og:site_name" content="Antikvarijat Vremeplov" />
        <meta property="og:image" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />
        <meta property="og:image:secure_url" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $meta['title'] }} - Antikvarijat Vremeplov" />
        <meta name="twitter:description" content="{{ $meta['description'] }}" />
        <meta name="twitter:image" content="{{ config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' }}" />
        @foreach ($meta['tags'] as $tag)
            <meta name="{{ $tag['name'] }}" content="{{ $tag['content'] }}">
        @endforeach
    @endpush
@endif

@if (Route::currentRouteName() == 'pretrazi')
    @php
        $searchTerm = trim((string) request()->input('pojam'));
    @endphp
    @if ($searchTerm !== '')
        @section('google_data_layer')
            <script>window.VremeplovAnalytics.track('search', {search_term: @json($searchTerm)});</script>
        @endsection
    @endif
@endif

@section('content')

    <div class="catalog-heading" style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
        <div class="container catalog-heading__inner">
            @if (isset($crumbs) && ! empty($crumbs))
                <nav class="catalog-heading__breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb mb-0">
                        @foreach ($crumbs['itemListElement'] as $crumb)
                            @if ($loop->last)
                                <li class="breadcrumb-item active" aria-current="page">{{ $crumb['name'] }}</li>
                            @else
                                <li class="breadcrumb-item">
                                    <a href="{{ $crumb['item'] }}">
                                        @if ($loop->first)
                                            <i class="fa-regular fa-house" aria-hidden="true"></i>
                                        @endif
                                        <span>{{ $crumb['name'] }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif

            @if (isset($meta) && ! empty($meta))
                <div class="catalog-heading__copy">
                    <h1 class="catalog-heading__title">{{ $meta['title'] }}</h1>
                    @if (! empty($meta['description']))
                        <p class="catalog-heading__description">{{ $meta['description'] }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>



    @php
        $catalogFiltersEnabled = (isset($group) && $group === 'knjige') || isset($author) || isset($publisher) || request()->routeIs('pretrazi');
        $catalogBackCrumb = isset($crumbs['itemListElement']) && count($crumbs['itemListElement']) > 1
            ? $crumbs['itemListElement'][count($crumbs['itemListElement']) - 2]
            : null;
        $catalogBackUrl = is_array($catalogBackCrumb) ? ($catalogBackCrumb['item'] ?? route('index')) : route('index');
        $catalogBackLabel = is_array($catalogBackCrumb) ? ($catalogBackCrumb['name'] ?? 'prethodnu stranicu') : 'prethodnu stranicu';
    @endphp
    <div class="container pb-4 mb-2 mb-md-4 mt-4" id="filter-app">
        <div class="row catalog-loading-shell" aria-hidden="true" v-if="false">
            <aside class="catalog-filter-column">
                <div class="catalog-filter-panel catalog-filter-skeleton-panel">
                    <div class="catalog-filter-header">
                        <h2>
                            <i class="fa-duotone fa-list-tree d-none d-lg-inline-flex" aria-hidden="true"></i>
                            <span class="d-none d-lg-inline">Menu</span>
                        </h2>
                    </div>
                    <a class="catalog-filter-back" href="{{ $catalogBackUrl }}">
                        <i class="fa-regular fa-arrow-left" aria-hidden="true"></i>
                        <span>Natrag na {{ $catalogBackLabel }}</span>
                    </a>
                    <div class="catalog-filter-body catalog-filter-body--loading">
                        @for ($section = 0; $section < 7; $section++)
                            <div class="catalog-filter-skeleton-row">
                                <span class="catalog-skeleton catalog-skeleton--icon"></span>
                                <span class="catalog-skeleton catalog-skeleton--filter-label"></span>
                                <span class="catalog-skeleton catalog-skeleton--chevron"></span>
                            </div>
                        @endfor
                    </div>
                    <div class="catalog-filter-actions catalog-filter-actions--loading">
                        <span class="catalog-skeleton catalog-skeleton--button"></span>
                        <span class="catalog-skeleton catalog-skeleton--button catalog-skeleton--button-wide"></span>
                    </div>
                </div>
            </aside>

            <section class="catalog-products-section">
                <div class="catalog-toolbar">
                    <div class="catalog-toolbar__controls">
                        <span class="catalog-skeleton catalog-skeleton--sort"></span>
                    </div>
                    <span class="catalog-skeleton catalog-skeleton--total d-none d-lg-block"></span>
                </div>
                <div class="row catalog-products-grid catalog-products-skeleton-grid">
                    @for ($card = 0; $card < 8; $card++)
                        <div class="catalog-product-col d-flex align-items-stretch">
                            <div class="catalog-product-skeleton">
                                <span class="catalog-skeleton catalog-skeleton--image"></span>
                                <div class="catalog-product-skeleton__body">
                                    <span class="catalog-skeleton catalog-skeleton--eyebrow"></span>
                                    <span class="catalog-skeleton catalog-skeleton--line"></span>
                                    <span class="catalog-skeleton catalog-skeleton--line catalog-skeleton--line-short"></span>
                                    <span class="catalog-skeleton catalog-skeleton--price"></span>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </section>
        </div>

        <div class="row catalog-app-content" v-cloak>
            <filter-view ids="{{ isset($ids) ? $ids : null }}"
                         group="{{ isset($group) ? $group : null }}"
                         catalog-root="{{ request()->route('group') === \App\Helpers\Helper::categoryGroupPath(true) ? 'all' : '' }}"
                         cat="{{ isset($cat) ? $cat : null }}"
                         subcat="{{ isset($subcat) ? $subcat : null }}"
                         author="{{ isset($author) ? $author['slug'] : null }}"
                         publisher="{{ isset($publisher) ? $publisher['slug'] : null }}"
                         back-url="{{ $catalogBackUrl }}"
                         back-label="{{ $catalogBackLabel }}"
                         :filters-enabled="{{ $catalogFiltersEnabled ? 'true' : 'false' }}">
            </filter-view>
            <products-view ids="{{ isset($ids) ? $ids : null }}"
                           group="{{ isset($group) ? $group : null }}"
                           cat="{{ isset($cat) ? $cat['id'] : null }}"
                           subcat="{{ isset($subcat) ? $subcat['id'] : null }}"
                           author="{{ isset($author) ? $author['slug'] : null }}"
                           publisher="{{ isset($publisher) ? $publisher['slug'] : null }}"
                           :filters-enabled="{{ $catalogFiltersEnabled ? 'true' : 'false' }}">
            </products-view>
        </div>
    </div>

    @if (isset($products))
        <section class="container pb-4 mb-2 mb-md-4 mt-4" id="catalog-ssr" aria-label="Popis artikala">
            <div class="d-flex justify-content-end pb-3">
                <span class="fs-sm text-dark btn btn-white btn-sm text-nowrap">
                    Ukupno {{ number_format($products->total(), 0, ',', '.') }} artikala
                </span>
            </div>
            @if ($products->count())
                <div class="row mx-n2 mb-3">
                    @foreach ($products as $product)
                        <div class="col-lg-3 col-md-4 col-6 px-2 mb-4 d-flex align-items-stretch">
                            @include('front.catalog.category.product', ['product' => $product])
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-center">
                    {{ $products->onEachSide(1)->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <h2 class="h4">Nema rezultata</h2>
                    <p>Promijenite kriterije ili pokušajte s drugim pojmom.</p>
                </div>
            @endif
        </section>
        <noscript>
            <style>
                .catalog-loading-shell { display: none !important; }
                #catalog-ssr { display: block !important; }
            </style>
        </noscript>
    @endif

    @if (isset($author) && $author && ! empty($author->description))
        <div class="container pb-4 mb-2 mb-md-4" >
            {!! $author->description !!}
        </div>
    @endif

    @if (isset($publisher) && $publisher && ! empty($publisher->description))
        <div class="container pb-4 mb-2 mb-md-4">
            {!! $publisher->description !!}
        </div>
    @endif

    @if (! empty($meta['content']))
        <section class="container pb-4 mb-2 mb-md-4 catalog-editorial" aria-labelledby="catalog-editorial-title">
            <div class="bg-white rounded-3 shadow-sm p-4 p-md-5">
                <h2 class="h3 font-title mb-3" id="catalog-editorial-title">O kategoriji {{ $meta['title'] }}</h2>
                @foreach ($meta['content'] as $paragraph)
                    <p @if ($loop->last) class="mb-0" @endif>{{ $paragraph }}</p>
                @endforeach
            </div>
        </section>
    @elseif (isset($subcat) && $subcat && ! empty($subcat->description) && ($meta['show_category_description'] ?? true))
        <div class="container pb-4 mb-2 mb-md-4" >
            {!! $subcat->description !!}
        </div>
    @elseif (isset($cat) && $cat && ! empty($cat->description) && ($meta['show_category_description'] ?? true))
        <div class="container pb-4 mb-2 mb-md-4" >
            {!! $cat->description !!}
        </div>
    @endif

@endsection

@push('js_after')
    @if (isset($crumbs))
        <script type="application/ld+json">
            {!! collect($crumbs)->toJson() !!}
        </script>
    @endif
@endpush
