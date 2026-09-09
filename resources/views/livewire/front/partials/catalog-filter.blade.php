<aside class="col-lg-4">
    <!-- Sidebar-->
    <div class="offcanvas offcanvas-collapse bg-white w-100 rounded-3 shadow-lg py-1 pt-0" id="shop-sidebar" style="max-width: 22rem;" wire:ignore.self>
        <div class="offcanvas-cap align-items-center shadow-sm" wire:ignore>
            <h2 class="h5 mb-0">Filtriraj</h2>
            <button class="btn-close ms-auto" type="button" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body py-grid-gutter px-lg-grid-gutter">
            <!-- Categories-->
            @if ( ! empty($categories))
                <div class="widget widget-categories mb-4 pb-4 border-bottom">
                    @if (! $subcategory)
                        <h3 class="widget-title">Kategorije</h3>
                    @else
                        <h3 class="widget-title">Podkategorije</h3>
                    @endif
                    <div class="accordion mt-n1" id="shop-categories">
                        @foreach ($categories as $category)
                            <h3 class="accordion-header">
                                <a href="{{ $category['url'] }}" class="accordion-button py-2 none collapsed" role="link">
                                    {{ $category['title'] }} <span class="badge bg-secondary ms-2 position-absolute end-0">{{ $category['count'] }}</span>
                                </a>
                            </h3>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Date range-->
            <div class="widget mb-4 pb-4 border-bottom">
                <h3 class="widget-title">Godina izdanja</h3>
                <div >
                    <div class="d-flex pb-1">
                        <div class="w-50 pe-2 me-2">
                            <div class="input-group input-group-sm">
                                <input class="form-control range-slider-value-min" placeholder="Od" type="text" wire:model="start">
                                <span class="input-group-text">g</span>
                            </div>
                        </div>
                        <div class="w-50 ps-2">
                            <div class="input-group input-group-sm">
                                <input class="form-control range-slider-value-max" placeholder="Do" type="text" wire:model="end">
                                <span class="input-group-text">g</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter by Brand-->
            <div class="widget widget-filter mb-4 pb-4 border-bottom d-none d-sm-block">
                <h3 class="widget-title">Autor</h3>
                <div class="input-group input-group-sm mb-2 autocomplete">
                    <input type="search" wire:model.debounce.300ms="searcha" class=" form-control rounded-end pe-5" placeholder="Pretraži autora"><i class="fa-regular fa-magnifying-glass position-absolute top-50 end-0 translate-middle-y fs-sm me-3"></i>
                    @if ( ! empty($authors))
                        <div id="myInputautocomplete-list" class="autocomplete-items">
                            @forelse($authors as $author)
                                <div wire:click="resolveRoute('{{ url($author->url) }}')">
                                    {{ $author->title }}<span class="fs-xs text-muted float-right"></span>
                                    <span class="badge bg-secondary ms-2 position-absolute end-0 me-2">{{ $author->products_count }}</span>
                                </div>
                            @empty
                                <div>Nema autora prema upitu</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>


            <!-- Filter by NAkladnik-->
            <div class="widget widget-filter mb-4 pb-4 border-bottom d-none d-sm-block">
                <h3 class="widget-title">Nakladnici</h3>
                <div class="input-group input-group-sm mb-2 autocomplete">
                    <input type="search" wire:model.debounce.300ms="searchp" class=" form-control rounded-end pe-5" placeholder="Pretraži nakladnika"><i class="fa-regular fa-magnifying-glass position-absolute top-50 end-0 translate-middle-y fs-sm me-3"></i>
                    @if ( ! empty($publishers))
                        <div id="myInputautocomplete-list" class="autocomplete-items">
                            @forelse($publishers as $publisher)
                                <div wire:click="resolveRoute('{{ url($publisher->url) }}')">
                                    {{ $publisher->title }}
                                    <span class="badge bg-secondary ms-2 position-absolute end-0 me-2">{{ $publisher->products_count }}</span>
                                </div>
                            @empty
                                <div>Nema nakladnika prema upitu</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>

            <button type="button" onclick="cleanURL();" class="btn btn-primary mt-4"><i class=" fa-regular fa-trash-can"></i> Očisti sve</button>

            @if (strpos(Request::path(), 'zemljovidi-i-vedute') !== false && Request::path() != 'zemljovidi-i-vedute')
                <a href="{{ route('catalog.route', ['group' => 'zemljovidi-i-vedute']) }}" class="btn btn-secondary ms-2 mt-4"><i class="fa-regular fa-arrow-left me-2"></i>Povratak</a>

                @elseif (strpos(Request::path(), \App\Helpers\Helper::categoryGroupPath(true)) !== false && Request::path() != config('settings.group_path'))
             <a href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}" class="btn btn-secondary ms-2 mt-4"><i class="fa-regular fa-arrow-left me-2"></i>Povratak</a>
            @endif
</div>
</div>
</aside>

