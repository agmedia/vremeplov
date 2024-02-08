
@push('css_after')
    <script>
        "use strict";
    </script>
    <!-- Include the PayPal JavaScript SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id={{ $data['client_id'] }}&currency={{ $data['currency'] }}"></script>
@endpush

<div class="row">
    <div class="col-md-6">
        <a class="btn btn-secondary d-block w-100" href="{{ route('naplata') }}">
            <i class="ci-arrow-left  me-1"></i><span class="d-none d-sm-inline">Povratak na plaćanje</span><span class="d-inline d-sm-none">Povratak</span>
        </a>
    </div>
    <div class="col-md-6">
        <!-- Set up a container element for the button -->
        <div id="paypal-button-container"></div>
    </div>
    <div class="col-md-12">
        <div class="alert alert-success d-flex align-items-center fade mt-3" role="alert" id="success_alert">
            <i class="h3 ci-check-circle text-success" style="margin: 0 20px 5px 0;"></i>
            <div class="ml-3">
                Uspješno plaćanje..! Hvala vam na povjerenju.
            </div>
        </div>
        <div class="alert alert-warning d-flex align-items-center fade d-none mt-3" role="alert" id="warning_alert">
            <i class="h3 ci-announcement text-warning" style="margin: 0 20px 5px 0;"></i>
            <div>
                Došlo je do otkazivanja plaćanja..! Možda želite dodati još
                <a href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}"> nešto</a> u košaricu?
            </div>
        </div>
        <div class="alert alert-danger d-flex align-items-center fade d-none mt-3" role="alert" id="danger_alert">
            <i class="h3 ci-close-circle text-danger" style="margin: 0 20px 5px 0;"></i>
            <div>
                Došlo je do greške prilikom plačanja..! Pokušajte ponovo ili nas <a href="{{ route('kontakt') }}"> kontaktirajte</a>.
            </div>
        </div>
    </div>
</div>


@push('js_after')
    <script>
        window.onload = function (e) {
            // Render the PayPal button into #paypal-button-container
            paypal.Buttons({
                style: {
                    layout: 'horizontal',
                    label:  'paypal',
                    tagline: false
                },
                // Call your server to set up the transaction
                createOrder: function(data, actions) {
                    return '{{ $data['vendor_id'] }}';
                },

                onError: (data, actions) => {
                    switchAlert('danger');
                },

                onCancel: (data, actions) => {
                    switchAlert('warning');
                },

                // Call your server to finalize the transaction
                onApprove: function(data, actions) {
                    switchAlert('success');

                    let url = '{{ $data['approve_url'] }}?status={{ $data['status'] }}' +
                        '&provjera=' + data.orderID +
                        //'&approval_code=' + data.facilitatorAccessToken +
                        '&signature=' + data.payerID +
                        '&return_json=1';

                    return axios.get(url).then(function(response) {
                        if (response.data.success) {
                            window.location.href = response.data.href;
                        }

                        setTimeout(() => {
                            window.location.href = '{{ route('checkout.success') }}'
                        }, 3500);
                    });
                }

            }).render('#paypal-button-container');
        }

        /**
         *
         * @param alert 'success', 'warning', 'danger'
         */
        function switchAlert(alert) {
            let types = ['success', 'warning', 'danger'];

            types.forEach((value, index) => {
                $('#' + value + '_alert').removeClass('show');
                $('#' + value + '_alert').addClass('d-none');
            });

            $('#' + alert + '_alert').removeClass('d-none');
            $('#' + alert + '_alert').addClass('show');
        }
    </script>
@endpush