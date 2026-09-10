@extends('front.layouts.app')

@section('title', 'Prijava')

@section('content')
    <section class="auth-gateway" aria-hidden="true">
        <div class="container text-center">
            <img class="auth-gateway__logo" src="{{ asset('media/img/vremeplov-logo.svg') }}" alt="">
            <h1 class="auth-gateway__title">Antikvarijat Vremeplov</h1>
            <p class="auth-gateway__text">Prijavite se u svoj korisnički račun.</p>
        </div>
    </section>
@endsection
