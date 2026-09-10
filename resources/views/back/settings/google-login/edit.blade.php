@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <h1 class="h2 mt-2 mb-1">Google prijava</h1>
                    <div class="text-muted">OAuth prijava za postojeće korisnike web trgovine</div>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="row">
            <div class="col-lg-8">
                <form method="POST" action="{{ route('google-login.update') }}" autocomplete="off">
                    @csrf
                    {{ method_field('PATCH') }}

                    <div class="block block-rounded">
                        <div class="block-header block-header-default">
                            <h3 class="block-title"><i class="fab fa-google mr-1"></i> Postavke Google prijave</h3>
                        </div>
                        <div class="block-content">
                            <div class="alert alert-info">
                                Google prijava povezuje isključivo već postojeći Vremeplov račun s istom verificiranom e-mail adresom. Novi korisnički račun ne izrađuje se automatski.
                            </div>

                            <div class="form-group">
                                <label for="google-login-enabled">Status</label>
                                <select class="form-control @error('enabled') is-invalid @enderror" id="google-login-enabled" name="enabled">
                                    <option value="1" @if((string) old('enabled', (int) $settings['enabled']) === '1') selected @endif>Omogućeno</option>
                                    <option value="0" @if((string) old('enabled', (int) $settings['enabled']) === '0') selected @endif>Onemogućeno</option>
                                </select>
                                @error('enabled') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="google-login-client-id">Google Client ID</label>
                                <input
                                    class="form-control @error('client_id') is-invalid @enderror"
                                    id="google-login-client-id"
                                    name="client_id"
                                    type="text"
                                    value="{{ old('client_id', $settings['client_id']) }}"
                                    placeholder="1234567890-abc.apps.googleusercontent.com"
                                    maxlength="500"
                                    autocomplete="off"
                                >
                                @error('client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="google-login-client-secret">Google Client Secret</label>
                                <input
                                    class="form-control @error('client_secret') is-invalid @enderror"
                                    id="google-login-client-secret"
                                    name="client_secret"
                                    type="password"
                                    value=""
                                    placeholder="{{ $settings['has_client_secret'] ? 'Spremljen — ostavite prazno ako ga ne mijenjate' : 'GOCSPX-...' }}"
                                    maxlength="500"
                                    autocomplete="new-password"
                                >
                                @error('client_secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if($settings['has_client_secret'])
                                    <small class="form-text text-success"><i class="fa fa-lock mr-1"></i>Tajni ključ je spremljen šifrirano.</small>
                                @endif
                            </div>

                            <div class="form-group">
                                <label for="google-login-callback">Authorized redirect URI</label>
                                <div class="input-group">
                                    <input class="form-control" id="google-login-callback" type="text" value="{{ $callbackUrl }}" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-alt-secondary" id="copy-google-login-callback" type="button" title="Kopiraj adresu">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Ovu adresu dodajte u Google Cloud Console pod Authorized redirect URIs za OAuth 2.0 Client ID vrste Web application.</small>
                            </div>
                        </div>
                        <div class="block-content bg-body-light">
                            <button class="btn btn-hero-success mb-3" type="submit">
                                <i class="fa fa-save mr-1"></i>Spremi postavke
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Google Cloud priprema</h3>
                    </div>
                    <div class="block-content">
                        <ol class="pl-3">
                            <li class="mb-3">U Google Cloud Console otvorite <strong>APIs &amp; Services → Credentials</strong>.</li>
                            <li class="mb-3">Izradite OAuth Client ID tipa <strong>Web application</strong>.</li>
                            <li class="mb-3">Dodajte prikazani callback URL među <strong>Authorized redirect URIs</strong>.</li>
                            <li class="mb-3">Ovdje zalijepite Client ID i Client Secret te uključite status.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script>
        document.getElementById('copy-google-login-callback')?.addEventListener('click', function () {
            const callback = document.getElementById('google-login-callback');

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(callback.value);
                return;
            }

            callback.select();
            document.execCommand('copy');
        });
    </script>
@endpush
