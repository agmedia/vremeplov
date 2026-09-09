@extends('back.layouts.backend')

@section('content')
    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa-duotone fa-books" aria-hidden="true"></i> Marketing</div>
                    <h1 class="admin-page-title">Otkup knjiga</h1>
                    <p class="admin-page-description">Pregledajte ponude korisnika, fotografije knjiga i tijek obrade.</p>
                </div>
                <a class="btn btn-alt-secondary" href="{{ route('book-purchases.content.edit') }}"><i class="fa-duotone fa-pen-to-square mr-1"></i>Uredi tekstove stranice</a>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <div>
                    <h2 class="block-title mb-1">Prijave za otkup knjiga</h2>
                    <span class="admin-count">{{ number_format($purchases->total(), 0, ',', '.') }} prijava</span>
                </div>
            </div>

            <div class="block-content bg-body-dark">
                <form method="get" action="{{ route('book-purchases.index') }}">
                    <div class="form-row align-items-end">
                        <div class="col-lg-4 form-group">
                            <label for="book-purchase-search">Pretraživanje</label>
                            <input class="form-control" id="book-purchase-search" name="search" type="search" value="{{ $search }}" placeholder="Referenca, ime, e-mail, telefon...">
                        </div>
                        <div class="col-lg-2 form-group">
                            <label for="book-purchase-status">Status</label>
                            <select class="form-control" id="book-purchase-status" name="status">
                                <option value="">Svi statusi</option>
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ $selectedStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 form-group">
                            <label for="book-purchase-date-from">Datum od</label>
                            <input class="form-control" id="book-purchase-date-from" name="date_from" type="date" value="{{ $dateFrom }}">
                        </div>
                        <div class="col-lg-2 form-group">
                            <label for="book-purchase-date-to">Datum do</label>
                            <input class="form-control" id="book-purchase-date-to" name="date_to" type="date" value="{{ $dateTo }}">
                        </div>
                        <div class="col-lg-2 form-group d-flex">
                            <button class="btn btn-primary flex-fill mr-2" type="submit" title="Primijeni filtre"><i class="fa-duotone fa-magnifying-glass"></i></button>
                            <a class="btn btn-alt-secondary" href="{{ route('book-purchases.index') }}" title="Očisti filtre"><i class="fa-duotone fa-xmark"></i></a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="block-content">
                <div class="table-responsive admin-table-frame">
                    <table class="table table-borderless table-striped table-vcenter admin-data-table">
                        <thead><tr><th>Referenca</th><th>Zaprimljeno</th><th>Status</th><th>Podaci</th><th class="text-center">Fotografije</th><th class="text-center">Radnje</th></tr></thead>
                        <tbody>
                        @forelse($purchases as $purchase)
                            <tr>
                                <td><a class="font-w600" href="{{ route('book-purchases.show', $purchase) }}">{{ $purchase->reference }}</a></td>
                                <td class="text-nowrap">{{ optional($purchase->submitted_at)->format('d.m.Y. H:i') }}</td>
                                <td><span class="badge badge-{{ $statusColors[$purchase->status] ?? 'secondary' }}">{{ $statuses[$purchase->status] ?? $purchase->status }}</span></td>
                                <td>
                                    <div class="font-w600">{{ $purchase->full_name }}</div>
                                    <a href="mailto:{{ $purchase->email }}">{{ $purchase->email }}</a><br>
                                    <span class="text-muted">{{ $purchase->phone }}</span>
                                </td>
                                <td class="text-center"><span class="badge badge-light">{{ count($purchase->photos ?: []) }}</span></td>
                                <td class="text-center"><a class="btn btn-sm btn-alt-primary" href="{{ route('book-purchases.show', $purchase) }}" title="Otvori prijavu"><i class="fa-duotone fa-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-5" colspan="6">Nema pronađenih prijava za otkup knjiga.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $purchases->links() }}
            </div>
        </div>
    </div>
@endsection
