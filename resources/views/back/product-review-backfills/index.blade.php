@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light admin-page-hero">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <span class="admin-page-kicker"><i class="fa fa-paper-plane mr-1" aria-hidden="true"></i>Recenzije</span>
                    <h1>Pozivi za stare kupnje</h1>
                    <p class="admin-page-subtitle">Kontrolirano slanje starijim kupcima, uz pregled primatelja i zaštitu od duplikata.</p>
                </div>
                <a class="btn btn-alt-primary my-2" href="{{ route('reviews') }}">
                    <i class="fa fa-comments mr-1" aria-hidden="true"></i>Moderacija komentara
                </a>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        @unless($available)
            <div class="alert alert-danger">
                Modul još nema potrebne tablice. Prije korištenja primijenite nove migracije.
            </div>
        @endunless

        @unless($enabled)
            <div class="alert alert-warning">
                Slanje je trenutačno isključeno. Za pokretanje je potrebna postavka <strong>REVIEW_REQUEST_EMAILS_ENABLED=true</strong>.
            </div>
        @endunless

        <div class="alert alert-info">
            <strong>Bez dvostrukog slanja:</strong> automatsko slanje i ovaj modul koriste istu evidenciju i istu normaliziranu e-mail adresu. Svaka adresa dobiva najviše jedan poziv. Automatski proces i dalje obrađuje narudžbe iz zadnjih {{ $lookbackDays }} dana nakon {{ config('reviews.request_delay_days', 10) }} dana, a ovdje su dopuštene samo starije kupnje.
        </div>

        <div class="block block-rounded admin-list-block">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">1. Pripremite pregled</h2>
                    <small class="text-muted">U ovom koraku ništa se ne šalje.</small>
                </div>
            </div>
            <div class="block-content">
                <form method="GET" action="{{ route('product-review-backfills.index') }}">
                    <input type="hidden" name="preview" value="1">
                    <div class="form-row">
                        <div class="col-md-3 mb-3">
                            <label class="admin-filter-label" for="review-backfill-from">Od datuma</label>
                            <input class="form-control" id="review-backfill-from" type="date" name="date_from" required
                                   max="{{ $latestDate }}" value="{{ old('date_from', request('date_from', now()->subYear()->toDateString())) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="admin-filter-label" for="review-backfill-to">Do datuma</label>
                            <input class="form-control" id="review-backfill-to" type="date" name="date_to" required
                                   max="{{ $latestDate }}" value="{{ old('date_to', request('date_to', $latestDate)) }}">
                            <small class="form-text text-muted">Najkasnije {{ \Carbon\Carbon::parse($latestDate)->format('d.m.Y.') }}</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="admin-filter-label" for="review-backfill-limit">Najviše poruka</label>
                            <input class="form-control" id="review-backfill-limit" type="number" name="limit" required min="1" max="{{ $maxOrders }}"
                                   value="{{ old('limit', request('limit', 1000)) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="admin-filter-label" for="review-backfill-interval">Razmak slanja</label>
                            <select class="form-control" id="review-backfill-interval" name="interval_seconds">
                                @foreach($intervalOptions as $seconds)
                                    <option value="{{ $seconds }}" @if((int) old('interval_seconds', request('interval_seconds', $defaultInterval)) === (int) $seconds) selected @endif>
                                        svakih {{ $seconds }} s{{ (int) $seconds === 5 ? ' — preporučeno' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary px-4" type="submit" @unless($available) disabled @endunless>
                        <i class="fa fa-search mr-1" aria-hidden="true"></i>Pregledaj primatelje
                    </button>
                </form>
            </div>
        </div>

        @if($preview)
            @php
                $hours = intdiv($preview['estimated_seconds'], 3600);
                $minutes = (int) ceil(($preview['estimated_seconds'] % 3600) / 60);
                $duration = $hours > 0 ? $hours . ' h ' . $minutes . ' min' : max(1, $minutes) . ' min';
            @endphp
            <div class="block block-rounded border border-primary admin-list-block">
                <div class="block-header block-header-default admin-toolbar">
                    <div>
                        <h2 class="block-title mb-1">2. Potvrdite slanje</h2>
                        <small class="text-muted">Pronađeno {{ number_format($preview['eligible_count'], 0, ',', '.') }}, u batch ulazi najviše {{ number_format($preview['selected_count'], 0, ',', '.') }}.</small>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row mb-3">
                        <div class="col-md-4 mb-2"><strong class="font-size-h4">{{ number_format($preview['selected_count'], 0, ',', '.') }}</strong><br><span class="text-muted">poruka u batchu</span></div>
                        <div class="col-md-4 mb-2"><strong class="font-size-h4">{{ $preview['values']['interval_seconds'] }} sekundi</strong><br><span class="text-muted">razmak između poruka</span></div>
                        <div class="col-md-4 mb-2"><strong class="font-size-h4">oko {{ $duration }}</strong><br><span class="text-muted">najmanje procijenjeno trajanje</span></div>
                    </div>

                    @if($preview['orders']->isNotEmpty())
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-striped table-vcenter">
                                <thead><tr><th>Narudžba</th><th>Datum kupnje/slanja</th><th>Primatelj</th></tr></thead>
                                <tbody>
                                @foreach($preview['orders'] as $order)
                                    <tr>
                                        <td><a href="{{ route('orders.show', ['order' => $order]) }}">#{{ $order->id }}</a></td>
                                        <td>{{ $order->eligible_date->format('d.m.Y. H:i') }}</td>
                                        <td>{{ $order->masked_email }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($preview['selected_count'] > $preview['orders']->count())
                            <p class="small text-muted">Prikazano je prvih {{ $preview['orders']->count() }} primatelja.</p>
                        @endif
                    @endif

                    @if($preview['selected_count'] > 0)
                        <form id="review-backfill-start-form" method="POST" action="{{ route('product-review-backfills.store') }}"
                              data-recipient-count="{{ $preview['selected_count'] }}">
                            @csrf
                            <input type="hidden" name="date_from" value="{{ $preview['values']['date_from'] }}">
                            <input type="hidden" name="date_to" value="{{ $preview['values']['date_to'] }}">
                            <input type="hidden" name="limit" value="{{ $preview['values']['limit'] }}">
                            <input type="hidden" name="interval_seconds" value="{{ $preview['values']['interval_seconds'] }}">
                            <div class="custom-control custom-checkbox mb-3">
                                <input class="custom-control-input" id="review-backfill-confirmed" type="checkbox" name="confirmed" value="1" required>
                                <label class="custom-control-label" for="review-backfill-confirmed">Provjerio sam razdoblje, količinu i tempo slanja.</label>
                            </div>
                            <button id="review-backfill-start-button" class="btn btn-success px-4" type="submit"
                                    @unless($available && $enabled) disabled aria-disabled="true" @endunless>
                                <i class="fa fa-paper-plane mr-1" aria-hidden="true"></i>Pokreni slanje
                            </button>
                            @unless($available && $enabled)
                                <p class="small text-danger mt-2 mb-0">Slanje nije moguće dok migracije i produkcijska postavka za e-mailove nisu uključene.</p>
                            @endunless
                        </form>
                    @else
                        <div class="alert alert-warning mb-0">Za odabrane uvjete nema novih primatelja.</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="block block-rounded admin-list-block">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">Povijest slanja</h2>
                    <small class="text-muted">Zadnjih {{ $batches->count() }} batcheva.</small>
                </div>
                <a class="btn btn-sm btn-alt-primary" href="{{ route('product-review-backfills.index') }}">
                    <i class="fa fa-sync-alt mr-1" aria-hidden="true"></i>Osvježi status
                </a>
            </div>
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter admin-data-table">
                        <thead><tr><th>Batch</th><th>Razdoblje i tempo</th><th>Napredak</th><th>Rezultat</th><th>Status</th><th class="text-right">Akcija</th></tr></thead>
                        <tbody>
                        @forelse($batches as $batch)
                            @php
                                $percent = $batch->total_count > 0 ? min(100, (int) round(($batch->processed_count / $batch->total_count) * 100)) : 100;
                                $badge = ['pending' => 'warning', 'running' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary'][$batch->status] ?? 'secondary';
                            @endphp
                            <tr>
                                <td data-label="Batch"><strong>#{{ $batch->id }}</strong><div class="small text-muted">{{ $batch->created_at->format('d.m.Y. H:i') }}</div></td>
                                <td data-label="Razdoblje">{{ $batch->date_from->format('d.m.Y.') }} – {{ $batch->date_to->format('d.m.Y.') }}<div class="small text-muted">svakih {{ $batch->interval_seconds }} s · limit {{ number_format($batch->requested_limit, 0, ',', '.') }}</div></td>
                                <td data-label="Napredak" style="min-width:180px;">
                                    <div>{{ number_format($batch->processed_count, 0, ',', '.') }} / {{ number_format($batch->total_count, 0, ',', '.') }}</div>
                                    <div class="progress" style="height:6px;"><div class="progress-bar" role="progressbar" style="width:{{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                                </td>
                                <td data-label="Rezultat"><span class="text-success">{{ $batch->sent_count }} poslano</span><div class="small text-muted">{{ $batch->skipped_count }} preskočeno · {{ $batch->failed_count }} neuspjelo</div></td>
                                <td data-label="Status"><span class="badge badge-{{ $badge }}">{{ $statuses[$batch->status] ?? $batch->status }}</span></td>
                                <td class="text-right" data-label="Akcija">
                                    @if($batch->isActive())
                                        <form method="POST" action="{{ route('product-review-backfills.cancel', ['backfill' => $batch]) }}" onsubmit="return confirm('Zaustaviti batch #{{ $batch->id }}? Već poslane poruke ostaju poslane.');">
                                            @csrf
                                            <button class="btn btn-sm btn-alt-danger" type="submit"><i class="fa fa-stop mr-1" aria-hidden="true"></i>Zaustavi</button>
                                        </form>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="admin-empty-state"><i class="fa fa-paper-plane" aria-hidden="true"></i><strong>Još nema pokrenutih povijesnih slanja.</strong></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/product-review-backfills.js') }}?v=1"></script>
@endpush
