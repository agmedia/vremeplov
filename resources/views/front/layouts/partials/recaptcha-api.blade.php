@if (config('services.recaptcha.sitekey'))
    @once
        <style>
            .grecaptcha-badge { visibility: hidden !important; }
            .recaptcha-notice { color: #857b7c; font-size: .72rem; line-height: 1.4; }
        </style>
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.sitekey') }}"></script>
        <script>
            window.VremeplovRecaptcha = window.VremeplovRecaptcha || {
                execute: function (action, fieldId) {
                    return new Promise(function (resolve, reject) {
                        if (typeof window.grecaptcha === 'undefined') {
                            reject(new Error('reCAPTCHA API nije učitan.'));
                            return;
                        }

                        window.grecaptcha.ready(function () {
                            window.grecaptcha.execute(@json(config('services.recaptcha.sitekey')), {action: action})
                                .then(function (token) {
                                    var field = document.getElementById(fieldId);

                                    if (!field || !token) {
                                        reject(new Error('reCAPTCHA token nije dostupan.'));
                                        return;
                                    }

                                    field.value = token;
                                    resolve(token);
                                })
                                .catch(reject);
                        });
                    });
                }
            };
        </script>
    @endonce
@endif
