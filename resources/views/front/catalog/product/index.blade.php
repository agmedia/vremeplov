@extends('front.layouts.app')
@php
    $productSeoTitle = $prod->card_name;
    $productPageTitle = mb_strlen($productSeoTitle) <= 42
        ? $productSeoTitle . ' - Antikvarijat Vremeplov'
        : $productSeoTitle;
    $productLanguageCount = count(\App\Support\CatalogFilterValue::facetValues('origin', $prod->origin));
    $productEyebrow = $subcat ? $subcat->title : ($cat ? $cat->title : \Illuminate\Support\Str::ucfirst($group));
    $productEyebrowUrl = $subcat
        ? route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat])
        : ($cat
            ? route('catalog.route', ['group' => $group, 'cat' => $cat])
            : route('catalog.route', ['group' => $group]));
    $productReviewCount = $reviews->count();
    $productReviewAverage = $productReviewCount ? round((float) $reviews->avg('stars'), 1) : 0;
    $productFilledStars = (int) round($productReviewAverage);
    $productDescriptionHtml = (string) $prod->description;
    $productDescriptionAnalysisHtml = preg_replace(
        '/<(?:br\s*\/?>|\/p|\/div|\/li|\/h[1-6]|\/tr)>/i',
        "\n",
        $productDescriptionHtml
    ) ?: $productDescriptionHtml;
    $productDescriptionText = trim(str_replace(
        "\xC2\xA0",
        ' ',
        html_entity_decode(strip_tags($productDescriptionAnalysisHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')
    ));
    $productDescriptionSignal = $productDescriptionText;
    foreach (array_filter([
        trim((string) $prod->name),
        trim((string) $prod->card_name),
        $prod->author ? trim((string) $prod->author->title) : '',
    ]) as $identityText) {
        $productDescriptionSignal = preg_replace(
            '/' . preg_quote($identityText, '/') . '/iu',
            ' ',
            $productDescriptionSignal
        ) ?: $productDescriptionSignal;
    }
    $productDescriptionSignal = preg_replace(
        '/\b(?:broj|šifra|sifra|sku|ean|isbn)\s*:?\s*[\p{L}\p{N}.\/_-]+\b/iu',
        ' ',
        $productDescriptionSignal
    ) ?: $productDescriptionSignal;
    $productDescriptionSignal = preg_replace(
        '/\b(?:autor|nakladnik|izdavač|izdavac|godina(?:\s+izdanja)?|jezik|pismo|broj\s+stranica|stranica|uvez|stanje|dimenzije|mjesto\s+izdavanja)\s*:\s*[^\n.;|]{1,100}/iu',
        ' ',
        $productDescriptionSignal
    ) ?: $productDescriptionSignal;
    $productDescriptionSignal = trim(preg_replace('/\s+/u', ' ', $productDescriptionSignal) ?: $productDescriptionSignal);
    $productDescriptionWordCount = preg_match_all('/\p{L}[\p{L}\p{M}’\'-]*/u', $productDescriptionSignal) ?: 0;
    $productHasDescription = $productDescriptionWordCount >= 2;
    $displayCatalogLabel = static function (?string $label): string {
        $label = trim((string) $label);

        if (in_array(mb_strtoupper($label, 'UTF-8'), ['VBZ', 'HRT', 'HNK', 'HAZU', 'JAZU'], true)) {
            return mb_strtoupper($label, 'UTF-8');
        }

        return $label !== '' && mb_strtoupper($label, 'UTF-8') === $label
            ? mb_convert_case(mb_strtolower($label, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')
            : $label;
    };
    $productAuthorLabel = $prod->author ? $displayCatalogLabel($prod->author->title) : '';
    $productPublisherLabel = $prod->publisher ? $displayCatalogLabel($prod->publisher->title) : '';
    $productNote = trim((string) $prod->note);
    if ($productNote !== '' && preg_match('/\p{L}/u', $productNote) && mb_strtoupper($productNote, 'UTF-8') === $productNote) {
        $productNoteLower = mb_strtolower($productNote, 'UTF-8');
        $productNote = mb_strtoupper(mb_substr($productNoteLower, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($productNoteLower, 1, null, 'UTF-8');
        $productNote = preg_replace_callback(
            '/\b(?:isbn|issn|ean|dvd|cd|vhs|lp|ep|hnk|hrt|hazu|jazu|eu|rh|bih|sad|sfrj|sssr|nob|nato|unesco|pdf|tv|pc|ai)\b/iu',
            static fn (array $match): string => mb_strtolower($match[0], 'UTF-8') === 'bih'
                ? 'BiH'
                : mb_strtoupper($match[0], 'UTF-8'),
            $productNote
        ) ?: $productNote;
    }
@endphp
@section ('title', $productPageTitle)
@section ('description', $seo['description'])
@section ('canonical', url($prod->url))
@push('meta_tags')

    @php
        $productImagePath = strtolower((string) parse_url($prod->image, PHP_URL_PATH));
        $productImageType = str_ends_with($productImagePath, '.webp') ? 'image/webp' : (str_ends_with($productImagePath, '.png') ? 'image/png' : 'image/jpeg');
    @endphp

    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="product" />
    <meta property="og:title" content="{{ $productSeoTitle }}" />
    <meta property="og:description" content="{{ $seo['description']  }}" />
    <meta property="og:url" content="{{ url($prod->url) }}"  />
    <meta property="og:site_name" content="Antikvarijat Vremeplov" />
    <meta property="og:updated_time" content="{{ $prod->updated_at  }}" />
    <meta property="og:image" content="{{ $prod->image }}" />
    <meta property="og:image:secure_url" content="{{ $prod->image }}" />
    <meta property="og:image:width" content="640" />
    <meta property="og:image:height" content="480" />
    <meta property="og:image:type" content="{{ $productImageType }}" />
    <meta property="og:image:alt" content="{{ $prod->image_alt ?: $prod->card_name }}" />
    <meta property="product:price:amount" content="{{ $prod->main_price }}" />
    <meta property="product:price:currency" content="EUR" />
    <meta property="product:availability" content="{{ $prod->quantity > 0 ? 'instock' : 'out of stock' }}" />
    <meta property="product:retailer_item_id" content="{{ $prod->sku }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $productSeoTitle }}" />
    <meta name="twitter:description" content="{{ $seo['description'] }}" />
    <meta name="twitter:image" content="{{ $prod->image }}" />
    <link rel="stylesheet" media="screen" href="{{ asset('vendor/lightgallery/css/lightgallery-bundle.min.css')}}"/>

@endpush

@push('css_after')
    <link rel="stylesheet" media="screen" href="{{ asset('js/slick/slick.css') }}">
    <link rel="stylesheet" media="screen" href="{{ asset('js/slick/slick-theme.css') }}">
    <link rel="stylesheet" media="screen" href="{{ asset('css/front-product-detail.css?v=1.0.11') }}">
@endpush

@if (session('analytics_event') === 'add_to_wishlist' && isset($gdl))
    @push('js_after')
        <script>
            window.VremeplovAnalytics.track('add_to_wishlist', {
                ecommerce: {items: [@json($gdl)]}
            });
        </script>
    @endpush
@endif

@if (isset($gdl))
    @section('google_data_layer')
        <script>
            window.VremeplovAnalytics.track('view_item', {
                ecommerce: {
                    'items': [<?php echo json_encode($gdl); ?>]
                }
            });
        </script>
    @endsection
@endif

@section('content')



   <div class="container product-view">
       <!-- Page title + breadcrumb-->
       <nav class="product-view__breadcrumbs" aria-label="breadcrumb">
           <ol class="breadcrumb">
               <li class="breadcrumb-item"><a href="{{ route('index') }}"><i class="fa-regular fa-house" aria-hidden="true"></i>Naslovnica</a></li>
               @if ($group)
                   @if ($group && ! $cat && ! $subcat)
                       <li class="breadcrumb-item active" aria-current="page">{{ \Illuminate\Support\Str::ucfirst($group) }}</li>
                   @elseif ($group && $cat)
                       <li class="breadcrumb-item active" aria-current="page"><a href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li>
                   @endif

                   @if ($cat && ! $subcat)
                       @if ($prod)
                           <li class="breadcrumb-item active" aria-current="page"><a href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                       @else
                           <li class="breadcrumb-item active" aria-current="page">{{ $cat->title }}</li>
                       @endif
                   @elseif ($cat && $subcat)
                       <li class="breadcrumb-item active" aria-current="page"><a href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                       @if ($prod)
                           @if ($cat && ! $subcat)
                               <li class="breadcrumb-item active" aria-current="page"><a href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ \Illuminate\Support\Str::limit($prod->card_name, 50) }}</a></li>
                           @else
                               <li class="breadcrumb-item active" aria-current="page"><a href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat]) }}">{{ $subcat->title }}</a></li>
                           @endif
                       @endif
                   @endif
               @endif

           </ol>
       </nav>
       <!-- Content-->
       <section class="row product-view__hero">
           @include('back.layouts.partials.session')
           <!-- Product Gallery + description-->
           <div class="col-xl-6 product-view__hero-column">
               <div class="product-view__panel product-view__gallery-panel">
                   <div class="product-gallery" id="gallery">
                       <div class="main-image product-thumb product-gallery__inner">
                           <div class="galerija slider slider-for product-gallery__stage">

                               @if ( ! empty($prod->image))
                                   <div class="item single-product product-gallery__slide">
                                       <a class="link product-gallery__link" href="{{  ($prod->image) }}">
                                           <img class="product-gallery__image" src="{{  ($prod->image) }}" alt="{{ $prod->image_alt ?: $prod->name }}" fetchpriority="high">
                                       </a>
                                   </div>
                               @endif

                               @if ($prod->images->count())
                                   @foreach ($prod->images as $key => $image)
                                       <div class="item single-product product-gallery__slide">
                                           <a class="link product-gallery__link" href="{{  config('settings.images_domain') .($image->image) }}">
                                               <img class="product-gallery__image" src="{{  config('settings.images_domain') .($image->image) }}" alt="{{ $image->alt ?: $prod->name }}">
                                           </a>
                                       </div>
                                   @endforeach
                               @endif
                           </div>

                           <ul class="slider slider-nav product-gallery__thumbnails" aria-label="Ostale fotografije artikla">
                               @if ($prod->images->count())
                                   @if ( ! empty($prod->thumb))
                                       <li><img src="{{  ($prod->thumb) }}" class="thumb" width="100" height="100" alt="{{ $prod->name }}"></li>
                                   @endif
                                   @foreach ($prod->images as $key => $image)
                                       <li><img src="{{  config('settings.images_domain') .($image->thumb) }}" class="thumb" width="100" height="100" alt="{{ $image->alt ?: $prod->name }}"></li>
                                   @endforeach
                               @endif
                           </ul>
                       </div>
                   </div>
               </div>
           </div>
           <div class="col-xl-6 product-view__hero-column">
               <div class="product-view__panel product-view__purchase-panel">
                   <a class="product-view__eyebrow" href="{{ $productEyebrowUrl }}">
                       <i class="fa-regular fa-book-open" aria-hidden="true"></i>{{ $productEyebrow }}
                   </a>

                   <div class="product-view__badges" aria-label="Status artikla">
                       @if ( $prod->quantity < 1)
                           <span class="badge bg-warning">Rasprodano</span>
                       @endif

                       @if ($prod->main_price > $prod->main_special)
                           <span class="badge bg-primary">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($prod->price, $prod->special())), 0) }}%</span>
                       @endif
                   </div>

                   <h1 class="product-view__title">{{ $prod->card_name }}</h1>

                   @if ($productReviewCount > 0)
                       <a class="product-view__rating" href="#reviews" aria-label="Ocjena {{ number_format($productReviewAverage, 1, ',', '.') }} od 5 na temelju {{ $productReviewCount }} recenzija">
                           <span aria-hidden="true">
                               @for ($star = 1; $star <= 5; $star++)
                                   <i class="fa-{{ $star <= $productFilledStars ? 'solid' : 'regular' }} fa-star"></i>
                               @endfor
                           </span>
                           <small>{{ number_format($productReviewAverage, 1, ',', '.') }} · {{ $productReviewCount }} {{ $productReviewCount === 1 ? 'recenzija' : 'recenzije' }}</small>
                       </a>
                   @endif

                   <div class="product-view__commerce">
                       <div class="product-view__price-block">
                           @if ($prod->main_price > $prod->main_special)
                               <span class="product-view__price">{{ $prod->main_special_text }}</span>
                               <del class="product-view__old-price">{{ $prod->main_price_text }}</del>
                               <span class="badge bg-secondary">Akcija</span>
                           @else
                               <span class="product-view__price">{{ $prod->main_price_text }}</span>
                           @endif

                           @if($prod->secondary_price_text)
                               <span class="product-view__secondary-price">
                                   @if ($prod->main_price > $prod->main_special)
                                       {{ $prod->secondary_special_text }} <del>{{ $prod->secondary_price_text }}</del>
                                   @else
                                       {{ $prod->secondary_price_text }}
                                   @endif
                               </span>
                           @endif
                       </div>

                       <span class="product-view__availability {{ $prod->quantity > 0 ? 'is-available' : 'is-unavailable' }}">
                           <i class="fa-regular {{ $prod->quantity > 0 ? 'fa-circle-check' : 'fa-circle-xmark' }}" aria-hidden="true"></i>
                           {{ $prod->quantity > 0 ? 'Dostupno' : 'Nije dostupno' }}
                       </span>
                   </div>

                   <div class="product-view__buy">
                       @if ($prod->quantity > 0)
                           <add-to-cart-btn id="{{ $prod->id }}" available="{{ $prod->quantity }}"></add-to-cart-btn>
                       @else
                           <a class="btn btn-primary btn-shadow product-view__notify" href="#wishlist-modal" data-bs-toggle="modal">
                               <i class="fa-regular fa-bell" aria-hidden="true"></i> Obavijesti me o dostupnosti
                           </a>
                       @endif
                   </div>

                   <div class="product-view__delivery-note" role="note">
                       <span class="product-view__delivery-icon"><i class="fa-regular fa-truck-fast" aria-hidden="true"></i></span>
                       <div>
                           <strong>Besplatna dostava u Hrvatskoj</strong>
                           <span>Za narudžbe veće od 70 €.</span>
                       </div>
                   </div>

                   <!-- Product panels-->
                   <div class="accordion product-view__accordion" id="productPanels">
                       <div class="accordion-item">
                           <h3 class="accordion-header"><a class="accordion-button" href="#productInfo" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="productInfo"><i class="fa-regular fa-circle-info" aria-hidden="true"></i>Osnovne informacije</a></h3>
                           <div class="accordion-collapse collapse show" id="productInfo" data-bs-parent="#productPanels">
                               <div class="accordion-body">
                                   <ul class="product-view__info-list">
                                       @if ($showAuthorLink)
                                           <li><strong>Autor:</strong> <a href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ $productAuthorLabel }} </a></li>
                                       @endif
                                       @if ($showPublisherLink)
                                           <li><strong>Nakladnik:</strong> <a href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ $productPublisherLabel }}</a> </li>
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
                           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#shippingOptions" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="shippingOptions"><i class="fa-regular fa-truck-fast" aria-hidden="true"></i>Opcije dostave</a></h3>
                           <div class="accordion-collapse collapse" id="shippingOptions" data-bs-parent="#productPanels">
                               <div class="accordion-body">

                                   @foreach($shipping_methods as $shipping_method)
                                       <div class="product-view__method-row">
                                           <div>
                                               <div class="product-view__method-title">{{ $shipping_method->title }}</div>
                                               {{--  <div class="fs-sm text-muted"> Besplatna dostava za narudžbe iznad {{ config('settings.free_shipping') }}€</div>--}}
                                               @if ($prod->shipping_time)
                                                   <span class="product-view__method-description">Rok dostave: {{ $prod->shipping_time }}</span>
                                               @endif
                                           </div>
                                           <strong>{{ $shipping_method->data->price }}€</strong>
                                       </div>
                                   @endforeach

                               </div>
                           </div>
                       </div>
                       <div class="accordion-item">
                           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#localStore" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="localStore"><i class="fa-regular fa-credit-card" aria-hidden="true"></i>Načini plaćanja</a></h3>
                           <div class="accordion-collapse collapse" id="localStore" data-bs-parent="#productPanels">
                               <div class="accordion-body">
                                   @foreach($payment_methods as $payment_method)
                                       @if($prod->origin == 'Engleski' and $payment_method->code == 'cod' )

                                       @else
                                           <div class="product-view__method-row">
                                               <div>
                                                   <div class="product-view__method-title">{{ $payment_method->title }}</div>
                                                   @if (isset($payment_method->data->description))
                                                       <div class="product-view__method-description">{{ $payment_method->data->description }}</div>
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
                   <div class="product-view__share">
                       <span>Podijelite naslov</span>
                       <!-- ShareThis BEGIN --><div class="sharethis-inline-share-buttons"></div><!-- ShareThis END -->
                   </div>

                   @if ($showAuthorLink || $showPublisherLink || $productEyebrow)
                       <nav class="product-view__explore" aria-label="Istražite povezane naslove">
                           <h2>Istražite još</h2>
                           <div class="product-view__explore-links">
                               @if ($showAuthorLink)
                                   <a class="btn btn-outline-primary product-view__explore-link" href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">
                                       Još knjiga autora {{ $productAuthorLabel }}
                                   </a>
                               @endif
                               @if ($showPublisherLink)
                                   <a class="btn btn-outline-primary product-view__explore-link" href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">
                                       Više od nakladnika {{ $productPublisherLabel }}
                                   </a>
                               @endif
                               @if ($productEyebrow)
                                   <a class="btn btn-outline-primary product-view__explore-link" href="{{ $productEyebrowUrl }}">
                                       Kategorija {{ $productEyebrow }}
                                   </a>
                               @endif
                           </div>
                       </nav>
                   @endif
               </div>
           </div>
       </section>
       <!-- Related products-->

       <section class="product-view__details" id="tabs_widget">
           <div class="product-view__panel product-view__details-panel">
               <!-- Tabs-->
               <ul class="nav nav-tabs product-view__tabs" role="tablist">
                   <li class="nav-item"><a class="nav-link active" href="#specs" data-bs-toggle="tab" role="tab"><span>{{ $productHasDescription ? 'Opis' : 'Detalji' }}</span> </a></li>
                    <li class="nav-item"><a class="nav-link" href="#reviews" data-bs-toggle="tab" role="tab"><span>Recenzije ({{ $reviews->count() }})</span></a></li>
               </ul>
               <div class="product-view__details-body">
                   <div class="tab-content">
                       <!-- Tech specs tab-->
                       <div class="tab-pane fade show active" id="specs" role="tabpanel">
                           <!-- Specs table-->
                           <div class="row product-view__description-grid{{ $productHasDescription ? '' : ' product-view__description-grid--metadata-only' }}">
                               @if ($productHasDescription)
                                   <div class="col-md-7 d-flex flex-column product-view__description-copy">
                                       <h3 class="product-view__minor-heading">Sažetak</h3>
                                       <div class="product-view__description-text">
                                           {!! $productDescriptionHtml !!}
                                       </div>

                                       @if(!empty($prod->tags))
                                           <div class="product-view__tags">
                                               @if(!empty($prod->tags))
                                                   @foreach($prod->tags as $tag)
                                                       <a class="btn btn-outline-primary btn-sm"
                                                      href="{{ route('tag', ['pojam' => $tag]) }}">
                                                       #{{ $tag }}
                                                   </a>
                                                   @endforeach
                                               @endif
                                           </div>
                                       @endif
                                   </div>
                               @endif

                               <div class="{{ $productHasDescription ? 'col-md-5' : 'col-12 product-view__metadata--full' }} product-view__metadata">
                                   <h3 class="product-view__minor-heading">Dodatne informacije</h3>
                                   <ul class="product-view__metadata-list">

                                       {{--@if ($prod->origin)
                                           <li class="d-flex justify-content-between pb-2 border-bottom">
                                               <span class="text-muted">Jezik:</span><span>{{ $prod->origin }}</span>
                                           </li>
                                       @endif--}}

                                       @if ($prod->year)
                                           <li>
                                               <span class="text-muted">Godina izdanja:</span><span>{{ $prod->year }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->origin)
                                           <li>
                                               <span class="text-muted">{{ $productLanguageCount > 1 ? 'Jezici:' : 'Jezik:' }}</span><span>{{ $prod->origin }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->pages)
                                           <li>
                                               <span class="text-muted">Broj stranica:</span><span>{{ $prod->pages }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->dimensions)
                                           <li>
                                               <span class="text-muted">Dimenzije:</span><span>{{ $prod->dimensions.' cm' }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->letter)
                                           <li>
                                               <span class="text-muted">Pismo:</span><span>{{ $prod->letter }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->condition)
                                           <li>
                                               <span class="text-muted">Stanje:</span><span>{{ $prod->condition }}</span>
                                           </li>
                                       @endif

                                       @if ($prod->binding)
                                           <li>
                                               <span class="text-muted">Uvez:</span><span>{{ $prod->binding }}</span>
                                           </li>
                                       @endif

                                   </ul>

                                   @if ($productNote !== '')
                                       <aside class="product-view__note" aria-label="Napomena o primjerku">
                                           <i class="fa-duotone fa-triangle-exclamation" aria-hidden="true"></i>
                                           <div>
                                               <h4 class="h6 mb-1">Napomena o primjerku</h4>
                                               <p>{{ $productNote }}</p>
                                           </div>
                                       </aside>
                                   @endif
                               </div>
                           </div>

                       </div>
                       <div class="tab-pane fade" id="reviews" role="tabpanel">
                           <div class="row product-view__reviews-grid">
                               <div class="col-lg-7">
                                   <h3 class="product-view__minor-heading">Recenzije kupaca</h3>

                                   @forelse ($reviews as $review)
                                       <article class="product-view__review-card">
                                           <div class="product-view__review-head">
                                               <div>
                                                   <strong>{{ $review->fname }} {{ $review->lname }}</strong>
                                                   @if ($review->is_verified_purchase)
                                                       <span class="badge bg-success ms-2">Potvrđena kupnja</span>
                                                   @endif
                                               </div>
                                               <small class="text-muted">{{ \Illuminate\Support\Carbon::make($review->created_at)->format('d.m.Y.') }}</small>
                                           </div>
                                           <div class="product-view__review-stars">
                                               @for ($i = 1; $i <= 5; $i++)
                                                   <i class="fa-{{ $i <= (int) $review->stars ? 'solid' : 'regular' }} fa-star{{ $i <= (int) $review->stars ? ' active' : '' }}" aria-hidden="true"></i>
                                               @endfor
                                           </div>
                                           <p class="mb-0">{!! nl2br(e(strip_tags($review->message))) !!}</p>
                                       </article>
                                   @empty
                                       <p class="text-muted mb-0">Još nema recenzija za ovaj proizvod.</p>
                                   @endforelse
                               </div>

                               <div class="col-lg-5 mt-4 mt-lg-0">
                                   <div class="product-view__review-form">
                                   <h3 class="product-view__minor-heading">Napišite recenziju</h3>
                                   @php
                                       $authUser = auth()->user();
                                       $detail = $authUser ? optional($authUser->details) : null;
                                       $defaultReviewName = $authUser
                                           ? trim(($detail && $detail->fname ? $detail->fname : '') . ' ' . ($detail && $detail->lname ? $detail->lname : ''))
                                           : '';
                                       if ($defaultReviewName === '' && $authUser) {
                                           $defaultReviewName = $authUser->name;
                                       }
                                   @endphp
                                   <form method="POST" action="{{ route('komentar.proizvoda') }}" id="review-form">
                                       @csrf
                                       <input type="hidden" name="product_id" value="{{ $prod->id }}">
                                       <input type="hidden" name="recaptcha" id="recaptcha_review">

                                       <div class="mb-3">
                                           <label class="form-label" for="review-name">Ime</label>
                                           <input class="form-control" id="review-name" type="text" name="name" value="{{ old('name', $defaultReviewName) }}" required>
                                       </div>

                                       <div class="mb-3">
                                           <label class="form-label" for="review-email">Email</label>
                                           <input class="form-control" id="review-email" type="email" name="email" value="{{ old('email', $authUser ? $authUser->email : '') }}" required>
                                       </div>

                                       <div class="mb-3">
                                           <label class="form-label" for="review-stars">Ocjena</label>
                                           <select class="form-select" id="review-stars" name="stars" required>
                                               <option value="5" {{ old('stars', '5') == '5' ? 'selected' : '' }}>5 - Odlično</option>
                                               <option value="4" {{ old('stars') == '4' ? 'selected' : '' }}>4 - Vrlo dobro</option>
                                               <option value="3" {{ old('stars') == '3' ? 'selected' : '' }}>3 - Dobro</option>
                                               <option value="2" {{ old('stars') == '2' ? 'selected' : '' }}>2 - Dovoljno</option>
                                               <option value="1" {{ old('stars') == '1' ? 'selected' : '' }}>1 - Slabo</option>
                                           </select>
                                       </div>

                                       <div class="mb-3">
                                           <label class="form-label" for="review-message">Komentar</label>
                                           <textarea class="form-control" id="review-message" name="message" rows="5" maxlength="1000" required>{{ old('message') }}</textarea>
                                       </div>

                                       <button type="submit" class="btn btn-primary w-100">Pošalji recenziju</button>
                                       @include('front.layouts.partials.recaptcha-notice')
                                   </form>
                                   </div>
                               </div>
                           </div>
                       </div>

                   </div>
               </div>
           </div>
       </section>
       @if($authorProducts->isNotEmpty())
           <section class="product-view__products-section">
               <div class="product-view__section-heading">
                   <h2>Još knjiga autora {{ $productAuthorLabel }}</h2>
                   <p>Drugi dostupni naslovi istog autora.</p>
               </div>
               <div class="tns-carousel tns-controls-static tns-controls-outside product-view__carousel">
                   <div class="tns-carousel-inner" data-carousel-options='{"items":2,"controls":false,"nav":{{ $authorProducts->count() > 2 ? 'true' : 'false' }},"loop":false,"responsive":{"0":{"items":2,"gutter":5},"500":{"items":2,"gutter":10},"768":{"items":3,"nav":{{ $authorProducts->count() > 3 ? 'true' : 'false' }},"gutter":10},"1100":{"items":4,"controls":{{ $authorProducts->count() > 4 ? 'true' : 'false' }},"nav":{{ $authorProducts->count() > 4 ? 'true' : 'false' }},"gutter":10},"1300":{"items":5,"controls":{{ $authorProducts->count() > 5 ? 'true' : 'false' }},"nav":{{ $authorProducts->count() > 5 ? 'true' : 'false' }},"gutter":10}}}'>
                       @foreach ($authorProducts as $authorProduct)
                           <div>
                               @include('front.catalog.category.product', ['product' => $authorProduct])
                           </div>
                       @endforeach
                   </div>
               </div>
           </section>
       @endif
       @if($publisherProducts->isNotEmpty())
           <section class="product-view__products-section">
               <div class="product-view__section-heading">
                   <h2>Više knjiga nakladnika {{ $productPublisherLabel }}</h2>
                   <p>Drugi dostupni naslovi istog nakladnika.</p>
               </div>
               <div class="tns-carousel tns-controls-static tns-controls-outside product-view__carousel">
                   <div class="tns-carousel-inner" data-carousel-options='{"items":2,"controls":false,"nav":{{ $publisherProducts->count() > 2 ? 'true' : 'false' }},"loop":false,"responsive":{"0":{"items":2,"gutter":5},"500":{"items":2,"gutter":10},"768":{"items":3,"nav":{{ $publisherProducts->count() > 3 ? 'true' : 'false' }},"gutter":10},"1100":{"items":4,"controls":{{ $publisherProducts->count() > 4 ? 'true' : 'false' }},"nav":{{ $publisherProducts->count() > 4 ? 'true' : 'false' }},"gutter":10},"1300":{"items":5,"controls":{{ $publisherProducts->count() > 5 ? 'true' : 'false' }},"nav":{{ $publisherProducts->count() > 5 ? 'true' : 'false' }},"gutter":10}}}'>
                       @foreach ($publisherProducts as $publisherProduct)
                           <div>
                               @include('front.catalog.category.product', ['product' => $publisherProduct])
                           </div>
                       @endforeach
                   </div>
               </div>
           </section>
       @endif
       <!-- Related products -->
       <section class="product-view__products-section">
           <div class="product-view__section-heading">
               <h2>Možda vas zanima</h2>
               <p>Još odabranih naslova iz slične kategorije.</p>
           </div>
           <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled product-view__carousel">
               <div class="tns-carousel-inner tns-nav-enabled" data-carousel-options='{"items": 2, "controls": false, "nav": true, "responsive": {"0":{"items":2, "gutter": 5},"500":{"items":2, "gutter": 10},"768":{"items":3, "gutter": 10}, "1100":{"items":4, "controls": true, "gutter": 10}, "1300":{"items":5, "controls": true, "gutter": 10}, "1600":{"items":5, "controls": true, "gutter": 10}}}'>
                   @foreach (collect($related)->where('quantity', '>', 0)->unique('id')->take(15) as $cat_product)
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

       <section class="product-view__products-section">
           <div class="product-view__section-heading">
               <h2>Nedavno pregledano</h2>
               <p>Naslovi koje ste otvorili tijekom ovog pregleda.</p>
           </div>
           <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled product-view__carousel">
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
            infinite:       true,

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

    @include('front.layouts.partials.recaptcha-js', [
        'action' => 'review',
        'fieldId' => 'recaptcha_review',
        'formId' => 'review-form',
    ])

    @include('front.layouts.modals.wishlist-email')
@endpush
