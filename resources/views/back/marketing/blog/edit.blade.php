@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/flatpickr/flatpickr.min.css') }}">
@endpush

@php
    $relatedSlider = isset($blog) ? $blog->related_slider : [];
    $relatedSliderMode = old('related_slider_mode', $relatedSlider['mode'] ?? 'none');
    $relatedSliderTitle = old('related_slider_title', $relatedSlider['title'] ?? '');
    $relatedSliderAuthorId = old('related_slider_author_id', $relatedSlider['author_id'] ?? 0);
    $relatedSliderProductIds = old('related_slider_products', $relatedSlider['product_ids'] ?? []);
@endphp

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Blog edit</h1>
            </div>
        </div>
    </div>

    <div class="content content-full content-boxed">
        @include('back.layouts.partials.session')

        <form action="{{ isset($blog) ? route('blogs.update', ['blog' => $blog]) : route('blogs.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($blog))
                {{ method_field('PATCH') }}
            @endif

            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ back()->getTargetUrl() }}">
                        <i class="fa fa-arrow-left mr-1"></i> Povratak
                    </a>
                    <div class="block-options">
                        <div class="custom-control custom-switch custom-control-success">
                            <input type="checkbox" class="custom-control-input" id="dm-post-edit-active" name="status" {{ (isset($blog) and $blog->status) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="dm-post-edit-active">Aktiviraj</label>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">

                            <div class="form-group">
                                <label for="title-input">Naslov</label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naslov..." value="{{ isset($blog) ? $blog->title : old('title') }}" onkeyup="SetSEOPreview()">
                            </div>

                            <div class="form-group">
                                <label for="short-description-input">Sažetak</label>
                                <textarea class="form-control" id="short-description-input" name="short_description" rows="3" placeholder="Kratki uvod u članak...">{{ isset($blog) ? $blog->short_description : old('short_description') }}</textarea>
                                <div class="form-text text-muted font-size-sm font-italic">Vidljivo na početnoj stranici</div>
                            </div>
                            <div class="form-group row">
                                <div class="col-xl-6">
                                    <label>Glavna slika</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="image-input" name="image" data-toggle="custom-file-input" onchange="readURL(this);">
                                        <label class="custom-file-label" for="image-input">Odaberite sliku</label>
                                    </div>
                                    <div class="mt-2">
                                        <img class="img-fluid" id="image-view" src="{{ isset($blog) ? asset($blog->image) : asset('media/img/lightslider.webp') }}" alt="">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row mb-4 admin-rich-text-editor">
                                <div class="col-md-12">
                                    <label for="description-editor">Opis</label>
                                    <textarea id="description-editor" name="description">{!! isset($blog) ? $blog->description : old('description') !!}</textarea>
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-xl-6">
                                    <label for="publish-date-input">Datum objave</label>
                                    <input type="text" class="js-flatpickr form-control bg-white" id="publish-date-input"
                                           value="{{ isset($blog) && $blog->publish_date ? \Illuminate\Support\Carbon::make($blog->publish_date)->format('d.m.Y') : '' }}"
                                           name="publish_date" data-enable-time="true" placeholder="Ili ostavi prazno za odmah...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-header block-header-default">
                    <div>
                        <h3 class="block-title">Povezane knjige na dnu članka</h3>
                        <p class="font-size-sm text-muted mb-0 mt-1">Odaberite pojedinačne knjige ili autora. Slider se prikazuje samo kad postoje dostupne knjige.</p>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="related-slider-mode">Izvor knjiga</label>
                                        <select class="form-control" id="related-slider-mode" name="related_slider_mode">
                                            <option value="none" {{ $relatedSliderMode === 'none' ? 'selected' : '' }}>Bez slidera</option>
                                            <option value="books" {{ $relatedSliderMode === 'books' ? 'selected' : '' }}>Ručno odabrane knjige</option>
                                            <option value="author" {{ $relatedSliderMode === 'author' ? 'selected' : '' }}>Dostupne knjige autora</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="related-slider-title">Naslov slidera</label>
                                        <input class="form-control" id="related-slider-title" name="related_slider_title" type="text" maxlength="191" value="{{ $relatedSliderTitle }}" placeholder="Npr. Još naslova Julesa Vernea">
                                        <small class="form-text text-muted">Ako ostane prazno, naslov se oblikuje automatski.</small>
                                    </div>
                                </div>
                            </div>

                            <div id="related-slider-author" class="related-slider-panel">
                                <div class="form-group">
                                    <label>Autor čije će se dostupne knjige prikazati</label>
                                    @livewire('back.layout.search.author-search', [
                                        'author_id' => (int) $relatedSliderAuthorId,
                                        'inputName' => 'related_slider_author_id',
                                        'allowCreate' => false,
                                    ])
                                    @error('related_slider_author_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div id="related-slider-books" class="related-slider-panel">
                                <label>Odabrane knjige</label>
                                @livewire('back.marketing.action-group-list', [
                                    'group' => 'product',
                                    'list' => $relatedSliderProductIds,
                                    'inputName' => 'related_slider_products',
                                    'maxItems' => 15,
                                    'productGroup' => 'knjige',
                                    'includeGroupInput' => false,
                                    'emitListState' => false,
                                ])
                                @error('related_slider_products')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Meta Data - SEO</h3>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center">
                        <div class="col-md-10 ">
                                <div class="form-group">
                                    <label for="meta-title-input">Meta naslov</label>
                                    <input type="text" class="js-maxlength form-control" id="meta-title-input" name="meta_title" value="{{ isset($blog) ? $blog->meta_title : old('meta_title') }}" maxlength="70" data-always-show="true" data-placement="top">
                                    <small class="form-text text-muted">
                                        70 znakova max
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="meta-description-input">Meta opis</label>
                                    <textarea class="js-maxlength form-control" id="meta-description-input" name="meta_description" rows="4" maxlength="160" data-always-show="true" data-placement="top">{{ isset($blog) ? $blog->meta_description : old('meta_description') }}</textarea>
                                    <small class="form-text text-muted">
                                        160 znakova max
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="slug-input">SEO link (url)</label>
                                    <input type="text" class="form-control" id="slug-input" name="slug" value="{{ isset($blog) ? $blog->slug : old('slug') }}" disabled>
                                </div>

                        </div>
                    </div>
                </div>
                <div class="block-content bg-body-light">
                    <div class="row justify-content-center push">
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-hero-success my-2">
                                <i class="fas fa-save mr-1"></i> Snimi
                            </button>
                        </div>
                        @if (isset($blog))
                            <div class="col-md-5 text-right">
                                <a href="{{ route('blogs.destroy', ['blog' => $blog]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Obriši" onclick="event.preventDefault(); document.getElementById('delete-blog-form{{ $blog->id }}').submit();">
                                    <i class="fa fa-trash-alt"></i> Obriši
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if (isset($blog))
            <form id="delete-blog-form{{ $blog->id }}" action="{{ route('blogs.destroy', ['blog' => $blog]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/admin-rich-text-editor.js?v=20260909') }}"></script>
    <script src="{{ asset('js/plugins/flatpickr/flatpickr.min.js') }}"></script>

    <!-- Page JS Helpers (CKEditor 5 plugins) -->
    <script>jQuery(function(){Dashmix.helpers(['flatpickr']);});</script>

    <script>
        $(() => {
            const relatedSliderMode = document.getElementById('related-slider-mode');
            const relatedSliderAuthor = document.getElementById('related-slider-author');
            const relatedSliderBooks = document.getElementById('related-slider-books');

            function updateRelatedSliderPanels() {
                const mode = relatedSliderMode ? relatedSliderMode.value : 'none';

                if (relatedSliderAuthor) {
                    relatedSliderAuthor.hidden = mode !== 'author';
                }
                if (relatedSliderBooks) {
                    relatedSliderBooks.hidden = mode !== 'books';
                }
            }

            if (relatedSliderMode) {
                relatedSliderMode.addEventListener('change', updateRelatedSliderPanels);
                updateRelatedSliderPanels();
            }

            VremeplovRichTextEditor.create('#description-editor', {
                uploadUrl: '{{ route('blogs.upload.image') }}?_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '&blog_id={{ (isset($blog->id) && $blog->id) ?: 0 }}',
            }).catch( error => {
                console.error(error);
            });
        })
    </script>

    <script>
        function SetSEOPreview() {
            let title = $('#title-input').val();
            $('#slug-input').val(slugify(title));
        }

        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#image-view')
                    .attr('src', e.target.result);
                };

                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

@endpush
