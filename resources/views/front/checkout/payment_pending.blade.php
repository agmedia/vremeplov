@extends('front.layouts.app')

@section('content')
    <div class="container pb-5 mb-sm-4">
        <div class="pt-5">
            <div class="card py-3 mt-sm-3">
                <div class="card-body text-center">
                    <h2 class="h4 pb-3">Potvrda plaćanja je u tijeku</h2>
                    <p class="fs-sm mb-2">
                        PayPal još šalje sigurnu potvrdu za narudžbu broj {{ $order->id }}.
                    </p>
                    <p class="fs-sm">
                        Nemojte ponavljati plaćanje. Ova će se stranica uskoro sama osvježiti.
                    </p>
                    <a class="btn btn-primary mt-3" href="{{ route('checkout.return.paypal') }}">
                        Provjeri ponovno
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.setTimeout(function () {
            window.location.reload();
        }, 4000);
    </script>
@endsection
