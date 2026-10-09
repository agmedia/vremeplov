@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center">
                <div>
                    <div class="font-size-sm font-w600 text-uppercase text-warning mb-1">
                        <i class="fa fa-envelope-open-text mr-1"></i> Marketing
                    </div>
                    <h1 class="font-size-h2 font-w600 mb-1">Newsletter prijave</h1>
                    <div class="text-muted">Pregled adresa koje su dale privolu za primanje novosti.</div>
                </div>
                <a class="btn btn-primary mt-3 mt-md-0"
                   href="{{ route('newsletter-subscribers.export', array_filter($filters, function ($value) { return $value !== '' && $value !== 'all'; })) }}">
                    <i class="fa fa-file-csv mr-1"></i> Izvezi prikazane
                </a>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="row row-deck mb-4">
            @foreach ([
                ['label' => 'Ukupno prijava', 'value' => $statistics['total'], 'icon' => 'fa-users', 'class' => 'text-primary'],
                ['label' => 'Aktivne prijave', 'value' => $statistics['active'], 'icon' => 'fa-check-circle', 'class' => 'text-success'],
                ['label' => 'Neaktivne prijave', 'value' => $statistics['inactive'], 'icon' => 'fa-user-slash', 'class' => 'text-muted'],
            ] as $stat)
                <div class="col-md-4">
                    <div class="block block-rounded mb-0 h-100">
                        <div class="block-content d-flex align-items-center py-4">
                            <i class="fa {{ $stat['icon'] }} fa-2x {{ $stat['class'] }} mr-3"></i>
                            <div>
                                <div class="font-size-h2 font-w700 mb-0">{{ number_format($stat['value'], 0, ',', '.') }}</div>
                                <div class="text-muted">{{ $stat['label'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Mailchimp</h3>
                @if ($mailchimpConnection['ready'])
                    <span class="badge badge-success">Povezivanje postavljeno</span>
                @elseif (! $mailchimpSettings['enabled'])
                    <span class="badge badge-secondary">Povezivanje nije uključeno</span>
                @elseif (! $mailchimpConnection['available'])
                    <span class="badge badge-warning">Čeka nadogradnju</span>
                @else
                    <span class="badge badge-warning">Povezivanje nije dovršeno</span>
                @endif
            </div>
            <div class="block-content">
                <div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center mb-4">
                    <div class="mr-lg-4">
                        @if ($mailchimpConnection['ready'])
                            <p class="mb-1">Nove aktivne prijave s privolom automatski se usklađuju s Mailchimpom.</p>
                        @elseif (! $mailchimpConnection['available'])
                            <p class="mb-1">Za usklađivanje je potrebno dovršiti nadogradnju web stranice.</p>
                        @else
                            <p class="mb-1">Za povezivanje unesite API ključ i Audience ID iz Mailchimpa te uključite povezivanje.</p>
                        @endif
                        <div class="text-muted">Postojeće odjave ostaju važeće.</div>
                    </div>
                    <form action="{{ route('newsletter-subscribers.sync', array_filter($filters, function ($value) { return $value !== '' && $value !== 'all'; })) }}"
                          method="post" class="mt-3 mt-lg-0">
                        @csrf
                        <button type="submit" class="btn btn-primary" {{ ! $mailchimpConnection['ready'] ? 'disabled' : '' }}>
                            <i class="fa fa-sync-alt mr-1"></i> Uskladi aktivne prijave
                        </button>
                        <div class="font-size-sm text-muted mt-2">Do 25 prijava iz odabranog pregleda.</div>
                    </form>
                </div>

                <div class="row text-center mb-4">
                    @foreach ([
                        ['label' => 'Usklađene', 'value' => $mailchimpStatistics['synced']],
                        ['label' => 'Na čekanju', 'value' => $mailchimpStatistics['pending']],
                        ['label' => 'Potrebna provjera', 'value' => $mailchimpStatistics['error']],
                    ] as $stat)
                        <div class="col-sm-4 mb-2">
                            <div class="font-size-h3 font-w700">{{ number_format($stat['value'], 0, ',', '.') }}</div>
                            <div class="text-muted">{{ $stat['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="font-size-sm text-muted">Brojevi se odnose na aktivne prijave s privolom.</p>

                <details class="mb-4" {{ ! $mailchimpConnection['ready'] ? 'open' : '' }}>
                    <summary class="font-w600 mb-3">Postavke povezivanja</summary>
                    <form action="{{ route('newsletter-subscribers.mailchimp-settings', array_filter($filters, function ($value) { return $value !== '' && $value !== 'all'; })) }}" method="post">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="enabled" value="0">
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" id="mailchimp-enabled" name="enabled" value="1"
                                   {{ $mailchimpSettings['enabled'] ? 'checked' : '' }}>
                            <label class="custom-control-label" for="mailchimp-enabled">Uključi povezivanje s Mailchimpom</label>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-lg-5">
                                <label for="mailchimp-api-key">Mailchimp API ključ</label>
                                <input type="password" class="form-control" id="mailchimp-api-key" name="api_key" value=""
                                       autocomplete="new-password" maxlength="255">
                                <small class="form-text text-muted">{{ $mailchimpSettings['key_configured'] ? 'Ključ je spremljen. Ostavite prazno ako ga ne mijenjate.' : 'Unesite ključ jednom. Nakon spremanja neće se prikazivati.' }}</small>
                            </div>
                            <div class="form-group col-sm-4 col-lg-2">
                                <label for="mailchimp-server-prefix">Poslužitelj</label>
                                <input type="text" class="form-control" id="mailchimp-server-prefix" name="server_prefix"
                                       value="{{ $mailchimpSettings['server_prefix'] }}" placeholder="us1" maxlength="20">
                            </div>
                            <div class="form-group col-sm-8 col-lg-5">
                                <label for="mailchimp-audience-id">Audience ID</label>
                                <input type="text" class="form-control" id="mailchimp-audience-id" name="audience_id"
                                       value="{{ $mailchimpSettings['audience_id'] }}" maxlength="80">
                            </div>
                        </div>
                        <details class="mb-3">
                            <summary class="mb-3">Automatski povrat odjava</summary>
                            @if ($mailchimpWebhookUrl)
                                <div class="form-group">
                                    <label for="mailchimp-webhook-url">Adresa za povrat odjava</label>
                                    <input type="text" class="form-control" id="mailchimp-webhook-url" value="{{ $mailchimpWebhookUrl }}" readonly autocomplete="off">
                                    <small class="form-text text-muted">Kopirajte ovu adresu u Mailchimpove postavke webhooka.</small>
                                </div>
                            @else
                                <p class="text-muted">Adresa za povrat odjava bit će dostupna nakon spremanja postavki.</p>
                            @endif
                            <div class="form-group">
                                <label for="mailchimp-webhook-secret">Ključ za provjeru odjava (opcionalno)</label>
                                <input type="password" class="form-control" id="mailchimp-webhook-secret" name="webhook_signing_secret"
                                       value="" autocomplete="new-password" maxlength="255">
                                <small class="form-text text-muted">{{ $mailchimpSettings['signing_configured'] ? 'Ključ je spremljen. Ostavite prazno ako ga ne mijenjate.' : 'Unesite ako ste postavili ključ za potpisivanje Mailchimpovih webhookova.' }}</small>
                            </div>
                        </details>
                        <button type="submit" class="btn btn-outline-primary">Spremi postavke</button>
                    </form>
                </details>
            </div>
        </div>

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">
                    Prijavljene adrese
                    <span class="font-size-sm text-muted ml-2">{{ number_format($subscribers->total(), 0, ',', '.') }} rezultata</span>
                </h3>
            </div>

            <div class="block-content">
                <form action="{{ route('newsletter-subscribers.index') }}" method="get" class="mb-4">
                    <div class="form-row align-items-end">
                        <div class="form-group col-lg-6">
                            <label for="newsletter-search">Pretraži</label>
                            <input type="search" class="form-control" id="newsletter-search" name="search"
                                   value="{{ $filters['search'] }}" placeholder="E-mail adresa ili ime korisnika">
                        </div>
                        <div class="form-group col-sm-6 col-lg-2">
                            <label for="newsletter-status">Status</label>
                            <select class="form-control" id="newsletter-status" name="status">
                                <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>Svi</option>
                                <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Aktivni</option>
                                <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>Neaktivni</option>
                            </select>
                        </div>
                        <div class="form-group col-sm-6 col-lg-2">
                            <label for="newsletter-source">Izvor</label>
                            <select class="form-control" id="newsletter-source" name="source">
                                <option value="">Svi izvori</option>
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}" {{ $filters['source'] === $source ? 'selected' : '' }}>
                                        {{ $source === 'homepage' ? 'Web stranica' : $source }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-lg-2 d-flex">
                            <button type="submit" class="btn btn-primary flex-fill mr-2">
                                <i class="fa fa-search mr-1"></i> Prikaži
                            </button>
                            <a href="{{ route('newsletter-subscribers.index') }}" class="btn btn-outline-secondary" title="Očisti filtre">
                                <i class="fa fa-times"></i><span class="sr-only">Očisti filtre</span>
                            </a>
                        </div>
                    </div>
                </form>

                <div class="alert alert-info d-flex align-items-start" role="status">
                    <i class="fa fa-info-circle mt-1 mr-2"></i>
                    <div>Izvoz sadrži samo trenutno filtrirane rezultate. Usklađivanje prenosi prijave u Mailchimp bez slanja newslettera.</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter">
                        <thead>
                        <tr>
                            <th>E-mail</th>
                            <th>Korisnik</th>
                            <th>Izvor</th>
                            <th class="text-center">Privola</th>
                            <th class="text-center">Status</th>
                            <th>Mailchimp</th>
                            <th>Datum prijave</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($subscribers as $subscriber)
                            <tr>
                                <td class="font-w600">
                                    <a href="mailto:{{ $subscriber->email }}">{{ $subscriber->email }}</a>
                                </td>
                                <td>
                                    @if ($subscriber->user)
                                        <a href="{{ route('users.edit', ['user' => $subscriber->user]) }}">{{ $subscriber->user->name }}</a>
                                    @else
                                        <span class="text-muted">Gost</span>
                                    @endif
                                </td>
                                <td>{{ $subscriber->source === 'homepage' ? 'Web stranica' : ($subscriber->source ?: '—') }}</td>
                                <td class="text-center">
                                    @if ($subscriber->gdpr)
                                        <span class="badge badge-success">Da</span>
                                    @else
                                        <span class="badge badge-danger">Ne</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($subscriber->status)
                                        <span class="badge badge-success">Aktivan</span>
                                    @else
                                        <span class="badge badge-secondary">Neaktivan</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! $subscriber->status)
                                        <span class="badge badge-secondary">Neaktivno</span>
                                    @elseif (! $subscriber->gdpr)
                                        <span class="badge badge-secondary">Bez privole</span>
                                    @else
                                        @php($syncState = $mailchimpStates[$subscriber->mailchimp_sync_status ?: 'pending'] ?? $mailchimpStates['error'])
                                        <span class="badge {{ $syncState['class'] }}">{{ $syncState['label'] }}</span>
                                        @if ($subscriber->mailchimp_synced_at)
                                            <small class="d-block text-muted">{{ $subscriber->mailchimp_synced_at->format('d.m.Y. H:i') }}</small>
                                        @endif
                                    @endif
                                </td>
                                <td>{{ optional($subscriber->subscribed_at)->format('d.m.Y. H:i') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fa fa-inbox fa-2x d-block mb-2"></i>
                                    Nema newsletter prijava za odabrane filtre.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $subscribers->links() }}
            </div>
        </div>
    </div>
@endsection
