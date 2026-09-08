@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full d-flex justify-content-between align-items-center">
            <div><h1 class="font-size-h2 font-w400 mb-1">{{ $termination->reference }}</h1><div class="text-muted">Izjava o jednostranom raskidu ugovora</div></div>
            <a class="btn btn-alt-secondary" href="{{ route('contract-terminations.index') }}"><i class="fa-duotone fa-arrow-left mr-1"></i>Natrag</a>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        @if($termination->notification_error)
            <div class="alert alert-warning"><strong>Obavijesti nisu u cijelosti poslane</strong><div style="white-space:pre-line">{{ $termination->notification_error }}</div></div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Podaci izjave</h3>
                        <span class="badge badge-{{ $statusColors[$termination->status] ?? 'secondary' }}">{{ $statuses[$termination->status] ?? $termination->status }}</span>
                    </div>
                    <div class="block-content">
                        <div class="alert alert-primary font-w600">Potrošač je nedvosmisleno izjavio da jednostrano raskida ugovor za navedenu robu.</div>
                        <div class="table-responsive"><table class="table table-vcenter"><tbody>
                            <tr><th style="width:230px">Referenca</th><td>{{ $termination->reference }}</td></tr>
                            <tr><th>Podneseno</th><td>{{ optional($termination->submitted_at)->format('d.m.Y. H:i:s') }}</td></tr>
                            <tr><th>Ime i prezime</th><td>{{ $termination->full_name }}</td></tr>
                            <tr><th>E-mail</th><td><a href="mailto:{{ $termination->email }}">{{ $termination->email }}</a></td></tr>
                            <tr><th>Telefon</th><td>{{ $termination->phone ?: '—' }}</td></tr>
                            <tr><th>Adresa</th><td>{{ $termination->address }}, {{ $termination->postal_code }} {{ $termination->city }}, {{ $termination->country }}</td></tr>
                            <tr><th>Broj narudžbe / ugovora</th><td>{{ $termination->order_number }} @if($termination->order)<a class="ml-2" href="{{ route('orders.show', $termination->order) }}">Otvori narudžbu #{{ $termination->order->id }}</a>@endif</td></tr>
                            <tr><th>Datum narudžbe</th><td>{{ optional($termination->order_date)->format('d.m.Y.') ?: '—' }}</td></tr>
                            <tr><th>Datum primitka robe</th><td>{{ optional($termination->received_date)->format('d.m.Y.') ?: '—' }}</td></tr>
                            <tr><th>Artikli</th><td style="white-space:pre-line">{{ $termination->items }}</td></tr>
                            <tr><th>IBAN</th><td>{{ $termination->iban ?: '—' }}</td></tr>
                        </tbody></table></div>
                    </div>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default"><h3 class="block-title">Evidencija e-mailova</h3></div>
                    <div class="block-content">
                        <div class="row">
                            <div class="col-md-6 mb-3"><strong>Potvrda korisniku</strong><br>@if($termination->consumer_notified_at)<span class="text-success"><i class="fa-duotone fa-circle-check mr-1"></i>{{ $termination->consumer_notified_at->format('d.m.Y. H:i:s') }}</span>@else<span class="text-danger">Nije poslano</span>@endif</div>
                            <div class="col-md-6 mb-3"><strong>Obavijest administratoru</strong><br>@if($termination->admin_notified_at)<span class="text-success"><i class="fa-duotone fa-circle-check mr-1"></i>{{ $termination->admin_notified_at->format('d.m.Y. H:i:s') }}</span>@else<span class="text-danger">Nije poslano</span>@endif</div>
                        </div>
                        <form method="POST" action="{{ route('contract-terminations.resend', $termination) }}">@csrf<button class="btn btn-alt-primary mb-4" type="submit"><i class="fa-duotone fa-paper-plane mr-1"></i>Ponovno pošalji oba e-maila</button></form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="block block-rounded">
                    <div class="block-header block-header-default"><h3 class="block-title">Obrada zahtjeva</h3></div>
                    <form method="POST" action="{{ route('contract-terminations.update', $termination) }}">
                        @csrf @method('PATCH')
                        <div class="block-content">
                            <div class="form-group"><label for="termination-status">Status</label><select class="form-control" id="termination-status" name="status" required>@foreach($statuses as $value => $label)<option value="{{ $value }}" {{ old('status', $termination->status) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                            <div class="form-group"><label for="termination-note">Interna napomena</label><textarea class="form-control" id="termination-note" name="internal_note" rows="8" maxlength="5000">{{ old('internal_note', $termination->internal_note) }}</textarea><small class="form-text text-muted">Nije vidljiva korisniku.</small></div>
                            @if($termination->handler)<p class="text-muted small">Zadnja obrada: {{ $termination->handler->name }}, {{ optional($termination->handled_at)->format('d.m.Y. H:i') }}</p>@endif
                        </div>
                        <div class="block-content bg-body-light"><button class="btn btn-hero-success mb-3" type="submit"><i class="fa-duotone fa-floppy-disk mr-1"></i>Spremi obradu</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
