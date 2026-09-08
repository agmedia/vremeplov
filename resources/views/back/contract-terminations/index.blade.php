@extends('back.layouts.backend')

@section('content')
    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa-duotone fa-file-signature" aria-hidden="true"></i> Prodaja</div>
                    <h1 class="admin-page-title">Jednostrani raskidi ugovora</h1>
                    <p class="admin-page-description">Pregled zaprimljenih izjava, povezanih narudžbi i tijeka obrade.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <div>
                    <h2 class="block-title mb-1">Zaprimljene izjave</h2>
                    <span class="admin-count">{{ number_format($terminations->total(), 0, ',', '.') }} zahtjeva</span>
                </div>
            </div>

            <div class="block-content bg-body-dark">
                <form method="GET" action="{{ route('contract-terminations.index') }}">
                    <div class="form-row align-items-end">
                        <div class="col-md-7 form-group">
                            <label for="termination-search">Pretraživanje</label>
                            <input class="form-control" id="termination-search" type="search" name="search" value="{{ $search }}" placeholder="Referenca, narudžba, ime ili e-mail...">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="termination-status">Status</label>
                            <select class="form-control" id="termination-status" name="status">
                                <option value="">Svi statusi</option>
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ $selectedStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <button class="btn btn-primary btn-block" type="submit"><i class="fa-duotone fa-magnifying-glass mr-1"></i>Pretraži</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="block-content">
                <div class="table-responsive admin-table-frame">
                    <table class="table table-borderless table-striped table-vcenter admin-data-table">
                        <thead><tr><th>Referenca</th><th>Podneseno</th><th>Status</th><th>Kupac</th><th>Narudžba</th><th class="text-center">Radnje</th></tr></thead>
                        <tbody>
                        @forelse($terminations as $termination)
                            <tr>
                                <td><a class="font-w600" href="{{ route('contract-terminations.show', $termination) }}">{{ $termination->reference }}</a></td>
                                <td class="text-nowrap">{{ optional($termination->submitted_at)->format('d.m.Y. H:i') }}</td>
                                <td><span class="badge badge-{{ $statusColors[$termination->status] ?? 'secondary' }}">{{ $statuses[$termination->status] ?? $termination->status }}</span></td>
                                <td><div class="font-w600">{{ $termination->full_name }}</div><a href="mailto:{{ $termination->email }}">{{ $termination->email }}</a></td>
                                <td>
                                    @if($termination->order)
                                        <a href="{{ route('orders.show', $termination->order) }}">#{{ $termination->order->id }}</a>
                                    @else
                                        {{ $termination->order_number }}
                                    @endif
                                </td>
                                <td class="text-center"><a class="btn btn-sm btn-alt-primary" href="{{ route('contract-terminations.show', $termination) }}" title="Otvori zahtjev"><i class="fa-duotone fa-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-5" colspan="6">Nema pronađenih izjava o raskidu ugovora.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $terminations->links() }}
            </div>
        </div>
    </div>
@endsection
