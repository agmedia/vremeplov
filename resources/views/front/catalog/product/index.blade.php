@extends('front.layouts.app')
@section ('title', $seo['title'])
@section ('description', $seo['description'])
@push('meta_tags')

    <link rel="canonical" href="{{ env('APP_URL')}}/{{ $prod->url }}" />
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="product" />
    <meta property="og:title" content="{{ $seo['title'] }}" />
    <meta property="og:description" content="{{ $seo['description']  }}" />
    <meta property="og:url" content="{{ env('APP_URL')}}/{{ $prod->url }}"  />
    <meta property="og:site_name" content="Antikvarijat Vremeplov" />
    <meta property="og:updated_time" content="{{ $prod->updated_at  }}" />
    <meta property="og:image" content="{{ asset($prod->image) }}" />
    <meta property="og:image:secure_url" content="{{ asset($prod->image) }}" />
    <meta property="og:image:width" content="640" />
    <meta property="og:image:height" content="480" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="{{ $prod->image_alt }}" />
    <meta property="product:price:amount" content="{{ number_format($prod->price, 2) }}" />
    <meta property="product:price:currency" content="EUR" />
    <meta property="product:availability" content="instock" />
    <meta property="product:retailer_item_id" content="{{ $prod->sku }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $seo['title'] }}" />
    <meta name="twitter:description" content="{{ $seo['description'] }}" />
    <meta name="twitter:image" content="{{ asset($prod->image) }}" />



@endpush

@if (isset($gdl))
    @section('google_data_layer')
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                'event': 'view_item',
                'ecommerce': {
                    'items': [<?php echo json_encode($gdl); ?>]
                } });
        </script>
    @endsection
@endif

@section('content')

    <!-- Page Title-->
    <div class="bg-light pt-2 pb-2"  style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
        <div class="container d-lg-block justify-content-end py-1">
            <div class="order-lg-2 mb-0 mt-0 mb-lg-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark flex-lg-nowrap justify-content-center ">
                        <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>

                        @if ($group)
                            @if ($group && ! $cat && ! $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li>
                            @elseif ($group && $cat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li>
                            @endif

                            @if ($cat && ! $subcat)
                                @if ($prod)
                                    <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                                @else
                                    <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $cat->title }}</li>
                                @endif
                            @elseif ($cat && $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                                @if ($prod)
                                    @if ($cat && ! $subcat)
                                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ \Illuminate\Support\Str::limit($prod->name, 50) }}</a></li>
                                    @else
                                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat]) }}">{{ $subcat->title }}</a></li>
                                    @endif
                                @endif
                            @endif
                        @endif

                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::limit($prod->name, 20) }}</li>
                    </ol>
                </nav>
            </div>

        </div>
    </div>

    <div class="container">
        <!-- Gallery + details-->
        <div class="bg-light  rounded-3 px-2 py-3 mt-3 mb-3">
            <div class="px-lg-3">
                <div class="row">
                    <!-- Product gallery-->
                    <div class="col-lg-5 pe-lg-0 pt-lg-3 pb-lg-3">
                        <div class="product-gallery">
                            <div class="product-gallery-preview order-sm-2">
                                @if ( ! empty($prod->image))
                                <div class="product-gallery-preview-item active" id="first"><img  src="{{ asset($prod->image) }}"  alt="{{ $prod->name }}"></div>


                                @endif

                                @if ($prod->images->count())
                                    @foreach ($prod->images as $key => $image)
                                        <div class="product-gallery-preview-item" id="key{{ $key + 1 }}"><img  src="{{ asset($image->image) }}" alt="{{ $image->alt }}"></div>
                                    @endforeach
                                @endif
                            </div>

                           <div class="product-gallery-thumblist order-sm-1" style="z-index: 10;position: relative;">
                                @if ($prod->images->count())
                                @if ( ! empty($prod->thumb))
                                    <a class="product-gallery-thumblist-item active" href="#first"><img src="{{ asset($prod->thumb) }}" alt="{{ $prod->name }}"></a>
                                @endif


                                    @foreach ($prod->images as $key => $image)
                                        <a class="product-gallery-thumblist-item" href="#key{{ $key + 1 }}"><img src="{{ url('cache/thumb?size=100x100&src=' . $image->thumb) }}" width="100" height="100" alt="{{ $image->alt }}"></a>
                                    @endforeach
                                @endif
                            </div>


                        </div>
                    </div>
                    <!-- Product details-->
                    <div class="col-lg-7 pt-4 pt-lg-0">
                        <div class="product-details ms-auto me-auto pb-3 ps-2 pe-2">
                            <div class="order-lg-1 pe-lg-4 text-center text-lg-start mt-3">
                                @if ( $prod->quantity < 1)
                                    <span class="badge bg-warning mb-2">Rasprodano</span>
                                @endif
                                <h1 class="h3 text-dark mb-0"> {{ $prod->name }}</h1>
                            </div>

                            <div class="mb-0 mt-4 text-center text-lg-start">
                                @if ($prod->main_price > $prod->main_special)
                                    <span class="h3 fw-normal text-primary me-1">{{ $prod->main_special_text }}</span>
                                    <span class="text-muted fs-sm me-3">*{{ $prod->main_price_text }}</span>

                                @else
                                    <span class="h3 fw-medium text-primary me-1">{{ $prod->main_price_text }}</span>
                                @endif

                            </div>

                            @if($prod->secondary_price_text)
                                <div class="mb-3 mt-1 text-center text-lg-start">
                                    @if ($prod->main_price > $prod->main_special)
                                        <span class=" fs-sm text-muted me-1"> {{ $prod->secondary_special_text }}</span>
                                        <span class="text-muted fs-sm me-3">*{{ $prod->secondary_price_text }}</span>
                                    @else
                                        <span class="fs-sm text-muted  me-1">{{ $prod->secondary_price_text }}</span>
                                    @endif
                                </div>
                            @endif
                            @if ($prod->main_price > $prod->main_special)

                                <div class="mb-3 mt-1 text-center text-lg-start">
                                    <span class=" fs-sm text-muted me-1"> *Najniža cijena u zadnjih 30 dana.</span>
                                </div>

                            @endif

                            @if ( $prod->quantity > 0)
                            <add-to-cart-btn id="{{ $prod->id }}" available="{{ $prod->quantity }}"></add-to-cart-btn>

                            @endif


                            <!-- Product panels-->
                           <ul class="list-unstyled fs-sm spec">
                                @if ($prod->author)
                                    <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Autor</span><span class="text-muted"><a class="product-meta text-primary" href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ $prod->author->title }}</a></span></li>
                                @endif
                                @if ($prod->publisher)
                                    <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Izdavač</span><a class="product-meta text-primary" href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ $prod->publisher->title }}</a></li>
                                @endif
                                <li class="d-flex justify-content-between mb-2 bg-gray-50 pb-2 border-bottom"><span class="text-dark fw-medium">Šifra</span><span class="text-muted">{{ $prod->sku }}</span></li>

                                    @if ($prod->condition)
                                    <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Stanje</span><span class="text-muted">{{ $prod->condition ?: '...' }}</span></li>
                                    @endif
                                    @if ($prod->quantity)
                                        @if ($prod->decrease or $prod->quantity > 0)
                                            <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Dostupnost</span><span class="text-muted">Na stanju</span></li>
                                        @else
                                            <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Dostupnost</span><span class="text-muted">Po Narudžbi. 7 - 14 dana.</span></li>
                                        @endif
                                    @else
                                        <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Dostupnost</span><span class="text-muted">Rasprodano</span></li>

                                    @endif


                                @if ($prod->pages)
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Broj stranica</span><span class="text-muted">{{ $prod->pages ?: '...' }}</span></li>
                                @endif
                                @if ($prod->year)
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Godina izdanja</span><span class="text-muted">{{ $prod->year ?: '...' }}</span></li>
                                @endif
                                @if ($prod->dimensions)
                                    <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Dimenzije</span><span class="text-muted">{{ $prod->dimensions.' cm' ?: '...' }}</span></li>
                                @endif
                                @if ($prod->origin)
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Mjesto izdavanja</span><span class="text-muted">{{ $prod->origin ?: '...' }}</span></li>
                                @endif
                                @if ($prod->letter)
                                    <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Pismo</span><span class="text-muted">{{ $prod->letter ?: '...' }}</span></li>
                                 @endif
                                @if ($prod->binding)
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Uvez</span><span class="text-muted">{{ $prod->binding ?: '...' }}</span></li>
                                @endif
                            </ul>

                            <div class="row align-items-center pt-1">
                                <div class="col-lg-12 fs-md">

                                   {!! $prod->description !!}

                                </div>
                            </div>

                            <div class=" pt-0 pb-4 mb-1">
                                <div class="mt-2">
                                    <!-- ShareThis BEGIN --><div class="sharethis-inline-share-buttons"></div><!-- ShareThis END -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



            </div>
        </div>

    </div>



    <!-- Product carousel (You may also like)-->
    <div class="container-fluid py-5 bg-white bg-size-cover bg-position-center" style="background-image: url({{ config('settings.images_domain') . 'media/img/zuzi-bck-transparent.svg' }});">
        <div class="container my-md-3" >
            <h2 class="h3 text-center pb-4">Izdvojeno iz kategorije</h2>
            <div class="tns-carousel tns-controls-static tns-controls-outside p-0 ps-sm-1 pe-sm-1">
                <div class="tns-carousel-inner mb-3" data-carousel-options='{"items": 2, "controls": true, "nav": true, "autoHeight": false, "responsive": {"0":{"items":2, "gutter": 10},"500":{"items":2, "gutter": 18},"768":{"items":3, "gutter": 20}, "1100":{"items":5, "gutter": 30}}}'>
                    @foreach ($related as $cat_product)
                        @if ($cat_product->id  != $prod->id)
                            <div>
                                @include('front.catalog.category.product', ['product' => $cat_product])
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script type="application/ld+json">
        {!! collect($crumbs)->toJson() !!}
    </script>
    <script type="application/ld+json">
        {!! collect($bookscheme)->toJson() !!}
    </script>
    <script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=6134a372eae16400120a5035&product=sop' async='async'></script>
@endpush
