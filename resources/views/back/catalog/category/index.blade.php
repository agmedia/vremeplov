@extends('back.layouts.backend')

@section('content')
    @php
        $categoryCount = $categoriess->sum(function ($categories) {
            return $categories->sum(function ($category) {
                return 1 + $category->subcategories->count();
            });
        });
    @endphp

    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa fa-layer-group" aria-hidden="true"></i> Katalog</div>
                    <h1 class="admin-page-title">Kategorije</h1>
                    <p class="admin-page-description">Upravljajte grupama, glavnim kategorijama i pripadajućim podkategorijama.</p>
                </div>
                <div class="admin-toolbar-actions">
                    <a class="btn btn-alt-secondary" href="{{ route('categories.groups') }}">
                        <i class="fa fa-list-alt mr-1" aria-hidden="true"></i> Grupe kategorija
                    </a>
                    <a class="btn btn-primary" href="{{ route('category.create') }}">
                        <i class="fa fa-plus-square mr-1" aria-hidden="true"></i> Nova kategorija
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default admin-toolbar">
                <div>
                    <h2 class="block-title mb-1">Struktura kategorija <span class="admin-count">{{ number_format($categoryCount, 0, ',', '.') }}</span></h2>
                    <p class="text-muted mb-0 font-size-sm">Otvorite grupu kako biste pregledali i uredili njezine kategorije.</p>
                </div>
                <span class="text-muted font-size-sm">{{ $categoriess->count() }} {{ $categoriess->count() === 1 ? 'grupa' : 'grupa' }}</span>
            </div>

            <div class="block-content">
                <div id="category-groups" class="admin-category-groups" role="tablist" aria-multiselectable="true">
                    @forelse($categoriess as $group => $categories)
                        @php
                            $groupId = 'category-group-' . $loop->iteration;
                            $groupProductCount = $categories->sum('products_count');
                            $groupSetting = $categoryGroups->firstWhere('slug', $group);
                            $groupTitle = $groupSetting->title ?? \Illuminate\Support\Str::ucfirst(str_replace('-', ' ', $group));
                        @endphp
                        <section class="admin-category-group">
                            <button class="admin-category-group-toggle {{ $loop->first ? '' : 'collapsed' }}" type="button" data-toggle="collapse" data-target="#{{ $groupId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $groupId }}">
                                <span class="admin-category-group-icon"><i class="fa fa-folder" aria-hidden="true"></i></span>
                                <span class="admin-category-group-copy">
                                    <small>Grupa kategorija</small>
                                    <strong>{{ $groupTitle }}</strong>
                                </span>
                                <span class="admin-category-group-meta">
                                    <span>{{ $categories->count() }} glavnih</span>
                                    <span>{{ number_format($groupProductCount, 0, ',', '.') }} artikala</span>
                                </span>
                                <i class="fa fa-chevron-down admin-category-chevron" aria-hidden="true"></i>
                            </button>

                            <div id="{{ $groupId }}" class="collapse {{ $loop->first ? 'show' : '' }}" data-parent="#category-groups">
                                <div class="admin-category-list">
                                    @forelse($categories as $category)
                                        <div class="admin-category-item">
                                            <div class="admin-category-main">
                                                <span class="admin-category-level-icon"><i class="fa fa-folder-open" aria-hidden="true"></i></span>
                                                <div class="admin-category-title">
                                                    <a href="{{ route('category.edit', ['category' => $category]) }}">{{ $category->title }}</a>
                                                    <small>{{ $category->subcategories->count() }} {{ $category->subcategories->count() === 1 ? 'podkategorija' : 'podkategorija' }}</small>
                                                </div>
                                            </div>
                                            <div class="admin-category-item-meta">
                                                <span class="admin-category-product-count" title="Broj artikala u kategoriji"><i class="fa fa-book mr-1" aria-hidden="true"></i>{{ number_format($category->products_count, 0, ',', '.') }}</span>
                                                <span class="badge badge-pill {{ $category->status ? 'badge-success' : 'badge-secondary' }}">{{ $category->status ? 'Aktivna' : 'Neaktivna' }}</span>
                                                <a href="{{ route('category.edit', ['category' => $category]) }}" class="btn btn-sm btn-alt-secondary" title="Uredi kategoriju" aria-label="Uredi kategoriju {{ $category->title }}"><i class="fa fa-pencil-alt" aria-hidden="true"></i></a>
                                            </div>
                                        </div>

                                        @foreach($category->subcategories as $subcategory)
                                            <div class="admin-category-item admin-category-item-sub">
                                                <div class="admin-category-main">
                                                    <span class="admin-category-branch" aria-hidden="true"></span>
                                                    <span class="admin-category-level-icon"><i class="fa fa-tag" aria-hidden="true"></i></span>
                                                    <div class="admin-category-title">
                                                        <a href="{{ route('category.edit', ['category' => $subcategory]) }}">{{ $subcategory->title }}</a>
                                                        <small>Podkategorija od “{{ $category->title }}”</small>
                                                    </div>
                                                </div>
                                                <div class="admin-category-item-meta">
                                                    <span class="admin-category-product-count" title="Broj artikala u podkategoriji"><i class="fa fa-book mr-1" aria-hidden="true"></i>{{ number_format($subcategory->products_count, 0, ',', '.') }}</span>
                                                    <span class="badge badge-pill {{ $subcategory->status ? 'badge-success' : 'badge-secondary' }}">{{ $subcategory->status ? 'Aktivna' : 'Neaktivna' }}</span>
                                                    <a href="{{ route('category.edit', ['category' => $subcategory]) }}" class="btn btn-sm btn-alt-secondary" title="Uredi podkategoriju" aria-label="Uredi podkategoriju {{ $subcategory->title }}"><i class="fa fa-pencil-alt" aria-hidden="true"></i></a>
                                                </div>
                                            </div>
                                        @endforeach
                                    @empty
                                        <div class="admin-empty-state">Ova grupa nema kategorija.</div>
                                    @endforelse
                                </div>
                            </div>
                        </section>
                    @empty
                        <div class="admin-empty-state">
                            <i class="fa fa-folder-open" aria-hidden="true"></i>
                            <strong>Nema grupa ni kategorija.</strong>
                            <div class="mt-2"><a href="{{ route('category.create') }}">Dodajte prvu kategoriju.</a></div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
