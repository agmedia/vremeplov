@if (config('services.recaptcha.sitekey'))
    @php($recaptchaError = $recaptchaError ?? '')
    <p class="recaptcha-notice small text-muted mt-2 mb-0">
        Ovu stranicu štiti reCAPTCHA; primjenjuju se Googleova
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Pravila privatnosti</a>
        i <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">Uvjeti pružanja usluge</a>.
    </p>
    <div class="recaptcha-feedback small text-danger mt-1{{ $recaptchaError ? '' : ' d-none' }}" role="alert">{{ $recaptchaError }}</div>
@endif
