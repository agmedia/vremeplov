


<!-- Hero slider-->
<section class="tns-carousel tns-controls-lg mb-0 bg-white">
    <div class="tns-carousel-inner" data-carousel-options="{&quot;mode&quot;: &quot;gallery&quot;, &quot;responsive&quot;: {&quot;0&quot;:{&quot;nav&quot;:false, &quot;controls&quot;: false},&quot;992&quot;:{&quot;nav&quot;:false, &quot;controls&quot;: false}}}">
        <!-- Item-->
        <div class="px-lg-5" style="background-image: url({{ config('settings.images_domain') . 'media/img/pexels-suzy-hazelwood-3765180-1.webp' }});box-shadow: inset 0 0 0 1000px rgba(55, 63, 80,.7);
            -webkit-background-size: cover;
            -moz-background-size: cover;
            -o-background-size: cover;
            background-size: cover;">
            <div class="d-lg-flex justify-content-center align-items-center ps-lg-4">
                <div class="position-relative mx-auto py-5 px-4 mb-lg-5 order-lg-1" style="max-width: 42rem; z-index: 10;">
                    <div class="pb-lg-5 mb-lg-5 pt-lg-5 mt-lg-5 text-center">
                        <p class="fs-lg text-light pb-2 from-bottom delay-0">Poštovani kupci, zbog velikog broja narudžbi molimo vas za strpljenje prilikom obrade. Vaš Žuži team</p>
                        <span class="badge bg-dark fw-semibold fs-lg p-2 mb-4 rounded-1 from-bottom delay-0">ZUZI SHOP</span>
                        <h1 class="text-light display-6 fw-bold from-bottom delay-1">Online knjižara i antikvarijat</h1>
                        <p class="fs-xl text-light pb-0 from-bottom delay-3">Prodaja i otkup rabljenih i novih knjiga</p>
                        <p class="text-light pb-0 from-bottom delay-4">Knjižara: <a class="text-light" href="https://goo.gl/maps/n9hHexFj7vVxUGGi8">Ul. Antuna Šoljana 33, 10090, Zagreb</a></p>
                        <p class="text-light pb-2 from-bottom delay-4"> Radno vrijeme: Pon-Pet: 8-20, Sub: 9-15</p>

                        <div class="scale-up delay-5 mx-auto mx-lg-0"><a class="btn btn-primary" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}">Pogledajte ponudu<i class="ci-arrow-right ms-2 me-n1"></i></a></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Item-->



    </div>
    <section class="spikeswtop"></section>
</section>

<section class="bg-white " >
    <div class="container py-4  ">
    <h2 class="text-center fw-bold pt-0">Popularne kategorije</h2>
    <p class="text-muted text-center mb-5">Odaberite željeni naslov iz jedne od naših kategorija</p>
        <div class="tns-carousel">
            <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "autoHeight": false, "responsive": {"0":{"items":2, "gutter": 20},"740":{"items":2, "gutter": 20},"900":{"items":3, "gutter": 20}, "1100":{"items":4, "gutter": 30}}}'>
                @foreach ($kategorijefeatured as $cat)
                    <div class="article mb-grid-gutter">
                        <a class="card border-0 shadow" href="{{ url(\Illuminate\Support\Str::slug($cat->group) . '/' . $cat->slug) }}">
                            <span class="blog-entry-meta-label fs-sm"><i class="ci-heart text-primary me-0"></i></span>
                            <img class="card-img-top" loading="lazy" width="310" height="310" src="{{ $cat->image }}" alt="{{ $cat->title }}">
                            <div class="card-body py-3 text-center">
                                <h3 class="h6 mt-1 text-primary">{{ $cat->title }}</h3>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>

<section class=" pb-0 pt-lg-0 ">
    <div class="container">
        <div class="row pt-0 mt-2 mt-lg-3 mb-md-2">
            <div class="col-lg-12 mb-grid-gutter">
                <div class="d-block d-sm-flex justify-content-between align-items-center bg-dark rounded-3 shadow">
                    <div class="pt-5 py-sm-5 px-4 ps-md-5 mb-3 text-center text-sm-start">

                        <span class="badge bg-black fw fs-lg p-2 mb-4 rounded-1 from-bottom delay-0">Posebna ponuda</span>
                        <p class="fs-xl fw-bold text-light pb-0 from-bottom delay-3">Stephen King: Kula tmine II i III </p>
                        <p class="fs-xl text-light pb-0 from-bottom delay-3"><small>+ GRATIS knjiga na poklon: Gospodar prstenova - dvije kule</small></p>
                        <p class=" h4 pb-2 font-bold mb-3 text-white"><small>Prije:</small> 48,71 €   <small>Sada:</small> 13,27 €</p>
                        <a class="btn mb-3 btn-primary" href="https://www.zuzi.hr/kategorija-proizvoda/beletristika/stephen-king-kula-tmine-iiiii-gospodar-prstenova-dvije-kule">Pogledajte ponudu <i class="ci-arrow-right ms-2 me-n1"></i></a>
                    </div>
                   <a href="https://www.zuzi.hr/kategorija-proizvoda/beletristika/stephen-king-kula-tmine-iiiii-gospodar-prstenova-dvije-kule"> <img class="d-block mx-auto mx-sm-0 rounded-end rounded-xs pb-4 pb-sm-0" src="{{ config('settings.images_domain') . 'media/img/stephen-king-kula.webp' }}"  width="480" alt="Knjige Stephena Kinga"> </a>
                </div>
            </div>

        </div>
    </div>
</section>
