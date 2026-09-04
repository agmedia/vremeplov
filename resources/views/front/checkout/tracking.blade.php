@extends('front.layouts.app')

@section('title', 'Praćenje narudžbe #' . $order->id . ' - Antikvarijat Vremeplov')
@section('description', 'Siguran pregled statusa dostave narudžbe.')
@push('meta_tags')
    <meta name="robots" content="noindex,nofollow">
@endpush

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-muted text-uppercase small mb-2">Narudžba #{{ $order->id }}</div>
                        <h1 class="h3 mb-4">Status dostave</h1>

                        <div class="border-start border-3 border-primary ps-3 mb-4">
                            <strong>{{ $order->shipping_tracking_status ?: ($order->shipped ? 'Narudžba je poslana.' : 'Narudžba se priprema.') }}</strong>
                            @if ($order->shipping_tracking_updated_at)
                                <div class="text-muted small mt-1">Ažurirano {{ $order->shipping_tracking_updated_at->format('d.m.Y. H:i') }}</div>
                            @endif
                        </div>

                        <dl class="row mb-4">
                            <dt class="col-sm-4">Dostavljač</dt>
                            <dd class="col-sm-8">{{ strtoupper($order->shipping_carrier ?: $order->shipping_method) }}</dd>
                            @if ($order->tracking_code)
                                <dt class="col-sm-4">Broj pošiljke</dt>
                                <dd class="col-sm-8">{{ $order->tracking_code }}</dd>
                            @endif
                        </dl>

                        @if ($order->shipping_tracking_url)
                            <a class="btn btn-primary" href="{{ $order->shipping_tracking_url }}" target="_blank" rel="noopener noreferrer">Otvori praćenje dostavljača</a>
                        @endif
                        <a class="btn btn-outline-secondary ms-2" href="{{ route('kontakt') }}">Trebate pomoć?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
