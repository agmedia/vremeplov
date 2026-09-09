@php
    $recaptchaAction = $action ?? 'submit';
    $recaptchaFieldId = $fieldId ?? 'recaptcha';
    $recaptchaFormId = $formId ?? null;
@endphp

@include('front.layouts.partials.recaptcha-api')

@if (config('services.recaptcha.sitekey'))
    <script>
        (function () {
            var bindRecaptcha = function () {
                var field = document.getElementById(@json($recaptchaFieldId));
                var form = @json($recaptchaFormId)
                    ? document.getElementById(@json($recaptchaFormId))
                    : (field ? field.form : null);

                if (!field || !form || form.dataset.recaptchaBound === '1') {
                    return;
                }

                form.dataset.recaptchaBound = '1';

                form.addEventListener('submit', function (event) {
                    if (form.dataset.recaptchaTokenReady === '1') {
                        delete form.dataset.recaptchaTokenReady;
                        delete form.dataset.recaptchaPending;
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    if (form.dataset.recaptchaPending === '1') {
                        return;
                    }

                    form.dataset.recaptchaPending = '1';

                    var submitter = event.submitter;
                    var feedback = form.querySelector('.recaptcha-feedback');
                    var submitControls = Array.prototype.slice.call(
                        form.querySelectorAll('button[type="submit"], input[type="submit"]')
                    );
                    var disabledStates = submitControls.map(function (control) {
                        return control.disabled;
                    });
                    var restoreSubmitControls = function () {
                        submitControls.forEach(function (control, index) {
                            control.disabled = disabledStates[index];
                        });
                    };

                    submitControls.forEach(function (control) {
                        control.disabled = true;
                    });

                    if (feedback) {
                        feedback.textContent = '';
                        feedback.classList.add('d-none');
                    }

                    window.VremeplovRecaptcha.execute(@json($recaptchaAction), @json($recaptchaFieldId))
                        .then(function () {
                            delete form.dataset.recaptchaPending;
                            restoreSubmitControls();
                            form.dataset.recaptchaTokenReady = '1';

                            if (typeof form.requestSubmit === 'function') {
                                if (submitter && submitter.form === form) {
                                    form.requestSubmit(submitter);
                                } else {
                                    form.requestSubmit();
                                }
                            } else {
                                HTMLFormElement.prototype.submit.call(form);
                            }
                        })
                        .catch(function () {
                            delete form.dataset.recaptchaPending;
                            restoreSubmitControls();

                            if (feedback) {
                                feedback.textContent = 'Sigurnosna provjera nije uspjela. Osvježite stranicu i pokušajte ponovno.';
                                feedback.classList.remove('d-none');
                            }
                        });
                }, true);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindRecaptcha);
            } else {
                bindRecaptcha();
            }
        })();
    </script>
@endif
