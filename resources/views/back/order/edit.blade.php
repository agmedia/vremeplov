@extends('back.layouts.backend')
@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa fa-edit" aria-hidden="true"></i> Uređivanje</div>
                    <h1 class="admin-page-title">{{ isset($order) ? 'Narudžba #' . $order->id : 'Nova narudžba' }}</h1>
                    <p class="admin-page-description">{{ isset($order) ? 'Uredite artikle, podatke kupca, dostavu i način plaćanja.' : 'Ručno unesite artikle, kupca, dostavu i način plaćanja.' }}</p>
                </div>
                <div class="admin-toolbar-actions">
                    <a class="btn btn-light" href="{{ route('orders') }}"><i class="fa fa-arrow-left mr-1" aria-hidden="true"></i> Sve narudžbe</a>
                    @if(isset($order))
                        <a class="btn btn-alt-secondary" href="{{ route('orders.show', ['order' => $order]) }}"><i class="fa fa-eye mr-1" aria-hidden="true"></i> Pregled</a>
                    @endif
                    <button type="submit" class="btn btn-primary" form="order-edit-form">
                        <i class="fas fa-save mr-1" aria-hidden="true"></i> {{ isset($order) ? 'Spremi promjene' : 'Spremi narudžbu' }}
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Page Content -->
    <div class="content admin-order-edit-page">
        @include('back.layouts.partials.session')

        @if(isset($order) && $order->payment_review_error)
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading"><i class="fa fa-exclamation-triangle mr-2"></i>Potrebna je ručna provjera plaćanja</h4>
                <p class="mb-1">{{ $order->payment_review_error }}</p>
                <small>Prije promjene statusa ili zalihe provjerite izvornu transakciju kod pružatelja plaćanja.</small>
            </div>
        @endif

        <form id="order-edit-form" action="{{ isset($order) ? route('orders.update', ['order' => $order]) : route('orders.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if (isset($order))
            {{ method_field('PATCH') }}
        @endif

        <!-- Products -->
            <div class="block block-rounded admin-editor-section" id="ag-order-products-app">
                <div class="block-header block-header-default">
                    <h2 class="block-title"><i class="fa fa-book mr-2" aria-hidden="true"></i>Artikli</h2>
                </div>
                <div class="block-content">
                    <ag-order-products
                            products="{{ isset($order) ? json_encode($order->products) : '' }}"
                            totals="{{ isset($order) ? json_encode($order->totals) : '' }}"
                            products_autocomplete_url="{{ route('products.autocomplete') }}">
                    </ag-order-products>
                </div>
            </div>
            <!-- END Products -->

            <!-- Customer -->
            <div class="row">
                <div class="col-sm-7">
                    <!-- Billing Address -->
                    <div class="block block-rounded admin-editor-section">
                        <div class="block-header block-header-default">
                            <h2 class="block-title"><i class="fa fa-user mr-2" aria-hidden="true"></i>Kupac i adresa dostave</h2>
                            <div class="block-options">
                                @if (isset($order) && $order->user_id)
                                    <span class="small text-gray mr-3">Kupac je registriran</span><i class="fa fa-user text-success"></i>
                                @else
                                    <span class="small font-weight-light mr-3">Kupac nije registriran</span><i class="fa fa-user text-danger-light"></i>
                                @endif
                            </div>
                        </div>
                        <div class="block-content">
                            <div class="row justify-content-center push">
                                <div class="col-md-11">
                                    <div class="form-group row items-push">
                                        <div class="col-md-6">
                                            <label for="fname-input">Ime</label>
                                            <input type="text" class="form-control" id="fname-input" name="fname" placeholder="Upišite ime kupca" value="{{ isset($order) ? $order->shipping_fname : old('fname') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lname-input">Prezime</label>
                                            <input type="text" class="form-control" id="lname-input" name="lname" placeholder="Upišite prezime kupca" value="{{ isset($order) ? $order->shipping_lname : old('lname') }}">
                                        </div>

                                        <div class="col-md-12">
                                            <label for="address-input">Adresa</label>
                                            <input type="text" class="form-control" id="address-input" name="address" placeholder="Upišite adresu kupca" value="{{ isset($order) ? $order->shipping_address : old('address') }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label for="zip-input">Poštanski br.</label>
                                            <input type="text" class="form-control" id="zip-input" name="zip" placeholder="Upišite poštanski broj kupca" value="{{ isset($order) ? $order->shipping_zip : old('zip') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="city-input">Grad</label>
                                            <input type="text" class="form-control" id="city-input" name="city" placeholder="Upišite grad kupca" value="{{ isset($order) ? $order->shipping_city : old('city') }}">
                                        </div>
                                        <div class="col-md-5">
                                            <label for="state-input">Država</label>
                                            <input type="text" class="form-control" id="state-input" name="state" placeholder="Upišite državu kupca" value="{{ isset($order) ? $order->shipping_state : old('state') }}">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="phone-input">Telefon</label>
                                            <input type="text" class="form-control" id="phone-input" name="phone" placeholder="Upišite telefon kupca" value="{{ isset($order) ? $order->shipping_phone : old('phone') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email-input">Email</label>
                                            <input type="text" class="form-control" id="email-input" name="email" placeholder="Upišite email kupca" value="{{ isset($order) ? $order->shipping_email : old('email') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END Billing Address -->
                </div>
                <div class="col-sm-5">
                    <!-- Shipping -->
                    <div class="block block-rounded admin-editor-section">
                        <div class="block-header block-header-default">
                            <h2 class="block-title"><i class="fa fa-shipping-fast mr-2" aria-hidden="true"></i>Način dostave</h2>
                        </div>
                        <div class="block-content">
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label for="shipping-select">Dostava</label>
                                    <select class="js-select2 form-control" id="shipping-select" name="shipping" style="width: 100%;" data-placeholder="Odaberite način dostave..." @if($boxNowShipmentLocked ?? false) disabled @endif>
                                        <option></option>
                                        @foreach ($shippings as $shipping)
                                            <option value="{{ $shipping->code }}" {{ ((isset($order)) and ($order->shipping_code == $shipping->code)) ? 'selected' : '' }}>{{ $shipping->title }}</option>
                                        @endforeach
                                    </select>
                                    @if($boxNowShipmentLocked ?? false)
                                        <input type="hidden" name="shipping" value="{{ $order->shipping_code ?: 'boxnow' }}">
                                        <small class="form-text text-muted">Dostava je zaključana jer je BOX NOW pošiljka već kreirana.</small>
                                    @endif
                                </div>
                                <div class="col-md-4">
                                    <label for="shipping-amount-input">Iznos</label>
                                    <input type="text" class="form-control" id="shipping-amount-input" name="shipping_amount" placeholder="Upišite iznos" value="{{ isset($order) ? optional($order->totals()->where('code', 'shipping')->first())->value : old('shipping_amount') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Payments -->
                    <div class="block block-rounded admin-editor-section">
                        <div class="block-header block-header-default">
                            <h2 class="block-title"><i class="fa fa-credit-card mr-2" aria-hidden="true"></i>Način plaćanja</h2>
                        </div>
                        <div class="block-content">
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label for="payment-select">Plaćanje</label>
                                    <select class="js-select2 form-control" id="payment-select" name="payment" style="width: 100%;" data-placeholder="Odaberite način plaćanja..." @if($boxNowShipmentLocked ?? false) disabled @endif>
                                        <option></option>
                                        @foreach ($payments as $payment)
                                            <option value="{{ $payment->code }}" {{ ((isset($order)) and ($order->payment_code == $payment->code)) ? 'selected' : '' }}>{{ $payment->title }}</option>
                                        @endforeach
                                    </select>
                                    @if($boxNowShipmentLocked ?? false)
                                        <input type="hidden" name="payment" value="{{ $order->payment_code }}">
                                        <small class="form-text text-muted">Plaćanje je zaključano jer je BOX NOW pošiljka već kreirana.</small>
                                    @endif
                                </div>
                                <div class="col-md-4">
                                    <label for="payment-amount-input">Iznos</label>
                                    <input type="text" class="form-control" id="payment-amount-input" name="payment_amount" placeholder="Upišite iznos" value="{{ (isset($order) && $order->totals()->where('code', 'payment')->first()) ? $order->totals()->where('code', 'payment')->first()->value : old('payment_amount') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END Customer -->

            @if(isset($order))
            <!-- Log Messages -->
            <div class="block block-rounded admin-editor-section">
                <div class="block-header block-header-default admin-toolbar">
                    <div>
                        <h3 class="block-title mb-1">Povijest narudžbe</h3>
                        <p class="text-muted mb-0 font-size-sm">Statusi, komentari i vrijeme promjena.</p>
                    </div>
                    <div class="admin-toolbar-actions">
                        <div class="dropdown">
                            <button type="button" class="btn btn-alt-secondary" id="btn-add-comment">
                                <i class="fa fa-comment mr-1" aria-hidden="true"></i> Dodaj komentar
                            </button>
                            <button type="button" class="btn btn-primary" id="dropdown-ecom-filters" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Promijeni status
                                <i class="fa fa-angle-down ml-1"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdown-ecom-filters">
                                @foreach ($statuses as $status)
                                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="javascript:setStatus({{ $status->id }});">
                                        <span class="badge badge-pill badge-{{ $status->color }}">{{ $status->title }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="block-content">
                    <div class="table-responsive admin-table-frame">
                    <table class="table table-borderless table-striped table-vcenter font-size-sm mb-0 admin-history-table">
                        <thead>
                        <tr>
                            <th>Status</th>
                            <th>Vrijeme</th>
                            <th>Autor</th>
                            <th>Komentar</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($order->history as $record)
                            <tr>
                                <td>
                                    @if ($record->status)
                                        <span class="badge badge-pill badge-{{ $record->status->color }}">{{ $record->status->title }}</span>
                                    @else
                                        <span class="text-muted">Komentar</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ \Illuminate\Support\Carbon::make($record->created_at)->locale('hr_HR')->diffForHumans() }}</strong>
                                    <small class="d-block text-muted">{{ \Illuminate\Support\Carbon::make($record->created_at)->format('d.m.Y. H:i') }}</small>
                                </td>
                                <td>{{ $record->user ? $record->user->name : trim($record->order->shipping_fname . ' ' . $record->order->shipping_lname) }}</td>
                                <td>{{ $record->comment ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            @endif

        </form>
    </div>
    <!-- END Page Content -->

@endsection

@push('modals')
    @if(isset($order))
    <div class="modal fade" id="comment-modal" tabindex="-1" role="dialog" aria-labelledby="comment--modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Dodaj komentar</h3>
                        <div class="block-options">
                            <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10">
                                <div class="form-group mb-4">
                                    <label for="status-select">Promijeni status</label>
                                    <select class="js-select2 form-control" id="status-select" name="status" style="width: 100%;" data-placeholder="Promijeni status narudžbe">
                                        <option value="0">Bez promjene statusa</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="comment-input">Komentar</label>
                                    <textarea class="form-control" name="comment" id="comment-input" rows="7"></textarea>
                                </div>

                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); changeStatus();">
                            Snimi <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
@endpush

@push('js_after')
    <script src="{{ asset('js/vue.js') }}"></script>
    <script src="{{ asset('js/components/ag-order-products.js') }}"></script>

    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {
            $('#shipping-select').select2({});
            $('#payment-select').select2({});

            @if(isset($order))
                $('#status-select').select2({});

                $('#btn-add-comment').on('click', () => {
                    $('#comment-modal').modal('show');
                    $('#status-select').val(0);
                    $('#status-select').trigger('change');
                });
            @endif
        })

        @if(isset($order))
        /**
         *
         * @param status
         */
        function setStatus(status) {
            $('#comment-modal').modal('show');
            $('#status-select').val(status);
            $('#status-select').trigger('change');
        }

        /**
         *
         */
        function changeStatus() {
            let item = {
                order_id: {{ $order->id }},
                comment: $('#comment-input').val(),
                status: $('#status-select').val()
            };

            axios.post("{{ route('api.order.status.change') }}", item)
            .then(response => {
                if (response.data.message) {
                    $('#comment-modal').modal('hide');

                    successToast.fire({
                        timer: 1500,
                        text: response.data.message,
                    }).then(() => {
                        location.reload();
                    })

                } else {
                    return errorToast.fire(response.data.error);
                }
            }).catch(error => {
                const message = error.response && error.response.data && error.response.data.error
                    ? error.response.data.error
                    : 'Status nije moguće promijeniti. Pokušajte ponovno.';
                errorToast.fire(message);
            });
        }
        @endif
    </script>

@endpush
