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
    <meta property="og:site_name" content="Antikvarijat Biblos" />
    <meta property="og:updated_time" content="{{ $prod->updated_at  }}" />
    <meta property="og:image" content="{{ asset($prod->image) }}" />
    <meta property="og:image:secure_url" content="{{ asset($prod->image) }}" />
    <meta property="og:image:width" content="640" />
    <meta property="og:image:height" content="480" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="{{ $prod->image_alt }}" />
    <meta property="product:price:amount" content="{{ $prod->main_price }}" />
    <meta property="product:price:currency" content="EUR" />
    <meta property="product:availability" content="instock" />
    <meta property="product:retailer_item_id" content="{{ $prod->sku }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $seo['title'] }}" />
    <meta name="twitter:description" content="{{ $seo['description'] }}" />
    <meta name="twitter:image" content="{{ asset($prod->image) }}" />
    <link rel="stylesheet" media="screen" href="{{ asset('vendor/lightgallery/css/lightgallery-bundle.min.css')}}"/>

@endpush

@push('css_after')
    <style>
        .product-view section {
            margin-bottom: 1.5rem;
        }

        .product-view .h-100.bg-light.shadow.rounded-3 {
            border: 1px solid #e7e2d8;
        }

        .product-view .h3,
        .product-view .h5 {
            line-height: 1.25;
        }

        .product-view .text-accent {
            color: #2d2821 !important;
        }

        .product-view .badge.bg-warning {
            background-color: #cfa55a !important;
            color: #fff;
        }

        .product-view .badge.bg-secondary {
            background-color: #7a7467 !important;
            color: #fff;
        }

        .product-view .accordion-button {
            color: #3f3a33;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .product-view .accordion-button:not(.collapsed) {
            color: #2d2821;
            background-color: #f4f2ed;
        }

        .product-view .alert.alert-secondary {
            margin-top: 0.75rem;
            margin-bottom: 1.25rem;
            background-color: #f0f1f4;
            border-color: #e2e4ea;
            color: #3f3a33;
        }
    </style>
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



   <div class="container product-view">
       <!-- Page title + breadcrumb-->
       <nav class="my-3" aria-label="breadcrumb">
           <ol class="breadcrumb flex-lg-nowrap">
               <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
               @if ($group)
                   @if ($group && ! $cat && ! $subcat)
                       <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::ucfirst($group) }}</li>
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

           </ol>
       </nav>
       <!-- Content-->
       <section class="row g-0 mx-n2 ">
           @include('back.layouts.partials.session')
           <!-- Product Gallery + description-->
           <div class="col-xl-6 px-2 mb-3">


               <div class="h-100 bg-light shadow rounded-3 p-4">
                   <div class="" id="gallery" style="max-height:750px">
                       <div class="main-image product-thumb">

                           <div class="galerija slider slider-for  mb-3">

                               @if ( ! empty($prod->image))


                                   <div class="item single-product" >
                                       <a class="link" href="{{  ($prod->image) }}">
                                           <img src="{{  ($prod->image) }}" alt="{{ $prod->name }}" height="600" style="max-height:600px">
                                       </a>
                                   </div>


                               @endif

                               @if ($prod->images->count())
                                   @foreach ($prod->images as $key => $image)
                                       <div class="item single-product" >
                                           <a class="link" href="{{  config('settings.images_domain') .($image->image) }}">
                                               <img src="{{  config('settings.images_domain') .($image->image) }}" alt="{{ $image->alt }}" height="600" style="max-height:600px">
                                           </a>
                                       </div>

                                   @endforeach
                               @endif
                           </div>

                           <ul class=" slider slider-nav mt-2 mb-2">
                               @if ($prod->images->count())
                                   @if ( ! empty($prod->thumb))

                                       <li><img src="{{  ($prod->thumb) }}" class="thumb" width="100" height="100" alt="{{ $prod->name }}"></li>


                                   @endif
                               @foreach ($prod->images as $key => $image)
                                   <li><img src="{{  config('settings.images_domain') .($image->thumb) }}" class="thumb" width="100" height="100" alt="{{ $image->alt }}"></li>
                               @endforeach

                               @endif
                           </ul>
                       </div>
                   </div>
               </div>
           </div>
           <div class="col-xl-6 px-2 mb-3">
               <div class="h-100 bg-light shadow  rounded-3 py-5 px-4 px-sm-5">

                   @if ( $prod->quantity < 1)
                       <span class="badge bg-warning ">Rasprodano</span>
                   @endif

                   @if ($prod->main_price > $prod->main_special)
                       <span class="badge bg-primary ">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($prod->price, $prod->special())), 0) }}%</span>
                   @endif



                   <h1 class="h3"><span style="font-weight: 300;">{{ $prod->author ? $prod->author->title.':' : '' }}</span> {{ $prod->name }}</h1>

                       <div class="mb-0 mt-4">
                           @if ($prod->main_price > $prod->main_special)
                               <span class="h3 fw-normal text-accent me-1">{{ $prod->main_special_text }}</span>
                               <del class="text-muted fs-lg me-3">{{ $prod->main_price_text }}</del>
                               <span class="badge bg-secondary align-middle mt-n2">Akcija</span>
                           @else
                               <span class="h3 fw-normal text-accent me-1">{{ $prod->main_price_text }}</span>
                           @endif
                          {{--  @if ($prod->quantity)
                               <span class="badge bg-success align-middle mt-n2">Dostupno</span>
                           @else
                               <span class="badge bg-secondary align-middle mt-n2">Nije dostupno</span>
                           @endif--}}
                       </div>

                       @if($prod->secondary_price_text)
                           <div class="mb-3 mt-1">
                               @if ($prod->main_price > $prod->main_special)
                                   <span class="h3 fw-normal text-accent me-1">{{ $prod->secondary_special_text }}</span>
                                   <del class="text-muted fs-lg me-3">{{ $prod->secondary_price_text }}</del>
                               @else
                                   <span class="h3 fw-normal text-accent me-1">{{ $prod->secondary_price_text }}</span>
                               @endif
                           </div>
                       @endif

                @if ($prod->quantity > 0)
                    <add-to-cart-btn id="{{ $prod->id }}" available="{{ $prod->quantity }}"></add-to-cart-btn>
                @else
                    <a class="btn btn-primary btn-shadow d-block w-100 mt-2" href="#wishlist-modal" data-bs-toggle="modal">
                        <i class="ci-bell"></i> Obavijesti me o dostupnosti
                    </a>
                @endif

                       <!-- Light alert -->
                       <div class="alert alert-secondary d-flex fs-sm" role="alert">
                           <div class="alert-icon">
                               <i class="ci-gift"></i>
                           </div>
                           <div> Besplatna dostava u RH za narudžbe veće od 70 €</div>
                       </div>

                   <!-- Product panels-->
                   <div class="accordion mb-4" id="productPanels">
                       <div class="accordion-item">
                           <h3 class="accordion-header"><a class="accordion-button" href="#productInfo" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="productInfo"><i class="ci-announcement text-muted fs-lg align-middle mt-n1 me-2"></i>Osnovne informacije</a></h3>
                           <div class="accordion-collapse collapse show" id="productInfo" data-bs-parent="#productPanels">
                               <div class="accordion-body">

                                   <ul class="fs-sm ps-4 mb-0 info-list">
                                       @if ($prod->author)
                                           <li><strong>Autor:</strong> <a href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ $prod->author->title }} </a></li>
                                       @endif
                                       @if ($prod->publisher)
                                           <li><strong>Nakladnik:</strong> <a href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ $prod->publisher->title }}</a> </li>
                                       @endif
                                       @if ($prod->isbn)
                                           <li><strong>EAN:</strong> {{ $prod->isbn }} </li>
                                       @endif
                                       @if ($prod->quantity)
                                           @if ($prod->decrease)
                                               <li><strong>Dostupnost:</strong> {{ $prod->quantity }} </li>
                                           @else
                                               <li><strong>Dostupnost:</strong> <span class="badge bg-success align-middle ">Dostupno</span></li>
                                           @endif
                                       @else
                                           <li><strong>Dostupnost:</strong> <span class="badge bg-secondary align-middle ">Rasprodano</span></li>
                                       @endif
                                           @if ($prod->condition)
                                       <li><strong>Stanje:</strong> {{ $prod->condition }} </li>
                                           @endif
                                           @if ($prod->sku)
                                               <li><strong>Šifra:</strong> {{ $prod->sku }} </li>
                                           @endif
                                   </ul>

                               </div>
                           </div>
                       </div>
                       <div class="accordion-item">
                           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#shippingOptions" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="shippingOptions"><i class="ci-delivery text-muted lead align-middle mt-n1 me-2"></i>Opcije dostave</a></h3>
                           <div class="accordion-collapse collapse" id="shippingOptions" data-bs-parent="#productPanels">
                               <div class="accordion-body fs-sm">

                                   @foreach($shipping_methods as $shipping_method)
                                       <div class="d-flex justify-content-between  py-2">
                                           <div>
                                               <div class="fw-semibold text-dark">{{ $shipping_method->title }}</div>
                                               {{--  <div class="fs-sm text-muted"> Besplatna dostava za narudžbe iznad {{ config('settings.free_shipping') }}€</div>--}}
                                               @if ($prod->shipping_time)

                                                   <span class=" fs-sm text-muted me-1"> Rok dostave: {{ $prod->shipping_time }}</span>

                                               @endif
                                           </div>
                                           <div>{{ $shipping_method->data->price }}€ </div>
                                       </div>
                                   @endforeach

                               </div>
                               <small class="mt-2"></small>
                           </div>
                       </div>
                       <div class="accordion-item">
                           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#localStore" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="localStore"><i class="ci-card text-muted fs-lg align-middle mt-n1 me-2"></i>Načini plaćanja</a></h3>
                           <div class="accordion-collapse collapse" id="localStore" data-bs-parent="#productPanels">
                               <div class="accordion-body fs-sm">


                                   @foreach($payment_methods as $payment_method)
                                       @if($prod->origin == 'Engleski' and $payment_method->code == 'cod' )

                                       @else
                                           <div class="d-flex justify-content-between  py-2">
                                               <div>
                                                   <div class="fw-semibold text-dark">{{ $payment_method->title }}</div>
                                                   @if (isset($payment_method->data->description))
                                                       <div class="fs-sm text-muted">{{ $payment_method->data->description }}</div>
                                                   @endif
                                               </div>
                                           </div>
                                       @endif
                                   @endforeach

                               </div>


                           </div>
                       </div>
                   </div>
                   <!-- Sharing-->
                   <!-- ShareThis BEGIN --><div class="sharethis-inline-share-buttons"></div><!-- ShareThis END -->
               </div>
           </div>
       </section>
       <!-- Related products-->

       <section class="mx-n2 pb-2 px-2 mb-xl-3" id="tabs_widget">
           <div class="bg-light px-2 mb-3 shadow rounded-3">
               <!-- Tabs-->
               <ul class="nav nav-tabs" role="tablist">
                   <li class="nav-item"><a class="nav-link py-4 px-sm-4 active" href="#specs" data-bs-toggle="tab" role="tab"><span>Opis</span> </a></li>

               </ul>
               <div class="px-4 pt-lg-3 pb-3 mb-5">
                   <div class="tab-content px-lg-3">
                       <!-- Tech specs tab-->
                       <div class="tab-pane fade show active" id="specs" role="tabpanel">
                           <!-- Specs table-->
                           <div class="row pt-2">
                               <div class="col-lg-7 col-sm-7 d-flex flex-column">

                                   {{-- Naziv i autor --}}
                                   <h2 class="h5 mb-2 pb-0">{{ $prod->name }}</h2>
                                   @if ($prod->author)
                                       <h3 class="h6 mb-4">{{ $prod->author->title }}</h3>
                                   @endif

                                   {{-- Sažetak i opis --}}
                                   <p class="h6">Sažetak</p>
                                   <div class="fs-md pb-2 mb-4">
                                       {!! $prod->description !!}
                                   </div>

                                   {{-- Autor i tagovi na dnu --}}
                                   @if ($prod->author || !empty($prod->tags))
                                       <div class="mt-auto pt-3 pb-4">
                                           @if ($prod->author)
                                               <a class="btn btn-outline-primary btn-sm btn-shadow me-2 mb-2"
                                                  href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">
                                                   #{{ $prod->author->title }}
                                               </a>
                                           @endif

                                           @if(!empty($prod->tags))
                                               @foreach($prod->tags as $tag)
                                                   <a class="btn btn-outline-primary btn-sm btn-shadow me-2 mb-2"
                                                      href="{{ route('tag', ['pojam' => $tag]) }}">
                                                       #{{ $tag }}
                                                   </a>
                                               @endforeach
                                           @endif
                                       </div>
                                   @endif
                               </div>

                               <div class="col-lg-5 col-sm-5">
                                   <h3 class="h6">Dodatne informacije</h3>
                                   <ul class="list-unstyled fs-md pb-2">

                                       @if ($prod->author)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Autor:</span>
                                               <span>
                        <a href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">
                            {{ Illuminate\Support\Str::limit($prod->author->title, 30) }}
                        </a>
                    </span>
                                           </li>
                                       @endif

                                       @if ($prod->publisher)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Izdavač:</span>
                                               <span>
                        <a href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">
                            {{ Illuminate\Support\Str::limit($prod->publisher->title, 30) }}
                        </a>
                    </span>
                                           </li>
                                       @endif

                                       {{--@if ($prod->origin)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Jezik:</span><span>{{ $prod->origin }}</span>
                                           </li>
                                       @endif--}}

                                       @if ($prod->year)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Godina izdanja:</span><span>{{ $prod->year }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->origin)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Mjesto izdavanja:</span><span>{{ $prod->origin }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->pages)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Broj stranica:</span><span>{{ $prod->pages }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->dimensions)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Dimenzije:</span><span>{{ $prod->dimensions.' cm' }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->letter)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Pismo:</span><span>{{ $prod->letter }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->condition)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Stanje:</span><span>{{ $prod->condition }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->binding)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Uvez:</span><span>{{ $prod->binding }}</span>
                                           </li>
                                       @endif

                                   </ul>
                               </div>
                           </div>

                       </div>
                       <!-- Reviews tab-->

                   </div>
               </div>
           </div>
       </section>
       <!-- Product description-->
       <section class="pb-5 mb-2 mb-xl-4">
           <div class=" flex-wrap justify-content-between align-items-center  text-center">
               <h2 class="h3 mb-4 pt-1 font-title me-3 text-center"> Možda vas zanima</h2>

           </div>
           <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2">
               <div class="tns-carousel-inner tns-nav-enabled" data-carousel-options='{"items": 2, "controls": false, "nav": true, "responsive": {"0":{"items":2, "gutter": 5},"500":{"items":2, "gutter": 10},"768":{"items":3, "gutter": 10}, "1100":{"items":4, "controls": true, "gutter": 10}, "1300":{"items":5, "controls": true, "gutter": 10}, "1600":{"items":5, "controls": true, "gutter": 10}}}'>
                   @foreach ($cat->products()->where('quantity', '>', 0)->take(15)->get()->unique() as $cat_product)
                       @if ($cat_product->id  != $prod->id)
                           <div>
                               @include('front.catalog.category.product', ['product' => $cat_product])
                           </div>
                       @endif
                   @endforeach
               </div>
           </div>
       </section>
       @if(isset($recentProducts) && $recentProducts->isNotEmpty())

       <section class="pb-5 mb-2 mb-xl-4">
           <div class=" flex-wrap justify-content-between align-items-center  text-center">
               <h2 class="h3 mb-4 pt-1 font-title me-3 text-center"> Nedavno pregledano</h2>

           </div>
           <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2">
               <div class="tns-carousel-inner tns-nav-enabled" data-carousel-options='{"items": 2, "controls": false, "nav": true, "responsive": {"0":{"items":2, "gutter": 5},"500":{"items":2, "gutter": 10},"768":{"items":3, "gutter": 10}, "1100":{"items":4, "controls": true, "gutter": 10}, "1300":{"items":5, "controls": true, "gutter": 10}, "1600":{"items":5, "controls": true, "gutter": 10}}}'>
                   @foreach ($recentProducts as $recent)
                       @if ($recent->id != $prod->id)
                           <div>
                               @include('front.catalog.category.product', ['product' => $recent])
                           </div>
                       @endif
                   @endforeach
               </div>
           </div>
       </section>
       @endif
   </div>

@endsection

@push('js_after')

    <link rel="stylesheet" media="screen" href="{{ asset('js/slick/slick.css') }}">
    <link rel="stylesheet" media="screen" href="{{ asset('js/slick/slick-theme.css') }}">
    <script src="{{ asset('js/slick/slick.min.js') }}"></script>
    <link rel="stylesheet" media="screen" href="{{ asset('js/simple-lightbox.css?v2.14.0') }}">
    <script src="{{ asset('js/simple-lightbox.js?v2.14.0') }}"></script>


    <script type="application/ld+json">
        {!! collect($crumbs)->toJson() !!}
    </script>
    <script type="application/ld+json">
        {!! collect($bookscheme)->toJson() !!}
    </script>
    <script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=6134a372eae16400120a5035&product=sop' async='async'></script>

    <script>
        (function () {
            var $gallery = new SimpleLightbox('.galerija a', {});
        })();
    </script>
    @php($i = 0)
    @if ($prod->images->count())
        @foreach ($prod->images as $key => $image)
            @if($image->default == '1')
                @php($i = $key)
            @endif
        @endforeach
    @endif



    <script>
        var $carousel = $('.slider-for').slick({
            slidesToShow:   1,
            slidesToScroll: 1,
            initialSlide: {{ $i }},
            arrows:         false,
            fade:           true,
            asNavFor:       '.slider-nav'
        });
        var $thumbs   = $('.slider-nav').slick({
            slidesToShow:   5,
            slidesToScroll: 1,
            asNavFor:       '.slider-for',
            dots:           false,
            centerMode:     false,
            focusOnSelect:  true,
            loop:           true,

        });


        $(".form-check").click(function () {
            var artworkId = $(this).data('target');

            console.log(artworkId);
            var artIndex = $carousel.find('[data-target="' + artworkId + '"]').data('slick-index');

            console.log(artIndex);

            $carousel.slick('slickGoTo', artIndex);
        });

    </script>

    <script>
        (function () {
            var $gallery = new SimpleLightbox('a.gal', {});
        })();
    </script>

    @include('front.layouts.modals.wishlist-email')
@endpush
