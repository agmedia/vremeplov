@extends('back.layouts.backend')

@section('content')
    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa fa-user-edit" aria-hidden="true"></i> Katalog</div>
                    <h1 class="admin-page-title">Autori</h1>
                    <p class="admin-page-description">Pretražujte i uređujte autore povezane s artiklima.</p>
                </div>
                <a class="btn btn-primary" href="{{ route('authors.create') }}"><i class="fa fa-plus-square mr-1" aria-hidden="true"></i> Novi autor</a>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">Svi autori <span class="admin-count">{{ number_format($authors->total(), 0, ',', '.') }}</span></h2>
                    <p class="text-muted mb-0 font-size-sm">Naziv, status i brze akcije.</p>
                </div>
                <form action="{{ route('authors') }}" method="GET" class="admin-directory-search">
                    <div class="input-group">
                        <input type="search" class="form-control" id="author-search-input" name="search" placeholder="Pretraži autore" value="{{ request('search') }}">
                        <div class="input-group-append"><button class="btn btn-primary" type="submit" aria-label="Pretraži"><i class="fa fa-search" aria-hidden="true"></i></button></div>
                    </div>
                    @if(request()->filled('search'))
                        <a href="{{ route('authors') }}" class="btn btn-alt-secondary"><i class="fa fa-times mr-1" aria-hidden="true"></i> Očisti</a>
                    @endif
                </form>
            </div>
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter admin-directory-table">
                        <thead><tr><th>Naziv</th><th class="text-center">Status</th><th class="text-right">Radnje</th></tr></thead>
                        <tbody>
                        @forelse($authors as $author)
                            <tr>
                                <td><a class="admin-directory-name" href="{{ route('authors.edit', ['author' => $author]) }}">{{ $author->title }}</a></td>
                                <td class="text-center"><span class="badge badge-pill {{ $author->status ? 'badge-success' : 'badge-secondary' }}">{{ $author->status ? 'Aktivan' : 'Neaktivan' }}</span></td>
                                <td class="text-right">
                                    <span class="admin-row-actions">
                                        <a href="{{ route('authors.edit', ['author' => $author]) }}" class="btn btn-sm btn-alt-secondary" title="Uredi" aria-label="Uredi autora {{ $author->title }}"><i class="fa fa-pencil-alt" aria-hidden="true"></i></a>
                                        <button type="button" class="btn btn-sm btn-alt-danger" onclick="deleteItem({{ $author->id }}, '{{ route('authors.destroy.api') }}');" title="Obriši" aria-label="Obriši autora {{ $author->title }}"><i class="fa fa-trash-alt" aria-hidden="true"></i></button>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="admin-empty-state" colspan="3"><i class="fa fa-user-edit" aria-hidden="true"></i><strong>Nema pronađenih autora.</strong></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $authors->links() }}
            </div>
        </div>
    </div>
@endsection
