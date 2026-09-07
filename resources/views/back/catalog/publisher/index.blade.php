@extends('back.layouts.backend')

@section('content')
    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa fa-building" aria-hidden="true"></i> Katalog</div>
                    <h1 class="admin-page-title">Izdavači</h1>
                    <p class="admin-page-description">Pretražujte i uređujte izdavače povezane s artiklima.</p>
                </div>
                <a class="btn btn-primary" href="{{ route('publishers.create') }}"><i class="fa fa-plus-square mr-1" aria-hidden="true"></i> Novi izdavač</a>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">Svi izdavači <span class="admin-count">{{ number_format($publishers->total(), 0, ',', '.') }}</span></h2>
                    <p class="text-muted mb-0 font-size-sm">Naziv, status i brze akcije.</p>
                </div>
                <form action="{{ route('publishers') }}" method="GET" class="admin-directory-search">
                    <div class="input-group">
                        <input type="search" class="form-control" id="publisher-search-input" name="search" placeholder="Pretraži izdavače" value="{{ request('search') }}">
                        <div class="input-group-append"><button class="btn btn-primary" type="submit" aria-label="Pretraži"><i class="fa fa-search" aria-hidden="true"></i></button></div>
                    </div>
                    @if(request()->filled('search'))
                        <a href="{{ route('publishers') }}" class="btn btn-alt-secondary"><i class="fa fa-times mr-1" aria-hidden="true"></i> Očisti</a>
                    @endif
                </form>
            </div>
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter admin-directory-table">
                        <thead><tr><th>Naziv</th><th class="text-center">Status</th><th class="text-right">Radnje</th></tr></thead>
                        <tbody>
                        @forelse($publishers as $publisher)
                            <tr>
                                <td><a class="admin-directory-name" href="{{ route('publishers.edit', ['publisher' => $publisher]) }}">{{ $publisher->title }}</a></td>
                                <td class="text-center"><span class="badge badge-pill {{ $publisher->status ? 'badge-success' : 'badge-secondary' }}">{{ $publisher->status ? 'Aktivan' : 'Neaktivan' }}</span></td>
                                <td class="text-right">
                                    <span class="admin-row-actions">
                                        <a href="{{ route('publishers.edit', ['publisher' => $publisher]) }}" class="btn btn-sm btn-alt-secondary" title="Uredi" aria-label="Uredi izdavača {{ $publisher->title }}"><i class="fa fa-pencil-alt" aria-hidden="true"></i></a>
                                        <button type="button" class="btn btn-sm btn-alt-danger" onclick="deleteItem({{ $publisher->id }}, '{{ route('publishers.destroy.api') }}');" title="Obriši" aria-label="Obriši izdavača {{ $publisher->title }}"><i class="fa fa-trash-alt" aria-hidden="true"></i></button>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="admin-empty-state" colspan="3"><i class="fa fa-building" aria-hidden="true"></i><strong>Nema pronađenih izdavača.</strong></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $publishers->links() }}
            </div>
        </div>
    </div>
@endsection
