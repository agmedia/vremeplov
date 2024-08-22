<div>
    <div class="steps steps-dark pt-2 pb-3 mb-5">
        <a class="step-item active" href="{{ route('kosarica') }}">
            <div class="step-progress"><span class="step-count">1</span></div>
            <div class="step-label"><i class="ci-cart"></i>Košarica</div>
        </a>
        <a class="step-item @if($step == 'podaci') current @endif @if(in_array($step, ['podaci', 'dostava', 'placanje'])) active @endif" wire:click="changeStep('podaci')" href="javascript:void(0);">
            <div class="step-progress"><span class="step-count">2</span></div>
            <div class="step-label"><i class="ci-user-circle"></i>Podaci</div>
        </a>
        <a class="step-item @if($step == 'dostava') current @endif @if(in_array($step, ['dostava', 'placanje'])) active @endif" wire:click="changeStep('dostava')" href="javascript:void(0);">
            <div class="step-progress"><span class="step-count">3</span></div>
            <div class="step-label"><i class="ci-package"></i>Dostava</div>
        </a>
        <a class="step-item @if($step == 'placanje') current @endif @if(in_array($step, ['placanje'])) active @endif" wire:click="changeStep('placanje')" href="javascript:void(0);">
            <div class="step-progress"><span class="step-count">4</span></div>
            <div class="step-label"><i class="ci-card"></i>Plaćanje</div>
        </a>
        <a class="step-item" href="{{ ($payment != '') ? route('pregled') : '#' }}">
            <div class="step-progress"><span class="step-count">5</span></div>
            <div class="step-label"><i class="ci-check-circle"></i>Pregledaj</div>
        </a>
    </div>

    @if ( ! empty($gdl) && ! $gdl_shipping && ! $gdl_payment)
        @section('google_data_layer')
            <script>
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({ ecommerce: null });
                window.dataLayer.push({
                    'event': '<?php echo $gdl_event; ?>',
                    'ecommerce': {
                        'items': <?php echo json_encode($gdl); ?>
                    } });
            </script>
        @endsection
    @endif

    @if ( ! empty($gdl) && $gdl_shipping && $gdl_event == 'add_shipping_info')
        @section('google_data_layer')
            <script>
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({ ecommerce: null });
                window.dataLayer.push({
                    'event': '<?php echo $gdl_event; ?>',
                    'ecommerce': {
                        'shipping_tier': '<?php echo $gdl_shipping; ?>',
                        'items': <?php echo json_encode($gdl); ?>
                    } });
            </script>
        @endsection
    @endif

    @if ( ! empty($gdl) && $gdl_payment && $gdl_event == 'add_payment_info')
        @section('google_data_layer')
            <script>
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({ ecommerce: null });
                window.dataLayer.push({
                    'event': '<?php echo $gdl_event; ?>',
                    'ecommerce': {
                        'payment_type': '<?php echo $gdl_payment; ?>',
                        'items': <?php echo json_encode($gdl); ?>
                    } });
            </script>
        @endsection
    @endif

    @if ($step == 'podaci')
        <h2 class="h6 pt-1 pb-3 mb-3 border-bottom">Adresa dostave</h2>

        @if (session()->has('login_success'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                {{ session('login_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (auth()->guest())
            <div class="alert alert-custom d-flex mb-3" role="alert">
                <div class="alert-icon">
                    <i class="ci-user"></i>
                </div>
                <div><a data-bs-toggle="collapse" href="#collapseLogin" role="button" aria-expanded="false" aria-controls="collapseLogin" class="alert-link">Prijava</a> za registrirane korisnike!</div>
            </div>

            @if (session()->has('error'))
                <div class="alert alert-primary alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div id="collapseLogin" aria-expanded="false" class="collapse">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <div class="mb-3">
                                    <label class="form-label" for="si-email">Email adresa</label>
                                    <input class="form-control" type="email" wire:model.defer="login.email" placeholder="" required>
                                    <div class="invalid-feedback">Molimo upišite ispravnu email adresu.</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="mb-3">
                                    <label class="form-label" for="si-password">Lozinka</label>
                                    <div class="password-toggle">
                                        <input class="form-control" type="password" wire:model.defer="login.pass" required>
                                        <label class="password-toggle-btn" aria-label="Show/hide password">
                                            <input class="password-toggle-check" type="checkbox"><span class="password-toggle-indicator"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="mb-3 d-flex flex-wrap justify-content-between">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" wire:model.defer="login.remember" id="si-remember">
                                        <label class="form-check-label" for="si-remember">Zapamti me</label>
                                    </div>
                                    <a class="fs-sm" href="{{ route('register') }}">Registriraj se..!</a>
                                </div>
                                <button class="btn btn-primary btn-shadow d-block w-100" wire:click="authUser()" type="button">Prijava</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-sm-6">
                <div class="mb-3">
                    <label class="form-label" for="checkout-fn">Ime <span class="text-danger">*</span></label>
                    <input class="form-control @error('address.fname') is-invalid @enderror" type="text" wire:model.defer="address.fname">
                    @error('address.fname') <div class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                </div>
            </div>
            <div class="col-sm-6">
                <div class="mb-3">
                    <label class="form-label" for="checkout-ln">Prezime <span class="text-danger">*</span></label>
                    <input class="form-control @error('address.lname') is-invalid @enderror" type="text" wire:model.deferl="address.lname">
                    @error('address.lname') <div class="invalid-feedback animated fadeIn">Prezime je obvezno</div> @enderror
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <div class="mb-3">
                    <label class="form-label" for="checkout-email">E-mail Adresa <span class="text-danger">*</span></label>
                    <input class="form-control @error('address.email') is-invalid @enderror" type="email" wire:model.defer="address.email">
                    @error('address.email') <div class="invalid-feedback animated fadeIn">Email adresa je obavezna</div> @enderror
                </div>
            </div>
            <div class="col-sm-6">
                <div class="mb-3">
                    <label class="form-label" for="checkout-phone">Telefon <span class="text-danger">*</span></label>
                    <input class="form-control @error('address.phone') is-invalid  @enderror" type="text" wire:model.defer="address.phone">
                    @error('address.phone') <div class="invalid-feedback animated fadeIn">Broj telefona je obavezan</div> @enderror
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <div class="mb-3">
                    <label class="form-label" for="checkout-address">Adresa <span class="text-danger">*</span></label>
                    <input class="form-control @error('address.address') is-invalid @enderror" type="text" wire:model.defer="address.address">
                    @error('address.address') <div class="invalid-feedback animated fadeIn">Adresa je obvezno</div> @enderror
                </div>
            </div>
            <div class="col-sm-6">
                <div class="mb-3">
                    <label class="form-label" for="checkout-city">Grad <span class="text-danger">*</span></label>
                    <input class="form-control @error('address.city') is-invalid @enderror" type="text" wire:model.defer="address.city">
                    @error('address.city') <div class="invalid-feedback animated fadeIn">Grad je obvezan</div> @enderror
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <div class="mb-3">
                    <label class="form-label" for="checkout-zip">Poštanski broj <span class="text-danger">*</span></label>
                    <input class="form-control @error('address.zip') is-invalid @enderror" type="text" wire:model.defer="address.zip">
                    @error('address.zip') <div class="invalid-feedback animated fadeIn">Poštanski broj je obvezan</div> @enderror
                </div>
            </div>
            <div class="col-sm-6">
                <div class="mb-3" wire:ignore>
                    <label class="form-label" for="checkout-country">Država <span class="text-danger">*</span></label>
                    <select class="form-select @error('address.state') is-invalid @enderror" id="state-select" wire:model="address.state" wire:change="stateSelected($event.target.value)">
                        <option value=""></option>
                        @foreach ($countries as $country)
                            <option value="{{ $country['name'] }}">{{ $country['name'] }}</option>
                        @endforeach
                    </select>
                    @error('address.state') <div class="invalid-feedback animated fadeIn">Država je obvezna</div> @enderror
                </div>
            </div>
        </div>

        <h2 class="h6 pt-3 pb-3 mb-2"><a data-bs-toggle="collapse" href="#collapseOib" role="button" wire:click="viewR1()" aria-expanded="false" aria-controls="collapseLogin" class="alert-link">Trebate R1 račun?</a></h2>

        <div id="collapseOib" aria-expanded="false" class="collapse {{ $view_r1 }}">

            <div class="row ">
                <div class="col-sm-6">
                    <div class="mb-3">
                        <label class="form-label" for="checkout-company">Tvrtka</label>
                        <input class="form-control" type="text" wire:model="address.company">
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="mb-3">
                        <div class="mb-3">
                            <label class="form-label" for="checkout-oib">OIB</label>
                            <input class="form-control" type="text" wire:model="address.oib">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="d-flex pt-4 mt-3">
            <div class="w-50 pe-3"><a class="btn btn-secondary d-block w-100" href="{{ route('kosarica') }}"><i class="ci-arrow-left mt-sm-0 me-1"></i><span class="d-none d-sm-inline">Povratak na košaricu</span><span class="d-inline d-sm-none">Povratak</span></a></div>
            <div class="w-50 ps-2"><a class="btn btn-primary d-block w-100" wire:click="changeStep('dostava')" href="javascript:void(0);"><span class="d-none d-sm-inline">Na odabir dostave</span><span class="d-inline d-sm-none">Nastavi</span><i class="ci-arrow-right mt-sm-0 ms-1"></i></a></div>
        </div>

    @endif


    @if ($step == 'dostava')
        <h2 class="h6 pt-1 pb-3 mb-3 ">Odaberite način dostave</h2>
        <div class="table-responsive">
            <table class="table table-hover fs-sm border-top">
                <thead>
                <tr>
                    <th class="align-middle"></th>
                    <th class="align-middle">Dostava</th>
                    <th class="align-middle">Vrijeme dostave</th>
                    <th class="align-middle">Cijena</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($shippingMethods as $s_method)



               {{-- dd($shippingMethods) --}}
                    <tr wire:click="selectShipping('{{ $s_method->code }}')" style="cursor: pointer;">
                        <td>
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="radio" id="{{ $s_method->code }}" value="{{ $s_method->code }}" wire:model="shipping">
                                <label class="form-check-label" for="courier"></label>
                            </div>
                        </td>
                        <td class="align-middle"><span class="text-dark fw-medium">{{ $s_method->title }}</span><br>
                            <span class="text-muted">{!! $s_method->data->short_description !!}</span><br>
                            @if ($s_method->code == 'gls_eu' && $view_comment)
                                <input class="form-control mt-2" type="text" wire:model="comment" placeholder="">
                            @endif
                        </td>
                        <td class="align-middle">{{ $s_method->data->time }}</td>
                        <td class="align-middle">
                            @if ($is_free_shipping and $s_method->geo_zone == 1)
                                € 0
                                @if ($secondary_price)
                                    <br>0 kn
                                @endif
                            @else
                                € {{ $s_method->data->price }}
                                    @if ($secondary_price)
                                        <br>{{ $s_method->data->price ? number_format($s_method->data->price * $secondary_price, 2) : '0' }} kn
                                    @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>


        @foreach ($shippingMethods as $s_method)
            @if ($s_method->code == 'gls_paketomat' && $view_commentp)

                <div style="height: 600px">
                    <gls-dpm country="hr" id="test-map"></gls-dpm>
                </div>

                <input class="form-control mt-2" type="text" id="commentp"  wire:model="commentp" placeholder="" readonly required>
                @error('commentp')             <script>location.reload();</script>         @enderror
                @error('commentp') <small class="text-danger">Obavezan odabir gls paketomata </small> @enderror


            @endif



        @endforeach


        @error('shipping') <small class="text-danger">Način dostave je obvezan</small> @enderror
        <div class=" d-flex pt-4 mt-3">
            <div class="w-50 pe-3"><a class="btn btn-secondary d-block w-100" wire:click="changeStep('podaci')" href="javascript:void(0);"><i class="ci-arrow-left mt-sm-0 me-1"></i><span class="d-none d-sm-inline">Povratak na unos podataka</span><span class="d-inline d-sm-none">Povratak</span></a></div>
            <div class="w-50 ps-2"><a class="btn btn-primary d-block w-100" wire:click="changeStep('placanje')" href="javascript:void(0);"><span class="d-none d-sm-inline">Na odabir plaćanja</span><span class="d-inline d-sm-none">Nastavi</span><i class="ci-arrow-right mt-sm-0 ms-1"></i></a></div>
        </div>
    @endif


    @if ($step == 'placanje')
        <h2 class="h6 pt-1 pb-3 mb-3 ">Odaberite način plaćanja</h2>
        <div class="table-responsive">
            <table class="table table-hover fs-sm border-top">
                <tbody>
                @foreach ($paymentMethods as $p_method)
                    <tr wire:click="selectPayment('{{ $p_method->code }}')" style="cursor: pointer;">
                        <td>
                            <div class="form-check mb-2  ">
                                <input class="form-check-input" type="radio" value="{{ $p_method->code }}" wire:model="payment">
                                <label class="form-check-label" for="courier"></label>
                            </div>
                        </td>
                        <td class="align-middle"><span class="text-dark fw-medium">{{ $p_method->title }}</span><br><span class="text-muted">{{ $p_method->data->description }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @error('payment') <small class="text-danger">Način plaćanja je obvezan</small> @enderror
        <div class=" d-flex pt-4 mt-3">
            <div class="w-50 pe-3"><a class="btn btn-secondary d-block w-100" wire:click="changeStep('dostava')" href="javascript:void(0);"><i class="ci-arrow-left mt-sm-0 me-1"></i><span class="d-none d-sm-inline">Povratak na odabir dostave</span><span class="d-inline d-sm-none">Povratak</span></a></div>
            <div class="w-50 ps-2"><a class="btn btn-primary d-block w-100" href="{{ ($payment != '') ? route('pregled') : '#' }}"><span class="d-none d-sm-inline">Pregledajte narudžbu</span><span class="d-inline d-sm-none">Nastavi</span><i class="ci-arrow-right mt-sm-0 ms-1"></i></a></div>
        </div>
    @endif

</div>

@push('css_after')





@endpush

@push('js_after')

    <script>
        var el = document.getElementById('test-map');
        el.addEventListener('change', (e) => {
            document.getElementById('commentp').value = e.detail.contact.address + ', ' + e.detail.contact.city + '_' + e.detail.id;
            document.getElementById("commentp").dispatchEvent(new Event('input'));
            alert('Odabrali ste:' + e.detail.name);

        });
    </script>

    <script type="module" src="https://map.gls-croatia.com/widget/gls-dpm.js"></script>







@endpush
