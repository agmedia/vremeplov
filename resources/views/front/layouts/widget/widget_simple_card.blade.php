<!-- {"title": "Banneri", "description": "Kombinirani promotivni banneri."} -->
@if (collect($data)->isNotEmpty())
    <section class="container py-3">
        <div class="row g-3">
            @foreach ($data as $widget)
                @php
                    $width = in_array((int) $widget['width'], [4, 6, 8, 12], true) ? (int) $widget['width'] : 12;
                    $hasLink = ! empty($widget['url']) && $widget['url'] !== '/';
                @endphp
                <div class="col-12 col-xl-{{ $width }}">
                    <article class="widget-simple-card d-flex flex-column flex-sm-row {{ $widget['right'] ? '' : 'flex-sm-row-reverse' }} rounded-3">
                        <div class="widget-simple-card__copy p-4 d-flex flex-column justify-content-center text-center text-sm-start">
                            <h2 class="h3 font-title mb-2">{{ $widget['title'] }}</h2>
                            @if (! empty($widget['subtitle']))
                                <p class="text-muted mb-3">{{ $widget['subtitle'] }}</p>
                            @endif
                            @if ($hasLink)
                                <div><a class="btn btn-primary btn-sm" href="{{ url($widget['url']) }}">Pogledajte ponudu <i class="fa-regular fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
                            @endif
                        </div>
                        @if (! empty($widget['image']))
                            @if ($hasLink)<a class="widget-simple-card__media" href="{{ url($widget['url']) }}">@else<div class="widget-simple-card__media">@endif
                                <img src="{{ $widget['image'] }}" width="420" height="300" loading="lazy" alt="{{ $widget['title'] }}">
                            @if ($hasLink)</a>@else</div>@endif
                        @endif
                    </article>
                </div>
            @endforeach
        </div>
    </section>
@endif
