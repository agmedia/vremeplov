@extends('front.layouts.app')

@php
    $publishersCanonical = route('catalog.route.publisher');
    if (! $letter && (int) request()->input('page', 1) > 1) {
        $publishersCanonical .= '?page=' . (int) request()->input('page');
    }
@endphp
@section('title', 'Nakladnici - Antikvarijat Vremeplov')
@section('description', 'Popis nakladnika čija su izdanja dostupna u Antikvarijatu Vremeplov. Pretražite nakladnike abecednim redom.')
@section('canonical', $publishersCanonical)

@if (isset($meta_tags))
    @push('meta_tags')
        @foreach ($meta_tags as $tag)
            <meta name="{{ $tag['name'] }}" content="{{ $tag['content'] }}">
        @endforeach
    @endpush
@endif

@section('content')

    <section class="position-relative  bg-size-cover bg-position-center-x position-relative py-3 mb-3" style="background-image: url({{ config('settings.images_domain') . 'media/img/zuzi-bck.svg' }});background-repeat: repeat-x;background-position-y: bottom;">
        <div class="container position-relative zindex-5 py-4 my-3">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="text-light text-center">Lista nakladnika</h1>
                    <p class="pb-0 text-light text-center mb-0">Pretraživanje prema početnom slovu imena nakladnika</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Topics grid-->
    <section class="container py-3 mb-5">
        <div class="row align-items-center py-md-3">
            <div class="col-lg-12   py-2 text-center">
                <div class="scrolling-wrapper">

                @foreach ($letters as $item)
                    @if ($item['active'])
                        <a href="{{ route('catalog.route.publisher', ['publisher' => null, 'letter' => $item['value']]) }}"
                           class="btn btn-secondary btn-icon cardd mb-2 @if($item['value'] == $letter) bg-fourth text-white @endif">
                            <span class="h4 @if($item['value'] == $letter) text-white @else text-dark @endif py-0 mb-0 px-1">{{ $item['value'] }}</span>
                        </a>
                    @else
                        <span class="btn btn-secondary btn-icon cardd mb-2 disabled" aria-disabled="true"><span class="h4 text-dark py-0 mb-0 px-1">{{ $item['value'] }}</span></span>
                    @endif
                @endforeach
                </div>
            </div>
        </div>

        <div class="row py-md-3">
            <div class="col-lg-12 text-center mb-5">
                <h2>{{ $letter ? 'Nakladnici – ' . $letter : 'Svi nakladnici' }}</h2>
                <hr>
            </div>

            @foreach ($publishers as $publisher)
                <div class="col-sm-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-0"> <a href="{{ url($publisher['url']) }}" class="text-dark">{{ $publisher['title'] }} <span class="badge rounded-pill bg-secondary float-end">{{ $publisher['products_count'] }}</span></a></h6>
                        </div>
                    </div>
                </div>
            @endforeach

        </div>

        <div class="row py-md-3">
            <div class="col-lg-12">
                {{ $publishers->links() }}
            </div>
        </div>

    </section>

@endsection

@push('js_after')
    <style>
        @media only screen and (max-width: 1040px) {
            .scrolling-wrapper {
                overflow-x: scroll;
                overflow-y: hidden;
                white-space: nowrap;
                padding-bottom: 15px;
            }
        }
    </style>
@endpush
