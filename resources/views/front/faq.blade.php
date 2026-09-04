@extends('front.layouts.app')

@section('content')

    <!-- Page Title-->
    <div class=" bg-dark pt-4 pb-3" style="background-image: url({{ config('settings.images_domain') . 'media/img/indexslika.jpg' }});-webkit-background-size: cover;-moz-background-size: cover;-o-background-size: cover;background-size: cover;">
        <div class="container d-lg-flex justify-content-between py-2 py-lg-3">
            <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-light flex-lg-nowrap justify-content-center justify-content-lg-start">
                        <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">Česta pitanja</li>
                    </ol>
                </nav>

            </div>
            <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                <h1 class="text-light">Česta pitanja</h1>
            </div>
        </div>
    </div>


    <div class="container">



        <div class="mt-5 mb-5">

    <!-- Flush accordion. Use this when you need to render accordions edge-to-edge with their parent container -->
    <div class="accordion accordion-flush" id="accordionFlushExample">


    @foreach ($faq as $fa)

        <!-- Item -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="flush-heading{{ $fa->id }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse{{ $fa->id }}" aria-expanded="false" aria-controls="flush-collapse{{ $fa->id }}">{{ $fa->title }}</button>
                </h2>
                <div class="accordion-collapse collapse" id="flush-collapse{{ $fa->id }}" aria-labelledby="flush-heading{{ $fa->id }}" data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body">  {!! $fa->description !!}</div>
                </div>
            </div>

    @endforeach











    </div>

        </div>
    </div>




@endsection
