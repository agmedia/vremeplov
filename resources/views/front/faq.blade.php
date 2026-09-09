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

@section('content')

    @include('front.layouts.partials.page-heading', ['title' => 'Česta pitanja'])


    <div class="container">



        <div class="mt-5 mb-5">

    <!-- Flush accordion. Use this when you need to render accordions edge-to-edge with their parent container -->
    <div class="accordion accordion-flush" id="accordionFlushExample">


    @foreach ($faq as $fa)

        <!-- Item -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="flush-heading{{ $fa->id }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse{{ $fa->id }}" aria-expanded="false" aria-controls="flush-collapse{{ $fa->id }}">{{ $fa->title }}</button>
                </h2>
                <div class="accordion-collapse collapse" id="flush-collapse{{ $fa->id }}" aria-labelledby="flush-heading{{ $fa->id }}" data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body">  {!! $fa->description !!}</div>
                </div>
            </div>

    @endforeach











    </div>

        </div>
    </div>




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
