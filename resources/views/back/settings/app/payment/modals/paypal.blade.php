<div class="modal fade" id="payment-modal-paypal" tabindex="-1" role="dialog" aria-labelledby="modal-payment-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-popout" role="document">
        <div class="modal-content rounded">
            <div class="block block-themed block-transparent mb-0">
                <div class="block-header bg-primary">
                    <h3 class="block-title">Paypal Payment Gateway</h3>
                    <div class="block-options">
                        <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </a>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center">
                        <div class="col-md-10">

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="paypal-title">Naslov</label>
                                        <input type="text" class="form-control" id="paypal-title" name="title">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paypal-min">Minimalni iznos narudžbe</label>
                                        <input type="text" class="form-control" id="paypal-min" name="min">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paypal-price">Iznos naknade za način plaćanja</label>
                                        <input type="text" class="form-control" id="paypal-price" name="data['price']">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="paypal-description">Kratki opis <span class="small text-gray">(Prikazuje se prilikom odabira plaćanja.)</span></label>
                                <textarea class="js-maxlength form-control" id="paypal-description" name="data['description']" rows="2" maxlength="160" data-always-show="true" data-placement="top"></textarea>
                                <small class="form-text text-muted">
                                    160 znakova max
                                </small>
                            </div>

                            <div class="block block-themed block-transparent mb-4">
                                <div class="block-content bg-body pb-3">
                                    <div class="row justify-content-center">
                                        <div class="col-md-11">
                                            <h5>LIVE</h5>
                                            <div class="form-group">
                                                <label for="paypal-live-id">ID prodajnog mjesta (CLIENT_ID):</label>
                                                <input type="text" class="form-control" id="paypal-live-id" name="data['live_id']">
                                            </div>
                                            <div class="form-group">
                                                <label for="paypal-live-secret">Tajni ključ (SECRET):</label>
                                                <input type="text" class="form-control" id="paypal-live-secret" name="data['live_secret']">
                                            </div>
                                            <div class="form-group">
                                                <label for="paypal-live-app">ID Aplikacije (APP_ID):</label>
                                                <input type="text" class="form-control" id="paypal-live-app" name="data['live_app']">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="block block-themed block-transparent mb-4">
                                <div class="block-content bg-body pb-3">
                                    <div class="row justify-content-center">
                                        <div class="col-md-11">
                                            <h5>TEST - Sandbox</h5>
                                            <div class="form-group">
                                                <label for="paypal-test-id">ID prodajnog mjesta (CLIENT_ID):</label>
                                                <input type="text" class="form-control" id="paypal-test-id" name="data['test_id']">
                                            </div>
                                            <div class="form-group">
                                                <label for="paypal-test-secret">Tajni ključ (SECRET):</label>
                                                <input type="text" class="form-control" id="paypal-test-secret" name="data['test_secret']">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="block block-themed block-transparent mb-4">
                                <div class="block-content bg-body pb-0">
                                    <div class="row justify-content-center">
                                        <div class="col-md-11">
                                            <div class="form-group">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="d-block">Test mod.</label>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <div class="custom-control custom-radio custom-control-inline custom-control-success custom-control-lg">
                                                            <input type="radio" class="custom-control-input" id="paypal-test-on" name="test" checked="" value="1">
                                                            <label class="custom-control-label" for="paypal-test-on">Da</label>
                                                        </div>
                                                        <div class="custom-control custom-radio custom-control-inline custom-control-danger custom-control-lg">
                                                            <input type="radio" class="custom-control-input" id="paypal-test-off" name="test" value="0">
                                                            <label class="custom-control-label" for="paypal-test-off">Ne</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12 mt-4">
                                                        <div class="form-group">
                                                            <label for="paypal-callback">URL za slanje odgovora: <span class="small text-gray">Ovo također mora biti upisano u WSPay control panelu.</span></label>
                                                            <input type="text" class="form-control" id="paypal-callback" name="data['callback']" value="{{ url('/') }}">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="paypal-geo-zone">Geo zona <span class="small text-gray">(Ostaviti prazno ako se odnosi na sve..)</span></label>
                                        <select class="js-select2 form-control" id="paypal-geo-zone" name="geo_zone" style="width: 100%;" data-placeholder="Odaberite geo zonu">
                                            <option></option>
                                            @foreach ($geo_zones as $geo_zone)
                                                <option value="{{ $geo_zone->id }}" {{ ((isset($shipping)) and ($shipping->geo_zone == $geo_zone->id)) ? 'selected' : '' }}>{{ $geo_zone->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paypal-price">Poredak</label>
                                        <input type="text" class="form-control" id="paypal-sort-order" name="sort_order">
                                    </div>
                                </div>
                                <div class="col-md-6 text-right" style="padding-top: 37px;">
                                    <div class="form-group">
                                        <label class="css-control css-control-sm css-control-success css-switch res">
                                            <input type="checkbox" class="css-control-input" id="paypal-status" name="status">
                                            <span class="css-control-indicator"></span> Status načina plaćanja
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="paypal-code" name="code" value="paypal">
                        </div>
                    </div>
                </div>
                <div class="block-content block-content-full text-right bg-light">
                    <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                        Odustani <i class="fa fa-times ml-2"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); create_paypal();">
                        Snimi <i class="fa fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('payment-modal-js')
    <script>
        $(() => {
            $('#paypal-geo-zone').select2({
                minimumResultsForSearch: Infinity,
                allowClear: true
            });
        });
        /**
         *
         */
        function create_paypal() {
            let item = {
                title: $('#paypal-title').val(),
                code: $('#paypal-code').val(),
                min: $('#paypal-min').val(),
                data: {
                    price: $('#paypal-price').val(),
                    description: $('#paypal-description').val(),
                    live_id: $('#paypal-live-id').val(),
                    live_secret: $('#paypal-live-secret').val(),
                    live_app: $('#paypal-live-app').val(),
                    test_id: $('#paypal-test-id').val(),
                    test_secret: $('#paypal-test-secret').val(),
                    callback: $('#paypal-callback').val(),
                    test: $("input[name='test']:checked").val(),
                },
                geo_zone: $('#paypal-geo-zone').val(),
                status: $('#paypal-status')[0].checked,
                sort_order: $('#paypal-sort-order').val()
            };

            axios.post("{{ route('api.payment.store') }}", {data: item})
            .then(response => {
                console.log(response.data)
                if (response.data.success) {
                    location.reload();
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         * @param item
         */
        function edit_paypal(item) {
            $('#paypal-title').val(item.title);
            $('#paypal-min').val(item.min);
            $('#paypal-price').val(item.data.price);
            $('#paypal-description').val(item.data.description);

            $('#paypal-live-id').val(item.data.live_id);
            $('#paypal-live-secret').val(item.data.live_secret);
            $('#paypal-live-app').val(item.data.live_app);
            $('#paypal-test-id').val(item.data.test_id);
            $('#paypal-test-secret').val(item.data.test_secret);

            $('#paypal-callback').val(item.data.callback);

            $("input[name=test][value='" + item.data.test + "']").prop("checked",true);

            $('#paypal-geo-zone').val(item.geo_zone);
            $('#paypal-geo-zone').trigger('change');

            $('#paypal-sort-order').val(item.sort_order);
            $('#paypal-code').val(item.code);

            if (item.status) {
                $('#paypal-status')[0].checked = item.status ? true : false;
            }
        }
    </script>
@endpush