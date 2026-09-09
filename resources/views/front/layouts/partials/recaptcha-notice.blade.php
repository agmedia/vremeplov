@if (config('services.recaptcha.sitekey'))
    <p class="recaptcha-notice small text-muted mt-2 mb-0">
        Ovu stranicu štiti reCAPTCHA; primjenjuju se Googleova
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Pravila privatnosti</a>
        i <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">Uvjeti pružanja usluge</a>.
    </p>
    <div class="recaptcha-feedback small text-danger mt-1 d-none" role="alert"></div>
@endif
