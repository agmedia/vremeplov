@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
    @php
        $hasActiveFilters = request()->filled('search')
            || request()->filled('status')
            || request()->filled('date_from')
            || request()->filled('date_to');
    @endphp

    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa-regular fa-bag-shopping" aria-hidden="true"></i> Prodaja</div>
                    <h1 class="admin-page-title">Narudžbe</h1>
                    <p class="admin-page-description">Pronađite narudžbu, provjerite plaćanje i upravljajte obradom i dostavom.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">Sve narudžbe <span class="admin-count">{{ number_format($orders->total(), 0, ',', '.') }}</span></h2>
                    <p class="text-muted mb-0 font-size-sm">Označite više narudžbi za zajedničku promjenu statusa.</p>
                </div>
                <div class="admin-toolbar-actions">
                    @if($hasActiveFilters)
                        <a href="{{ route('orders') }}" class="btn btn-alt-secondary">
                            <i class="fa fa-times mr-1" aria-hidden="true"></i> Očisti filtre
                        </a>
                    @endif
                    <button type="button" class="btn btn-light" data-toggle="collapse" data-target="#order-filters" data-admin-filter-toggle="orders-{{ auth()->id() }}" aria-expanded="true" aria-controls="order-filters">
                        <i class="fa fa-filter mr-1" aria-hidden="true"></i> Filtri
                        @if($hasActiveFilters)<span class="badge badge-primary ml-1">Aktivni</span>@endif
                    </button>
                </div>
            </div>

            <div id="order-filters" class="collapse show admin-filter-panel" data-admin-filter-memory="orders-{{ auth()->id() }}">
                <div class="block-content">
                    <form action="{{ route('orders') }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-xl-4 col-md-6 form-group">
                                <label for="search-input">Pretraživanje</label>
                                <div class="input-group">
                                    <input type="search" class="form-control" name="search" id="search-input" value="{{ request('search') }}" placeholder="Broj, kupac, e-mail, plaćanje...">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fa fa-search" aria-hidden="true"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 form-group">
                                <label for="filter-status">Status</label>
                                <select class="form-control" id="filter-status" name="status">
                                    <option value="">Svi statusi</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->id }}" {{ (string) request('status') === (string) $status->id ? 'selected' : '' }}>{{ $status->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-6 form-group">
                                <label for="date-from-input">Od datuma</label>
                                <input type="date" class="form-control" id="date-from-input" name="date_from" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-xl-2 col-md-6 form-group">
                                <label for="date-to-input">Do datuma</label>
                                <input type="date" class="form-control" id="date-to-input" name="date_to" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-xl-1 form-group admin-filter-actions">
                                <button type="submit" class="btn btn-primary btn-block" title="Primijeni filtre" aria-label="Primijeni filtre">
                                    <i class="fa fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="block-content">
                <div class="admin-bulk-bar" id="bulk-status-bar">
                    <div class="admin-bulk-selection">
                        <span class="admin-bulk-icon"><i class="fa fa-check-square" aria-hidden="true"></i></span>
                        <div>
                            <strong><span id="selected-orders-count">0</span> označeno</strong>
                            <small>Promijenite status odabranih narudžbi.</small>
                        </div>
                    </div>
                    <div class="admin-bulk-control">
                        <label class="sr-only" for="status-select">Promijeni status označenih narudžbi</label>
                        <select class="js-select2 form-control" id="status-select" name="bulk_status" style="width: 100%;" disabled data-placeholder="Odaberite novi status">
                            <option></option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="table-responsive admin-orders-table-wrap admin-table-frame">
                    <table class="table table-borderless table-striped table-vcenter admin-orders-table">
                        <thead>
                        <tr>
                            <th class="text-center">
                                <div class="custom-control custom-checkbox d-inline-block">
                                    <input class="custom-control-input" type="checkbox" id="checkAll">
                                    <label class="custom-control-label" for="checkAll"><span class="sr-only">Označi sve</span></label>
                                </div>
                            </th>
                            <th>Narudžba</th>
                            <th>Status i plaćanje</th>
                            <th>Kupac</th>
                            <th>Sažetak</th>
                            <th>Dostava</th>
                            <th class="text-right">Radnje</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($orders as $order)
                            @php
                                $abandonedCartState = $order->abandoned_cart_state ?? [];
                                $isUnfinishedOrder = (int) $order->order_status_id === (int) config('settings.order.status.unfinished', 8);
                                $shipmentCarrierHint = \Illuminate\Support\Str::lower(
                                    (string) $order->shipping_carrier . ' '
                                    . (string) $order->shipping_code . ' '
                                    . (string) $order->shipping_method
                                );
                                $isBoxNowShipment = \Illuminate\Support\Str::contains($shipmentCarrierHint, ['boxnow', 'box now']);
                                $isGlsShipment = \Illuminate\Support\Str::contains($shipmentCarrierHint, 'gls');
                                $boxNowTrackingId = $boxNowPolicy->parcelId($order);
                                $boxNowCanDispatch = $boxNowPolicy->canDispatch($order);
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox d-inline-block">
                                        <input class="custom-control-input order-checkbox" type="checkbox" value="{{ $order->id }}" id="order-{{ $order->id }}">
                                        <label class="custom-control-label" for="order-{{ $order->id }}"><span class="sr-only">Označi narudžbu {{ $order->id }}</span></label>
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-order-number">
                                        <a href="{{ route('orders.show', ['order' => $order]) }}">#{{ $order->id }}</a>
                                        <small class="admin-order-created-at" title="{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y. H:i') }}">
                                            <span>{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y.') }}</span>
                                            <span>{{ \Illuminate\Support\Carbon::make($order->created_at)->format('H:i') }}</span>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-order-status-payment">
                                        <span class="badge badge-pill badge-{{ $order->status->color }}">{{ $order->status->title }}</span>
                                        <small>{{ $order->payment_method ?: 'Način plaćanja nije zadan' }}</small>
                                        @foreach([1 => 'first', 2 => 'second'] as $sequence => $key)
                                            @if(! empty($abandonedCartState[$key]))
                                                <small class="text-success" title="{{ $abandonedCartState[$key]->source === 'manual' ? 'Ručno slanje' : 'Automatsko slanje' }}">
                                                    <i class="fa-duotone fa-envelope-circle-check mr-1" aria-hidden="true"></i>
                                                    {{ $sequence }}. podsjetnik: {{ $abandonedCartState[$key]->sent_at->format('d.m.Y. H:i') }}
                                                    ({{ $abandonedCartState[$key]->source === 'manual' ? 'ručno' : 'automatski' }})
                                                </small>
                                            @endif
                                        @endforeach
                                        @if($isUnfinishedOrder && ! empty($abandonedCartState['available']) && ! empty($abandonedCartState['next_scheduled_at']))
                                            <small class="text-muted">
                                                <i class="fa-duotone fa-clock mr-1" aria-hidden="true"></i>
                                                {{ $abandonedCartState['next_sequence'] }}. automatski: {{ $abandonedCartState['next_scheduled_at']->format('d.m.Y. H:i') }}
                                            </small>
                                        @endif
                                        @if($order->payment_review_error)
                                            <span class="admin-inline-warning" title="{{ $order->payment_review_error }}">
                                                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Provjera plaćanja
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-order-customer">
                                        <a href="{{ route('orders.show', ['order' => $order]) }}">{{ trim($order->shipping_fname . ' ' . $order->shipping_lname) ?: 'Nepoznat kupac' }}</a>
                                        <small title="{{ $order->shipping_email }}">{{ $order->shipping_email ?: 'E-mail nije unesen' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-order-summary">
                                        <strong>€ {{ number_format((float) $order->total, 2, ',', '.') }}</strong>
                                        <small>{{ $order->products_count }} {{ $order->products_count == 1 ? 'artikl' : 'artikala' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-order-shipping">
                                        <small title="{{ $order->shipping_method }}">{{ $order->shipping_method ?: 'Dostava nije zadana' }}</small>
                                        <span class="admin-shipping-actions">
                                            @if($isBoxNowShipment)
                                                @if($boxNowTrackingId)
                                                    @if($order->shipping_tracking_url)
                                                        <a href="{{ $order->shipping_tracking_url }}" target="_blank" rel="noopener" class="admin-tracking-code">{{ $boxNowTrackingId }}</a>
                                                    @else
                                                        <span class="admin-tracking-code">{{ $boxNowTrackingId }}</span>
                                                    @endif
                                                    @if($canManageBoxNow)
                                                        <button type="button" class="btn btn-sm btn-alt-info" onclick="refreshBoxNow({{ $order->id }})" title="Osvježi Box Now status" aria-label="Osvježi Box Now status narudžbe {{ $order->id }}"><i class="fa fa-sync-alt" aria-hidden="true"></i></button>
                                                        <a class="btn btn-sm btn-alt-success" href="{{ route('order.boxnow.label', ['order' => $order]) }}" title="Preuzmi PDF adresnicu" aria-label="Preuzmi PDF adresnicu narudžbe {{ $order->id }}"><i class="fa fa-file-pdf" aria-hidden="true"></i></a>
                                                    @endif
                                                @elseif($boxNowCanDispatch && $canManageBoxNow)
                                                    <button type="button" class="btn btn-sm btn-alt-warning" onclick="sendBoxNow({{ $order->id }})" title="Pošalji u Box Now" aria-label="Pošalji narudžbu {{ $order->id }} u Box Now"><i class="fa fa-box" aria-hidden="true"></i></button>
                                                @else
                                                    <span class="text-muted">Nije spremno za slanje</span>
                                                @endif
                                            @elseif($isGlsShipment)
                                                @if($order->printed)
                                                    @if($order->tracking_code)
                                                        <a href="{{ $order->shipping_tracking_url ?: route('orders.show', ['order' => $order]) }}" target="{{ $order->shipping_tracking_url ? '_blank' : '_self' }}" rel="noopener" class="admin-tracking-code">{{ $order->tracking_code }}</a>
                                                    @elseif($order->shipping_parcel_id)
                                                        <span class="admin-tracking-code" title="Tracking broj još nije dostupan">{{ $order->shipping_parcel_id }}</span>
                                                    @else
                                                        <span class="text-success"><i class="fa fa-check-circle mr-1" aria-hidden="true"></i> Poslano</span>
                                                    @endif
                                                    @if($order->tracking_code || $order->shipping_parcel_id)
                                                        <button type="button" class="btn btn-sm btn-alt-info" onclick="refreshTracking({{ $order->id }})" title="Osvježi GLS status" aria-label="Osvježi GLS status narudžbe {{ $order->id }}"><i class="fa fa-sync-alt" aria-hidden="true"></i></button>
                                                    @endif
                                                @else
                                                    <button type="button" class="btn btn-sm btn-alt-warning" onclick="sendGLS({{ $order->id }})" title="Pošalji u GLS" aria-label="Pošalji narudžbu {{ $order->id }} u GLS"><i class="fa fa-shipping-fast" aria-hidden="true"></i></button>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <span class="admin-row-actions">
                                        <a class="btn btn-sm btn-alt-secondary" href="{{ route('orders.show', ['order' => $order]) }}" title="Pregledaj" aria-label="Pregledaj narudžbu {{ $order->id }}"><i class="fa fa-eye" aria-hidden="true"></i></a>
                                        <a class="btn btn-sm btn-alt-secondary" href="{{ route('orders.edit', ['order' => $order]) }}" title="Uredi" aria-label="Uredi narudžbu {{ $order->id }}"><i class="fa fa-edit" aria-hidden="true"></i></a>
                                        @if($isUnfinishedOrder && ! empty($abandonedCartState['available']))
                                            <form class="d-inline-block" method="POST" action="{{ route('orders.abandoned-cart-reminder.send', $order) }}" onsubmit="return confirm('Poslati {{ $abandonedCartState['next_sequence'] }}. podsjetnik kupcu sada?');">
                                                @csrf
                                                <button class="btn btn-sm btn-alt-primary" type="submit" title="Pošalji {{ $abandonedCartState['next_sequence'] }}. podsjetnik sada" aria-label="Pošalji podsjetnik za narudžbu {{ $order->id }}">
                                                    <i class="fa-duotone fa-envelope-open-text" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @elseif($isUnfinishedOrder && ! empty($abandonedCartState['complete']))
                                            <button class="btn btn-sm btn-light" type="button" disabled title="Poslana su oba podsjetnika"><i class="fa-duotone fa-envelope-circle-check text-success"></i></button>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-empty-state" colspan="7">
                                    <i class="fa fa-receipt" aria-hidden="true"></i>
                                    <strong>Nema narudžbi za odabrane filtre.</strong>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $orders->links() }}
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {
            const $bulkStatus = $('#status-select');
            const $checkboxes = $('.order-checkbox');
            const $checkAll = $('#checkAll');

            $bulkStatus.select2({
                placeholder: 'Odaberite novi status',
                minimumResultsForSearch: Infinity
            });

            function selectedOrderIds() {
                return $checkboxes.filter(':checked').map(function () {
                    return this.value;
                }).get();
            }

            function updateBulkControls() {
                const selected = selectedOrderIds();
                $('#selected-orders-count').text(selected.length);
                $bulkStatus.prop('disabled', selected.length === 0);
                $checkAll.prop('checked', selected.length > 0 && selected.length === $checkboxes.length);
                $checkAll.prop('indeterminate', selected.length > 0 && selected.length < $checkboxes.length);
            }

            $checkAll.on('change', function () {
                $checkboxes.prop('checked', this.checked);
                updateBulkControls();
            });

            $checkboxes.on('change', updateBulkControls);

            $bulkStatus.on('change', function () {
                const statusId = this.value;
                const orderIds = selectedOrderIds();

                if (!statusId || orderIds.length === 0) {
                    return;
                }

                $bulkStatus.prop('disabled', true);

                axios.post('{{ route('api.order.status.change') }}', {
                    selected: statusId,
                    orders: '[' + orderIds.join(',') + ']'
                }).then(() => {
                    location.reload();
                }).catch((error) => {
                    const message = error.response && error.response.data && error.response.data.error
                        ? error.response.data.error
                        : 'Statuse nije moguće promijeniti. Pokušajte ponovno.';
                    errorToast.fire(message);
                    updateBulkControls();
                    $bulkStatus.val(null).trigger('change.select2');
                });
            });

            updateBulkControls();
        });

        function sendShipment(orderId, endpoint) {
            axios.post(endpoint, {order_id: orderId})
                .then((response) => {
                    if (!response.data.message) {
                        errorToast.fire(response.data.error || 'Slanje pošiljke nije uspjelo.');
                        return;
                    }

                    successToast.fire({timer: 1500, text: response.data.message})
                        .then(() => location.reload());
                })
                .catch((error) => {
                    const message = error.response && error.response.data && error.response.data.error
                        ? error.response.data.error
                        : 'Slanje pošiljke nije uspjelo.';
                    errorToast.fire(message);
                });
        }

        function sendGLS(orderId) {
            sendShipment(orderId, "{{ route('api.order.send.gls') }}");
        }

        function sendBoxNow(orderId) {
            sendShipment(orderId, "{{ route('api.order.send.boxnow') }}");
        }

        function refreshBoxNow(orderId) {
            sendShipment(orderId, "{{ route('api.order.tracking.boxnow.refresh') }}");
        }

        function refreshTracking(orderId) {
            sendShipment(orderId, "{{ route('api.order.tracking.refresh') }}");
        }
    </script>
@endpush
