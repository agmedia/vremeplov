@extends('front.layouts.app')

@if (isset($gdl))
    @section('google_data_layer')
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                'event': 'view_cart',
                'ecommerce': {'items': <?php echo json_encode($gdl); ?>}
            });
        </script>
    @endsection
@endif

@section('content')

<!-- Page Title-->
<div class="page-title bg-dark pt-4"  style="background-image: url({{ config('settings.images_domain') . 'media/img/zuzi-bck.svg' }});background-repeat: repeat-x;background-position-y: bottom;">
    <div class="container d-lg-flex justify-content-between py-2 py-lg-3">
        <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-light flex-lg-nowrap justify-content-center justify-content-lg-start">
                    <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                    <li class="breadcrumb-item text-nowrap active" aria-current="page">Košarica</li>
                </ol>
            </nav>
        </div>
        <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
            <h1 class="h3 text-light mb-0">Košarica</h1>
        </div>
    </div>
   <div class="container">
     <div class="row">

         <section class="col-lg-12">
             <div class="steps steps-light pt-2 pb-3 mb-2">
                 <a class="step-item current active" href="{{ route('kosarica') }}">
                     <div class="step-progress"><span class="step-count">1</span></div>
                     <div class="step-label"><i class="ci-cart"></i>Košarica</div>
                 </a>
                 <a class="step-item" href="{{ route('naplata', ['step' => 'podaci']) }}">
                     <div class="step-progress"><span class="step-count">2</span></div>
                     <div class="step-label"><i class="ci-user-circle"></i>Podaci</div>
                 </a>
                 <a class="step-item" href="{{ route('naplata', ['step' => 'dostava']) }}">
                     <div class="step-progress"><span class="step-count">3</span></div>
                     <div class="step-label"><i class="ci-package"></i>Dostava</div>
                 </a>
                 <a class="step-item" href="{{ route('naplata', ['step' => 'placanje']) }}">
                     <div class="step-progress"><span class="step-count">4</span></div>
                     <div class="step-label"><i class="ci-card"></i>Plaćanje</div>
                 </a>
                 <a class="step-item" href="{{ route('pregled') }}">
                     <div class="step-progress"><span class="step-count">5</span></div>
                     <div class="step-label"><i class="ci-check-circle"></i>Pregledaj</div>
                 </a>
             </div>
         </section>

     </div>
 </div>
</div>
<section class="spikesg" ></section>
<div class="container pb-5 mt-5 mb-2 mb-md-4">
    <div class="row">

        <section class="col-lg-8">
            <cart-view continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}" freeship="{{ config('settings.free_shipping') }}"></cart-view>

        </section>
        <!-- Sidebar-->
        <aside class="col-lg-4 pt-4 pt-lg-0 ps-xl-5">
            <cart-view-aside route="kosarica" continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}"></cart-view-aside>
        </aside>
    </div>

</div>

@endsection
