@extends('front.layouts.app')

@section('title', 'Ocijenite kupljene knjige | Antikvarijat Vremeplov')
@section('description', 'Ocijenite artikle iz narudžbe #' . $invitation->order_id . '.')

@push('meta_tags')
    <meta name="robots" content="noindex,nofollow,noarchive">
@endpush

@section('content')
    <div class="container py-5">
        <div class="mx-auto" style="max-width:920px;">
            @include('front.layouts.partials.session')

            <div class="text-center mb-5">
                <div class="h2 mb-3" style="color:#c7a361;letter-spacing:.14em;" aria-hidden="true">★★★★★</div>
                <h1 class="h2 font-title">Jesu li knjige pronašle svoje mjesto?</h1>
                <p class="text-muted mb-0">Narudžba #{{ $invitation->order_id }} već je neko vrijeme kod vas. Vaš iskren dojam pomoći će drugim čitateljima.</p>
            </div>

            @if ($items->isEmpty())
                <div class="alert alert-info">U ovoj narudžbi nema artikala dostupnih za recenziju.</div>
            @elseif ($items->every(fn ($item) => $item->review_submitted))
                <div class="alert alert-success text-center">Hvala! Recenzirali ste sve dostupne artikle iz ove narudžbe.</div>
            @endif

            @foreach ($items as $item)
                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start mb-3">
                            <img src="{{ $item->product->thumb }}" alt="" width="72" height="96" class="rounded me-3" style="object-fit:cover;">
                            <div>
                                <h2 class="h5 mb-1 font-title">{{ $item->name ?: $item->product->name }}</h2>
                                <span class="badge bg-success">Potvrđena kupnja</span>
                            </div>
                        </div>

                        @if ($item->review_submitted)
                            <div class="alert alert-success mb-0">Recenzija je poslana i čeka provjeru.</div>
                        @else
                            <form method="POST" action="{{ $formAction }}">
                                @csrf
                                <input type="hidden" name="order_product_id" value="{{ $item->id }}">

                                <div class="mb-3" style="max-width:240px;">
                                    <label class="form-label" for="stars-{{ $item->id }}">Ocjena *</label>
                                    <select class="form-select" id="stars-{{ $item->id }}" name="stars" required>
                                        <option value="">Odaberite ocjenu</option>
                                        <option value="5">5 — Odlično</option>
                                        <option value="4">4 — Vrlo dobro</option>
                                        <option value="3">3 — Dobro</option>
                                        <option value="2">2 — Dovoljno</option>
                                        <option value="1">1 — Slabo</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="message-{{ $item->id }}">Vaš dojam *</label>
                                    <textarea class="form-control" id="message-{{ $item->id }}" name="message" rows="4" minlength="10" maxlength="1000" required></textarea>
                                    <div class="form-text">Recenzija će biti objavljena nakon provjere. Vaš e-mail neće biti prikazan.</div>
                                </div>

                                <button class="btn btn-primary" type="submit">Pošalji recenziju</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endsection
