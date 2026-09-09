@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/flatpickr/flatpickr.min.css') }}">
@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Info stranica edit</h1>
            </div>
        </div>
    </div>

    <div class="content content-full content-boxed">
        @include('back.layouts.partials.session')

        <form action="{{ isset($page) ? route('pages.update', ['page' => $page]) : route('pages.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($page))
                {{ method_field('PATCH') }}
            @endif

            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ back()->getTargetUrl() }}">
                        <i class="fa fa-arrow-left mr-1"></i> Povratak
                    </a>
                    <div class="block-options">
                        <div class="custom-control custom-switch custom-control-success">
                            <input type="checkbox" class="custom-control-input" id="status-switch" name="status" {{ (isset($page) and $page->status) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="status-switch">Aktiviraj</label>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">

                            <div class="form-group">
                                <label for="title-input">Naslov</label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naslov..." value="{{ isset($page) ? $page->title : old('title') }}" onkeyup="SetSEOPreview()">
                            </div>

                            <div class="form-group">
                                <label for="group-select">Grupa</label>
                                <select class="js-select2 form-control" id="group-select" name="group" style="width: 100%;">
                                    @foreach ($groups as $group)
                                        <option value="{{ $group }}" {{ ((isset($page)) and ($page->subgroup == $group)) ? 'selected' : '' }}>{{ $group }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group row d-none">
                                <div class="col-xl-6">
                                    <label>Glavna slika</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="image-input" name="image" data-toggle="custom-file-input" onchange="readURL(this);">
                                        <label class="custom-file-label" for="image-input">Odaberite sliku</label>
                                    </div>
                                    <div class="mt-2">
                                        <img class="img-fluid" id="image-view" src="{{ isset($page) ? asset($page->image) : asset('media/img/lightslider.webp') }}" alt="">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row mb-4 admin-rich-text-editor">
                                <div class="col-md-12">
                                    <label for="description-editor">Opis</label>
                                    <textarea id="description-editor" name="description">{!! isset($page) ? $page->description : old('description') !!}</textarea>
                                </div>
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
                            <form action="be_pages_ecom_product_edit.html" method="POST" onsubmit="return false;">
                                <div class="form-group">
                                    <label for="meta-title-input">Meta naslov</label>
                                    <input type="text" class="js-maxlength form-control" id="meta-title-input" name="meta_title" value="{{ isset($page) ? $page->meta_title : old('meta_title') }}" maxlength="70" data-always-show="true" data-placement="top">
                                    <small class="form-text text-muted">
                                        70 znakova max
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="meta-description-input">Meta opis</label>
                                    <textarea class="js-maxlength form-control" id="meta-description-input" name="meta_description" rows="4" maxlength="160" data-always-show="true" data-placement="top">{{ isset($page) ? $page->meta_description : old('meta_description') }}</textarea>
                                    <small class="form-text text-muted">
                                        160 znakova max
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="slug-input">SEO link (url)</label>
                                    <input type="text" class="form-control" id="slug-input" name="slug" value="{{ isset($page) ? $page->slug : old('slug') }}" disabled>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
                <div class="block-content bg-body-light">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-hero-success my-2">
                                <i class="fas fa-save mr-1"></i> Snimi
                            </button>
                        </div>
                        @if (isset($page) && $page->subgroup != '/')
                            <div class="col-md-6 text-right">
                                <a href="{{ route('pages.destroy', ['page' => $page]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Obriši" onclick="event.preventDefault(); document.getElementById('delete-page-form{{ $page->id }}').submit();">
                                    <i class="fa fa-trash-alt"></i> Obriši
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if (isset($page))
            <form id="delete-page-form{{ $page->id }}" action="{{ route('pages.destroy', ['page' => $page]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/plugins/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <!-- Page JS Helpers (CKEditor 5 plugins) -->
    <script>jQuery(function(){Dashmix.helpers(['flatpickr']);});</script>


    <script>
        $(() => {
            $('#group-select').select2({
                placeholder: 'Odaberite ili upišite novu grupu...',
                tags: true
            });

            const pageHeader = document.getElementById('page-header');
            const editorToolbarOffset = pageHeader ? Math.ceil(pageHeader.getBoundingClientRect().height) : 0;

            ClassicEditor
            .create(document.querySelector('#description-editor'), {
                toolbar: {
                    viewportTopOffset: editorToolbarOffset,
                },
                ckfinder: {
                    uploadUrl: '{{ route('pages.upload.image') }}?_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '&page_id={{ (isset($page->id) && $page->id) ?: 0 }}',
                }
            })
            .then( editor => {
                const stickyPanel = editor.ui.view.stickyPanel;

                if (stickyPanel) {
                    stickyPanel.unbind('isActive');
                    stickyPanel.isActive = true;
                }
            } )
            .catch( error => {
                console.error(error);
            } );
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
