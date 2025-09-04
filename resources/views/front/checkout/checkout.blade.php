@extends('front.layouts.app')



@section('content')

    <div class="page-title-overlap bg-light pt-4"  style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
        <div class="container d-lg-flex justify-content-between py-2 py-lg-3">
            <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark flex-lg-nowrap justify-content-center justify-content-lg-start">
                        <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">Košarica</li>
                    </ol>
                </nav>
            </div>
            <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                <h1 class="h3 text-dark mb-0">Dovršite narudžbu</h1>
            </div>
        </div>

    </div>

    <div class="container pb-5 mb-2 mb-md-4">
        <div class="row">
            <section class="col-lg-8">



                @livewire('front.checkout', ['step' => $step, 'is_free_shipping' => $is_free_shipping], key('checkout-' . ($step ?? 'init')))


            </section>
            <!-- Sidebar-->
            <aside class="col-lg-4 pt-4 pt-lg-0 ps-xl-5 d-none d-lg-block">
                <cart-view-aside route="naplata" continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}"></cart-view-aside>
            </aside>
        </div>
    </div>

@endsection

@push('js_after')






@endpush
