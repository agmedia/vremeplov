@extends('front.layouts.app')

@section('title', 'Kontakt - Antikvarijat Vremeplov Zagreb')
@section('description', 'Kontaktirajte Antikvarijat Vremeplov u Zagrebu. Adresa: Zvonimirova 24, telefon 091 762 7441.')
@section('canonical', route('kontakt'))

@push('meta_tags')
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Kontakt - Antikvarijat Vremeplov Zagreb" />
    <meta property="og:description" content="Kontaktirajte Antikvarijat Vremeplov u Zagrebu. Adresa: Zvonimirova 24, telefon 091 762 7441." />
    <meta property="og:url" content="{{ route('kontakt') }}" />
@endpush

@push('css_after')
    <link rel="stylesheet" media="screen" href="{{ asset('css/front-contact.css?v=1.0.1') }}">
@endpush

@section('content')
    <div class="contact-page">
        @include('front.layouts.partials.page-heading', [
            'title' => 'Kontaktirajte nas',
            'current' => 'Kontakt',
        ])

        <section class="contact-page__content">
            <div class="container">
                @include('front.layouts.partials.session')

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

                            <form action="{{ route('poruka') }}" method="POST" id="contact-form" data-analytics-form="contact">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label" for="cf-name">Vaše ime <span aria-hidden="true">*</span></label>
                                        <div class="contact-field">
                                            <i class="fa-regular fa-user" aria-hidden="true"></i>
                                            <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" id="cf-name" value="{{ old('name') }}" autocomplete="name" required aria-required="true">
                                        </div>
                                        @error('name')<div class="invalid-feedback d-block">Molimo upišite vaše ime!</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="cf-email">Email adresa <span aria-hidden="true">*</span></label>
                                        <div class="contact-field">
                                            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                            <input class="form-control @error('email') is-invalid @enderror" type="email" id="cf-email" name="email" value="{{ old('email') }}" autocomplete="email" required aria-required="true">
                                        </div>
                                        @error('email')<div class="invalid-feedback d-block">Molimo upišite ispravnu email adresu!</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="cf-phone">Broj telefona <span aria-hidden="true">*</span></label>
                                        <div class="contact-field">
                                            <i class="fa-regular fa-phone" aria-hidden="true"></i>
                                            <input class="form-control @error('phone') is-invalid @enderror" type="tel" id="cf-phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" required aria-required="true">
                                        </div>
                                        @error('phone')<div class="invalid-feedback d-block">Molimo upišite broj telefona!</div>@enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label" for="cf-message">Upit <span aria-hidden="true">*</span></label>
                                        <textarea class="form-control contact-message @error('message') is-invalid @enderror" id="cf-message" rows="7" name="message" required aria-required="true">{{ old('message') }}</textarea>
                                        @error('message')<div class="invalid-feedback d-block">Molimo upišite poruku!</div>@enderror
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
                                @include('front.layouts.partials.recaptcha-notice')
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
    @include('front.layouts.partials.recaptcha-js', [
        'action' => 'contact',
        'fieldId' => 'recaptcha',
        'formId' => 'contact-form',
    ])
    @if (session()->has('success'))
        <script>window.VremeplovAnalytics.track('generate_lead', {form_name: 'contact'});</script>
    @endif
@endpush
