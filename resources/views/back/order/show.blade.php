@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/magnific-popup/magnific-popup.css') }}">
@endpush

@section('content')
    @php
        $shippingHint = \Illuminate\Support\Str::lower(
            (string) $order->shipping_carrier . ' '
            . (string) $order->shipping_code . ' '
            . (string) $order->shipping_method
        );
        $isBoxNowOrder = \Illuminate\Support\Str::contains($shippingHint, ['boxnow', 'box now']);
        $boxNowTrackingId = $boxNowPolicy->parcelId($order);
        $boxNowCanDispatch = $boxNowPolicy->canDispatch($order);
    @endphp

    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa fa-receipt" aria-hidden="true"></i> Narudžba</div>
                    <h1 class="admin-page-title">Narudžba #{{ $order->id }}</h1>
                    <p class="admin-page-description">{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y. H:i') }} · {{ trim($order->shipping_fname . ' ' . $order->shipping_lname) }}</p>
                </div>
                <div class="admin-toolbar-actions">
                    <a class="btn btn-alt-secondary" href="{{ route('orders') }}"><i class="fa fa-arrow-left mr-1" aria-hidden="true"></i> Sve narudžbe</a>
                    <a class="btn btn-primary" href="{{ route('orders.edit', ['order' => $order]) }}"><i class="fa fa-edit mr-1" aria-hidden="true"></i> Uredi</a>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        @if($order->payment_review_error)
            <div class="alert alert-danger admin-payment-warning" role="alert">
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                <div>
                    <h4>Potrebna je ručna provjera plaćanja</h4>
                    <p>{{ $order->payment_review_error }}</p>
                    <small>Prije promjene statusa ili zalihe provjerite izvornu transakciju kod pružatelja plaćanja.</small>
                </div>
            </div>
        @endif

        <div class="admin-order-kpi-grid">
            <div class="block block-rounded admin-order-kpi">
                <span>Status</span>
                <strong><span class="badge badge-pill badge-{{ $order->status->color }}">{{ $order->status->title }}</span></strong>
            </div>
            <div class="block block-rounded admin-order-kpi">
                <span>Ukupno</span>
                <strong>€ {{ number_format((float) $order->total, 2, ',', '.') }}</strong>
            </div>
            <div class="block block-rounded admin-order-kpi">
                <span>Plaćanje</span>
                <strong title="{{ $order->payment_method }}">{{ $order->payment_method ?: 'Nije zadano' }}</strong>
            </div>
            <div class="block block-rounded admin-order-kpi">
                <span>Dostava</span>
                <strong title="{{ $order->shipping_method }}">{{ $order->shipping_method ?: 'Nije zadana' }}</strong>
            </div>
        </div>

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h2 class="block-title">Artikli <span class="admin-count">{{ $order->products->count() }}</span></h2>
            </div>
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter admin-order-products-table">
                        <thead>
                        <tr>
                            <th>Slika</th>
                            <th>Artikl</th>
                            <th>Polica</th>
                            <th class="text-center">Kol.</th>
                            <th class="text-right">Cijena</th>
                            <th class="text-right">Ukupno</th>
                        </tr>
                        </thead>
                        <tbody class="js-gallery">
                        @forelse($order->products as $line)
                            @php
                                $catalogProduct = $line->product;
                                $productImage = $catalogProduct && $catalogProduct->image
                                    ? $catalogProduct->thumb
                                    : asset('media/avatars/avatar0.jpg');
                            @endphp
                            <tr>
                                <td>
                                    <a class="img-link img-link-zoom-in img-lightbox" href="{{ $productImage }}">
                                        <img class="admin-order-product-thumb" src="{{ $productImage }}" alt="" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('media/avatars/avatar0.jpg') }}';">
                                    </a>
                                </td>
                                <td>
                                    <strong class="admin-order-product-name">{{ $line->name }}</strong>
                                    <small class="text-muted">Šifra: {{ $catalogProduct ? ($catalogProduct->sku ?: '—') : 'artikl više nije u katalogu' }}</small>
                                </td>
                                <td>{{ $catalogProduct ? ($catalogProduct->polica ?: '—') : '—' }}</td>
                                <td class="text-center"><strong>{{ $line->quantity }}</strong></td>
                                <td class="text-right">€ {{ number_format((float) $line->price, 2, ',', '.') }}</td>
                                <td class="text-right"><strong>€ {{ number_format((float) $line->total, 2, ',', '.') }}</strong></td>
                            </tr>
                        @empty
                            <tr><td class="admin-empty-state" colspan="6">Narudžba nema spremljenih artikala.</td></tr>
                        @endforelse

                        @foreach($order->totals as $total)
                            <tr class="admin-order-total-row">
                                <td colspan="5" class="text-right"><strong>{{ $total->title }}:</strong></td>
                                <td class="text-right"><strong>€ {{ number_format((float) $total->value, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="block block-rounded h-100">
                    <div class="block-header block-header-default">
                        <h2 class="block-title"><i class="fa fa-map-marker-alt mr-2" aria-hidden="true"></i>Adresa dostave</h2>
                    </div>
                    <div class="block-content">
                        <div class="admin-customer-card">
                            <div class="admin-customer-avatar"><i class="fa fa-user" aria-hidden="true"></i></div>
                            <div>
                                <h3>{{ trim($order->shipping_fname . ' ' . $order->shipping_lname) ?: 'Nepoznat kupac' }}</h3>
                                <p>{{ $order->shipping_address ?: 'Adresa nije unesena' }}<br>{{ trim($order->shipping_zip . ' ' . $order->shipping_city) }}{{ $order->shipping_state ? ', ' . $order->shipping_state : '' }}</p>
                                @if($order->company || $order->oib)
                                    <p>{{ $order->company }}{{ $order->company && $order->oib ? ' · ' : '' }}{{ $order->oib ? 'OIB: ' . $order->oib : '' }}</p>
                                @endif
                                <div class="admin-contact-links">
                                    @if($order->shipping_phone)<a href="tel:{{ $order->shipping_phone }}"><i class="fa fa-phone" aria-hidden="true"></i>{{ $order->shipping_phone }}</a>@endif
                                    @if($order->shipping_email)<a href="mailto:{{ $order->shipping_email }}"><i class="fa fa-envelope" aria-hidden="true"></i>{{ $order->shipping_email }}</a>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mt-4 mt-lg-0">
                <div class="block block-rounded h-100">
                    <div class="block-header block-header-default">
                        <h2 class="block-title"><i class="fa fa-sticky-note mr-2" aria-hidden="true"></i>Napomene</h2>
                    </div>
                    <div class="block-content">
                        @if(trim((string) $order->comment) !== '')
                            <p class="admin-order-note">{{ $order->comment }}</p>
                        @else
                            <p class="text-muted">Kupac nije ostavio napomenu.</p>
                        @endif
                        @if(trim((string) $order->commentp) !== '')
                            <div class="admin-order-pickup"><span>Paketomat / preuzimanje</span><strong>{{ $order->commentp }}</strong></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($isBoxNowOrder)
            <div class="block block-rounded mt-4">
                <div class="block-header block-header-default admin-toolbar">
                    <h2 class="block-title"><i class="fa fa-box mr-2" aria-hidden="true"></i>Box Now pošiljka</h2>
                    <div class="admin-toolbar-actions">
                        @if($boxNowTrackingId && $canManageBoxNow)
                            <a class="btn btn-sm btn-alt-success" href="{{ route('order.boxnow.label', ['order' => $order]) }}"><i class="fa fa-file-pdf mr-1" aria-hidden="true"></i> PDF adresnica</a>
                            <button type="button" class="btn btn-sm btn-alt-info" onclick="refreshBoxNow()"><i class="fa fa-sync-alt mr-1" aria-hidden="true"></i> Osvježi status</button>
                        @elseif(! $boxNowTrackingId && $boxNowCanDispatch && $canManageBoxNow)
                            <button type="button" class="btn btn-sm btn-alt-warning" onclick="sendBoxNow()"><i class="fa fa-box mr-1" aria-hidden="true"></i> Pošalji u Box Now</button>
                        @elseif(! $boxNowTrackingId && ! $boxNowCanDispatch)
                            <span class="badge badge-danger">Slanje nije dopušteno za ovaj status</span>
                        @endif
                    </div>
                </div>
                <div class="block-content">
                    <div class="admin-shipment-grid">
                        <div class="admin-meta-list">
                            <div class="admin-meta-row"><span>Carrier</span><strong>{{ $order->shipping_carrier ?: 'Box Now' }}</strong></div>
                            <div class="admin-meta-row"><span>Parcel ID</span><strong>{{ $order->shipping_parcel_id ?: '—' }}</strong></div>
                            <div class="admin-meta-row"><span>Tracking broj</span><strong>{{ $order->tracking_code ?: '—' }}</strong></div>
                            <div class="admin-meta-row"><span>Paketomat</span><strong>{{ $order->commentp ?: '—' }}</strong></div>
                        </div>
                        <div class="admin-meta-list">
                            <div class="admin-meta-row"><span>Status</span><strong>{{ $order->shipping_tracking_status ?: 'Pošiljka još nije kreirana.' }}</strong></div>
                            <div class="admin-meta-row"><span>Kod statusa</span><strong>{{ $order->shipping_tracking_status_code ?: '—' }}</strong></div>
                            <div class="admin-meta-row"><span>Osvježeno</span><strong>{{ $order->shipping_tracking_updated_at ? $order->shipping_tracking_updated_at->format('d.m.Y. H:i:s') : '—' }}</strong></div>
                            <div class="admin-meta-row"><span>Praćenje</span><strong>@if($order->shipping_tracking_url)<a href="{{ $order->shipping_tracking_url }}" target="_blank" rel="noopener">Otvori <i class="fa fa-external-link-alt ml-1" aria-hidden="true"></i></a>@else — @endif</strong></div>
                        </div>
                    </div>

                    @if($canManageBoxNow && ! empty($order->shipping_tracking_payload))
                        <details class="admin-api-details">
                            <summary>Zadnji Box Now API odgovor</summary>
                            <pre>{{ json_encode($order->shipping_tracking_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                </div>
            </div>
        @endif

        <div class="block block-rounded mt-4">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">Povijest narudžbe</h2>
                    <p class="text-muted mb-0 font-size-sm">Statusi, komentari i vrijeme promjena.</p>
                </div>
                <div class="admin-toolbar-actions">
                    <button type="button" class="btn btn-alt-secondary" id="btn-add-comment"><i class="fa fa-comment mr-1" aria-hidden="true"></i> Dodaj komentar</button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Promijeni status</button>
                        <div class="dropdown-menu dropdown-menu-right">
                            @foreach($statuses as $status)
                                <a class="dropdown-item" href="javascript:setStatus({{ $status->id }});"><span class="badge badge-pill badge-{{ $status->color }}">{{ $status->title }}</span></a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter admin-history-table">
                        <thead><tr><th>Status</th><th>Vrijeme</th><th>Autor</th><th>Komentar</th></tr></thead>
                        <tbody>
                        @forelse($order->history as $record)
                            <tr>
                                <td>@if($record->status)<span class="badge badge-pill badge-{{ $record->status->color }}">{{ $record->status->title }}</span>@else<span class="text-muted">Komentar</span>@endif</td>
                                <td><strong>{{ \Illuminate\Support\Carbon::make($record->created_at)->locale('hr_HR')->diffForHumans() }}</strong><small class="d-block text-muted">{{ \Illuminate\Support\Carbon::make($record->created_at)->format('d.m.Y. H:i') }}</small></td>
                                <td>{{ $record->user ? $record->user->name : trim($order->shipping_fname . ' ' . $order->shipping_lname) }}</td>
                                <td>{{ $record->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="admin-empty-state" colspan="4">Nema zabilježenih promjena.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="comment-modal" tabindex="-1" role="dialog" aria-labelledby="comment-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title" id="comment-modal-title">Komentar i status</h3>
                        <div class="block-options"><button type="button" class="btn-block-option text-white" data-dismiss="modal" aria-label="Zatvori"><i class="fa fa-times" aria-hidden="true"></i></button></div>
                    </div>
                    <div class="block-content">
                        <div class="form-group">
                            <label for="status-select">Promijeni status</label>
                            <select class="js-select2 form-control" id="status-select" name="status" style="width: 100%;">
                                <option value="0">Bez promjene statusa</option>
                                @foreach($statuses as $status)<option value="{{ $status->id }}">{{ $status->title }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="comment-input">Komentar</label>
                            <textarea class="form-control" name="comment" id="comment-input" rows="6" placeholder="Upišite internu napomenu..."></textarea>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Odustani</button>
                        <button type="button" class="btn btn-primary" id="save-status-button" onclick="changeStatus()"><i class="fa fa-save mr-1" aria-hidden="true"></i> Snimi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/magnific-popup/jquery.magnific-popup.min.js') }}"></script>
    <script>jQuery(function(){ Dashmix.helpers('magnific-popup'); });</script>
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {
            $('#status-select').select2({minimumResultsForSearch: Infinity});
            $('#btn-add-comment').on('click', () => {
                $('#status-select').val(0).trigger('change');
                $('#comment-input').val('');
                $('#comment-modal').modal('show');
            });
        });

        function setStatus(status) {
            $('#status-select').val(status).trigger('change');
            $('#comment-modal').modal('show');
        }

        function changeStatus() {
            const $button = $('#save-status-button');
            $button.prop('disabled', true);

            axios.post("{{ route('api.order.status.change') }}", {
                order_id: {{ $order->id }},
                comment: $('#comment-input').val(),
                status: $('#status-select').val()
            }).then((response) => {
                if (!response.data.message) {
                    errorToast.fire(response.data.error || 'Promjenu nije moguće spremiti.');
                    $button.prop('disabled', false);
                    return;
                }

                $('#comment-modal').modal('hide');
                successToast.fire({timer: 1500, text: response.data.message}).then(() => location.reload());
            }).catch((error) => {
                const message = error.response && error.response.data && error.response.data.error
                    ? error.response.data.error
                    : 'Status nije moguće promijeniti. Pokušajte ponovno.';
                errorToast.fire(message);
                $button.prop('disabled', false);
            });
        }

        function boxNowAction(endpoint) {
            axios.post(endpoint, {order_id: {{ $order->id }}})
                .then((response) => {
                    if (!response.data.message) {
                        errorToast.fire(response.data.error || 'Box Now akcija nije uspjela.');
                        return;
                    }
                    successToast.fire({timer: 1800, text: response.data.message}).then(() => location.reload());
                })
                .catch((error) => {
                    const message = error.response && error.response.data && error.response.data.error
                        ? error.response.data.error
                        : 'Box Now akcija nije uspjela.';
                    errorToast.fire(message);
                });
        }

        function sendBoxNow() {
            boxNowAction("{{ route('api.order.send.boxnow') }}");
        }

        function refreshBoxNow() {
            boxNowAction("{{ route('api.order.tracking.boxnow.refresh') }}");
        }
    </script>
@endpush
