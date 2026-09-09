@extends('front.layouts.app')
@if(isset($blogs))
    @php
        $blogTitle = 'Blog - Antikvarijat Vremeplov';
        $blogDescription = 'Medijske objave, članci i obavijesti Antikvarijata Vremeplov.';
        $blogCanonical = route('catalog.route.blog');
    @endphp
@else
    @php
        $blogTitleBase = trim((string) ($blog->meta_title ?: $blog->title));
        $blogTitle = mb_strlen($blogTitleBase) <= 42 ? $blogTitleBase . ' - Antikvarijat Vremeplov' : $blogTitleBase;
        $blogDescription = trim(strip_tags((string) ($blog->meta_description ?: $blog->short_description ?: $blog->description)));
        $blogDescription = mb_substr($blogDescription ?: 'Članak Antikvarijata Vremeplov.', 0, 160);
        $blogCanonical = route('catalog.route.blog', ['blog' => $blog]);
    @endphp
@endif

@section('title', $blogTitle)
@section('description', $blogDescription)
@section('canonical', $blogCanonical)

@push('meta_tags')
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="{{ isset($blogs) ? 'website' : 'article' }}" />
    <meta property="og:title" content="{{ $blogTitle }}" />
    <meta property="og:description" content="{{ $blogDescription }}" />
    <meta property="og:url" content="{{ $blogCanonical }}" />
    <meta property="og:site_name" content="Antikvarijat Vremeplov" />
    <meta property="og:image" content="{{ isset($blogs) ? config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' : $blog->image }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $blogTitle }}" />
    <meta name="twitter:description" content="{{ $blogDescription }}" />
    <meta name="twitter:image" content="{{ isset($blogs) ? config('settings.images_domain') . 'media/img/cover-vremeplov.jpg' : $blog->image }}" />
    @if(!isset($blogs))
        <meta property="article:published_time" content="{{ optional($blog->created_at)->toAtomString() }}" />
        <meta property="article:modified_time" content="{{ optional($blog->updated_at)->toAtomString() }}" />
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            '@id' => $blogCanonical . '#article',
            'mainEntityOfPage' => $blogCanonical,
            'headline' => $blog->title,
            'description' => $blogDescription,
            'image' => [$blog->image],
            'datePublished' => optional($blog->created_at)->toAtomString(),
            'dateModified' => optional($blog->updated_at)->toAtomString(),
            'inLanguage' => 'hr-HR',
            'publisher' => ['@id' => url('/#organization')],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endpush

@section('content')

    <!-- Page Title-->
    <div class="bg-light pt-4 pb-3"  style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
        <div class="container  justify-content-between py-2 py-lg-3">
            <div class="order-lg-2 mb-3  pt-lg-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark justify-content-center ">
                                <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="fa-regular fa-house"></i>Naslovnica</a></li>
                                <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('catalog.route.blog') }}"><i class="fa-regular fa-house"></i>Blog</a></li>

                                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ isset($blogs) ? 'Objave' : $blog->title }}</li>
                            </ol>
                        </nav>

            </div>
            <div class="order-lg-1 pe-lg-4 text-center ">
                @if(isset($blogs))
            <h1 class="text-dark">Blog</h1>
                @else
                    <h1 class="text-dark">{{ $blog->title }}</h1>
                @endif
        </div>
        </div>
    </div>

    @if(isset($blogs))

    <div class="container pb-5 mb-2 mb-md-4">

        <div class="pt-5 mt-md-2">
            <!-- Entries grid-->
            <div class="masonry-grid" data-columns="3">
                @foreach ($blogs as $blog)

                <article class="masonry-grid-item">
                    <div class="card">
                        <a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}"><img class="card-img-top" src="{{ $blog->image }}" loading="lazy" alt="{{ $blog->title }}"></a>
                        <div class="card-body">
                            <h2 class="h6 blog-entry-title"><a href="{{ route('catalog.route.blog', ['blog' => $blog]) }}">{{ $blog->title }}</a></h2>
                            <p class="fs-sm">{{ $blog->short_description }}</p>
                        </div>
                        <div class="card-footer d-flex align-items-left fs-xs">
                            <div class="me-auto text-nowrap"><a class="blog-entry-meta-link text-nowrap" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}">{{ \Carbon\Carbon::make($blog->created_at)->locale('hr')->format('d.m.Y.') }}</a></div>
                        </div>
                    </div>
                </article>

                @endforeach

            </div>

        </div>

    </div>
    @else
        @php
            $normalizeArticleText = static function ($value) {
                $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)), 'UTF-8');
            };
            $articleLeadText = trim(preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode(strip_tags((string) $blog->short_description), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ));
            $articleLead = $articleLeadText
                && ! \Illuminate\Support\Str::startsWith(
                    $normalizeArticleText($blog->description),
                    $normalizeArticleText($articleLeadText)
                )
                    ? \Illuminate\Support\Str::limit($articleLeadText, 360)
                    : '';
            $articleWords = preg_split(
                '/\s+/u',
                trim(strip_tags((string) $blog->description)),
                -1,
                PREG_SPLIT_NO_EMPTY
            );
            $readingMinutes = max(1, (int) ceil(count($articleWords ?: []) / 200));
        @endphp
        <div class="container blog-article-shell pb-5">
            <article class="blog-article pt-4 pt-md-5">
                <div class="blog-article__meta" aria-label="Podaci o članku">
                    <span><i class="fa-regular fa-calendar-days" aria-hidden="true"></i>{{ \Carbon\Carbon::make($blog->created_at)->locale('hr')->format('d.m.Y.') }}</span>
                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $readingMinutes }} min čitanja</span>
                </div>

                <figure class="blog-article__hero">
                    <img src="{{ $blog->image }}" loading="eager" fetchpriority="high" decoding="async" alt="{{ $blog->title }}">
                </figure>

                @if ($articleLead)
                    <p class="blog-article__lead">{{ $articleLead }}</p>
                @endif

                <div class="blog-article__body">
                    {!! $blog->description !!}
                </div>
            </article>
        </div>

        @if (! empty($relatedProductsWidget))
            @include('front.layouts.widget.widget_product_carousel', ['data' => $relatedProductsWidget])
        @endif

    @endif

@endsection
