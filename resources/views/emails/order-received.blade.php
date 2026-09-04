@extends('emails.layouts.base')

@section('email_title', 'Nova narudžba #' . $order->id . ' — Vremeplov administracija')
@section('preheader', 'Nova narudžba #' . $order->id . ' čeka obradu.')

@section('content')
    <div class="mail-eyebrow" style="margin:0 0 11px;color:#a17436;font-size:11px;font-weight:bold;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">Administracija · nova kupnja</div>
    <h1 class="mail-title" style="margin:0 0 15px;color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:31px;font-weight:normal;line-height:39px;">Stigla je nova narudžba</h1>
    <p style="margin:0 0 24px;color:#665b52;font-size:15px;line-height:24px;">Provjerite plaćanje, dostupnost artikala i odabrani način dostave prije daljnje obrade.</p>

    @include('emails.layouts.partials.order-meta', ['order' => $order, 'statusLabel' => 'Čeka obradu'])
    @include('emails.layouts.partials.order-details', ['order' => $order])
    @include('emails.layouts.partials.order-price-table', ['order' => $order])
    @include('emails.layouts.partials.payment-summary', ['order' => $order, 'showBankInstructions' => false])

    @if (! empty(trim((string) $order->comment)))
        <div style="margin-top:24px;padding:17px 19px;background-color:#fff8e8;border-left:4px solid #c7a361;color:#5f554d;font-size:13px;line-height:21px;">
            <strong style="color:#2d2224;">Komentar kupca</strong><br>{{ $order->comment }}
        </div>
    @endif
@endsection
