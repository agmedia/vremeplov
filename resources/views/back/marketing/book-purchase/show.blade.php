@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
            <div><h1 class="font-size-h2 font-w400 mb-1">{{ $purchase->reference }}</h1><div class="text-muted">Prijava za otkup knjiga · {{ optional($purchase->submitted_at)->format('d.m.Y. H:i') }}</div></div>
            <a class="btn btn-alt-secondary mt-3 mt-sm-0" href="{{ route('book-purchases.index') }}"><i class="fa-duotone fa-arrow-left mr-1"></i>Natrag na prijave</a>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="row">
            <div class="col-lg-8">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Podaci prijave</h3>
                        <span class="badge badge-{{ $statusColors[$purchase->status] ?? 'secondary' }}">{{ $statuses[$purchase->status] ?? $purchase->status }}</span>
                    </div>
                    <div class="block-content">
                        <div class="table-responsive"><table class="table table-vcenter"><tbody>
                            <tr><th style="width:220px">Referenca</th><td>{{ $purchase->reference }}</td></tr>
                            <tr><th>Zaprimljeno</th><td>{{ optional($purchase->submitted_at)->format('d.m.Y. H:i:s') }}</td></tr>
                            <tr><th>Ime i prezime</th><td>{{ $purchase->full_name }}</td></tr>
                            <tr><th>E-mail</th><td><a href="mailto:{{ $purchase->email }}">{{ $purchase->email }}</a></td></tr>
                            <tr><th>Kontakt broj</th><td><a href="tel:{{ preg_replace('/\s+/', '', $purchase->phone) }}">{{ $purchase->phone }}</a></td></tr>
                            <tr><th>Poštanski broj</th><td>{{ $purchase->postal_code }}</td></tr>
                        </tbody></table></div>
                    </div>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default"><h3 class="block-title">Fotografije <span class="text-muted ml-2">{{ count($purchase->photos ?: []) }}</span></h3></div>
                    <div class="block-content">
                        <div class="row">
                            @forelse($purchase->photos ?: [] as $index => $photo)
                                @php($previewable = in_array($photo['mime_type'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true))
                                <div class="col-sm-6 col-xl-4 mb-4">
                                    <a class="d-block border rounded overflow-hidden bg-body-light" href="{{ route('book-purchases.photos.show', [$purchase, $index]) }}" target="_blank" rel="noopener" style="aspect-ratio:1/1;">
                                        @if($previewable)
                                            <img class="w-100 h-100" src="{{ route('book-purchases.photos.show', [$purchase, $index]) }}" alt="Fotografija {{ $index + 1 }}" style="object-fit:cover;">
                                        @else
                                            <span class="d-flex flex-column align-items-center justify-content-center h-100 text-muted"><i class="fa-duotone fa-file-image fa-3x mb-2"></i>Otvori fotografiju</span>
                                        @endif
                                    </a>
                                    <div class="small text-truncate mt-2" title="{{ $photo['name'] ?? '' }}">{{ $photo['name'] ?? 'Fotografija ' . ($index + 1) }}</div>
                                    <div class="text-muted small">{{ number_format(((int) ($photo['size'] ?? 0)) / 1048576, 2, ',', '.') }} MB</div>
                                </div>
                            @empty
                                <div class="col-12 text-muted pb-4">Uz prijavu nema spremljenih fotografija.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="block block-rounded">
                    <div class="block-header block-header-default"><h3 class="block-title">Obrada prijave</h3></div>
                    <form method="post" action="{{ route('book-purchases.update', $purchase) }}">
                        @csrf @method('PATCH')
                        <div class="block-content">
                            <div class="form-group"><label for="book-purchase-status">Status</label><select class="form-control" id="book-purchase-status" name="status" required>@foreach($statuses as $value => $label)<option value="{{ $value }}" {{ old('status', $purchase->status) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                            <div class="form-group"><label for="book-purchase-note">Interna napomena</label><textarea class="form-control" id="book-purchase-note" name="internal_note" rows="8" maxlength="5000">{{ old('internal_note', $purchase->internal_note) }}</textarea><small class="form-text text-muted">Napomena nije vidljiva korisniku.</small></div>
                            @if($purchase->handler)<p class="text-muted small">Zadnja obrada: {{ $purchase->handler->name }}, {{ optional($purchase->handled_at)->format('d.m.Y. H:i') }}</p>@endif
                        </div>
                        <div class="block-content bg-body-light"><button class="btn btn-hero-success mb-3" type="submit"><i class="fa-duotone fa-floppy-disk mr-1"></i>Spremi obradu</button></div>
                    </form>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default"><h3 class="block-title text-danger">Brisanje prijave</h3></div>
                    <div class="block-content">
                        <p class="text-muted">Brisanjem se trajno uklanjaju prijava i sve učitane fotografije.</p>
                        <form method="post" action="{{ route('book-purchases.destroy', $purchase) }}" onsubmit="return confirm('Trajno obrisati prijavu i sve fotografije?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger mb-4" type="submit"><i class="fa-duotone fa-trash mr-1"></i>Obriši prijavu</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
