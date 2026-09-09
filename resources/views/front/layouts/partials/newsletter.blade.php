<section class="newsletter-signup" aria-labelledby="newsletter-title">
    <div class="container">
        <div class="newsletter-signup__shell">
            <div class="newsletter-signup__intro">
                <span class="newsletter-signup__icon" aria-hidden="true">
                    <i class="fa-duotone fa-envelope-open-text"></i>
                </span>
                <div>
                    <p class="newsletter-signup__eyebrow">Novosti iz antikvarijata</p>
                    <h2 class="h3 mb-2" id="newsletter-title">Rijetke knjige ne čekaju dugo</h2>
                    <p class="mb-0 text-muted">Primajte obavijesti o novim naslovima, posebnim ponudama i zanimljivostima iz Vremeplova.</p>
                </div>
            </div>

            <div class="newsletter-signup__form-wrap">
                @if (old('newsletter_form') && $errors->any())
                    <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session()->has('newsletter_success'))
                    <div class="alert alert-success py-2 px-3 mb-3" role="status">
                        {{ session('newsletter_success') }}
                    </div>
                @endif

                <div class="alert d-none py-2 px-3 mb-3" id="newsletter-alert" role="status"></div>

                <form action="{{ route('newsletter.subscribe') }}" method="post" id="newsletter-form" novalidate>
                    @csrf
                    <input type="hidden" name="newsletter_form" value="1">
                    <input type="hidden" name="newsletter_started_at" value="{{ app(\App\Services\NewsletterSignupGuard::class)->issueToken() }}">
                    <input type="hidden" name="recaptcha" id="recaptcha_newsletter">

                    <div class="newsletter-signup__honeypot" aria-hidden="true">
                        <label for="newsletter_website">Website</label>
                        <input type="text" id="newsletter_website" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <label class="visually-hidden" for="newsletter_email">E-mail adresa</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0" aria-hidden="true">
                            <i class="fa-regular fa-envelope text-primary"></i>
                        </span>
                        <input class="form-control bg-white border-start-0 ps-0" type="email" id="newsletter_email" name="email" value="{{ old('newsletter_form') ? old('email') : '' }}" placeholder="vaš@email.hr" autocomplete="email" required>
                        <button class="btn btn-primary" type="submit">
                            <span>Prijavite se</span>
                            <i class="fa-regular fa-arrow-right ms-2" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="newsletter_gdpr" name="gdpr" value="1" {{ old('newsletter_form') && old('gdpr') ? 'checked' : '' }} required>
                        <label class="form-check-label fs-xs text-muted" for="newsletter_gdpr">
                            Pristajem primati newsletter. Odjava je moguća u svakom trenutku.
                        </label>
                    </div>
                    @include('front.layouts.partials.recaptcha-notice')
                </form>
            </div>
        </div>
    </div>
</section>

@push('js_after')
    @include('front.layouts.partials.recaptcha-js', [
        'action' => 'newsletter',
        'fieldId' => 'recaptcha_newsletter',
        'formId' => 'newsletter-form',
    ])
    <script>
        (function () {
            var form = document.getElementById('newsletter-form');
            var alertBox = document.getElementById('newsletter-alert');

            if (!form || !alertBox || typeof window.fetch !== 'function') {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var button = form.querySelector('button[type="submit"]');
                var buttonLabel = button ? button.querySelector('span') : null;
                var originalLabel = buttonLabel ? buttonLabel.textContent : '';

                alertBox.classList.add('d-none');
                alertBox.classList.remove('alert-success', 'alert-danger');

                if (button) {
                    button.disabled = true;
                }
                if (buttonLabel) {
                    buttonLabel.textContent = 'Spremanje…';
                }

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (payload) {
                            if (!response.ok) {
                                throw payload;
                            }

                            return payload;
                        });
                    })
                    .then(function (payload) {
                        alertBox.textContent = payload.message || 'Hvala na prijavi!';
                        alertBox.classList.add('alert-success');
                        alertBox.classList.remove('d-none');
                        form.reset();
                    })
                    .catch(function (payload) {
                        var errors = payload && payload.errors ? payload.errors : {};
                        var firstError = Object.keys(errors).length ? errors[Object.keys(errors)[0]][0] : null;
                        alertBox.textContent = firstError || (payload && payload.message) || 'Prijavu trenutačno nije moguće spremiti. Pokušajte ponovno.';
                        alertBox.classList.add('alert-danger');
                        alertBox.classList.remove('d-none');
                    })
                    .finally(function () {
                        if (button) {
                            button.disabled = false;
                        }
                        if (buttonLabel) {
                            buttonLabel.textContent = originalLabel;
                        }
                    });
            });
        })();
    </script>
@endpush
