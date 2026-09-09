@php
    $headingParents = $parents ?? [];
    $headingCurrent = $current ?? $title;
@endphp

<div class="bg-light pt-4 pb-3" style="background-image:url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat:repeat;">
    <div class="container d-lg-flex justify-content-between gap-lg-4 py-2 py-lg-3">
        <nav class="order-lg-2 mb-3 mb-lg-0 pt-lg-2" aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-dark flex-lg-nowrap justify-content-center justify-content-lg-start mb-0">
                <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="fa-regular fa-house me-1"></i>Naslovnica</a></li>
                @foreach ($headingParents as $parent)
                    <li class="breadcrumb-item"><a class="text-nowrap" href="{{ $parent['url'] }}">{{ $parent['label'] }}</a></li>
                @endforeach
                <li class="breadcrumb-item active" aria-current="page">{{ $headingCurrent }}</li>
            </ol>
        </nav>
        <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
            <h1 class="h2 text-dark mb-0">{{ $title }}</h1>
        </div>
    </div>
</div>
