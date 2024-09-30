<!-- Footer-->

<section class=" pt-3 pb-2 " style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">

    <div class="container pt-lg-1">
     <div class="row pt-4 text-center">
         <div class="row pt-lg-2 text-left px-3 px-sm-1">

             <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter"><div class="d-inline-flex align-items-top-center text-start"><i class="ci-book text-primary" style="font-size: 2.6rem;"></i> <div class="ps-3"><p class="text-dark fw-bold fs-base mb-1">Preko 50000 artikala</p> <p class="text-dark fs-ms opacity-70 mb-0">Velika kolekcija naslova </p></div></div></div>

             <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter"><div class="d-inline-flex align-items-top-center text-start"><i class="ci-gift text-primary" style="font-size: 2.6rem;"></i> <div class="ps-3"><p class="text-dark fw-bold fs-base mb-1">Besplatna dostava</p> <p class="text-dark fs-ms opacity-70 mb-0">Za narudžbe iznad 70 €</p></div></div></div>


             <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter">

                 <div class="d-inline-flex align-items-top-center text-start"><i class="ci-truck text-primary" style="font-size: 2.6rem;"></i> <div class="ps-3"><p class="text-dark fw-bold fs-base mb-1">Brza dostava</p> <p class="text-dark fs-ms opacity-70 mb-0">Hrvatska pošta - naš partner u dostavi</p></div></div></div>

             <div class="col-lg-3 col-sm-6 col-6 mb-grid-gutter"><div class="d-inline-flex align-items-top-center text-start"><i class="ci-security-check text-primary" style="font-size: 2.6rem;"></i> <div class="ps-3"><p class="text-dark fw-bold fs-base mb-1">Sigurna kupovina</p> <p class="text-dark fs-ms opacity-70 mb-0">SSL certifikat i WSPay</p></div></div></div>



         </div>

     </div>

    </div>
</section>


<footer class="bg-dark pt-sm-5"  style="background-image: url({{ config('settings.images_domain') . 'media/img/footer-vintage-bg.jpg' }});background-repeat: repeat;">



    <div class="container pt-2 pb-3">
        <div class="row">
            <div class="col-md-3  text-center text-md-start mb-4">

                <h3 class="widget-title fw-700 d-none d-md-block text-white"><span>Antikvarijat Vremeplov</span></h3>
                <p class=" text-white  fs-md pb-1 d-none d-sm-block">

                    <strong>Adresa</strong><br>Radoslava Lopašića br.11<br> 10000 Zagreb</p>


                <p class=" text-white  fs-md pb-1 d-none d-sm-block">  <strong>Broj telefona</strong><br>
                  091 762 7441</p>

                <p class=" text-white  fs-md pb-1 d-none d-sm-block">  <strong>Radno vrijeme
                       </strong><br>
                    Pon-Pet: 09 -14h i 16 - 19h<br>
                    Sub: 10 - 13h

                </p>


                <div class="widget mt-4 text-md-nowrap text-center text-sm-start">
                    <a class="btn-social bs-light bg-primary bs-instagram me-2 mb-2" aria-label="Follow us on instagram" href="https://www.instagram.com/antiqueshopvremeplov/"><i class="ci-instagram"></i></a>
                    <a class="btn-social bs-light bg-primary bs-facebook me-2 mb-2" aria-label="Follow us on facebook" href="https://www.facebook.com/antikavrijatvremeplov"><i class="ci-facebook"></i></a>
                </div>
            </div>
            <!-- Mobile dropdown menu (visible on screens below md)-->
            <div class="col-12 d-md-none text-center mb-sm-4 pb-2">
                <div class="btn-group dropdown d-block mx-auto mb-3">
                    <button class="btn btn-outline-light border-light dropdown-toggle" type="button" data-bs-toggle="dropdown">Uvjeti kupnje</button>
                    <ul class="dropdown-menu my-1">
                        @foreach ($uvjeti_kupnje as $page)
                            <li><a class="dropdown-item" href="{{ route('catalog.route.page', ['page' => $page]) }}">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <!-- Desktop menu (visible on screens above md)-->
            <div class="col-md-3 d-none d-md-block text-center text-md-start mb-4">
                <div class="widget widget-links widget-light pb-2">
                    <h3 class="widget-title fw-700 text-white"><span>Iz ponude</span></h3>
                    <ul class="widget-list">

                        <li class="widget-list-item"><a class="widget-list-link" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}">Web shop</a></li>
                        <li class="widget-list-item"><a class="widget-list-link" href="{{ route('catalog.route', ['group' => '/knjige']) }}">Sve knjige</a>
                        <li class="widget-list-item"><a class="widget-list-link" href="{{ route('catalog.route.author') }}">Autori</a>
                        <li class="widget-list-item"><a class="widget-list-link" href="{{ route('catalog.route.actions') }}">Akcije</a>
                        <li class="widget-list-item"><a class="widget-list-link" href="{{ route('catalog.route.blog') }}">Blog</a></li>
                        <li class="widget-list-item"><a class="widget-list-link" href="{{ route('kontakt') }}">Kontakt</a></li>
                    </ul>
                </div>
            </div>

            <!-- Desktop menu (visible on screens above md)-->
            <div class="col-md-3 d-none d-md-block text-center text-md-start mb-4">
                <div class="widget widget-links widget-light pb-2">
                    <h3 class="widget-title fw-700 text-white"><span>Informacije</span></h3>
                    <ul class="widget-list">
                        @foreach ($uvjeti_kupnje as $page)
                            <li class="widget-list-item"><a class="widget-list-link" href="{{ route('catalog.route.page', ['page' => $page]) }}">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="col-md-3 d-none d-md-block text-center text-md-start mb-4">
                <div class="widget widget-links widget-light pb-2">
                    <h3 class="widget-title fw-700 text-white"><span>Načini plaćanja</span></h3>
                    <ul class="widget-list  ">
                        <li class="widget-list-item"><a href="info/nacini-placanja" class="widget-list-link" > kreditnom karticom jednokratno ili na rate</a></li>
                        <li class="widget-list-item"><a href="info/nacini-placanja" class="widget-list-link" > virmanom / općom uplatnicom / internet bankarstvom</a></li>
                        <li class="widget-list-item"><a href="info/nacini-placanja" class="widget-list-link" >gotovinom prilikom pouzeća</a></li>
                       <!-- <li class="widget-list-item"><a href="info/nacini-placanja" class="widget-list-link" >paypal-om</a></li>-->
                        <li class="widget-list-item"><a href="info/nacini-placanja" class="widget-list-link" >osobno preuzimanje i plaćanje u antikvarijatu</a></li>
                    </ul>

                </div>
            </div>
        </div>
    </div>
    <!-- Second row-->
    <div class="pt-0 bg-dark">


        <div class="container">



            <div class="d-md-flex justify-content-between pt-4 align-items-center">
                <div class="pb-4 fs-sm text-white  text-center text-md-start "><p class="mb-0">© 2023. Sva prava pridržana Antikvarijat Vremeplov. Web by <a class="text-white" title="Izrada web shopa - B2C ili B2B web trgovina - AG media" href="https://www.agmedia.hr/usluge/izrada-web-shopa/" target="_blank" rel="noopener">AG media</a></p></div>
                <div class="widget widget-links widget-light pb-4 text-center text-md-end">
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/visa.svg" width="55" height="35" alt="Visa"/>
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/maestro.svg" width="55" height="35" alt="Maestro"/>
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/mastercard.svg" width="55" height="35" alt="MasterCard"/>
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/diners.svg" width="55" height="35" alt="Diners"/>

              <!--      <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/paypal.svg" width="55" height="35" alt="Diners"/> -->


                </div>
            </div>
        </div>
    </div>
</footer>
