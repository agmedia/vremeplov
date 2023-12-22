<!-- {"title": "Page Carousel", "description": "Category, Publisher, Reviews."} -->
<section class=" container py-3 " >
    <div class="d-flex flex-wrap justify-content-between align-items-center pt-1  pb-3 mb-3">
        <h2 class="h3 mb-0 pt-3 font-title me-3"> {{ $data['title'] }}</h2>

        <a class="btn btn-outline-primary btn-sm  mt-3" href="{{ route('catalog.route', ['group' => '/knjige']) }}"><span class="d-none d-sm-inline-block">Pogledajte sve</span> <i class="ci-arrow-right "></i></a>
    </div>

    @if ($data['tablename'] == 'category')
            <div class="tns-carousel">
                <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "autoHeight": false, "responsive": {"0":{"items":2, "gutter": 10},"480":{"items":2, "gutter": 10},"800":{"items":3, "gutter": 20}, "1300":{"items":4, "gutter": 30}, "1440":{"items":5, "gutter": 30}}}'>
                @foreach ($data['items'] as $item)
                    <!-- Product-->
                        <div class="article mb-grid-gutter">
                            <a class="card border-0 shadow" href="{{ $item['group'] }}/{{ $item['slug'] }}">
                                <span class="blog-entry-meta-label fs-sm"><i class="ci-book text-primary me-0"></i></span>
                                <img class="card-img-top p-0" loading="lazy" width="300" height="300" src="{{ $item['thumb'] }}" alt="Kategorija {{ $item['title'] }}">
                                <div class="card-body py-2 text-center px-0">
                                    <h3 class="h6 mt-1 font-title text-primary">{{ $item['title'] }}</h3>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

    @elseif ($data['tablename'] == 'publisher')
        <div class="row pb-2 pb-sm-0 pb-md-3">
            @foreach ($data['items'] as $item)
            <div class="col-md-3 col-sm-4 col-6"><a class="d-block bg-white shadow-sm rounded-3 py-3 py-sm-4 mb-grid-gutter" href="{{ $item['url'] }}"><img loading="lazy" class="d-block mx-auto" src="{{ $item['image'] }}" style="width: 150px;" alt="{{ $item['title'] }}"></a></div>
            @endforeach
        </div>

    @elseif ($data['tablename'] == 'reviews')

        <div class="tns-carousel">
            <div class="tns-carousel-inner" data-carousel-options='{"items": 1, "controls": false, "autoplay": true, "autoHeight": true, "responsive": {"0":{"items":1, "gutter": 20},"480":{"items":2, "gutter": 20},"800":{"items":3, "gutter": 20}, "1300":{"items":4, "gutter": 30}}}'>
            @foreach ($data['items'] as $review)

                    <blockquote class="mb-2">
                        <div class="card card-body fs-md text-muted border-0 shadow-sm">
                            <div class="mb-2">
                                <div class="star-rating"> @for ($i = 0; $i < 5; $i++)
                                        @if (floor($review->stars) - $i >= 1)
                                            {{--Full Start--}}
                                            <i class="star-rating-icon ci-star-filled active"></i>
                                        @elseif ($review->stars - $i > 0)
                                            {{--Half Start--}}
                                            <i class="star-rating-icon ci-star"></i>
                                        @else
                                            {{--Empty Start--}}
                                            <i class="star-rating-icon ci-star"></i>
                                        @endif
                                    @endfor
                                </div>
                            </div>{{ strip_tags($review->message) }}
                        </div>
                        <footer class="d-flex justify-content-center align-items-center pt-4">
                            <div class="ps-3">
                                <p class="fs-sm fw-bold text-default mb-n1">{{ $review->fname }} {{ $review->lname }}</p>
                            </div>
                        </footer>
                    </blockquote>



                @endforeach
            </div>
        </div>

    @else

        <div class="tns-carousel pb-5">
            <div class="tns-carousel-inner" data-carousel-options="{&quot;items&quot;: 2, &quot;gutter&quot;: 15, &quot;controls&quot;: false, &quot;nav&quot;: true, &quot;responsive&quot;: {&quot;0&quot;:{&quot;items&quot;:1},&quot;500&quot;:{&quot;items&quot;:2},&quot;768&quot;:{&quot;items&quot;:2}, &quot;992&quot;:{&quot;items&quot;:3, &quot;gutter&quot;: 30}}}">
                @foreach ($data['items'] as $item)
                    <!-- Product-->
                    <div>
                        <div class="card"><a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $item]) }}"><img class="card-img-top" load="lazy" src="{{ $item['image'] }}" width="400" height="230" alt="{{ $item['title'] }}"></a>
                            <div class="card-body">
                                <h2 class="h6 blog-entry-title"><a href="{{ route('catalog.route.blog', ['blog' => $item]) }}">{{ $item['title'] }}</a></h2>
                                <p class="fs-sm">{{ $item['short_description'] }}</p>
                                <div class="fs-xs text-nowrap"><a class="blog-entry-meta-link text-nowrap" href="#">{{ \Carbon\Carbon::make($item['created_at'])->locale('hr')->format('d.m.Y.') }}</a></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>


    @endif



</section>
