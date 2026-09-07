(function () {
    'use strict';

    var form = document.getElementById('review-backfill-start-form');

    if (!form) {
        return;
    }

    var button = document.getElementById('review-backfill-start-button');
    var recipientCount = form.getAttribute('data-recipient-count');
    var isSubmitting = false;

    function submitForm() {
        if (isSubmitting) {
            return;
        }

        isSubmitting = true;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<i class="fa fa-circle-notch fa-spin mr-1"></i> Pripremam slanje…';

        HTMLFormElement.prototype.submit.call(form);
    }

    function nativeConfirmation() {
        if (window.confirm('Pokrenuti stvarno slanje za ' + recipientCount + ' primatelja?')) {
            submitForm();
        }
    }

    function requestConfirmation() {
        if (typeof window.Swal === 'undefined' || typeof window.Swal.fire !== 'function') {
            nativeConfirmation();
            return;
        }

        window.Swal.fire({
            title: 'Pokrenuti slanje?',
            text: 'Pripremit će se kontrolirano slanje za ' + recipientCount + ' primatelja.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Da, pokreni slanje',
            cancelButtonText: 'Odustani',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-success m-2',
                cancelButton: 'btn btn-alt-secondary m-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                submitForm();
            }
        }).catch(nativeConfirmation);
    }

    form.addEventListener('submit', function (event) {
        if (isSubmitting) {
            return;
        }

        event.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        requestConfirmation();
    });
}());
