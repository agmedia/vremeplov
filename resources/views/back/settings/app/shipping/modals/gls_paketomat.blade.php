<div class="modal fade" id="shipment-modal-gls_paketomat" tabindex="-1" role="dialog" aria-labelledby="modal-shipment-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-popout" role="document">
        <div class="modal-content rounded">
            <div class="block block-themed block-transparent mb-0">
                <div class="block-header bg-primary">
                    <h3 class="block-title">GLS Paketomat</h3>
                    <div class="block-options">
                        <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </a>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center">
                        <div class="col-md-10">

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="gls_paketomat-title">Naslov</label>
                                        <input type="text" class="form-control" id="gls_paketomat-title" name="title">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="gls_paketomat-price">Trošak isporuke</label>
                                        <input type="text" class="form-control" id="gls_paketomat-price" name="data['price']">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label for="dm-post-edit-slug">Geo zona <span class="small text-gray">(Geo zona na koju se odnosi dostava..)</span></label>
                                    <select class="js-select2 form-control" id="gls_paketomat-geo-zone" name="geo_zone" style="width: 100%;" data-placeholder="Odaberite geo zonu">
                                        <option></option>
                                        @foreach ($geo_zones as $geo_zone)
                                            <option value="{{ $geo_zone->id }}" {{ ((isset($shipping)) and ($shipping->geo_zone == $geo_zone->id)) ? 'selected' : '' }}>{{ $geo_zone->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="gls_paketomat-time">Trajanje isporuke <span class="small text-gray">(Tekstualno. Npr. 2-3 dana ili 2 do 7 radnih dana...)</span></label>
                                <input type="text" class="form-control" id="gls_paketomat-time" name="data['time']">
                            </div>

                            <div class="form-group mb-4">
                                <label for="gls_paketomat-short-description">Kratki opis <span class="small text-gray">(Prikazuje se prilikom odabira dostave.)</span></label>
                                <textarea class="js-maxlength form-control" id="gls_paketomat-short-description" name="data['short_description']" rows="2" maxlength="160" data-always-show="true" data-placement="top"></textarea>
                                <small class="form-text text-muted">
                                    160 znakova max
                                </small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="gls_paketomat-description">Detaljni opis <span class="small text-gray">(Ako je potreban. Prikazuje se ako je dostava odabrana prilikom kupnje.)</span></label>
                                <textarea class="form-control" id="gls_paketomat-description" name="data['description']" rows="4"></textarea>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="gls_paketomat-price">Poredak</label>
                                        <input type="text" class="form-control" id="gls_paketomat-sort-order" name="sort_order">
                                    </div>
                                </div>
                                <div class="col-md-6 text-right" style="padding-top: 37px;">
                                    <div class="form-group">
                                        <label class="css-control css-control-sm css-control-success css-switch res">
                                            <input type="checkbox" class="css-control-input" id="gls_paketomat-status" name="status">
                                            <span class="css-control-indicator"></span> Status načina dostave
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="gls_paketomat-code" name="code" value="gls_paketomat">
                        </div>
                    </div>
                </div>
                <div class="block-content block-content-full text-right bg-light">
                    <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                        Odustani <i class="fa fa-times ml-2"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); create_gls_paketomat();">
                        Snimi <i class="fa fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('shipment-modal-js')
    <script>
        $(() => {
            $('#gls_paketomat-geo-zone').select2({
                minimumResultsForSearch: Infinity,
                allowClear: true
            });
        });
        /**
         *
         */
        function create_gls_paketomat() {
            let item = {
                title: $('#gls_paketomat-title').val(),
                code: $('#gls_paketomat-code').val(),
                data: {
                    price: $('#gls_paketomat-price').val(),
                    time: $('#gls_paketomat-time').val(),
                    short_description: $('#gls_paketomat-short-description').val(),
                    description: $('#gls_paketomat-description').val(),
                },
                geo_zone: $('#gls_paketomat-geo-zone').val(),
                status: $('#gls_paketomat-status')[0].checked,
                sort_order: $('#gls_paketomat-sort-order').val()
            };

            axios.post("{{ route('api.shipping.store') }}", {data: item})
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
        function edit_gls_paketomat(item) {
            $('#gls_paketomat-title').val(item.title);
            $('#gls_paketomat-price').val(item.data.price);
            $('#gls_paketomat-time').val(item.data.time);
            $('#gls_paketomat-short-description').val(item.data.short_description);
            $('#gls_paketomat-description').val(item.data.description);
            $('#gls_paketomat-sort-order').val(item.sort_order);
            $('#gls_paketomat-code').val(item.code);

            if (item.status) {
                $('#gls_paketomat-status')[0].checked = item.status ? true : false;
            }
        }
    </script>
@endpush
