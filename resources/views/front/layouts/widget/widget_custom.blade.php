<!-- {"title": "Slider Index", "description": "Glavni slider sa slikama."} -->
@php
    $carouselOptions = [
        'items' => 1,
        'mode' => 'carousel',
        'nav' => true,
        'controls' => true,
        'autoplay' => true,
        'autoplayTimeout' => 7000,
        'loop' => true,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['controls' => false],
            576 => ['controls' => true],
        ],
    ];
@endphp

@if (collect($data)->isNotEmpty())
    <section class="tns-carousel mb-3 widget-touch-carousel widget-custom-hero-carousel">
        <div class="tns-carousel-inner" data-carousel-options='@json($carouselOptions)'>
            @foreach ($data as $widget)
                @php
                    $targetUrl = trim((string) ($widget['url'] ?? ''));
                    $hasLink = $targetUrl !== '' && $targetUrl !== '/';
                @endphp
                <div>
                    <div class="widget-custom-hero__slide px-3 px-md-5 py-4 text-center text-lg-start">
                        <div class="d-lg-flex justify-content-between align-items-center gap-4 mx-auto" style="max-width: 1226px;">
                            <div class="widget-custom-hero__copy py-lg-3 mx-auto mx-lg-0">
                                <h2 class="h1 text-primary font-title mb-2">{{ $widget['title'] }}</h2>
                                @if (! empty($widget['subtitle']))
                                    <p class="text-dark mb-3">{{ $widget['subtitle'] }}</p>
                                @endif
                                @if ($hasLink && ! empty($widget['button_text']))
                                    <a class="btn btn-primary" href="{{ url($targetUrl) }}">
                                        {{ $widget['button_text'] }}
                                        <i class="fa-regular fa-arrow-right ms-2" aria-hidden="true"></i>
                                    </a>
                                @endif
                            </div>

                            @if (! empty($widget['image']))
                                <div class="widget-custom-hero__media">
                                    @if ($hasLink)<a href="{{ url($targetUrl) }}">@endif
                                    <img src="{{ $widget['image'] }}" width="500" height="500" loading="{{ $loop->first ? 'eager' : 'lazy' }}" alt="{{ $widget['title'] }}">
                                    @if ($hasLink)</a>@endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
