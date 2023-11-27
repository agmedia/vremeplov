@extends('front.layouts.app')

@if (isset($meta_tags))
    @push('meta_tags')
        @foreach ($meta_tags as $tag)
            <meta name={{ $tag['name'] }} content={{ $tag['content'] }}>
        @endforeach
    @endpush
@endif

@section('content')


    <!-- Page Title (Light)-->
    <div class="bg-light pt-4 pb-3"  style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
        <div class="container d-lg-flex justify-content-between align-items-center py-2 py-lg-3">

            <div class="order-1 pe-lg-4 mb-3 mb-lg-0 text-center text-start align-items-center">
                <h1 class="h3 text-dark mb-0"> Lista autora</h1>
            </div>
            <div class="order-2 mb-3 mb-lg-0 pt-0 align-items-center">
                <form action="{{ route('pretrazi', ['tip' => 'author']) }}" method="get" style="max-width:500px; margin: 0 auto;">
                    <div class="input-group input-group-lg flex-nowrap">
                        <input type="text" class="form-control rounded-start" name="{{ config('settings.search_keyword') }}" placeholder="Pretražite po autoru">
                        <button class="btn btn-primary btn-lg fs-base" type="submit"><i class="ci-search"></i></button>
                    </div>
                </form>
            </div>


        </div>

    </div>



    <!-- Topics grid-->
    <section class="container py-3 mb-5">
        <div class="row align-items-center py-md-3">
            <div class="col-lg-12   py-2 text-center">
                <div class="scrolling-wrapper">
                @foreach ($letters as $item)
                    <a href="{{ route('catalog.route.author', ['author' => null, 'letter' => $item['value']]) }}"
                       class="btn btn-outline-primary btn-sm  mb-2 @if( ! $item['active']) disabled @endif @if($item['value'] == $letter) bg-primary  @endif">
                        <h3 class="h6  @if($item['value'] == $letter) text-white @else  @endif  py-0 mb-0 px-1">{{ $item['value'] }}</h3></a>
                @endforeach
                </div>
            </div>
        </div>

        <div class="row py-md-3">
            <div class="col-lg-12 text-center mb-5">

                @if($letter == 0)
                <h1>Svi autori</h1>
                @else
                    {{$letter}}
                @endif

            </div>
            @foreach ($authors as $author)
                <div class=" col-sm-4 col-md-3 mb-3">
                    <div class="card border-bottom-primary">
                        <div class="card-body">
                            <h6 class="card-title mb-0"> <a href="{{ url($author['url']) }}" class="text-dark">{{ $author['title'] }} <span class="badge rounded-pill bg-secondary float-end">{{ $author['products_count'] }}</span></a></h6>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row  py-md-3">

                {{ $authors->onEachSide(1)->links() }}

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
