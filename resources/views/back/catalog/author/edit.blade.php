@extends('back.layouts.backend')

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Autor edit</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('authors') }}">Autori</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Novi autor</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="content content-full content-boxed">

        <!-- END Page Content -->
    @include('back.layouts.partials.session')
    <!-- New Post -->
        <form action="{{ isset($author) ? route('authors.update', ['author' => $author]) : route('authors.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($author))
                {{ method_field('PATCH') }}
            @endif
            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ back()->getTargetUrl() }}">
                        <i class="fa fa-arrow-left mr-1"></i> Povratak
                    </a>
                    <div class="block-options d-inline-block">
                        <div class="custom-control custom-switch custom-control-success d-inline-block mr-5">
                            <input type="checkbox" class="custom-control-input" id="featured-switch" name="featured"{{ (isset($author->featured) and $author->featured) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="featured-switch">Izdvojeni autor</label>
                        </div>
                        <div class="custom-control custom-switch custom-control-success d-inline-block">
                            <input type="checkbox" class="custom-control-input" id="author-switch" name="status"{{ (isset($author->status) and $author->status) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="author-switch">Aktiviraj</label>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">

                            <div class="form-group">
                                <label for="title-input">Naziv izdavača</label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naziv autora" value="{{ isset($author) ? $author->title : old('title') }}" onkeyup="SetSEOPreview()">
                            </div>

                            <div class="form-group">
                                <label for="slug-input">SEO link (url)</label>
                                <input type="text" class="form-control" id="slug-input" name="slug" value="{{ isset($author) ? $author->slug : old('slug') }}" disabled>
                            </div>

                            <div class="form-group">
                                <label for="description-editor">Opis izdavača</label>
                                <textarea id="description-editor" name="description">{!! isset($author) ? $author->description : old('description') !!}</textarea>
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
                                <input type="text" class="js-maxlength form-control" id="meta-title-input" name="meta_title" value="{{ isset($author) ? $author->meta_title : old('meta_title') }}" maxlength="70" data-always-show="true" data-placement="top">
                                <small class="form-text text-muted">
                                    70 znakova max
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="meta-description-input">Meta opis</label>
                                <textarea class="js-maxlength form-control" id="meta-description-input" name="meta_description" rows="4" maxlength="160" data-always-show="true" data-placement="top">{{ isset($author) ? $author->meta_description : old('meta_description') }}</textarea>
                                <small class="form-text text-muted">
                                    160 znakova max
                                </small>
                            </div>

                            <div class="form-group row">
                                <div class="col-xl-6">
                                    <label>Open Graph slika</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="image-input" name="image" data-toggle="custom-file-input" onchange="readURL(this);">
                                        <label class="custom-file-label" for="image-input">Odaberite sliku</label>
                                    </div>
                                    <div class="mt-2">
                                        <img class="img-fluid" id="image-view" src="{{ isset($author) ? asset($author->image) : asset('media/img/lightslider.webp') }}" alt="">
                                    </div>
                                    <div class="form-text text-muted font-size-sm font-italic">Slika koja se pokazuje kada se link dijeli (facebook, twitter, itd.)</div>
                                </div>
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
                        <div class="col-md-5 text-right">
                        @if (isset($author))

                                <a href="{{ route('authors.destroy', ['author' => $author]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Obriši" onclick="event.preventDefault(); document.getElementById('delete-author-form{{ $author->id }}').submit();">
                                    <i class="fa fa-trash-alt"></i> Obriši
                                </a>

                        @endif
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <!-- END New Post -->
        @if (isset($author))
            <form id="delete-author-form{{ $author->id }}" action="{{ route('authors.destroy', ['author' => $author]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif

    </div>

@endsection

@push('js_after')
    <!-- Page JS Plugins -->
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>

    <script>
        $(() => {
            ClassicEditor
            .create( document.querySelector('#description-editor'))
            .then( editor => {
                console.log(editor);
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
