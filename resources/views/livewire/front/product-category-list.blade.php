<section class="col-lg-8">
    @php
        $analyticsItems = $products->getCollection()->values()->map(function ($product, $index) {
            $item = \App\Models\TagManager::getGoogleProductDataLayer($product, false);
            $item['index'] = $index;
            return $item;
        })->all();
    @endphp
    @if (! empty($analyticsItems))
        <script>
            if (!window.__vremeplovInitialProductListTracked) {
                window.__vremeplovInitialProductListTracked = true;
                window.VremeplovAnalytics.track('view_item_list', {
                    ecommerce: {
                        item_list_name: @json(request()->routeIs('pretrazi') ? 'Rezultati pretrage' : 'Katalog'),
                        items: @json($analyticsItems)
                    }
                });
            }
        </script>
    @endif
    <!-- Toolbar-->
    <div class="d-flex justify-content-center justify-content-sm-between align-items-center pt-2 pb-4 pb-sm-5">
        <div class="d-flex flex-wrap">
            <div class="dropdown me-2 d-md-none"><a class="btn btn-primary dropdown-toggle collapsed" href="#shop-sidebar" data-bs-toggle="collapse" aria-expanded="false"><i class="ci-filter-alt"></i></a></div>
            <div class="d-flex align-items-center flex-nowrap me-3 me-sm-4 pb-3">
                <label class="text-light opacity-75 text-nowrap fs-sm me-2 d-none d-sm-block" for="sorting"></label>
                <select class="form-select" id="sorting-select" wire:ignore>
                    <option value="" selected>Sortiraj</option>
                    @foreach (config('settings.sorting_list') as $item)
                        <option value="{{ $item['value'] }}" @if(request()->get('sort') == $item['value']) selected @endif>{{ $item['title'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
      <!--  <div class="d-flex pb-3"><a class="nav-link-style nav-link-light me-3" href="#"><i class="ci-arrow-left"></i></a><span class="fs-md text-light">{{ $products->currentPage() }} / {{ $products->lastPage() }}</span><a class="nav-link-style nav-link-light ms-3" href="#"><i class="ci-arrow-right"></i></a></div>-->

        <div class="d-flex pb-3">  <span class="fs-sm text-light btn btn-primary btn-sm text-nowrap ms-2 d-none d-sm-block">Ukupno {{ $products->total() }} artikala</span></div>


    </div>
    <!-- Products grid-->
    <div class="row mx-n2">
        @forelse ($products as $product)
            <div class="col-md-4 col-6  px-2 mb-4">
                @include('front.catalog.category.product')
            </div>
        @empty
            <div class="col-md-12 px-2 mb-4">
                @php
                   $name = Route::currentRouteName()
                @endphp
                @if ($name == 'pretrazi')



                    <h2>Nema rezultata pretrage</h2>

                    <p> Vaša pretraga za  <mark>"{{ $searchterm = request()->input('pojam') }}"</mark> pronašla je 0 rezultata.</p>

                    <h4 class="h5">Savjeti i smjernica</h4>

                    <ul class="list-style">
                        <li>Dvaput provjerite pravopis.</li>
                        <li>Ograničite pretragu na samo jedan ili dva pojma.</li>
                        <li>Budite manje precizni u terminologiji. Koristeći više općenitih termina prije ćete doći do sličnih i povezanih proizvoda.</li>
                    </ul>
                    <hr class="d-sm-none">


                @elseif ($name == 'catalog.route.actions')

                    <h2>Trenutno nema artikala na sniženju</h2>

                    <p> Navratite nek drugi put :-)</p>


                @else

                    <h2>Trenutno nema proizvoda</h2>

                    <p> Pogledajte u nekoj drugoj kategoriji ili probajte sa tražilicom :-)</p>

                    <hr class="d-sm-none">

                @endif


            </div>
        @endforelse
    </div>

    {{ $products->onEachSide(1)->links() }}




</section>
