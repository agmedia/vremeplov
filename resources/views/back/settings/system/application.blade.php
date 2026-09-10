@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Postavke Aplikacije</h1>
                <!--                <button class="btn btn-hero-success my-2" onclick="event.preventDefault(); openModal();">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> {{ __('back/app.statuses.new') }}</span>
                </button>-->
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="row">
            <div class="col-md-7">
                <div class="block">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Sadržaj zaglavlja i footera</h3>
                    </div>
                    <div class="block-content pb-3">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="announcement-text-input">Tekst gornje obavijesti @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="announcement-text-input" name="announcement_text" maxlength="255" value="{{ old('announcement_text', $data['storefront']['announcement_text']) }}">
                                <small class="form-text text-muted">Tekst se prikazuje centriran na vrhu stranice.</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="footer-title-input">Naziv u footeru @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="footer-title-input" name="footer_title" maxlength="191" value="{{ old('footer_title', $data['storefront']['footer_title']) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="footer-address-input">Adresa @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="footer-address-input" name="footer_address" maxlength="191" value="{{ old('footer_address', $data['storefront']['footer_address']) }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="footer-postal-code-input">Poštanski broj @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="footer-postal-code-input" name="footer_postal_code" maxlength="20" value="{{ old('footer_postal_code', $data['storefront']['footer_postal_code']) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="footer-city-input">Grad @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="footer-city-input" name="footer_city" maxlength="100" value="{{ old('footer_city', $data['storefront']['footer_city']) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="footer-phone-input">Broj telefona @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="footer-phone-input" name="footer_phone" maxlength="40" value="{{ old('footer_phone', $data['storefront']['footer_phone']) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="footer-weekday-hours-input">Radno vrijeme Pon-Pet @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="footer-weekday-hours-input" name="footer_weekday_hours" maxlength="191" value="{{ old('footer_weekday_hours', $data['storefront']['footer_weekday_hours']) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="footer-saturday-hours-input">Radno vrijeme subotom @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="footer-saturday-hours-input" name="footer_saturday_hours" maxlength="191" value="{{ old('footer_saturday_hours', $data['storefront']['footer_saturday_hours']) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="instagram-url-input">Instagram poveznica</label>
                                <input type="url" class="form-control" id="instagram-url-input" name="instagram_url" maxlength="500" value="{{ old('instagram_url', $data['storefront']['instagram_url']) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="facebook-url-input">Facebook poveznica</label>
                                <input type="url" class="form-control" id="facebook-url-input" name="facebook_url" maxlength="500" value="{{ old('facebook_url', $data['storefront']['facebook_url']) }}">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <button type="button" class="btn btn-sm btn-success" onclick="event.preventDefault(); storeStorefrontContent();">
                            Snimi <i class="fa fa-save ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="block">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Odaberi Glavnu Valutu</h3>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10 mt-3">
                                <div class="form-group">
                                    <select class="js-select2 form-control" id="currency-main-select" name="currency_main_select" style="width: 100%;" data-placeholder="Odaberite glavnu valutu">
                                        <option></option>
                                        @foreach ($data['currencies'] as $item)
                                            <option value="{{ $item->id }}" {{ ((isset($data['currency_main'])) and ($data['currency_main']->id == $item->id)) ? 'selected' : '' }}>
                                                {{ $item->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <button type="button" class="btn btn-sm btn-success" onclick="event.preventDefault(); storeMainCurrency();">
                            Snimi <i class="fa fa-save ml-2"></i>
                        </button>
                    </div>
                </div>

                <div class="block">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Google Maps API Key</h3>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-2">
                            <div class="col-md-10 mt-1">
                                <div class="form-group">
                                    <label for="email-input">Key @include('back.layouts.partials.required-star')</label>
                                    <input type="text" class="form-control" id="api-key-input" name="api_key" placeholder="" value="{{ isset($data['google_maps_key']->key) ? $data['google_maps_key']->key : old('api_key') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <button type="button" class="btn btn-sm btn-success" onclick="event.preventDefault(); storeGoogleMapsApiKey();">
                            Snimi <i class="fa fa-save ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('modals')

@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {
            $('#currency-main-select').select2({
                minimumResultsForSearch: Infinity
            });
        });

        /**
         *
         */
        function storeMainCurrency() {
            let item = {main: $('#currency-main-select').val()};

            axios.post("{{ route('api.currencies.store.main') }}", {data: item})
            .then(response => {
                console.log(response.data)
                if (response.data.success) {
                    return successToast.fire(response.data.success);
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         */
        function storeGoogleMapsApiKey() {
            let item = {key: $('#api-key-input').val()};

            axios.post("{{ route('api.application.google-api.store.key') }}", {data: item})
            .then(response => {
                console.log(response.data)
                if (response.data.success) {
                    return successToast.fire(response.data.success);
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         */
        function storeStorefrontContent() {
            let item = {
                announcement_text:      document.getElementById('announcement-text-input').value,
                footer_title:           document.getElementById('footer-title-input').value,
                footer_address:         document.getElementById('footer-address-input').value,
                footer_postal_code:     document.getElementById('footer-postal-code-input').value,
                footer_city:            document.getElementById('footer-city-input').value,
                footer_phone:           document.getElementById('footer-phone-input').value,
                footer_weekday_hours:   document.getElementById('footer-weekday-hours-input').value,
                footer_saturday_hours:  document.getElementById('footer-saturday-hours-input').value,
                instagram_url:          document.getElementById('instagram-url-input').value,
                facebook_url:           document.getElementById('facebook-url-input').value
            };

            axios.post("{{ route('api.application.storefront-content.store') }}", item)
            .then(response => {
                if (response.data.success) {
                    return successToast.fire(response.data.success);
                } else {
                    return errorToast.fire(response.data.message);
                }
            })
            .catch(error => {
                let errors = error.response?.data?.errors;
                let message = errors ? Object.values(errors).flat().join(' ') : 'Podatke nije moguće spremiti.';

                return errorToast.fire(message);
            });
        }

    </script>
@endpush
