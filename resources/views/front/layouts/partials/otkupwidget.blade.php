



<!-- Become Courier / Partner CTA-->
<section class=" pb-4 pt-lg-0 pb-sm-5">
    <div class="container">
    <div class="row pt-4 mt-2 mt-lg-3 mb-md-2">
        <div class="col-lg-6 mb-grid-gutter">
            <div class="d-block d-sm-flex justify-content-between align-items-center bg-dark rounded-3 shadow">
                <div class="pt-5 py-sm-5 px-4 ps-md-5 mb-3 text-center text-sm-start">
                    <h2 class="h3 text-white fw-bold">Rijetke knjige</h2>
                    <p class=" pb-2 text-white">Pogledajte našu kolekeciju popularnih i rijetkih naslova.</p><a class="btn mb-3 btn-primary" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true) . '/rijetke-knjige']) }}">Pogledajte ponudu <i class="ci-arrow-right ms-2 me-n1"></i></a>
                </div>
                <img class="d-block mx-auto mx-sm-0 rounded-end rounded-xs pb-4 pb-sm-0" src="{{ config('settings.images_domain') . 'media/img/rijetke.webp' }}"  width="250" alt="Rijetke knjige">
            </div>
        </div>
        <div class="col-lg-6 mb-grid-gutter">
            <div class="d-block d-sm-flex justify-content-between align-items-center bg-dark rounded-3 shadow">
                <div class="pt-5 py-sm-5 px-4 ps-md-5 mb-3   text-center text-sm-start">
                    <h2 class="h3 fw-bold text-white">Svezalice - pidžame za knjige</h2>
                    <p class="text-white pb-2">Za praktičnije čuvanje knjige, ručno izrađeno s puno ljubavi.

                        </p><a class="btn btn-primary mb-3" href="https://www.zuzi.hr/kategorija-proizvoda/svezalice-pidzame-za-knjige">Pogledajte ponudu <i class="ci-arrow-right ms-2 me-n1"></i></a>
                </div><img class="d-block mx-auto mx-sm-0 rounded-end rounded-xs pb-sm-0 pb-4" src="{{ config('settings.images_domain') . 'media/img/svezalice.webp' }}" width="250" alt="Stare knjige i uvezi">
            </div>
        </div>
    </div>
    </div>
</section>

<section class="spikesw"></section>
<section class="bg-dark bg-size-cover bg-position-center pt-5 pb-4" >
    <div class="container pt-lg-1">

        <div class="row pt-lg-2 text-center">
            <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter">
                <div class="d-inline-flex align-items-center text-start"><i class="ci-truck text-primary" style="font-size: 3rem;"></i>
                    <div class="ps-3">
                        <p class="text-light fw-bold fs-base mb-1">Brza dostava</p>
                        <p class="text-light fs-ms opacity-70 mb-0">Unutar 5 radnih dana</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter">
                <div class="d-inline-flex align-items-center text-start"><i class="ci-security-check text-primary" style="font-size: 3rem;"></i>
                    <div class="ps-3">
                        <p class="text-light fw-bold fs-base mb-1">Sigurna kupovina</p>
                        <p class="text-light fs-ms opacity-70 mb-0">SSL certifitikat i CorvusPay</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter">
                <div class="d-inline-flex align-items-center text-start"><i class="ci-bag text-primary" style="font-size: 3rem;"></i>
                    <div class="ps-3">
                        <p class="text-light fw-bold fs-base mb-1">Besplatna dostava</p>
                        <p class="text-light fs-ms opacity-70 mb-0">Za narudžbe iznad 70€</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter">
                <div class="d-inline-flex align-items-center text-start"><i class="ci-locked text-primary" style="font-size: 3rem;"></i>
                    <div class="ps-3">
                        <p class="text-light fw-bold fs-base mb-1">Zaštita kupca</p>
                        <p class="text-light fs-ms opacity-70 mb-0">Od narudžbe pa sve do dostave</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="spikesg"></section>
<!-- Reviews-->
<section class="bg-secondary py-5" style="background-image: url({{ config('settings.images_domain') . 'media/img/zuzi-bck-transparent.svg' }});z-index: 10;-webkit-background-size: cover;-moz-background-size: cover;-o-background-size: cover;background-size: cover;background-position: bottom">
    <div class="container py-md-4 pt-3 pb-0 py-sm-3">
        <h2 class="text-center text-dark fw-bold mb-4 mb-md-5">Komentari kupaca</h2>


        <div class="tns-carousel mb-3">
            <div class="tns-carousel-inner" data-carousel-options="{&quot;items&quot;: 2, &quot;controls&quot;: false, &quot;responsive&quot;: {&quot;0&quot;:{&quot;items&quot;:1, &quot;gutter&quot;:20}, &quot;576&quot;:{&quot;items&quot;:2, &quot;gutter&quot;:20},&quot;850&quot;:{&quot;items&quot;:3, &quot;gutter&quot;:20},&quot;1080&quot;:{&quot;items&quot;:3, &quot;gutter&quot;:25}}}">
                <blockquote class="mb-2">
                    <div class="card card-body fs-md  border-0 shadow-sm">
                        <div class="mb-2">
                            <div class="star-rating"><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i>
                            </div>
                        </div>Vrlo ljubazni, uslužni, pouzdani, često izađu u susret. Knjige očuvane i dobro zapakirane prilikom slanja. Surađujemo već dugo i svima preporuke.

                        <div class="pt-3">
                            <p class="fs-sm mb-n1">Helena J.</p>
                        </div>
                    </div>

                </blockquote>

                <blockquote class="mb-2">
                    <div class="card card-body fs-md  border-0 shadow-sm">
                        <div class="mb-2">
                            <div class="star-rating"><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i>
                            </div>
                        </div>Odlična trgovina, vrhunska usluga i ljubazni prodavači. Svaka preporuka.

                        <div class="pt-3">
                            <p class="fs-sm mb-n1">Katarina H.</p>
                        </div>
                    </div>

                </blockquote>
                <blockquote class="mb-2">
                    <div class="card card-body fs-md  border-0 shadow-sm">
                        <div class="mb-2">
                            <div class="star-rating"><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i>
                            </div>
                        </div>Predivan mali antikvarijat, sa jako ljubaznim i susretljivim prodavačima, kao stvoren za prave ljubitelje knjige.

                        <div class="pt-3">
                            <p class="fs-sm mb-n1">Mija S.</p>
                        </div>
                    </div>

                </blockquote>

                <blockquote class="mb-2">
                    <div class="card card-body fs-md  border-0 shadow-sm">
                        <div class="mb-2">
                            <div class="star-rating"><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i>
                            </div>
                        </div>Napokon i u Španskom mjesto za knjigoljupce, knjigu koju sam tražila po cijelom gradu našla samo kod njih!

                        <div class="pt-3">
                            <p class="fs-sm mb-n1">Kornelija B.</p>
                        </div>
                    </div>

                </blockquote>

                <blockquote class="mb-2">
                    <div class="card card-body fs-md  border-0 shadow-sm">
                        <div class="mb-2">
                            <div class="star-rating"><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i>
                            </div>
                        </div>Dečko mi je naručio knjigu koju isprva nismo mogli pronaći ali Vi ste ju naravno imali na stanju. Brza dostava, uredno zapakirano i najbitnije odličan izbor knjiga. Vidi se da volite to što radite. Velika pohvala i topla preporuka svima

                        <div class="pt-3">
                            <p class="fs-sm mb-n1">Valentina J.</p>
                        </div>
                    </div>

                </blockquote>

            </div>
        </div>
    </div>
</section>
