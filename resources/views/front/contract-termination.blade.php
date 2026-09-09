@extends('front.layouts.app')

@section('title', 'Obrazac za jednostrani raskid ugovora - Antikvarijat Vremeplov')
@section('description', 'Pošaljite izjavu o jednostranom raskidu ugovora sklopljenog na daljinu.')

@push('css_after')
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

        @include('front.layouts.partials.session')
        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><strong>Provjerite unesene podatke.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-lg-9">
                <form class="termination-card p-3 p-md-4" action="{{ route('contract-termination.send') }}" method="post" id="contract-termination-form" data-analytics-form="contract_termination">
                    @csrf
                    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="termination-note mb-4">Ovaj obrazac služi za raskid ugovora. Za reklamaciju neispravnog ili neusklađenog proizvoda javite se na <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</div>

                    <section class="mb-4">
                        <h2 class="termination-section-title mb-3"><span class="termination-step">1</span> Podaci potrošača</h2>
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label" for="ct-name">Ime i prezime *</label><input class="form-control @error('full_name') is-invalid @enderror" id="ct-name" name="full_name" value="{{ old('full_name') }}" required maxlength="150" autocomplete="name"></div>
                            <div class="col-md-6"><label class="form-label" for="ct-email">E-mail za potvrdu *</label><input class="form-control @error('email') is-invalid @enderror" id="ct-email" name="email" type="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email"><small class="text-muted">Na ovu adresu šaljemo dokazivu potvrdu primitka.</small></div>
                            <div class="col-md-6"><label class="form-label" for="ct-phone">Telefon <span class="text-muted">(neobavezno)</span></label><input class="form-control" id="ct-phone" name="phone" value="{{ old('phone') }}" maxlength="50" autocomplete="tel"></div>
                            <div class="col-12"><label class="form-label" for="ct-address">Ulica i kućni broj *</label><input class="form-control" id="ct-address" name="address" value="{{ old('address') }}" required maxlength="190" autocomplete="street-address"></div>
                            <div class="col-md-4"><label class="form-label" for="ct-postal">Poštanski broj *</label><input class="form-control" id="ct-postal" name="postal_code" value="{{ old('postal_code') }}" required maxlength="20" autocomplete="postal-code"></div>
                            <div class="col-md-5"><label class="form-label" for="ct-city">Mjesto *</label><input class="form-control" id="ct-city" name="city" value="{{ old('city') }}" required maxlength="100" autocomplete="address-level2"></div>
                            <div class="col-md-3"><label class="form-label" for="ct-country">Država *</label><input class="form-control" id="ct-country" name="country" value="{{ old('country', 'HR') }}" required maxlength="80" autocomplete="country"></div>
                        </div>
                    </section>

                    <hr class="my-4">
                    <section class="mb-4">
                        <h2 class="termination-section-title mb-3"><span class="termination-step">2</span> Podaci o kupnji</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="ct-order">Broj narudžbe ili računa *</label><input class="form-control" id="ct-order" name="order_number" value="{{ old('order_number') }}" required maxlength="80"></div>
                            <div class="col-md-3"><label class="form-label" for="ct-order-date">Datum narudžbe</label><input class="form-control" id="ct-order-date" name="order_date" type="date" value="{{ old('order_date') }}" max="{{ now()->toDateString() }}"></div>
                            <div class="col-md-3"><label class="form-label" for="ct-received-date">Datum primitka robe</label><input class="form-control" id="ct-received-date" name="received_date" type="date" value="{{ old('received_date') }}" max="{{ now()->toDateString() }}"></div>
                            <div class="col-12"><label class="form-label" for="ct-items">Artikli na koje se raskid odnosi *</label><textarea class="form-control" id="ct-items" name="items" rows="5" required maxlength="3000" placeholder="Naziv artikla, količina i, ako je poznata, šifra artikla">{{ old('items') }}</textarea></div>
                            <div class="col-12"><label class="form-label" for="ct-iban">IBAN za povrat <span class="text-muted">(samo ako je potreban)</span></label><input class="form-control" id="ct-iban" name="iban" value="{{ old('iban') }}" maxlength="50" placeholder="HR…"><small class="text-muted">Povrat se u pravilu izvršava istim sredstvom plaćanja. IBAN unesite za uplatu virmanom ili prema dogovoru.</small></div>
                        </div>
                    </section>

                    <hr class="my-4">
                    <section>
                        <h2 class="termination-section-title mb-3"><span class="termination-step">3</span> Izjava i slanje</h2>
                        <div class="form-check mb-3"><input class="form-check-input" id="ct-statement" name="statement" type="checkbox" value="1" required {{ old('statement') ? 'checked' : '' }}><label class="form-check-label" for="ct-statement">Ovime nedvosmisleno izjavljujem da jednostrano raskidam ugovor za gore navedenu robu. *</label></div>
                        @if (config('services.recaptcha.sitekey'))<input type="hidden" name="recaptcha" id="recaptcha">@endif
                        <button class="btn btn-primary px-4" type="submit"><i class="fa-regular fa-envelope me-2"></i>Pošalji izjavu</button>
                        @include('front.layouts.partials.recaptcha-notice')
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
