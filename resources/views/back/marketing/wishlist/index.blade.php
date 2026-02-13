@extends('back.layouts.backend')

@section('content')
    @php
        $activeTab = request('tab', 'wishlists');
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Liste želja</h1>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Liste želja i najtraženiji artikli</h3>
                <div class="block-options">
                    <a class="btn btn-primary" href="{{ route('wishlists', ['tab' => $activeTab]) }}">
                        Očisti filtere
                    </a>
                </div>
            </div>

            <div class="block-content">
                <ul class="nav nav-tabs nav-tabs-block" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'wishlists' ? 'active' : '' }}"
                           href="{{ route('wishlists', array_merge(request()->except('page'), ['tab' => 'wishlists'])) }}">
                            Sve liste želja
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'top-products' ? 'active' : '' }}"
                           href="{{ route('wishlists', array_merge(request()->except('page'), ['tab' => 'top-products'])) }}">
                            Najtraženiji artikli
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    @if ($activeTab === 'wishlists')
                        <div class="tab-pane fade show active" id="tab-wishlists" role="tabpanel">
                            <div class="block-content pt-3">
                                <div class="bg-body-dark p-3 mb-3">
                                    <form method="get" action="{{ route('wishlists') }}">
                                        <input type="hidden" name="tab" value="wishlists">
                                        <div class="form-group row">
                                            <div class="col-md-9">
                                                <div class="input-group">
                                                    <input type="text"
                                                           class="form-control"
                                                           name="search"
                                                           value="{{ request()->input('search') }}"
                                                           placeholder="Pretrazi po nazivu ili sifri artikla">
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-search"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="form-text small">
                                                    Pretrazi po nazivu ili sifri artikla.
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped table-vcenter">
                                        <thead>
                                        <tr>
                                            <th style="width: 80px;">Slika</th>
                                            <th>Naziv</th>
                                            <th>Sifra</th>
                                            <th>E-mail (korisnik)</th>
                                            <th>Status</th>
                                            <th>Dodano</th>
                                            <th>Poslano</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($wishlists as $w)
                                            <tr>
                                                <td>
                                                    @if($w->product && $w->product->image)
                                                        <img src="{{ $w->product->image }}" height="60" alt="">
                                                    @endif
                                                </td>
                                                <td>{{ $w->product->name ?? '---' }}</td>
                                                <td>{{ $w->product->sku ?? '---' }}</td>
                                                <td>{{ $w->email }}</td>
                                                <td>
                                                    @if((int) $w->sent === 1)
                                                        <span class="badge badge-success">Poslano</span>
                                                    @else
                                                        <span class="badge badge-warning">Na cekanju</span>
                                                    @endif
                                                </td>
                                                <td>{{ optional($w->created_at)->format('d.m.Y H:i') ?? '---' }}</td>
                                                <td>{{ optional($w->sent_at)->format('d.m.Y H:i') ?? '---' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">Nema zapisa u listi zelja.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                {{ $wishlists->appends(array_merge(request()->query(), ['tab' => 'wishlists']))->links() }}
                            </div>
                        </div>
                    @endif

                    @if ($activeTab === 'top-products')
                        <div class="tab-pane fade show active" id="tab-top-products" role="tabpanel">
                            <div class="block-content pt-3">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-striped">
                                        <thead>
                                        <tr>
                                            <th>Naziv artikla</th>
                                            <th>Šifra</th>
                                            <th class="text-right">Broj prijava</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse ($topProducts as $item)
                                            <tr>
                                                <td>{{ optional($item->product)->name ?? '---' }}</td>
                                                <td>{{ optional($item->product)->sku ?? '---' }}</td>
                                                <td class="text-right">{{ $item->total }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3">Nema zapisa za najtrazenije artikle.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>

                                    {{ $topProducts->appends(array_merge(request()->query(), ['tab' => 'top-products']))->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
