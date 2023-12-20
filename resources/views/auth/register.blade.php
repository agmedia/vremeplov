{{--<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <x-jet-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div>
                <x-jet-label for="name" value="{{ __('Name') }}" />
                <x-jet-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div class="mt-4">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            </div>

            <div class="mt-4">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-jet-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-jet-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-jet-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="mt-4">
                    <x-jet-label for="terms">
                        <div class="flex items-center">
                            <x-jet-checkbox name="terms" id="terms"/>

                            <div class="ml-2">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-jet-label>
                </div>
            @endif

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-jet-button class="ml-4">
                    {{ __('Register') }}
                </x-jet-button>
            </div>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>--}}

@extends('back.layouts.simple')

@section('content')

    <div class="row no-gutters justify-content-center bg-white bckimagelogin">
        <div class="hero-static col-sm-10 col-md-8 col-xl-4 d-flex align-items-center p-2 px-sm-0">
            <!-- Sign Up Block -->
            <div class="block block-rounded block-transparent block-fx-pop w-100 mb-0 overflow-hidden bg-image" style="background-image: url({{ asset('media/img/zuzi-bck-transparent.svg') }});">
                <div class="row no-gutters">
                    <div class="col-md-12 order-md-1 bg-white">
                        <div class="block-content block-content-full px-lg-5 py-md-5 py-lg-6">
                            <!-- Header -->
                            <div class="mb-2 text-center">
                                <a class="link-fx text-success font-w700 font-size-h1" href="{{ route('index') }}">
                                    <span class="text-dark">Antikvarijat</span> <span class="text-primary">Vremeplov</span>
                                </a>
                                <p class="text-uppercase font-w700 font-size-sm text-muted">Napravite korisnički račun</p>
                            </div>
                            <form method="POST" action="{{ route('register') }}">
                                @csrf
                                <div class="form-group">
                                    <input type="text" class="form-control form-control-alt" id="name" name="name" placeholder="Korisničko ime" value="{{ old('name') }}">
                                </div>
                                <div class="form-group">
                                    <input type="email" class="form-control form-control-alt" id="email" name="email" placeholder="Email" value="{{ old('email') }}">
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control form-control-alt" id="password" name="password" placeholder="Lozinka">
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control form-control-alt" id="password-confirmation" name="password_confirmation" placeholder="Potvrdite Lozinku">
                                </div>
                                @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                                    <div class="form-group">
                                        <x-jet-label for="terms">
                                            <div class="flex items-center">
                                                <x-jet-checkbox name="terms" id="terms"/>
                                                <label>
                                                    {!! __('Slažem se sa :terms_of_service', [
                                                            'terms_of_service' => '<a target="_blank" href="'.route('catalog.route.page',['page' => 'opci-uvjeti-kupnje']).'" class="link-fx">'.__('Uvjetima kupovine').'</a>',
                                                            'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="link-fx">'.__('Privacy Policy').'</a>',
                                                    ]) !!}
                                                </label>
                                            </div>
                                        </x-jet-label>
                                    </div>
                                @endif
                                {{--<div class="form-group">
                                    <a href="#" data-toggle="modal" data-target="#modal-terms">Terms &amp; Conditions</a>
                                    <div class="custom-control custom-checkbox custom-control-primary">
                                        <input type="checkbox" class="custom-control-input" id="signup-terms" name="signup-terms">
                                        <label class="custom-control-label" for="signup-terms">I agree</label>
                                    </div>
                                </div>--}}
                                <div class="form-group">
                                    <button type="submit" class="btn  btn-block btn-hero-primary">
                                        <i class="fa fa-fw fa-plus mr-1"></i> Registrirajte se
                                    </button>
                                </div>
                                <input type="hidden" name="recaptcha" id="recaptcha">
                            </form>
                            <!-- END Sign Up Form -->
                            <div class="mb-2 text-center">
                                <a class="link-fx font-size-sm" href="{{ route('login') }}">
                                    {{ __('Već ste registrani? Prijavite se') }}
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <!-- END Sign Up Block -->
        </div>
    </div>

@endsection

@push('js_after')
    @include('front.layouts.partials.recaptcha-js')
@endpush
