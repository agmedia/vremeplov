@extends('front.layouts.app')

@section('title', 'Obrazac za jednostrani raskid ugovora - Antikvarijat Vremeplov')
@section('description', 'Pošaljite izjavu o jednostranom raskidu ugovora sklopljenog na daljinu.')

@php($terminationFormSubmitted = old('contract_termination_form') === '1')

@push('css_after')
    <link rel="stylesheet" media="screen" href="{{ asset('css/front-form-validation.css?v=1.0.0') }}">
    <style>
        .termination-page{color:#435066}.termination-page h1,.termination-page h2,.termination-page h3{color:#33483b;font-family:Georgia,'Times New Roman',serif}.termination-card{background:#fff;border:1px solid #e0e4e9;border-radius:16px;box-shadow:0 9px 28px rgba(42,49,58,.06)}.termination-section-title{display:flex;align-items:center;gap:11px;font-size:1.05rem}.termination-step{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#f5f0e4;color:#a27d34;font:700 14px Arial,sans-serif}.termination-note{background:#f5f7fa;border-left:3px solid #94a7c1;border-radius:6px;padding:14px 16px;font-size:.88rem}.termination-side ul{padding-left:1.25rem}.termination-side li{padding-left:.35rem;margin-bottom:.85rem;font-size:.9rem;line-height:1.55}.termination-side li::marker{color:#c4a45e}.termination-page .form-label{font-weight:700;color:#374255}.termination-page .form-control{border-color:#d7deea;border-radius:12px;padding:.75rem 1rem}.termination-page .form-control:focus{border-color:#c4a45e;box-shadow:0 0 0 .2rem rgba(196,164,94,.15)}
    </style>
@endpush

@section('content')
    @include('front.layouts.partials.page-heading', [
        'title' => 'Jednostrani raskid ugovora',
    ])

    <main class="container termination-page py-4 py-lg-5">
        <div class="mb-4">
            <p class="mb-0">Ovim obrascem možete jednostavno i nedvosmisleno raskinuti ugovor sklopljen na daljinu. Razlog nije potrebno navesti, a potvrdu primitka bez odgađanja šaljemo na vaš e-mail.</p>
        </div>

        @include('front.layouts.partials.session', ['showValidationErrors' => false])

        <div class="row g-4 align-items-start">
            <div class="col-lg-9">
                <form class="termination-card p-3 p-md-4" action="{{ route('contract-termination.send') }}" method="post" id="contract-termination-form" data-analytics-form="contract_termination" data-inline-validation data-validation-summary="Provjerite označena polja i pokušajte ponovno." novalidate>
                    @csrf
                    <input type="hidden" name="contract_termination_form" value="1">
                    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="termination-note mb-4">Ovaj obrazac služi za raskid ugovora. Za reklamaciju neispravnog ili neusklađenog proizvoda javite se na <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</div>
                    <div class="form-validation-summary d-none" data-validation-summary role="alert" tabindex="-1">
                        <i class="fa-regular fa-circle-exclamation" aria-hidden="true"></i>
                        <span data-validation-summary-text>Provjerite označena polja i pokušajte ponovno.</span>
                    </div>

                    <section class="mb-4">
                        <h2 class="termination-section-title mb-3"><span class="termination-step">1</span> Podaci potrošača</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="ct-name">Ime i prezime *</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('full_name') ? ' is-invalid' : '' }}" id="ct-name" name="full_name" value="{{ $terminationFormSubmitted ? old('full_name') : '' }}" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('full_name') ? 'true' : 'false' }}" aria-describedby="ct-name-feedback" maxlength="150" autocomplete="name" data-validation-required="Upišite ime i prezime." data-server-error="{{ $terminationFormSubmitted ? $errors->first('full_name') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'full_name', 'controlId' => 'ct-name', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="ct-email">E-mail za potvrdu *</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('email') ? ' is-invalid' : '' }}" id="ct-email" name="email" type="email" value="{{ $terminationFormSubmitted ? old('email') : '' }}" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('email') ? 'true' : 'false' }}" aria-describedby="ct-email-feedback ct-email-help" maxlength="190" autocomplete="email" data-validation-required="Upišite e-mail adresu." data-validation-type="Upišite ispravnu e-mail adresu." data-server-error="{{ $terminationFormSubmitted ? $errors->first('email') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'email', 'controlId' => 'ct-email', 'showErrors' => $terminationFormSubmitted])
                                <small class="text-muted" id="ct-email-help">Na ovu adresu šaljemo dokazivu potvrdu primitka.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="ct-phone">Telefon <span class="text-muted">(neobavezno)</span></label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('phone') ? ' is-invalid' : '' }}" id="ct-phone" name="phone" type="tel" value="{{ $terminationFormSubmitted ? old('phone') : '' }}" aria-invalid="{{ $terminationFormSubmitted && $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="ct-phone-feedback" maxlength="50" autocomplete="tel" data-validation-phone="Upišite ispravan broj telefona (6–15 znamenki)." data-server-error="{{ $terminationFormSubmitted ? $errors->first('phone') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'phone', 'controlId' => 'ct-phone', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="ct-address">Ulica i kućni broj *</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('address') ? ' is-invalid' : '' }}" id="ct-address" name="address" value="{{ $terminationFormSubmitted ? old('address') : '' }}" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('address') ? 'true' : 'false' }}" aria-describedby="ct-address-feedback" maxlength="190" autocomplete="street-address" data-validation-required="Upišite ulicu i kućni broj." data-server-error="{{ $terminationFormSubmitted ? $errors->first('address') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'address', 'controlId' => 'ct-address', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="ct-postal">Poštanski broj *</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('postal_code') ? ' is-invalid' : '' }}" id="ct-postal" name="postal_code" value="{{ $terminationFormSubmitted ? old('postal_code') : '' }}" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('postal_code') ? 'true' : 'false' }}" aria-describedby="ct-postal-feedback" maxlength="20" autocomplete="postal-code" data-validation-required="Upišite poštanski broj." data-server-error="{{ $terminationFormSubmitted ? $errors->first('postal_code') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'postal_code', 'controlId' => 'ct-postal', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="ct-city">Mjesto *</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('city') ? ' is-invalid' : '' }}" id="ct-city" name="city" value="{{ $terminationFormSubmitted ? old('city') : '' }}" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('city') ? 'true' : 'false' }}" aria-describedby="ct-city-feedback" maxlength="100" autocomplete="address-level2" data-validation-required="Upišite mjesto." data-server-error="{{ $terminationFormSubmitted ? $errors->first('city') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'city', 'controlId' => 'ct-city', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="ct-country">Država *</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('country') ? ' is-invalid' : '' }}" id="ct-country" name="country" value="{{ $terminationFormSubmitted ? old('country', 'HR') : 'HR' }}" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('country') ? 'true' : 'false' }}" aria-describedby="ct-country-feedback" maxlength="80" autocomplete="country" data-validation-required="Upišite državu." data-server-error="{{ $terminationFormSubmitted ? $errors->first('country') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'country', 'controlId' => 'ct-country', 'showErrors' => $terminationFormSubmitted])
                            </div>
                        </div>
                    </section>

                    <hr class="my-4">
                    <section class="mb-4">
                        <h2 class="termination-section-title mb-3"><span class="termination-step">2</span> Podaci o kupnji</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="ct-order">Broj narudžbe ili računa *</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('order_number') ? ' is-invalid' : '' }}" id="ct-order" name="order_number" value="{{ $terminationFormSubmitted ? old('order_number') : '' }}" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('order_number') ? 'true' : 'false' }}" aria-describedby="ct-order-feedback" maxlength="80" data-validation-required="Upišite broj narudžbe ili računa." data-server-error="{{ $terminationFormSubmitted ? $errors->first('order_number') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'order_number', 'controlId' => 'ct-order', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="ct-order-date">Datum narudžbe</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('order_date') ? ' is-invalid' : '' }}" id="ct-order-date" name="order_date" type="date" value="{{ $terminationFormSubmitted ? old('order_date') : '' }}" max="{{ now()->toDateString() }}" aria-invalid="{{ $terminationFormSubmitted && $errors->has('order_date') ? 'true' : 'false' }}" aria-describedby="ct-order-date-feedback" data-validation-type="Upišite ispravan datum narudžbe." data-validation-max="Datum narudžbe ne može biti u budućnosti." data-server-error="{{ $terminationFormSubmitted ? $errors->first('order_date') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'order_date', 'controlId' => 'ct-order-date', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="ct-received-date">Datum primitka robe</label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('received_date') ? ' is-invalid' : '' }}" id="ct-received-date" name="received_date" type="date" value="{{ $terminationFormSubmitted ? old('received_date') : '' }}" max="{{ now()->toDateString() }}" aria-invalid="{{ $terminationFormSubmitted && $errors->has('received_date') ? 'true' : 'false' }}" aria-describedby="ct-received-date-feedback" data-validation-type="Upišite ispravan datum primitka robe." data-validation-max="Datum primitka robe ne može biti u budućnosti." data-validation-not-before="#ct-order-date" data-validation-not-before-message="Datum primitka robe ne može biti prije datuma narudžbe." data-server-error="{{ $terminationFormSubmitted ? $errors->first('received_date') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'received_date', 'controlId' => 'ct-received-date', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="ct-items">Artikli na koje se raskid odnosi *</label>
                                <textarea class="form-control{{ $terminationFormSubmitted && $errors->has('items') ? ' is-invalid' : '' }}" id="ct-items" name="items" rows="5" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('items') ? 'true' : 'false' }}" aria-describedby="ct-items-feedback" maxlength="3000" placeholder="Naziv artikla, količina i, ako je poznata, šifra artikla" data-validation-required="Navedite artikle na koje se raskid odnosi." data-server-error="{{ $terminationFormSubmitted ? $errors->first('items') : '' }}">{{ $terminationFormSubmitted ? old('items') : '' }}</textarea>
                                @include('front.layouts.partials.validation-feedback', ['field' => 'items', 'controlId' => 'ct-items', 'showErrors' => $terminationFormSubmitted])
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="ct-iban">IBAN za povrat <span class="text-muted">(samo ako je potreban)</span></label>
                                <input class="form-control{{ $terminationFormSubmitted && $errors->has('iban') ? ' is-invalid' : '' }}" id="ct-iban" name="iban" value="{{ $terminationFormSubmitted ? old('iban') : '' }}" aria-invalid="{{ $terminationFormSubmitted && $errors->has('iban') ? 'true' : 'false' }}" aria-describedby="ct-iban-feedback ct-iban-help" maxlength="50" placeholder="HR…" data-validation-iban="Upišite ispravan IBAN." data-server-error="{{ $terminationFormSubmitted ? $errors->first('iban') : '' }}">
                                @include('front.layouts.partials.validation-feedback', ['field' => 'iban', 'controlId' => 'ct-iban', 'showErrors' => $terminationFormSubmitted])
                                <small class="text-muted" id="ct-iban-help">Povrat se u pravilu izvršava istim sredstvom plaćanja. IBAN unesite za uplatu virmanom ili prema dogovoru.</small>
                            </div>
                        </div>
                    </section>

                    <hr class="my-4">
                    <section>
                        <h2 class="termination-section-title mb-3"><span class="termination-step">3</span> Izjava i slanje</h2>
                        <div class="mb-3" data-validation-control>
                            <div class="form-check">
                                <input class="form-check-input{{ $terminationFormSubmitted && $errors->has('statement') ? ' is-invalid' : '' }}" id="ct-statement" name="statement" type="checkbox" value="1" required aria-required="true" aria-invalid="{{ $terminationFormSubmitted && $errors->has('statement') ? 'true' : 'false' }}" aria-describedby="ct-statement-feedback" data-validation-required="Potvrdite izjavu o raskidu ugovora." data-server-error="{{ $terminationFormSubmitted ? $errors->first('statement') : '' }}" {{ $terminationFormSubmitted && old('statement') ? 'checked' : '' }}>
                                <label class="form-check-label" for="ct-statement">Ovime nedvosmisleno izjavljujem da jednostrano raskidam ugovor za gore navedenu robu. *</label>
                            </div>
                        </div>
                        @include('front.layouts.partials.validation-feedback', ['field' => 'statement', 'controlId' => 'ct-statement', 'showErrors' => $terminationFormSubmitted])
                        @if (config('services.recaptcha.sitekey'))<input type="hidden" name="recaptcha" id="recaptcha">@endif
                        <button class="btn btn-primary px-4" type="submit"><i class="fa-regular fa-envelope me-2"></i>Pošalji izjavu</button>
                        @include('front.layouts.partials.recaptcha-notice', [
                            'recaptchaError' => $terminationFormSubmitted ? $errors->first('recaptcha') : '',
                        ])
                        <p class="small text-muted mt-3 mb-0">Podatke koristimo isključivo za obradu zahtjeva i ispunjavanje zakonskih obveza.</p>
                    </section>
                </form>
            </div>
            <aside class="col-lg-3">
                <div class="termination-card termination-side p-4">
                    <h2 class="h5 mb-3">Važno prije slanja</h2>
                    <ul class="mb-4">
                        <li>Za ugovor sklopljen na daljinu rok je u pravilu 14 dana, bez navođenja razloga.</li>
                        <li>Za robu rok u pravilu počinje danom kada ste vi ili osoba koju ste odredili primili robu.</li>
                        <li>Robu vratite bez nepotrebnog odgađanja, najkasnije 14 dana od slanja izjave.</li>
                        <li>Izravne troškove povrata robe snosi potrošač.</li>
                        <li>Sačuvajte dokaz da ste robu poslali.</li>
                    </ul>
                    <hr>
                    <h3 class="h6">Adresa za povrat robe</h3>
                    <p class="small mb-0"><strong>Vremeplov razglednica d.o.o.</strong><br>Zvonimirova 24<br>10000 Zagreb</p>
                </div>
            </aside>
        </div>
    </main>
@endsection

@push('js_after')
    <script src="{{ asset('js/front-form-validation.js?v=1.0.0') }}"></script>
    @if (config('services.recaptcha.sitekey'))
        @include('front.layouts.partials.recaptcha-js', [
            'action' => 'contract_termination',
            'fieldId' => 'recaptcha',
            'formId' => 'contract-termination-form',
        ])
    @endif
    @if (session()->has('success'))
        <script>window.VremeplovAnalytics.track('generate_lead', {form_name: 'contract_termination'});</script>
    @endif
@endpush
