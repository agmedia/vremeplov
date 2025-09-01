@extends('front.layouts.app')

@if (isset($meta) && ! empty($meta))
    @section ( 'title', $meta['title'] . ' - Antikvarijat Vremeplov' )
    @section ( 'description', $meta['description'] )
    @push('meta_tags')
        <link rel="canonical" href="{{ $meta['canonical'] }}" />
        @foreach ($meta['tags'] as $tag)
            <meta name={{ $tag['name'] }} content={{ $tag['content'] }}>
        @endforeach
    @endpush
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
                <div class="order-lg-1 pe-lg-4 text-center">
                    @if (isset($meta) && ! empty($meta))
                        <h1 class="h3 text-dark mb-0">{{ $meta['title'] }}</h1>
                    @endif
                </div>
            @endif

            @if (Route::currentRouteName() == 'pretrazi')
                <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                    <h1 class="h3 text-dark mb-0"><span class="small fw-light me-2">Rezultati za:</span> {{ request()->input('pojam') }}</h1>
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

    @if (isset($author) && $author && ! empty($author->description))
        <div class="container pb-4 mb-2 mb-md-4" >
            {!! $author->description !!}
        </div>
    @endif

    @if (isset($cat) && isset($subcat))
        <div class="container pb-4 mb-2 mb-md-4" >
            @if ($cat && ! $subcat)
                {!! $cat->description !!}
            @elseif ($subcat && ! $subcat)
                {!! $cat->description !!}
            @endif
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
