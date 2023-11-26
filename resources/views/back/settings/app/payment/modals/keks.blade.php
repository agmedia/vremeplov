<div class="modal fade" id="payment-modal-keks" tabindex="-1" role="dialog" aria-labelledby="modal-payment-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-popout" role="document">
        <div class="modal-content rounded">
            <div class="block block-themed block-transparent mb-0">
                <div class="block-header bg-primary">
                    <h3 class="block-title">{{ __('back/app.payments.keks') }}</h3>
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
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="keks-title" class="w-100">{{ __('back/app.payments.input_title') }}</label>
                                        <input type="text" class="form-control" id="keks-title" name="keks-title" placeholder="">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="keks-min">{{ __('back/app.payments.min_order_amount') }}</label>
                                        <input type="text" class="form-control" id="keks-min" name="min">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label for="keks-geo-zone">{{ __('back/app.payments.geo_zone') }} <span class="small text-gray">{{ __('back/app.payments.geo_zone_label') }}</span></label>
                                    <select class="js-select2 form-control" id="keks-geo-zone" name="keks_geo_zone" style="width: 100%;" data-placeholder="{{ __('back/app.payments.select_geo') }}">
                                        <option></option>
                                        @foreach ($geo_zones as $geo_zone)
                                            <option value="{{ $geo_zone->id }}" {{ ((isset($shipping)) and ($shipping->geo_zone == $geo_zone->id)) ? 'selected' : '' }}>{{ isset($geo_zone->title) ? $geo_zone->title : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="keks-price">{{ __('back/app.payments.fee_amount') }}</label>
                                        <input type="text" class="form-control" id="keks-price" name="data['price']">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="bank-short-description" class="w-100">{{ __('back/app.payments.short_desc') }} <span class="small text-gray">{{ __('back/app.payments.short_desc_label') }}</span></label>
                                <textarea id="keks-short-description" class=" form-control"  name="data['short_description']" placeholder="" ></textarea>
                                <small class="form-text text-muted">
                                    160 {{ __('back/app.payments.chars') }} max
                                </small>
                            </div>

{{--                            <div class="form-group mb-4">--}}
{{--                                <label for="keks-description" class="w-100">{{ __('back/app.payments.long_desc') }}<span class="small text-gray"> {{ __('back/app.payments.long_desc_label') }}</span></label>--}}
{{--                                <textarea id="keks-description" class="form-control" rows="4" maxlength="160" data-always-show="true" name="data['description']" placeholder="" data-placement="top"></textarea>--}}
{{--                            </div>--}}

                            <div class="block block-themed block-transparent mb-4">
                                <div class="block-content bg-body pb-3">
                                    <div class="row justify-content-center">
                                        <div class="col-md-11">
                                            <div class="form-group">
                                                <label for="keks-cid">CID:</label>
                                                <input type="text" class="form-control" id="keks-cid" name="data['cid']">
                                            </div>
                                            <div class="form-group">
                                                <label for="keks-tid">TID:</label>
                                                <input type="text" class="form-control" id="keks-tid" name="data['tid']">
                                            </div>
                                            <div class="form-group">
                                                <label for="keks-secret-key">Secret Key:</label>
                                                <input type="text" class="form-control" id="keks-secret-key" name="data['secret_key']">
                                            </div>
                                            <div class="form-group">
                                                <label for="keks-token">Token:</label>
                                                <input type="text" class="form-control" id="keks-token" name="data['token']">
                                            </div>

                                            <div class="form-group">
                                                <label for="keks-callback">CallbackURL: </label>
                                                <input type="text" class="form-control" id="keks-callback" name="data['callback']" value="{{ url('/') }}">
                                            </div>

                                            <div class="form-group">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="d-block">Test mod.</label>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <div class="custom-control custom-radio custom-control-inline custom-control-success custom-control-lg">
                                                            <input type="radio" class="custom-control-input" id="keks-test-on" name="test_keks" checked="" value="1">
                                                            <label class="custom-control-label" for="keks-test-on">On</label>
                                                        </div>
                                                        <div class="custom-control custom-radio custom-control-inline custom-control-danger custom-control-lg">
                                                            <input type="radio" class="custom-control-input" id="keks-test-off" name="test_keks" value="0">
                                                            <label class="custom-control-label" for="keks-test-off">Off</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>


                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="keks-price">{{ __('back/app.payments.sort_order') }}</label>
                                        <input type="text" class="form-control" id="keks-sort-order" name="sort_order">
                                    </div>
                                </div>
                                <div class="col-md-6 text-right" style="padding-top: 37px;">
                                    <div class="form-group">
                                        <label class="css-control css-control-sm css-control-success css-switch res">
                                            <input type="checkbox" class="css-control-input" id="keks-status" name="status">
                                            <span class="css-control-indicator"></span> {{ __('back/app.payments.status_title') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="keks-code" name="code" value="keks">
                        </div>
                    </div>
                </div>
                <div class="block-content block-content-full text-right bg-light">
                    <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                        {{ __('back/app.payments.cancel') }} <i class="fa fa-times ml-2"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); create_keks();">
                        {{ __('back/app.payments.save') }} <i class="fa fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('payment-modal-js')
    <script>
        $(() => {
            //
            $('#keks-geo-zone').select2({
                minimumResultsForSearch: Infinity,
                allowClear: true
            });
        });
        /**
         *
         */
        function create_keks() {
            let item = {
                title: $('#keks-title').val(),
                code: $('#keks-code').val(),
                min: $('#keks-min').val(),
                data: {
                    price: $('#keks-price').val(),
                    short_description: $('#keks-short-description').val(),
                    //description: $('#keks-description').val(),
                    cid: $('#keks-cid').val(),
                    tid: $('#keks-tid').val(),
                    secret_key: $('#keks-secret-key').val(),
                    token: $('#keks-token').val(),
                    callback: $('#keks-callback').val(),
                    test: $("input[name='test_keks']:checked").val(),
                },
                geo_zone: $('#keks-geo-zone').val(),
                status: $('#keks-status')[0].checked,
                sort_order: $('#keks-sort-order').val()
            };

            axios.post("{{ route('api.payment.store') }}", {data: item})
            .then(response => {
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
        function edit_keks(item) {
            console.log(item.data.test);

            $('#keks-min').val(item.min);
            $('#keks-price').val(item.data.price);

            $('#keks-cid').val(item.data.cid);
            $('#keks-tid').val(item.data.tid);
            $('#keks-secret-key').val(item.data.secret_key);
            $('#keks-token').val(item.data.token);
            $('#keks-callback').val(item.data.callback);

            $("input[name=test_keks][value='" + item.data.test + "']").prop("checked",true);

            $('#keks-geo-zone').val(item.geo_zone);
            $('#keks-geo-zone').trigger('change');

            $('#keks-sort-order').val(item.sort_order);
            $('#keks-code').val(item.code);

            $('#keks-title').val(item.title);
            $('#keks-short-description').val(item.data.short_description);
            //$('#keks-description').val(item.data.description);

            if (item.status) {
                $('#keks-status')[0].checked = item.status ? true : false;
            }
        }
    </script>
@endpush
