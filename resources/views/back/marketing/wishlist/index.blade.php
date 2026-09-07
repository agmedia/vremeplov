@extends('back.layouts.backend')

@section('content')
    @php
        $canSendWishlist = auth()->user() && (
            Bouncer::is(auth()->user())->an('master') || Bouncer::is(auth()->user())->an('admin')
        );
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <div class="font-size-sm font-w600 text-uppercase text-warning mb-1">
                        <i class="fa fa-heart mr-1"></i> Marketing
                    </div>
                    <h1 class="font-size-h2 font-w600 mb-1">Liste želja</h1>
                    <div class="text-muted">Pratite interes kupaca i ručno pošaljite obavijest kada se knjiga vrati na zalihu.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">
                    <i class="fa fa-chart-line text-warning mr-2"></i>
                    Liste želja i najtraženiji artikli
                    <span class="font-size-sm text-muted ml-2">{{ $statistics['ready'] }} spremno za ručno slanje</span>
                </h3>
                <div class="block-options">
                    <a class="btn btn-outline-secondary" href="{{ route('wishlists', ['tab' => $activeTab]) }}">
                        <i class="fa fa-times mr-1"></i> Očisti filtere
                    </a>
                </div>
            </div>

            <div class="block-content">
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="fa fa-info-circle mr-2"></i>
                    <div>Wishlist mailovi ne šalju se automatski. Gornje srce prikazuje koliko ih je spremno, a svako slanje potvrđujete ručno.</div>
                </div>

                <ul class="nav nav-tabs nav-tabs-block" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'wishlists' ? 'active' : '' }}"
                           href="{{ route('wishlists', array_merge(request()->except(['wishlists_page', 'top_page']), ['tab' => 'wishlists'])) }}">
                            Sve liste želja
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'top-products' ? 'active' : '' }}"
                           href="{{ route('wishlists', array_merge(request()->except(['wishlists_page', 'top_page']), ['tab' => 'top-products'])) }}">
                            Najtraženiji artikli
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'statistics' ? 'active' : '' }}"
                           href="{{ route('wishlists', ['tab' => 'statistics']) }}">
                            Statistike
                        </a>
                    </li>
                </ul>

                @if ($activeTab === 'wishlists')
                    <div class="pt-4">
                        <form method="get" action="{{ route('wishlists') }}" class="mb-4">
                            <input type="hidden" name="tab" value="wishlists">
                            <div class="form-row align-items-end">
                                <div class="form-group col-lg-8">
                                    <label for="wishlist-search" class="font-w600">Pretraživanje</label>
                                    <input id="wishlist-search" type="text" class="form-control" name="search"
                                           value="{{ request()->input('search') }}"
                                           placeholder="Pretraži po nazivu, šifri artikla ili e-mailu">
                                </div>
                                <div class="form-group col-lg-3">
                                    <label for="wishlist-stock" class="font-w600">Filtriraj stanje</label>
                                    <select id="wishlist-stock" class="form-control" name="stock">
                                        <option value="all" {{ $stock === 'all' ? 'selected' : '' }}>Sva stanja</option>
                                        <option value="ready" {{ $stock === 'ready' ? 'selected' : '' }}>Spremno za slanje</option>
                                        <option value="waiting" {{ $stock === 'waiting' ? 'selected' : '' }}>Čeka zalihu</option>
                                        <option value="sent" {{ $stock === 'sent' ? 'selected' : '' }}>Poslano</option>
                                    </select>
                                </div>
                                <div class="form-group col-lg-1">
                                    <button type="submit" class="btn btn-primary btn-block" title="Primijeni filter">
                                        <i class="fa fa-filter"></i>
                                    </button>
                                </div>
                            </div>
                        </form>

                        @if ($canSendWishlist)
                            <form id="wishlist-bulk-form" method="post" action="{{ route('wishlists.send-selected') }}"
                                  onsubmit="return confirm('Poslati obavijesti za sve odabrane kupce?');">
                                @csrf
                                <div class="bg-body-light border rounded p-3 mb-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between">
                                    <div>
                                        <div class="custom-control custom-checkbox d-inline-block mr-3">
                                            <input type="checkbox" class="custom-control-input" id="wishlist-select-all">
                                            <label class="custom-control-label font-w600" for="wishlist-select-all">Odaberi sve spremne</label>
                                        </div>
                                        <span class="text-muted">Odabrano: <strong id="wishlist-selected-count">0</strong></span>
                                    </div>
                                    <button id="wishlist-send-selected" type="submit" class="btn btn-primary mt-3 mt-sm-0" disabled>
                                        <i class="fa fa-paper-plane mr-1"></i> Pošalji odabrano
                                    </button>
                                </div>
                            </form>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-borderless table-striped table-vcenter">
                                <thead>
                                <tr>
                                    @if ($canSendWishlist)<th style="width: 42px;"></th>@endif
                                    <th style="width: 80px;">Slika</th>
                                    <th>Artikl</th>
                                    <th>Šifra</th>
                                    <th class="text-center">Stanje</th>
                                    <th>E-mail</th>
                                    <th>Status</th>
                                    <th>Dodano</th>
                                    <th>Poslano</th>
                                    @if ($canSendWishlist)<th class="text-right">Akcija</th>@endif
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($wishlists as $wishlist)
                                    @php($ready = $wishlist->isReadyToSend())
                                    <tr>
                                        @if ($canSendWishlist)
                                            <td>
                                                @if ($ready)
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input js-wishlist-checkbox"
                                                               id="wishlist-{{ $wishlist->id }}" name="wishlist_ids[]"
                                                               value="{{ $wishlist->id }}" form="wishlist-bulk-form">
                                                        <label class="custom-control-label" for="wishlist-{{ $wishlist->id }}"><span class="sr-only">Odaberi</span></label>
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                        <td>
                                            @if(optional($wishlist->product)->image)
                                                <img src="{{ $wishlist->product->image }}" height="60" alt="">
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="font-w600">{{ optional($wishlist->product)->name ?? 'Artikl više ne postoji' }}</td>
                                        <td>{{ optional($wishlist->product)->sku ?? '—' }}</td>
                                        <td class="text-center">{{ $wishlist->product ? $wishlist->product->quantity : '—' }}</td>
                                        <td>{{ $wishlist->email }}</td>
                                        <td>
                                            @if((int) $wishlist->sent === 1)
                                                <span class="badge badge-success">Poslano</span>
                                            @elseif($ready)
                                                <span class="badge badge-primary">Spremno za slanje</span>
                                            @elseif((int) $wishlist->status === 1)
                                                <span class="badge badge-warning">Čeka zalihu</span>
                                            @else
                                                <span class="badge badge-secondary">Neaktivno</span>
                                            @endif
                                        </td>
                                        <td>{{ optional($wishlist->created_at)->format('d.m.Y. H:i') ?? '—' }}</td>
                                        <td>{{ optional($wishlist->sent_at)->format('d.m.Y. H:i') ?? '—' }}</td>
                                        @if ($canSendWishlist)
                                            <td class="text-right">
                                                @if($ready)
                                                    <form method="post" action="{{ route('wishlists.send', $wishlist) }}"
                                                          onsubmit="return confirm('Poslati wishlist obavijest kupcu?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-paper-plane mr-1"></i> Pošalji
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canSendWishlist ? 10 : 8 }}" class="text-center text-muted py-5">
                                            Nema zapisa za odabrane filtre.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $wishlists->appends(array_merge(request()->except('wishlists_page'), ['tab' => 'wishlists']))->links() }}
                    </div>
                @elseif ($activeTab === 'top-products')
                    <div class="pt-4 table-responsive">
                        <table class="table table-borderless table-striped table-vcenter">
                            <thead>
                            <tr>
                                <th style="width: 80px;">Slika</th>
                                <th>Artikl</th>
                                <th>Šifra</th>
                                <th class="text-center">Stanje</th>
                                <th class="text-right">Broj prijava</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($topProducts as $item)
                                <tr>
                                    <td>
                                        @if(optional($item->product)->image)
                                            <img src="{{ $item->product->image }}" height="60" alt="">
                                        @endif
                                    </td>
                                    <td class="font-w600">{{ optional($item->product)->name ?? 'Artikl više ne postoji' }}</td>
                                    <td>{{ optional($item->product)->sku ?? '—' }}</td>
                                    <td class="text-center">{{ optional($item->product)->quantity ?? '—' }}</td>
                                    <td class="text-right font-w600">{{ $item->total }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-5">Nema wishlist prijava.</td></tr>
                            @endforelse
                            </tbody>
                        </table>

                        {{ $topProducts->appends(['tab' => 'top-products'])->links() }}
                    </div>
                @else
                    <div class="row pt-4">
                        @foreach ([
                            ['label' => 'Ukupno prijava', 'value' => $statistics['total'], 'icon' => 'fa-list'],
                            ['label' => 'Spremno za slanje', 'value' => $statistics['ready'], 'icon' => 'fa-paper-plane'],
                            ['label' => 'Čeka zalihu', 'value' => $statistics['waiting'], 'icon' => 'fa-clock'],
                            ['label' => 'Poslano', 'value' => $statistics['sent'], 'icon' => 'fa-check'],
                            ['label' => 'Jedinstveni kupci', 'value' => $statistics['customers'], 'icon' => 'fa-users'],
                        ] as $stat)
                            <div class="col-sm-6 col-xl mb-3">
                                <div class="block block-rounded bg-body-light mb-0 h-100">
                                    <div class="block-content text-center py-4">
                                        <i class="fa {{ $stat['icon'] }} fa-2x text-warning mb-2"></i>
                                        <div class="font-size-h2 font-w700">{{ $stat['value'] }}</div>
                                        <div class="text-muted">{{ $stat['label'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('wishlist-select-all');
            const checkboxes = Array.from(document.querySelectorAll('.js-wishlist-checkbox'));
            const count = document.getElementById('wishlist-selected-count');
            const submit = document.getElementById('wishlist-send-selected');

            if (!selectAll || !count || !submit) {
                return;
            }

            const refresh = function () {
                const selected = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
                count.textContent = selected;
                submit.disabled = selected === 0;
                selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
                selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
            };

            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) { checkbox.checked = selectAll.checked; });
                refresh();
            });
            checkboxes.forEach(function (checkbox) { checkbox.addEventListener('change', refresh); });
            refresh();
        });
    </script>
@endpush
