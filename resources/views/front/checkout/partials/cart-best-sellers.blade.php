@if ($products->isNotEmpty())
    <section class="checkout-best-sellers py-4 py-lg-5" aria-labelledby="cart-best-sellers-title">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 pb-2 mb-2">
                <div>
                    <h2 class="h3 mb-1 font-title" id="cart-best-sellers-title">Najprodavanije u posljednjih 30 dana</h2>
                    <p class="text-muted fs-md mb-0">Naslovi koje su kupci Vremeplova najčešće birali tijekom proteklog mjeseca.</p>
                </div>
            </div>

            <div class="tns-carousel widget-touch-carousel pt-2 pb-3">
                <div class="tns-carousel-inner tns-nav-enabled" data-carousel-options='{"items":2,"controls":true,"nav":true,"mouseDrag":true,"touch":true,"preventScrollOnTouch":"auto","responsive":{"0":{"items":2,"gutter":8,"controls":false},"500":{"items":2,"gutter":14},"768":{"items":3,"gutter":18},"1100":{"items":5,"gutter":24}}}'>
                    @foreach ($products as $product)
                        <div>
                            @include('front.catalog.category.product', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
