@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                <div><h1 class="font-size-h2 font-w400 mb-1">Tekstovi stranice Otkup knjiga</h1><div class="text-muted">Svi sadržajni tekstovi javne stranice uređuju se ovdje.</div></div>
                <a class="btn btn-alt-secondary mt-3 mt-sm-0" href="{{ route('book-purchases.index') }}"><i class="fa-duotone fa-list mr-1"></i>Prijave za otkup</a>
            </div>
        </div>
    </div>

    <div class="content content-full content-boxed">
        @include('back.layouts.partials.session')

        <form method="post" action="{{ route('book-purchases.content.update') }}">
            @csrf @method('PATCH')

            <div class="block block-rounded">
                <div class="block-header block-header-default"><h3 class="block-title">Naslov i uvodni sadržaj</h3></div>
                <div class="block-content">
                    <div class="form-group"><label for="bp-content-title">Naslov stranice</label><input class="form-control" id="bp-content-title" name="title" value="{{ old('title', $content['title']) }}" maxlength="120" required></div>
                    <div class="form-group"><label for="bp-intro-title">Naslov uvodnog bloka</label><input class="form-control" id="bp-intro-title" name="intro_title" value="{{ old('intro_title', $content['intro_title']) }}" maxlength="191" required></div>
                    <div class="form-group admin-rich-text-editor"><label for="bp-intro-html">Uvodni tekst i upute</label><textarea id="bp-intro-html" name="intro_html">{!! old('intro_html', $content['intro_html']) !!}</textarea></div>
                </div>
            </div>

            <div class="block block-rounded">
                <div class="block-header block-header-default"><h3 class="block-title">Tekstovi obrasca</h3></div>
                <div class="block-content">
                    <div class="form-group"><label for="bp-form-title">Naslov obrasca</label><input class="form-control" id="bp-form-title" name="form_title" value="{{ old('form_title', $content['form_title']) }}" maxlength="191" required></div>
                    <div class="row">
                        @foreach([
                            'full_name_label' => 'Naziv polja za ime i prezime',
                            'postal_code_label' => 'Naziv polja za poštanski broj',
                            'email_label' => 'Naziv polja za e-mail',
                            'phone_label' => 'Naziv polja za kontakt broj',
                            'photos_label' => 'Naziv polja za fotografije',
                            'choose_photos_label' => 'Tekst gumba za odabir fotografija',
                            'no_photos_label' => 'Tekst kada fotografije nisu odabrane',
                            'selected_photos_label' => 'Tekst kada su fotografije odabrane (:count je broj)',
                            'remove_photo_label' => 'Tekst gumba za uklanjanje fotografije',
                            'submit_label' => 'Tekst gumba za slanje',
                        ] as $field => $label)
                            <div class="col-md-6"><div class="form-group"><label for="bp-{{ str_replace('_', '-', $field) }}">{{ $label }}</label><input class="form-control" id="bp-{{ str_replace('_', '-', $field) }}" name="{{ $field }}" value="{{ old($field, $content[$field]) }}" maxlength="120" required></div></div>
                        @endforeach
                    </div>
                    <div class="form-group"><label for="bp-photos-help">Uputa uz fotografije</label><textarea class="form-control" id="bp-photos-help" name="photos_help" rows="4" maxlength="1000" required>{{ old('photos_help', $content['photos_help']) }}</textarea></div>
                    <div class="form-group"><label for="bp-consent-text">Tekst privole</label><textarea class="form-control" id="bp-consent-text" name="consent_text" rows="4" maxlength="1000" required>{{ old('consent_text', $content['consent_text']) }}</textarea></div>
                    <div class="form-group"><label for="bp-success-message">Poruka nakon uspješnog slanja</label><textarea class="form-control" id="bp-success-message" name="success_message" rows="3" maxlength="1000" required>{{ old('success_message', $content['success_message']) }}</textarea></div>
                </div>
            </div>

            <div class="block block-rounded">
                <div class="block-header block-header-default"><h3 class="block-title">SEO</h3></div>
                <div class="block-content">
                    <div class="form-group"><label for="bp-meta-title">Meta naslov</label><input class="form-control" id="bp-meta-title" name="meta_title" value="{{ old('meta_title', $content['meta_title']) }}" maxlength="160" required></div>
                    <div class="form-group"><label for="bp-meta-description">Meta opis</label><textarea class="form-control" id="bp-meta-description" name="meta_description" rows="3" maxlength="255" required>{{ old('meta_description', $content['meta_description']) }}</textarea></div>
                </div>
                <div class="block-content bg-body-light"><button class="btn btn-hero-success mb-3" type="submit"><i class="fa-duotone fa-floppy-disk mr-1"></i>Spremi sve tekstove</button></div>
            </div>
        </form>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/admin-rich-text-editor.js?v=20260909') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            VremeplovRichTextEditor.create('#bp-intro-html').catch(error => console.error(error));
        });
    </script>
@endpush
