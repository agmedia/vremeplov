@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Kategorija edit</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('categories') }}">Kategorije</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Nova kategorija</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content content-full content-boxed">
        <!-- END Page Content -->
    @include('back.layouts.partials.session')
        <!-- New Post -->
        <form action="{{ isset($category) ? route('category.update', ['category' => $category]) : route('category.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($category))
                {{ method_field('PATCH') }}
            @endif
            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ back()->getTargetUrl() }}">
                        <i class="fa fa-arrow-left mr-1"></i> Povratak
                    </a>
                    <div class="block-options">
                        <div class="custom-control custom-switch custom-control-success">
                            <input type="checkbox" class="custom-control-input" id="category-switch" name="status" {{ (isset($category->status) and $category->status) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="category-switch">Aktiviraj</label>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">

                            <div class="form-group">
                                <label for="title-input">Naziv kategorije</label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naziv" value="{{ isset($category) ? $category->title : old('title') }}" onkeyup="SetSEOPreview()">
                            </div>
                            <div class="form-group">
                                <label for="group-select">Grupa</label>
                                <select class="js-select2 form-control" id="group-select" name="group" style="width: 100%;">
                                    @foreach ($groups as $group)
                                        <option value="{{ $group }}">{{ $group }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="parent-select">Glavna kategorija</label>
                                <select class="js-select2 form-control" id="parent-select" name="parent" style="width: 100%;">
                                    <option></option>
                                    @foreach ($parents as $id => $name)
                                        <option value="{{ $id }}" {{ (isset($category->parent_id) and $id == $category->parent_id) ? 'selected="selected"' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="slug-input">SEO link (url)</label>
                                <input type="text" class="form-control" id="slug-input" name="slug" value="{{ isset($category) ? $category->slug : old('slug') }}">
                            </div>

                            <div class="form-group">
                                <label for="dm-post-edit-slug">Opis kategorije</label>
                                <textarea id="description-editor" name="description">{!! isset($category) ? $category->description : old('description') !!}</textarea>
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
                        <div class="col-md-10">
                            <div class="form-group">
                                <label for="meta-title-input">Meta naslov</label>
                                <input type="text" class="js-maxlength form-control" id="meta-title-input" name="meta_title" value="{{ isset($category) ? $category->meta_title : old('meta_title') }}" maxlength="70" data-always-show="true" data-placement="top">
                                <small class="form-text text-muted">
                                    70 znakova max
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="meta-description-input">Meta opis</label>
                                <textarea class="js-maxlength form-control" id="meta-description-input" name="meta_description" rows="4" maxlength="160" data-always-show="true" data-placement="top">{{ isset($category) ? $category->meta_description : old('meta_description') }}</textarea>
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
                                        <img class="img-fluid" id="image-view" src="{{ isset($category) ? asset($category->image) : asset('media/img/lightslider.webp') }}" alt="">
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
                        @if (isset($category))

                                <a href="{{ route('category.destroy', ['category' => $category]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Obriši" onclick="event.preventDefault(); document.getElementById('delete-category-form{{ $category->id }}').submit();">
                                    <i class="fa fa-trash-alt"></i> Obriši
                                </a>

                        @endif
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <!-- END New Post -->
        @if (isset($category))
            <form id="delete-category-form{{ $category->id }}" action="{{ route('category.destroy', ['category' => $category]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>


@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        $(() => {
            $('#group-select').select2({
                placeholder: 'Odaberite ili upišite novu grupu...',
                tags: true
            });

            $('#parent-select').select2({
                placeholder: 'Ostavite prazno ako želite da ovo bude glavna kategorija.'
            });

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
