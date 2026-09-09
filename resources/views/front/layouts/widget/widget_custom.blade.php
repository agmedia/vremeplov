<!-- {"title": "Slider Index", "description": "Glavni slider sa slikama."} -->
@php
    $hasMultipleSlides = collect($data)->count() > 1;
    $carouselOptions = [
        'items' => 1,
        'mode' => 'carousel',
        'nav' => $hasMultipleSlides,
        'controls' => $hasMultipleSlides,
        'autoplay' => $hasMultipleSlides,
        'autoplayTimeout' => 7000,
        'loop' => $hasMultipleSlides,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['controls' => false],
            576 => ['controls' => $hasMultipleSlides],
        ],
    ];
@endphp

@if (collect($data)->isNotEmpty())
    <section class="container widget-custom-hero-section">
        <div class="tns-carousel widget-touch-carousel widget-custom-hero-carousel shadow">
            <div class="tns-carousel-inner" data-carousel-options='@json($carouselOptions)'>
                @foreach ($data as $widget)
                    @php
                        $targetUrl = trim((string) ($widget['url'] ?? ''));
                        $hasLink = $targetUrl !== '' && $targetUrl !== '/';
                        $slideColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($widget['color'] ?? ''))
                            ? $widget['color']
                            : '#ffffff';
                        $imageRight = ! empty($widget['right']);
                    @endphp
                    <div>
                        <article class="widget-custom-hero__slide" style="--widget-hero-background: {{ $slideColor }};">
                            <div class="widget-custom-hero__layout {{ empty($widget['image']) ? 'widget-custom-hero__layout--copy-only' : ($imageRight ? 'is-image-right' : 'is-image-left') }}">
                                <div class="widget-custom-hero__copy">
                                    @if (! empty($widget['eyebrow']))
                                        <p class="widget-custom-hero__eyebrow">
                                            <span class="widget-custom-hero__eyebrow-icon" aria-hidden="true"><i class="fa-duotone fa-{{ $widget['eyebrow_icon'] }}"></i></span>
                                            <span>{{ $widget['eyebrow'] }}</span>
                                        </p>
                                    @endif
                                    <h2 class="widget-custom-hero__title font-title">{{ $widget['title'] }}</h2>
                                    @if (! empty($widget['subtitle']))
                                        <p class="widget-custom-hero__subtitle">{{ $widget['subtitle'] }}</p>
                                    @endif
                                    @if (! empty($widget['benefits']))
                                        <ul class="widget-custom-hero__benefits" aria-label="Pogodnosti">
                                            @foreach ($widget['benefits'] as $benefit)
                                                <li>
                                                    <span class="widget-custom-hero__benefit-icon" aria-hidden="true"><i class="fa-regular fa-{{ $benefit['icon'] }}"></i></span>
                                                    <span>{{ $benefit['text'] }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if ($hasLink && ! empty($widget['button_text']))
                                        <div class="widget-custom-hero__action">
                                            <a class="btn btn-primary px-4" href="{{ url($targetUrl) }}">
                                                {{ $widget['button_text'] }}
                                                <i class="fa-regular fa-arrow-right ms-2" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                @if (! empty($widget['image']))
                                    <div class="widget-custom-hero__media-wrap">
                                        @if ($hasLink)<a class="widget-custom-hero__media" href="{{ url($targetUrl) }}">@else<div class="widget-custom-hero__media">@endif
                                            <img src="{{ $widget['image'] }}" width="800" height="800" loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" decoding="async" alt="{{ $widget['title'] }}">
                                        @if ($hasLink)</a>@else</div>@endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
