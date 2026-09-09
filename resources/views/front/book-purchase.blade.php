@extends('front.layouts.app')

@section('title', $content['meta_title'])
@section('description', $content['meta_description'])
@section('canonical', route('book-purchase.create'))

@push('meta_tags')
    <meta property="og:locale" content="hr_HR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $content['meta_title'] }}">
    <meta property="og:description" content="{{ $content['meta_description'] }}">
    <meta property="og:url" content="{{ route('book-purchase.create') }}">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        '@id' => route('book-purchase.create') . '#service',
        'name' => $content['title'],
        'description' => $content['meta_description'],
        'serviceType' => 'Otkup antikvarnih i rabljenih knjiga',
        'provider' => ['@id' => url('/#organization')],
        'areaServed' => ['@type' => 'Country', 'name' => 'Hrvatska'],
        'url' => route('book-purchase.create'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@push('css_after')
    <style>
        .book-purchase-page{color:#4f4440}.book-purchase-page h1,.book-purchase-page h2{color:#2d2224;font-family:Georgia,'Times New Roman',serif}.book-purchase-card{background:#fff;border:1px solid #e7ddd0;border-radius:16px;box-shadow:0 10px 32px rgba(45,34,36,.07)}.book-purchase-intro{font-size:1.05rem;line-height:1.8}.book-purchase-intro p:last-child{margin-bottom:0}.book-purchase-page .form-label{font-weight:700;color:#3f3331}.book-purchase-page .form-control{border-color:#ddcfbf;border-radius:11px;padding:.75rem 1rem}.book-purchase-page .form-control:focus{border-color:#c7a361;box-shadow:0 0 0 .2rem rgba(199,163,97,.17)}.book-purchase-files{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:12px;margin-top:16px}.book-purchase-file{position:relative;border:1px solid #e3d8ca;border-radius:12px;background:#faf7f2;padding:9px;min-width:0}.book-purchase-file__preview{display:flex;align-items:center;justify-content:center;width:100%;aspect-ratio:1/1;border-radius:8px;background:#eee7dc;overflow:hidden;color:#9b7f55}.book-purchase-file__preview img{width:100%;height:100%;object-fit:cover}.book-purchase-file__name{display:block;margin-top:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem}.book-purchase-file__remove{width:100%;margin-top:7px}.book-purchase-upload{border:1px dashed #c9b291;border-radius:13px;background:#fbf8f3;padding:20px}.book-purchase-required{color:#9d342d}.book-purchase-privacy{display:flex;align-items:flex-start;gap:.75rem;background:#f8f3ea;border-radius:10px;padding:14px 16px}.book-purchase-privacy .form-check-input{float:none;flex:0 0 auto;margin:.3em 0 0}.book-purchase-privacy .form-check-label{min-width:0}
    </style>
@endpush

@section('content')
    <main class="book-purchase-page">
        @include('front.layouts.partials.page-heading', ['title' => $content['title']])

        <section class="container py-4 py-lg-5">
            @include('front.layouts.partials.session')

            <div class="book-purchase-card p-4 p-lg-5 mb-4">
                <h2 class="h3 mb-3">{{ $content['intro_title'] }}</h2>
                <div class="book-purchase-intro">{!! $content['intro_html'] !!}</div>
            </div>

            <div class="book-purchase-card p-3 p-md-4 p-lg-5">
                <h2 class="h3 mb-4">{{ $content['form_title'] }}</h2>

                <form action="{{ route('book-purchase.store') }}" method="post" enctype="multipart/form-data" id="book-purchase-form" data-analytics-form="book_purchase">
                    @csrf
                    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="bp-name">{{ $content['full_name_label'] }} <span class="book-purchase-required">*</span></label>
                            <input class="form-control @error('full_name') is-invalid @enderror" id="bp-name" name="full_name" value="{{ old('full_name', $defaults['full_name']) }}" required maxlength="150" autocomplete="name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bp-postal-code">{{ $content['postal_code_label'] }} <span class="book-purchase-required">*</span></label>
                            <input class="form-control @error('postal_code') is-invalid @enderror" id="bp-postal-code" name="postal_code" value="{{ old('postal_code', $defaults['postal_code']) }}" required maxlength="20" autocomplete="postal-code">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bp-email">{{ $content['email_label'] }} <span class="book-purchase-required">*</span></label>
                            <input class="form-control @error('email') is-invalid @enderror" id="bp-email" name="email" type="email" value="{{ old('email', $defaults['email']) }}" required maxlength="190" autocomplete="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bp-phone">{{ $content['phone_label'] }} <span class="book-purchase-required">*</span></label>
                            <input class="form-control @error('phone') is-invalid @enderror" id="bp-phone" name="phone" value="{{ old('phone', $defaults['phone']) }}" required maxlength="50" autocomplete="tel">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="bp-photos">{{ $content['photos_label'] }} <span class="book-purchase-required">*</span></label>
                            <div class="book-purchase-upload">
                                <input class="visually-hidden" id="bp-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple required>
                                <button class="btn btn-outline-primary" id="bp-photo-trigger" type="button"><i class="fa-regular fa-images me-2"></i>{{ $content['choose_photos_label'] }}</button>
                                <span class="d-block d-md-inline-block ms-md-3 mt-2 mt-md-0 text-muted" id="bp-photo-summary">{{ $content['no_photos_label'] }}</span>
                                <p class="form-text mt-3 mb-0">{{ $content['photos_help'] }}</p>
                                <div class="book-purchase-files" id="bp-photo-list"></div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check book-purchase-privacy">
                                <input class="form-check-input @error('privacy') is-invalid @enderror" id="bp-privacy" name="privacy" type="checkbox" value="1" required {{ old('privacy') ? 'checked' : '' }}>
                                <label class="form-check-label" for="bp-privacy">{{ $content['consent_text'] }} <span class="book-purchase-required">*</span></label>
                            </div>
                        </div>

                        <div class="col-12">
                            @if (config('services.recaptcha.sitekey'))<input type="hidden" name="recaptcha" id="recaptcha">@endif
                            <button class="btn btn-primary px-4" type="submit"><i class="fa-regular fa-paper-plane me-2"></i>{{ $content['submit_label'] }}</button>
                            @include('front.layouts.partials.recaptcha-notice')
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection

@push('js_after')
    @if (config('services.recaptcha.sitekey'))
        @include('front.layouts.partials.recaptcha-js', [
            'action' => 'book_purchase',
            'fieldId' => 'recaptcha',
            'formId' => 'book-purchase-form',
        ])
    @endif
    @php
        $photoLabels = [
            'none' => $content['no_photos_label'],
            'selected' => $content['selected_photos_label'],
            'remove' => $content['remove_photo_label'],
        ];
    @endphp
    <script>
        (() => {
            const input = document.getElementById('bp-photos');
            const trigger = document.getElementById('bp-photo-trigger');
            const summary = document.getElementById('bp-photo-summary');
            const list = document.getElementById('bp-photo-list');
            const labels = @json($photoLabels);
            let files = [];
            let objectUrls = [];

            const syncInput = () => {
                if (typeof DataTransfer === 'undefined') return;
                const transfer = new DataTransfer();
                files.forEach(file => transfer.items.add(file));
                input.files = transfer.files;
            };

            const render = () => {
                objectUrls.forEach(url => URL.revokeObjectURL(url));
                objectUrls = [];
                list.innerHTML = '';
                summary.textContent = files.length ? labels.selected.replace(':count', files.length) : labels.none;

                files.forEach((file, index) => {
                    const card = document.createElement('div');
                    card.className = 'book-purchase-file';
                    const preview = document.createElement('div');
                    preview.className = 'book-purchase-file__preview';

                    if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                        const url = URL.createObjectURL(file);
                        objectUrls.push(url);
                        const image = document.createElement('img');
                        image.src = url;
                        image.alt = '';
                        preview.appendChild(image);
                    } else {
                        const icon = document.createElement('i');
                        icon.className = 'fa-regular fa-image fa-2x';
                        icon.setAttribute('aria-hidden', 'true');
                        preview.appendChild(icon);
                    }

                    const name = document.createElement('span');
                    name.className = 'book-purchase-file__name';
                    name.title = file.name;
                    name.textContent = file.name;
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-sm btn-outline-danger book-purchase-file__remove';
                    remove.textContent = labels.remove;
                    remove.addEventListener('click', () => {
                        files.splice(index, 1);
                        syncInput();
                        render();
                    });

                    card.append(preview, name, remove);
                    list.appendChild(card);
                });
            };

            trigger.addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                files = Array.from(input.files || []).slice(0, 20);
                syncInput();
                render();
            });
            window.addEventListener('beforeunload', () => objectUrls.forEach(url => URL.revokeObjectURL(url)));
        })();
    </script>
    @if (session()->has('success'))
        <script>window.VremeplovAnalytics.track('generate_lead', {form_name: 'book_purchase'});</script>
    @endif
@endpush
