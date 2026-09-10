@extends('front.layouts.app')

@section('title', 'Česta pitanja - Antikvarijat Vremeplov')
@section('description', 'Odgovori na česta pitanja o naručivanju, plaćanju, dostavi i kupnji u Antikvarijatu Vremeplov.')
@section('canonical', route('faq'))

@push('meta_tags')
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Česta pitanja - Antikvarijat Vremeplov" />
    <meta property="og:description" content="Odgovori na česta pitanja o naručivanju, plaćanju, dostavi i kupnji u Antikvarijatu Vremeplov." />
    <meta property="og:url" content="{{ route('faq') }}" />
@endpush

@push('css_after')
    <link rel="stylesheet" media="screen" href="{{ asset('css/front-faq.css?v=1.0.0') }}">
@endpush

@section('content')

    @include('front.layouts.partials.page-heading', ['title' => 'Česta pitanja'])

    <section class="faq-page">
        <div class="container">
            <div class="accordion accordion-flush faq-list" id="faqAccordion">
                @foreach ($faq as $fa)
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq-heading{{ $fa->id }}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse{{ $fa->id }}" aria-expanded="false" aria-controls="faq-collapse{{ $fa->id }}">
                                <span>{{ $fa->title }}</span>
                                <i class="fa-regular fa-chevron-down" aria-hidden="true"></i>
                            </button>
                        </h2>
                        <div class="accordion-collapse collapse" id="faq-collapse{{ $fa->id }}" aria-labelledby="faq-heading{{ $fa->id }}" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">{!! $fa->description !!}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection

@push('js_after')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faq->map(function ($item) {
                return [
                    '@type' => 'Question',
                    'name' => trim(strip_tags((string) $item->title)),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => trim(strip_tags((string) $item->description)),
                    ],
                ];
            })->values()->toArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush
