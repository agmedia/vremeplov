@extends('front.layouts.app')

@section ( 'title', '404 Error')

@section('content')
    <div class="container py-5 mb-lg-3">
        <div class="row justify-content-center pt-lg-4 text-center">
            <div class="col-lg-5 col-md-7 col-sm-9">
                <h1 class="display-404 py-lg-3">404</h1>
                <h2 class="h3 mb-4">Čini se da ne možemo pronaći stranicu koju tražite.</h2>
                <p class="fs-md mb-4">
                    <u>Evo nekoliko korisnih veza umjesto toga:</u>
                </p>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">
                <div class="row">
                    <div class="col-sm-4 mb-3">
                        <a class="card h-100 border-0 shadow-sm" href="{{ route('index') }}">
                            <div class="card-body">
                                <div class="d-flex align-items-center"><i class="ci-home text-primary h4 mb-0"></i>
                                    <div class="ps-3">
                                        <h5 class="fs-sm mb-0">Naslovnica</h5><span class="text-muted fs-ms">Povratak na naslovnu stranicu</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-4 mb-3"><a class="card h-100 border-0 shadow-sm" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#searchBox" role="button" aria-expanded="false" aria-controls="searchBox">
                            <div class="card-body">
                                <div class="d-flex align-items-center"><i class="ci-search text-primary h4 mb-0"></i>
                                    <div class="ps-3">
                                        <h5 class="fs-sm mb-0">Pretraži</h5><span class="text-muted fs-ms">Pronađite preko napredne tražilice</span>
                                    </div>
                                </div>
                            </div></a></div>
                    <div class="col-sm-4 mb-3"><a class="card h-100 border-0 shadow-sm" href="{{ route('faq') }}">
                            <div class="card-body">
                                <div class="d-flex align-items-center"><i class="ci-help text-primary h4 mb-0"></i>
                                    <div class="ps-3">
                                        <h5 class="fs-sm mb-0">Česta pitanja</h5><span class="text-muted fs-ms">Posjetite stranicu sa čestim pitanjima</span>
                                    </div>
                                </div>
                            </div></a></div>
                </div>
            </div>
        </div>
    </div>
@endsection
