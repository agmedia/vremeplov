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
                        <h3 class="block-title">General Info</h3>
                    </div>
                    <div class="block-content pb-3">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="title-input">Naslov Trgovine @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="" value="{{ isset($data['basic']->title) ? $data['basic']->title : '' }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="address-input">Adresa @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="address-input" name="address" placeholder="" value="{{ isset($data['basic']->address) ? $data['basic']->address : '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="zip-input">Zip @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="zip-input" name="zip" placeholder="" value="{{ isset($data['basic']->zip) ? $data['basic']->zip : '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="city-input">Grad @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="city-input" name="city" placeholder="" value="{{ isset($data['basic']->city) ? $data['basic']->city : '' }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="state-input">Država @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="state-input" name="state" placeholder="" value="{{ isset($data['basic']->state) ? $data['basic']->state : '' }}">
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="phone-input">Tel./Mob. @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="phone-input" name="phone" placeholder="" value="{{ isset($data['basic']->phone) ? $data['basic']->phone : '' }}">
                            </div>
                            <div class="col-md-7 mb-3">
                                <label for="email-input">Email @include('back.layouts.partials.required-star')</label>
                                <input type="text" class="form-control" id="email-input" name="email" placeholder="" value="{{ isset($data['basic']->email) ? $data['basic']->email : '' }}">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <button type="button" class="btn btn-sm btn-success" onclick="event.preventDefault(); storeBasicInfo();">
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
        function storeBasicInfo() {
            let item = {
                title:   document.getElementById('title-input').value,
                address: document.getElementById('address-input').value,
                zip:     document.getElementById('zip-input').value,
                city:    document.getElementById('city-input').value,
                state:   document.getElementById('state-input').value,
                phone:   document.getElementById('phone-input').value,
                email:   document.getElementById('email-input').value
            };

            axios.post("{{ route('api.application.basic.store') }}", item)
            .then(response => {
                if (response.data.success) {
                    return successToast.fire(response.data.success);
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

    </script>
@endpush
