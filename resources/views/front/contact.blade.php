@extends('front.layouts.app')

@section('title', 'Kontakt - Antikvarijat Vremeplov Zagreb')
@section('description', 'Kontaktirajte Antikvarijat Vremeplov u Zagrebu. Adresa: Zvonimirova 24, telefon 091 762 7441.')
@section('canonical', route('kontakt'))

@php($contactFormSubmitted = old('contact_form') === '1')

@push('meta_tags')
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Kontakt - Antikvarijat Vremeplov Zagreb" />
    <meta property="og:description" content="Kontaktirajte Antikvarijat Vremeplov u Zagrebu. Adresa: Zvonimirova 24, telefon 091 762 7441." />
    <meta property="og:url" content="{{ route('kontakt') }}" />
@endpush

@push('css_after')
    <link rel="stylesheet" media="screen" href="{{ asset('css/front-contact.css?v=1.0.1') }}">
    <link rel="stylesheet" media="screen" href="{{ asset('css/front-form-validation.css?v=1.0.0') }}">
@endpush

@section('content')
    <div class="contact-page">
        @include('front.layouts.partials.page-heading', [
            'title' => 'Kontaktirajte nas',
            'current' => 'Kontakt',
        ])

        <section class="contact-page__content">
            <div class="container">
                @include('front.layouts.partials.session', ['showValidationErrors' => false])

                <div class="contact-page__intro">
                    <span class="contact-page__eyebrow">Antikvarijat Vremeplov</span>
                    <p>Imate pitanje o knjizi, narudžbi ili otkupu? Javite nam se — rado ćemo pomoći.</p>
                </div>

                <div class="row g-4 align-items-stretch contact-page__primary">
                    <div class="col-lg-5">
                        <section class="contact-card contact-details" aria-labelledby="contact-details-title">
                            <div class="contact-details__heading">
                                <span class="contact-details__icon" aria-hidden="true">
                                    <i class="fa-regular fa-book-open"></i>
                                </span>
                                <div>
                                    <p class="contact-details__kicker">Posjetite nas</p>
                                    <h2 id="contact-details-title">Pronađite svoj sljedeći naslov</h2>
                                </div>
                            </div>

                            <address class="contact-list">
                                <a class="contact-list__item" href="#map">
                                    <span class="contact-list__icon" aria-hidden="true"><i class="fa-regular fa-location-dot"></i></span>
                                    <span>
                                        <small>Adresa</small>
                                        Zvonimirova 24, 10000 Zagreb
                                    </span>
                                    <i class="fa-regular fa-arrow-down contact-list__arrow" aria-hidden="true"></i>
                                </a>
                                <a class="contact-list__item" href="tel:+385917627441">
                                    <span class="contact-list__icon" aria-hidden="true"><i class="fa-regular fa-phone"></i></span>
                                    <span>
                                        <small>Telefon</small>
                                        091 762 7441
                                    </span>
                                    <i class="fa-regular fa-arrow-up-right contact-list__arrow" aria-hidden="true"></i>
                                </a>
                                <a class="contact-list__item" href="mailto:{{ config('mail.admin') }}">
                                    <span class="contact-list__icon" aria-hidden="true"><i class="fa-regular fa-envelope"></i></span>
                                    <span>
                                        <small>E-mail</small>
                                        {{ config('mail.admin') }}
                                    </span>
                                    <i class="fa-regular fa-arrow-up-right contact-list__arrow" aria-hidden="true"></i>
                                </a>
                            </address>

                            <div class="contact-hours">
                                <div class="contact-hours__title">
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <h3>Radno vrijeme</h3>
                                </div>
                                <dl>
                                    <div>
                                        <dt>Ponedjeljak – petak</dt>
                                        <dd>09 – 14 h <span>i</span> 16 – 19 h</dd>
                                    </div>
                                    <div>
                                        <dt>Subota</dt>
                                        <dd>10 – 13 h</dd>
                                    </div>
                                </dl>
                            </div>
                        </section>

                    </div>

                    <div class="col-lg-7">
                        <section class="contact-card contact-form-card" aria-labelledby="contact-form-title">
                            <div class="contact-section-heading contact-section-heading--form">
                                <span class="contact-section-heading__icon" aria-hidden="true"><i class="fa-regular fa-paper-plane"></i></span>
                                <div>
                                    <p>Pišite nam</p>
                                    <h2 id="contact-form-title">Pošaljite upit</h2>
                                </div>
                            </div>
                            <p class="contact-form-card__intro">Ispunite obrazac i odgovorit ćemo vam u najkraćem mogućem roku.</p>

                            <form action="{{ route('poruka') }}" method="POST" id="contact-form" data-analytics-form="contact" data-inline-validation data-validation-summary="Provjerite označena polja i pokušajte ponovno." novalidate>
                                @csrf
                                <input type="hidden" name="contact_form" value="1">
                                <div class="form-validation-summary d-none" data-validation-summary role="alert" tabindex="-1">
                                    <i class="fa-regular fa-circle-exclamation" aria-hidden="true"></i>
                                    <span data-validation-summary-text>Provjerite označena polja i pokušajte ponovno.</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label" for="cf-name">Vaše ime <span aria-hidden="true">*</span></label>
                                        <div class="contact-field" data-validation-control>
                                            <i class="fa-regular fa-user" aria-hidden="true"></i>
                                            <input class="form-control{{ $contactFormSubmitted && $errors->has('name') ? ' is-invalid' : '' }}" type="text" name="name" id="cf-name" value="{{ $contactFormSubmitted ? old('name') : '' }}" autocomplete="name" required aria-required="true" aria-invalid="{{ $contactFormSubmitted && $errors->has('name') ? 'true' : 'false' }}" aria-describedby="cf-name-feedback" minlength="2" maxlength="100" data-validation-required="Upišite vaše ime." data-validation-too-short="Ime mora sadržavati najmanje 2 znaka." data-server-error="{{ $contactFormSubmitted ? $errors->first('name') : '' }}">
                                        </div>
                                        @include('front.layouts.partials.validation-feedback', ['field' => 'name', 'controlId' => 'cf-name', 'showErrors' => $contactFormSubmitted])
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="cf-email">Email adresa <span aria-hidden="true">*</span></label>
                                        <div class="contact-field" data-validation-control>
                                            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                            <input class="form-control{{ $contactFormSubmitted && $errors->has('email') ? ' is-invalid' : '' }}" type="email" id="cf-email" name="email" value="{{ $contactFormSubmitted ? old('email') : '' }}" autocomplete="email" required aria-required="true" aria-invalid="{{ $contactFormSubmitted && $errors->has('email') ? 'true' : 'false' }}" aria-describedby="cf-email-feedback" maxlength="190" data-validation-required="Upišite e-mail adresu." data-validation-type="Upišite ispravnu e-mail adresu." data-server-error="{{ $contactFormSubmitted ? $errors->first('email') : '' }}">
                                        </div>
                                        @include('front.layouts.partials.validation-feedback', ['field' => 'email', 'controlId' => 'cf-email', 'showErrors' => $contactFormSubmitted])
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="cf-phone">Broj telefona <span aria-hidden="true">*</span></label>
                                        <div class="contact-field" data-validation-control>
                                            <i class="fa-regular fa-phone" aria-hidden="true"></i>
                                            <input class="form-control{{ $contactFormSubmitted && $errors->has('phone') ? ' is-invalid' : '' }}" type="tel" id="cf-phone" name="phone" value="{{ $contactFormSubmitted ? old('phone') : '' }}" autocomplete="tel" required aria-required="true" aria-invalid="{{ $contactFormSubmitted && $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="cf-phone-feedback" maxlength="30" data-validation-required="Upišite broj telefona." data-validation-phone="Upišite ispravan broj telefona (6–15 znamenki)." data-server-error="{{ $contactFormSubmitted ? $errors->first('phone') : '' }}">
                                        </div>
                                        @include('front.layouts.partials.validation-feedback', ['field' => 'phone', 'controlId' => 'cf-phone', 'showErrors' => $contactFormSubmitted])
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label" for="cf-message">Upit <span aria-hidden="true">*</span></label>
                                        <textarea class="form-control contact-message{{ $contactFormSubmitted && $errors->has('message') ? ' is-invalid' : '' }}" id="cf-message" rows="7" name="message" required aria-required="true" aria-invalid="{{ $contactFormSubmitted && $errors->has('message') ? 'true' : 'false' }}" aria-describedby="cf-message-feedback" minlength="10" maxlength="5000" data-validation-required="Upišite poruku." data-validation-too-short="Poruka mora sadržavati najmanje 10 znakova." data-server-error="{{ $contactFormSubmitted ? $errors->first('message') : '' }}">{{ $contactFormSubmitted ? old('message') : '' }}</textarea>
                                        @include('front.layouts.partials.validation-feedback', ['field' => 'message', 'controlId' => 'cf-message', 'showErrors' => $contactFormSubmitted])
                                    </div>

                                    <div class="col-12 contact-form-card__footer">
                                        <button class="btn btn-primary contact-submit" type="submit">
                                            Pošaljite upit
                                            <i class="fa-regular fa-arrow-right" aria-hidden="true"></i>
                                        </button>
                                        <p class="contact-required-note"><span aria-hidden="true">*</span> Obavezna polja</p>
                                    </div>
                                </div>
                                <input type="hidden" name="recaptcha" id="recaptcha">
                                @include('front.layouts.partials.recaptcha-notice', [
                                    'recaptchaError' => $contactFormSubmitted ? $errors->first('recaptcha') : '',
                                ])
                            </form>
                        </section>
                    </div>
                </div>

                <section class="contact-card contact-legal" aria-labelledby="contact-legal-title">
                    <div class="contact-section-heading">
                        <span class="contact-section-heading__icon" aria-hidden="true"><i class="fa-regular fa-building"></i></span>
                        <div>
                            <p>Podaci o tvrtki</p>
                            <h2 id="contact-legal-title">Impressum</h2>
                        </div>
                    </div>

                    <p class="contact-legal__company">Vremeplov razglednica d.o.o.</p>
                    <dl class="contact-legal__list">
                        <div>
                            <dt>Sjedište</dt>
                            <dd>Avenija Marina Držića 8, Zagreb, 10000</dd>
                        </div>
                        <div>
                            <dt>OIB</dt>
                            <dd>34413434459</dd>
                        </div>
                        <div>
                            <dt>MB</dt>
                            <dd>2623196</dd>
                        </div>
                        <div>
                            <dt>IBAN</dt>
                            <dd>HR4524020061100571694</dd>
                        </div>
                        <div>
                            <dt>Banka</dt>
                            <dd>ERSTE &amp; STEIERMÄRKISCHE BANK d.d. Rijeka</dd>
                        </div>
                        <div>
                            <dt>SWIFT</dt>
                            <dd>ESBCHR22</dd>
                        </div>
                    </dl>
                </section>

                <section class="contact-card contact-map" id="map" aria-labelledby="contact-map-title">
                    <div class="contact-map__header">
                        <div class="contact-section-heading">
                            <span class="contact-section-heading__icon" aria-hidden="true"><i class="fa-regular fa-map-location-dot"></i></span>
                            <div>
                                <p>Zvonimirova 24, Zagreb</p>
                                <h2 id="contact-map-title">Pronađite nas</h2>
                            </div>
                        </div>
                        <a class="contact-map__link" href="https://www.google.com/maps/search/?api=1&amp;query=Antikvarijat+Vremeplov+Zvonimirova+24+Zagreb" target="_blank" rel="noopener noreferrer">
                            Otvori u Google kartama
                            <i class="fa-regular fa-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="contact-map__frame">
                        <iframe title="Lokacija Antikvarijata Vremeplov na karti" src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d11124.005570722158!2d15.988982!3d45.8112305!3m2!1i1024!1i768!4f13.1!3m3!1m2!1s0x4765d6549337de2d%3A0xeb2609abc24978d!2sAntikvarijat%20Vremeplov!5e0!3m2!1shr!2shr!4v1701073620720!5m2!1shr!2shr" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </section>
            </div>
        </section>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/front-form-validation.js?v=1.0.0') }}"></script>
    @include('front.layouts.partials.recaptcha-js', [
        'action' => 'contact',
        'fieldId' => 'recaptcha',
        'formId' => 'contact-form',
    ])
    @if (session()->has('success'))
        <script>window.VremeplovAnalytics.track('generate_lead', {form_name: 'contact'});</script>
    @endif
@endpush
