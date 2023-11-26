@extends('front.layouts.app')

@section('content')

    @include('front.customer.layouts.header')

    <div class="container pb-5 mb-2 mb-md-4">
        <div class="row">
        @include('front.customer.layouts.sidebar')
        <!-- Content  -->
            <section class="col-lg-8">
                <!-- Toolbar-->
                <div class="d-none d-lg-flex justify-content-between align-items-center pt-lg-3 pb-4 pb-lg-5 mb-lg-3">
                    <h6 class="fs-base text-dark mb-0">Uredite svoje podatke ispod:</h6>
                    <a class="btn btn-primary btn-sm" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ci-sign-out me-2"></i>Odjava
                    </a>
                </div>

                @include('front.layouts.partials.session')
                <form action="{{ route('moj-racun.snimi', ['user' => $user]) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    {{ method_field('PATCH') }}

                    <div class="row ">
                        <div class="col-sm-12">
                            <h2 class="h6 pt-1 pb-3 mb-3 border-bottom">Osnovni podaci</h2>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label" for="checkout-fn">Ime</label>
                                <input class="form-control @error('fname') is-invalid @enderror" type="text" name="fname" value="{{ $user->details->fname }}">
                                @error('fname') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label" for="checkout-ln">Prezime</label>
                                <input class="form-control @error('lname') is-invalid @enderror" type="text" name="lname" value="{{ $user->details->lname }}">
                                @error('lname') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label" for="checkout-email">E-mail Adresa</label>
                                <input class="form-control @error('email') is-invalid @enderror" type="email" readonly name="email" value="{{ $user->email }}">
                                @error('email') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label" for="checkout-phone">Telefon</label>
                                <input class="form-control" type="text" name="phone" value="{{ $user->details->phone }}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <h2 class="h6 pt-1 pb-3 mb-3 border-bottom">Adresa dostave</h2>
                        </div>
                        <div class="col-sm-6">
                            <div class="col-sm-12">
                                <div class="mb-3">
                                    <label class="form-label" for="checkout-address">Adresa</label>
                                    <input class="form-control @error('address') is-invalid @enderror" type="text" name="address" value="{{ $user->details->address }}">
                                    @error('address') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label" for="checkout-city">Grad</label>
                                <input class="form-control @error('city') is-invalid @enderror" type="text" name="city" value="{{ $user->details->city }}">
                                @error('city') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label" for="checkout-zip">Poštanski broj</label>
                                <input class="form-control @error('zip') is-invalid @enderror" type="text" name="zip" value="{{ $user->details->zip }}">
                                @error('zip') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3" wire:ignore>
                                <label class="form-label" for="checkout-country">Država</label>
                                <select class="form-select g @error('state') is-invalid @enderror" id="checkout-country" name="state">
                                    <option value=""></option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country['name'] }}" {{ $country['name'] == $user->details->state ? 'selected' : '' }}>{{ $country['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('state') <div id="val-username-error" class="invalid-feedback animated fadeIn">Država je obvezna</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label" for="checkout-company">Tvrtka</label>
                                <input class="form-control" type="text" name="company" value="{{ $user->details->company }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <div class="mb-3">
                                    <label class="form-label" for="checkout-oib">OIB</label>
                                    <input class="form-control" type="text" name="oib" value="{{ $user->details->oib }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-12">
                            <button type="submit" class="btn btn-primary d-block w-100">Snimi</button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>
    </div>

@endsection
