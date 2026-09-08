@extends('front.layouts.app')

@if (isset($meta) && ! empty($meta))
    @php
        $canonicalUrl = $meta['canonical'];
        $pageNumber = max(1, (int) request()->input('page', 1));
        $hasFacetParameters = request()->hasAny(['start', 'end', 'autor', 'nakladnik', 'sort']);

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
    @php($searchTerm = trim((string) request()->input('pojam')))
    @if ($searchTerm !== '')
        @section('google_data_layer')
            <script>window.VremeplovAnalytics.track('search', {search_term: @json($searchTerm)});</script>
        @endsection
    @endif
@endif

@section('content')

    <!-- Page Title-->
    <div class="bg-light pt-4 pb-3"  style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
        <div class="container d-lg-flex justify-content-between py-2 py-lg-3">

            @if (isset($crumbs) && ! empty($crumbs))
                <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-dark flex-lg-nowrap justify-content-center">
                            @foreach ($crumbs['itemListElement'] as $crumb)
                                @if ($loop->last)
                                    <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $crumb['name'] }}</li>
                                @else
                                    <li class="breadcrumb-item"><a class="text-nowrap" href="{{ $crumb['item'] }}"><i class="ci-home"></i>{{ $crumb['name'] }}</a></li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                </div>
            @endif

            @if (isset($meta) && ! empty($meta))
                <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                    <h1 class="h3 text-dark mb-0">{{ $meta['title'] }}</h1>
                    @if (! empty($meta['description']))
                        <p class="text-dark opacity-75 mb-0 mt-2">{{ $meta['description'] }}</p>
                    @endif
                </div>
            @endif

        </div>
    </div>



    <div class="container pb-4 mb-2 mb-md-4 mt-4" id="filter-app" v-cloak>
        <div class="row">
            <filter-view ids="{{ isset($ids) ? $ids : null }}"
                         group="{{ isset($group) ? $group : null }}"
                         cat="{{ isset($cat) ? $cat : null }}"
                         subcat="{{ isset($subcat) ? $subcat : null }}"
                         author="{{ isset($author) ? $author['slug'] : null }}"
                         publisher="{{ isset($publisher) ? $publisher['slug'] : null }}">
            </filter-view>
            <products-view ids="{{ isset($ids) ? $ids : null }}"
                           group="{{ isset($group) ? $group : null }}"
                           cat="{{ isset($cat) ? $cat['id'] : null }}"
                           subcat="{{ isset($subcat) ? $subcat['id'] : null }}"
                           author="{{ isset($author) ? $author['slug'] : null }}"
                           publisher="{{ isset($publisher) ? $publisher['slug'] : null }}">
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

    @if (isset($subcat) && $subcat && ! empty($subcat->description))
        <div class="container pb-4 mb-2 mb-md-4" >
            {!! $subcat->description !!}
        </div>
    @elseif (isset($cat) && $cat && ! empty($cat->description))
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
