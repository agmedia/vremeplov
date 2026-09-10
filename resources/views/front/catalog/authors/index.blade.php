@extends('front.layouts.app')

@php
    $authorsCanonical = route('catalog.route.author');
    if (! $letter && (int) request()->input('page', 1) > 1) {
        $authorsCanonical .= '?page=' . (int) request()->input('page');
    }

    $foreignLetterValues = ['Q', 'W', 'X', 'Y'];
    $croatianLetters = $letters->reject(fn ($item) => in_array($item['value'], $foreignLetterValues, true));
    $foreignLetters = $letters->filter(fn ($item) => in_array($item['value'], $foreignLetterValues, true));
    $specialLetterValues = ['Č', 'Ć', 'Dž', 'Đ', 'Lj', 'Nj', 'Š', 'Ž'];

    $bookCountLabel = function ($count) {
        $count = (int) $count;
        $lastTwo = $count % 100;
        $last = $count % 10;

        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return $count . ' knjiga';
        }

        if ($last === 1) {
            return $count . ' knjiga';
        }

        if ($last >= 2 && $last <= 4) {
            return $count . ' knjige';
        }

        return $count . ' knjiga';
    };
@endphp

@section('title', 'Autori - Antikvarijat Vremeplov')
@section('description', 'Popis autora čija su djela dostupna u Antikvarijatu Vremeplov. Pretražite autore abecednim redom.')
@section('canonical', $authorsCanonical)

@if (isset($meta_tags))
    @push('meta_tags')
        @foreach ($meta_tags as $tag)
            <meta name="{{ $tag['name'] }}" content="{{ $tag['content'] }}">
        @endforeach
    @endpush
@endif

@push('css_after')
    <link rel="stylesheet" media="screen" href="/css/front-authors.css?v=1.0.0">
@endpush

@section('content')
    @include('front.layouts.partials.page-heading', [
        'title' => 'Autori',
        'current' => 'Autori',
    ])

    <section class="authors-directory py-4 py-lg-5">
        <div class="container">
            <div class="authors-tools">
                <div class="authors-tools__heading">
                    <div>
                        <p class="authors-eyebrow">Istražite našu ponudu</p>
                        <h2 class="h4 mb-1">Pronađite autora</h2>
                        <p class="text-muted mb-0">Pretražite po imenu ili odaberite početno slovo prezimena.</p>
                    </div>
                    <span class="authors-tools__mark" aria-hidden="true">A–Ž</span>
                </div>

                <form action="{{ route('pretrazi') }}" method="get" class="author-search" id="author-search-form"
                      data-suggest-url="{{ route('catalog.route.author.suggest') }}">
                    <input type="hidden" name="tip" value="author">
                    <label class="visually-hidden" for="author-search-input">Pretražite autore</label>
                    <div class="author-search__control">
                        <i class="fa-regular fa-magnifying-glass author-search__icon" aria-hidden="true"></i>
                        <input type="search"
                               class="author-search__input"
                               id="author-search-input"
                               name="{{ config('settings.search_keyword') }}"
                               placeholder="Upišite ime autora, npr. Krleža"
                               autocomplete="off"
                               autocapitalize="none"
                               spellcheck="false"
                               role="combobox"
                               aria-autocomplete="list"
                               aria-controls="author-search-suggestions"
                               aria-expanded="false">
                        <button class="author-search__submit" type="submit">
                            <span>Pretraži</span>
                            <i class="fa-regular fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="author-search__suggestions d-none" id="author-search-suggestions" role="listbox"></div>
                    <p class="author-search__hint mb-0">Prijedlozi se prikazuju nakon dva upisana slova.</p>
                    <div class="visually-hidden" id="author-search-status" role="status" aria-live="polite"></div>
                </form>

                <div class="authors-alphabet" aria-labelledby="authors-alphabet-title">
                    <div class="authors-alphabet__header">
                        <h3 class="authors-alphabet__title mb-0" id="authors-alphabet-title">Hrvatska abeceda</h3>
                        @if ($letter)
                            <a class="authors-alphabet__reset" href="{{ route('catalog.route.author') }}">Prikaži sve autore</a>
                        @endif
                    </div>

                    <nav class="authors-alphabet__letters" aria-label="Filtriraj autore po početnom slovu">
                        <a href="{{ route('catalog.route.author') }}"
                           class="authors-letter authors-letter--all {{ ! $letter ? 'is-active' : '' }}"
                           @if (! $letter) aria-current="page" @endif>Svi</a>

                        @foreach ($croatianLetters as $item)
                            @if ($item['active'])
                                <a href="{{ route('catalog.route.author', ['author' => null, 'letter' => $item['value']]) }}"
                                   class="authors-letter {{ in_array($item['value'], $specialLetterValues, true) ? 'authors-letter--special' : '' }} {{ $item['value'] === $letter ? 'is-active' : '' }}"
                                   aria-label="Autori na slovo {{ $item['value'] }}"
                                   @if ($item['value'] === $letter) aria-current="page" @endif>{{ $item['value'] }}</a>
                            @else
                                <span class="authors-letter {{ in_array($item['value'], $specialLetterValues, true) ? 'authors-letter--special' : '' }} is-disabled"
                                      aria-label="Nema autora na slovo {{ $item['value'] }}"
                                      aria-disabled="true">{{ $item['value'] }}</span>
                            @endif
                        @endforeach
                    </nav>

                    @if ($foreignLetters->contains('active', true))
                        <div class="authors-alphabet__foreign">
                            <span class="authors-alphabet__foreign-label">Strana slova</span>
                            <nav class="authors-alphabet__foreign-letters" aria-label="Autori na strana slova">
                                @foreach ($foreignLetters as $item)
                                    @if ($item['active'])
                                        <a href="{{ route('catalog.route.author', ['author' => null, 'letter' => $item['value']]) }}"
                                           class="authors-letter {{ $item['value'] === $letter ? 'is-active' : '' }}"
                                           aria-label="Autori na slovo {{ $item['value'] }}"
                                           @if ($item['value'] === $letter) aria-current="page" @endif>{{ $item['value'] }}</a>
                                    @endif
                                @endforeach
                            </nav>
                        </div>
                    @endif
                </div>
            </div>

            <div class="authors-results__heading">
                <div>
                    <p class="authors-eyebrow mb-1">Dostupno u antikvarijatu</p>
                    <h2 class="authors-results__title mb-0">
                        {{ $letter ? 'Autori na slovo ' . $letter : 'Svi autori' }}
                    </h2>
                </div>
                <p class="authors-results__count mb-0">
                    {{ $authors->total() }} {{ $authors->total() === 1 ? 'autor' : 'autora' }}
                </p>
            </div>

            @if ($authors->count())
                <div class="authors-grid">
                    @foreach ($authors as $author)
                        <a href="{{ url($author['url']) }}" class="author-card">
                            <span class="author-card__name">{{ $author['title'] }}</span>
                            <span class="author-card__meta">
                                <span>{{ $bookCountLabel($author['products_count']) }}</span>
                                <i class="fa-regular fa-arrow-right author-card__arrow" aria-hidden="true"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="authors-empty">
                    <span class="authors-empty__icon" aria-hidden="true"><i class="fa-regular fa-books"></i></span>
                    <h2 class="h5">Trenutačno nema dostupnih autora na ovo slovo</h2>
                    <p class="text-muted mb-3">Odaberite drugo slovo ili pregledajte cijeli popis autora.</p>
                    <a class="btn btn-outline-primary" href="{{ route('catalog.route.author') }}">Svi autori</a>
                </div>
            @endif

            @if ($authors->hasPages())
                <div class="authors-pagination" aria-label="Stranice popisa autora">
                    {{ $authors->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection

@push('js_after')
    <script src="/js/front-authors.js?v=1.0.0" defer></script>
@endpush
