<!-- {"title": "Page Carousel", "description": "Kategorije, izdavači, autori, blog i komentari."} -->
@if (collect($data['items'] ?? [])->isNotEmpty())
    <section class="py-4 storefront-deferred-section {{ $data['background'] ? 'reviews-widget' : '' }} {{ $data['css'] ?? '' }}">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div class="widget-section-heading">
                    <h2 class="h3 mb-0 font-title">{{ $data['title'] }}</h2>
                    @if (! empty($data['subtitle']))
                        <p class="text-muted mb-0 mt-1">{{ $data['subtitle'] }}</p>
                    @endif
                </div>
                @if (($data['tablename'] ?? '') === 'blog')
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('catalog.route.blog') }}">
                        Pogledajte sve <i class="fa-regular fa-arrow-right ms-1" aria-hidden="true"></i>
                    </a>
                @endif
            </div>

            @if (($data['tablename'] ?? '') === 'category')
                <div class="tns-carousel tns-nav-enabled widget-content-carousel widget-touch-carousel">
                    <div class="tns-carousel-inner" data-carousel-options='{"items":2,"controls":true,"nav":true,"mouseDrag":true,"touch":true,"responsive":{"0":{"items":2,"gutter":10,"controls":false},"800":{"items":3,"gutter":20,"controls":true},"1200":{"items":5,"gutter":24}}}'>
                        @foreach ($data['items'] as $item)
                            <div class="article mb-grid-gutter">
                                <a class="card border-0 h-100" href="{{ url(trim($item->group, '/') . '/' . $item->slug) }}">
                                    <span class="blog-entry-meta-label fs-sm"><i class="fa-duotone fa-books text-primary" aria-hidden="true"></i></span>
                                    <img class="card-img-top" loading="lazy" decoding="async" width="300" height="300" src="{{ $item->thumb }}" alt="Kategorija {{ $item->title }}">
                                    <div class="card-body py-2 text-center px-1">
                                        <h3 class="h6 mt-1 mb-1 font-title text-primary">{{ $item->title }}</h3>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>

            @elseif (in_array(($data['tablename'] ?? ''), ['publisher_list', 'author'], true))
                <div class="tns-carousel tns-nav-enabled widget-content-carousel widget-touch-carousel">
                    <div class="tns-carousel-inner" data-carousel-options='{"items":2,"controls":true,"nav":true,"mouseDrag":true,"touch":true,"responsive":{"0":{"items":2,"gutter":10,"controls":false},"800":{"items":3,"gutter":20,"controls":true},"1200":{"items":5,"gutter":24}}}'>
                        @foreach ($data['items'] as $item)
                            <div>
                                <a class="d-flex align-items-center justify-content-center bg-white border rounded-3 p-3 text-center h-100" href="{{ url($item->url) }}">
                                    @if (! empty($item->image))
                                        <img loading="lazy" decoding="async" src="{{ config('settings.images_domain') . ltrim($item->image, '/') }}" width="150" height="100" style="max-width:150px;height:100px;object-fit:contain" alt="{{ $item->title }}">
                                    @else
                                        <strong class="font-title text-primary">{{ $item->title }}</strong>
                                    @endif
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>

            @elseif (($data['tablename'] ?? '') === 'reviews')
                <div class="tns-carousel tns-nav-enabled widget-content-carousel reviews-widget__carousel widget-touch-carousel pb-3">
                    <div class="tns-carousel-inner" data-carousel-options='{"items":1,"controls":true,"nav":true,"autoplay":true,"autoplayTimeout":7000,"mouseDrag":true,"touch":true,"responsive":{"0":{"items":1,"gutter":16,"nav":false,"controls":false},"576":{"items":2,"gutter":20,"nav":true},"768":{"controls":true},"992":{"items":3,"gutter":24}}}'>
                        @foreach ($data['items'] as $review)
                            @php
                                $reviewProduct = $review->product;
                                $reviewProductUrl = $reviewProduct && $reviewProduct->url ? url($reviewProduct->url) : null;
                                $reviewerName = trim($review->fname . ' ' . $review->lname);
                                $reviewerInitial = mb_strtoupper(mb_substr($reviewerName ?: 'V', 0, 1));
                            @endphp
                            <div class="reviews-widget__slide">
                                <figure class="reviews-widget__card mb-0">
                                    <div class="reviews-widget__quote" aria-hidden="true">
                                        <i class="fa-solid fa-quote-left"></i>
                                    </div>

                                    <div class="star-rating reviews-widget__rating" aria-label="Ocjena {{ (int) $review->stars }} od 5">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="star-rating-icon fa-{{ $i <= (int) $review->stars ? 'solid' : 'regular' }} fa-star{{ $i <= (int) $review->stars ? ' active' : '' }}" aria-hidden="true"></i>
                                        @endfor
                                    </div>

                                    <blockquote class="reviews-widget__text">{{ strip_tags($review->message) }}</blockquote>

                                    <figcaption class="reviews-widget__footer">
                                        <div class="reviews-widget__identity">
                                            <span class="reviews-widget__initial" aria-hidden="true">{{ $reviewerInitial }}</span>
                                            <span class="reviews-widget__author">
                                                <strong>{{ $reviewerName }}</strong>
                                                @if ($review->is_verified_purchase)
                                                    <small><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Potvrđena kupnja</small>
                                                @endif
                                            </span>
                                        </div>

                                        @if ($reviewProduct && $reviewProductUrl)
                                            <a class="reviews-widget__product" href="{{ $reviewProductUrl }}">
                                                <i class="fa-duotone fa-book-open" aria-hidden="true"></i>
                                                <span>{{ $reviewProduct->card_name }}</span>
                                            </a>
                                        @endif
                                    </figcaption>
                                </figure>
                            </div>
                        @endforeach
                    </div>
                </div>

            @else
                <div class="tns-carousel tns-nav-enabled widget-content-carousel widget-touch-carousel pb-4">
                    <div class="tns-carousel-inner" data-carousel-options='{"items":1,"controls":true,"nav":true,"mouseDrag":true,"touch":true,"responsive":{"0":{"items":1,"gutter":15,"controls":false},"576":{"items":2,"gutter":20},"768":{"controls":true},"992":{"items":3,"gutter":30}}}'>
                        @foreach ($data['items'] as $item)
                            <div>
                                <article class="card h-100">
                                    <a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $item]) }}">
                                        <img class="card-img-top" loading="lazy" decoding="async" src="{{ $item->image }}" width="400" height="230" alt="{{ $item->title }}">
                                    </a>
                                    <div class="card-body">
                                        <h3 class="h6 blog-entry-title"><a href="{{ route('catalog.route.blog', ['blog' => $item]) }}">{{ $item->title }}</a></h3>
                                        <p class="fs-sm">{!! \Illuminate\Support\Str::limit(strip_tags($item->short_description), 180) !!}</p>
                                        <div class="fs-xs text-muted">{{ \Illuminate\Support\Carbon::make($item->created_at)->locale('hr')->format('d.m.Y.') }}</div>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
@endif
