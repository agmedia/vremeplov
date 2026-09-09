<!-- {"title": "Carousel", "description": "Carousel artikala po izboru, kategoriji ili izdavaču."} -->
@if (collect($data['items'] ?? [])->isNotEmpty())
    <section class="py-3 widget-product-carousel {{ $data['css'] ?? '' }}">
        <div class="container">
            <div class="{{ $data['container'] ? 'bg-white rounded-3 shadow-sm px-3 px-md-4 pt-2' : '' }}">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 pb-2 mb-2">
                    <div class="widget-section-heading">
                        <h2 class="h3 mb-0 pt-2 font-title">{{ $data['title'] }}</h2>
                        @if (! empty($data['subtitle']))
                            <p class="text-muted fs-md mb-0 mt-1">{{ $data['subtitle'] }}</p>
                        @endif
                    </div>
                    @if (! empty($data['url']) && $data['url'] !== '/')
                        <a class="btn btn-outline-primary btn-sm" href="{{ url($data['url']) }}">
                            Pogledajte ponudu <i class="fa-regular fa-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
                <div class="tns-carousel tns-nav-enabled widget-content-carousel widget-touch-carousel pt-2 pb-3">
                    <div class="tns-carousel-inner" data-carousel-options='{"items":2,"controls":true,"nav":true,"mouseDrag":true,"touch":true,"preventScrollOnTouch":"auto","responsive":{"0":{"items":2,"gutter":8,"controls":false},"500":{"items":2,"gutter":14},"768":{"items":3,"gutter":18,"controls":true},"1100":{"items":5,"gutter":24}}}'>
                        @foreach ($data['items'] as $product)
                            <div>
                                @include('front.catalog.category.product')
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
