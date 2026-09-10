(function () {
    'use strict';

    var formSelector = 'form[data-inline-validation]';
    var controlSelector = 'input:not([type="hidden"]):not([aria-hidden="true"]), textarea, select';

    var defaultMessages = {
        required: 'Ovo polje je obavezno.',
        email: 'Upišite ispravnu e-mail adresu.',
        pattern: 'Provjerite format unosa.',
        tooShort: 'Unos je prekratak.',
        tooLong: 'Unos je predugačak.',
        dateMax: 'Datum ne može biti u budućnosti.',
        dateMin: 'Odaberite kasniji datum.',
        phone: 'Upišite ispravan broj telefona.',
        iban: 'Upišite ispravan IBAN.',
        dateOrder: 'Datum primitka ne može biti prije datuma narudžbe.'
    };

    function trimmedValue(control) {
        return typeof control.value === 'string' ? control.value.trim() : '';
    }

    function isEmpty(control) {
        if (control.type === 'checkbox' || control.type === 'radio') {
            return !control.checked;
        }

        return trimmedValue(control) === '';
    }

    function ibanChecksumIsValid(iban) {
        var rearranged = iban.slice(4) + iban.slice(0, 4);
        var remainder = 0;

        for (var index = 0; index < rearranged.length; index += 1) {
            var character = rearranged.charAt(index);
            var numeric = /[A-Z]/.test(character)
                ? String(character.charCodeAt(0) - 55)
                : character;

            for (var digitIndex = 0; digitIndex < numeric.length; digitIndex += 1) {
                remainder = ((remainder * 10) + Number(numeric.charAt(digitIndex))) % 97;
            }
        }

        return remainder === 1;
    }

    function messageFor(control, form) {
        var value = trimmedValue(control);
        var validity = control.validity;

        if (control.required && isEmpty(control)) {
            return control.dataset.validationRequired || defaultMessages.required;
        }

        if (validity.badInput) {
            return control.dataset.validationType || control.dataset.validationPattern || defaultMessages.pattern;
        }

        if (value === '' && !control.required) {
            return '';
        }

        if (control.dataset.validationPhone !== undefined) {
            var digits = value.replace(/\D/g, '');
            var phoneCharactersAreValid = /^\+?[0-9() .\/-]+$/.test(value);

            if (!phoneCharactersAreValid || digits.length < 6 || digits.length > 15) {
                return control.dataset.validationPhone || defaultMessages.phone;
            }
        }

        if (control.dataset.validationIban !== undefined) {
            var iban = value.replace(/\s/g, '').toUpperCase();

            if (!/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(iban) || !ibanChecksumIsValid(iban)) {
                return control.dataset.validationIban || defaultMessages.iban;
            }
        }

        if (control.dataset.validationNotBefore) {
            var comparisonControl = form.querySelector(control.dataset.validationNotBefore);
            var comparisonValue = comparisonControl ? trimmedValue(comparisonControl) : '';

            if (value && comparisonValue && value < comparisonValue) {
                return control.dataset.validationNotBeforeMessage || defaultMessages.dateOrder;
            }
        }

        if (validity.typeMismatch) {
            return control.dataset.validationType || defaultMessages.email;
        }

        if (validity.patternMismatch) {
            return control.dataset.validationPattern || defaultMessages.pattern;
        }

        if (validity.tooShort) {
            return control.dataset.validationTooShort || defaultMessages.tooShort;
        }

        if (validity.tooLong) {
            return control.dataset.validationTooLong || defaultMessages.tooLong;
        }

        if (validity.rangeOverflow) {
            return control.dataset.validationMax || defaultMessages.dateMax;
        }

        if (validity.rangeUnderflow) {
            return control.dataset.validationMin || defaultMessages.dateMin;
        }

        return '';
    }

    function feedbackFor(control) {
        var feedbackId = control.id + '-feedback';
        var feedback = document.getElementById(feedbackId);

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.id = feedbackId;
            feedback.className = 'form-validation-message';
            feedback.setAttribute('aria-live', 'polite');

            var anchor = control.closest('[data-validation-control]') || control;
            anchor.insertAdjacentElement('afterend', feedback);
        }

        var describedBy = (control.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter(Boolean);

        if (describedBy.indexOf(feedbackId) === -1) {
            describedBy.push(feedbackId);
            control.setAttribute('aria-describedby', describedBy.join(' '));
        }

        return feedback;
    }

    function setState(control, message) {
        var feedback = feedbackFor(control);
        var invalid = message !== '';

        control.classList.toggle('is-invalid', invalid);
        control.setAttribute('aria-invalid', invalid ? 'true' : 'false');
        feedback.textContent = message;
        feedback.classList.toggle('is-visible', invalid);

        return !invalid;
    }

    function showSummary(summary, message) {
        if (!summary) {
            return;
        }

        var text = summary.querySelector('[data-validation-summary-text]');
        if (text) {
            text.textContent = message;
        }

        summary.classList.remove('d-none');
    }

    function hideSummary(summary) {
        if (summary) {
            summary.classList.add('d-none');
        }
    }

    function initForm(form) {
        if (form.dataset.inlineValidationBound === '1') {
            return;
        }

        form.dataset.inlineValidationBound = '1';

        var controls = Array.prototype.slice.call(form.querySelectorAll(controlSelector));
        var summary = form.querySelector('[data-validation-summary]');
        var summaryMessage = form.dataset.validationSummary || 'Provjerite označena polja i pokušajte ponovno.';
        var hasServerErrors = false;

        controls.forEach(function (control) {
            if (control.disabled) {
                return;
            }

            var serverError = (control.dataset.serverError || '').trim();
            if (serverError) {
                hasServerErrors = true;
                control.dataset.validationTouched = '1';
                setState(control, serverError);
            } else {
                feedbackFor(control);
            }

            var revalidate = function () {
                if (control.dataset.validationTouched === '1' || control.classList.contains('is-invalid')) {
                    setState(control, messageFor(control, form));
                }

                controls.forEach(function (dependent) {
                    if (dependent.dataset.validationNotBefore === '#' + control.id && dependent.dataset.validationTouched === '1') {
                        setState(dependent, messageFor(dependent, form));
                    }
                });

                if (!form.querySelector('.is-invalid')) {
                    hideSummary(summary);
                }
            };

            control.addEventListener('blur', function () {
                control.dataset.validationTouched = '1';
                setState(control, messageFor(control, form));
            });
            control.addEventListener('input', revalidate);
            control.addEventListener('change', revalidate);
        });

        if (hasServerErrors) {
            showSummary(summary, summaryMessage);
        }

        form.addEventListener('submit', function (event) {
            var firstInvalid = null;

            controls.forEach(function (control) {
                if (control.disabled) {
                    return;
                }

                control.dataset.validationTouched = '1';
                if (!setState(control, messageFor(control, form)) && !firstInvalid) {
                    firstInvalid = control;
                }
            });

            if (!firstInvalid) {
                hideSummary(summary);
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            showSummary(summary, summaryMessage);
            firstInvalid.focus({preventScroll: true});
            firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
        }, true);
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll(formSelector), initForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
