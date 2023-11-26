@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">{{ __('back/review.title_edit') }}</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('reviews') }}">{{ __('back/review.title') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('back/review.title_edit') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <div class="content content-full ">

        @include('back.layouts.partials.session')

        <form action="{{ isset($review) ? route('reviews.update', ['review' => $review]) : route('reviews.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($review))
                {{ method_field('PATCH') }}
            @endif

            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ route('reviews') }}">
                        <i class="fa fa-arrow-left mr-1"></i> {{ __('back/review.title_back') }}
                    </a>
                    <div class="block-options">
                        <div class="custom-control custom-switch custom-control-info block-options-item ml-4">
                            <input type="checkbox" class="custom-control-input" id="featured-switch" name="featured" @if (isset($review) and $review->featured) checked @endif>
                            <label class="custom-control-label" for="featured-switch">{{ __('back/review.title_featured') }}</label>
                        </div>
                        <div class="custom-control custom-switch custom-control-success block-options-item ml-4">
                            <input type="checkbox" class="custom-control-input" id="status-switch" name="status" @if (isset($review) and $review->status) checked @endif>
                            <label class="custom-control-label" for="status-switch">Status</label>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">

                            <div class="form-group row items-push mb-2">

                                <div class="col-md-6">
                                    <label for="name-input">{{ __('back/review.firstname') }} @include('back.layouts.partials.required-star')</label>
                                    <input type="text" class="form-control" id="name-input" name="name" placeholder="" value="{{ isset($review) ? $review->fname : old('fname') }}">
                                </div>
{{--                                <div class="col-md-3">--}}
{{--                                    <label for="lastname-input">{{ __('back/review.lastname') }} </label>--}}
{{--                                    <input type="text" class="form-control" id="lastname-input" name="lastname" placeholder="" value="{{ isset($review) ? $review->lname : old('lname') }}">--}}
{{--                                </div>--}}
                                <div class="col-md-6">
                                    <label for="email-input">Email @include('back.layouts.partials.required-star')</label>
                                    <input type="text" class="form-control" id="email-input" name="email" placeholder="" value="{{ isset($review) ? $review->email : old('email') }}">
                                </div>
                            </div>

                            <div class="form-group row items-push mb-3">
                                <div class="col-md-4" id="ag-star-rating-component">
                                    <label class="mb-2">{{ __('back/review.rating') }} @include('back.layouts.partials.required-star')</label>
{{--                                    <ag-star-rating value="{{ isset($review) ? $review->stars : old('stars') }}" increment="0.1" decimals="1"></ag-star-rating>--}}
                                    <select class="form-control form-select" required id="review-stars" name="stars">
                                        <option value="">Odaberite ocjenu</option>
                                        @for ($i = 5; $i > 0; $i--)
                                            @if (isset($review) && $review->stars == $i)
                                                <option value="{{ $i }}" selected>{{ $i }} stars</option>
                                            @else
                                                <option value="{{ $i }}">{{ $i }} star{{ $i != 1 ? 's' : '' }}</option>
                                            @endif
                                        @endfor
                                    </select>
                                </div>

                                <div class="col-md-8">
                                    <label for="email-input">Proizvod @include('back.layouts.partials.required-star')</label>
                                    @livewire('back.layout.search.product-search', ['product_id' => isset($review) ? $review->product_id : old('product_id'), 'list' => true])
                                </div>
                            </div>

                            <div class="form-group row items-push">
                                <div class="col-md-12">
                                    <label for="email-input">{{ __('back/review.message') }} @include('back.layouts.partials.required-star')</label>
                                    <textarea id="message-editor" name="message">{!! isset($review) ? $review->message : old('message') !!}</textarea>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="block-content bg-body-light">
                    <div class="row justify-content-center push mb-3">
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-hero-success mb-3">
                                <i class="fas fa-save mr-1"></i> {{ __('Snimi') }}
                            </button>
                        </div>

                            <div class="col-md-5 text-right">
                                @if (isset($review))
                                <a href="{{ route('reviews.destroy', ['review' => $review]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="{{ __('Obriši') }}" onclick="event.preventDefault(); document.getElementById('delete-review-form{{ $review->id }}').submit();">
                                    <i class="fa fa-trash-alt"></i> {{ __('Obriši') }}
                                </a>
                                @endif
                            </div>

                    </div>
                </div>
            </div>

        </form>

        @if (isset($review))
            <form id="delete-review-form{{ $review->id }}" review="{{ route('reviews.destroy', ['review' => $review]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/ag-star-rating.js') }}"></script>

    <script>
        $(() => {
            ClassicEditor
            .create(document.querySelector('#message-editor'))
            .then(editor => {
                //console.log(editor);
            })
            .catch(error => {
                //console.error(error);
            });
            /**
             *
             */
            $('#lang-select').select2({
                minimumResultsForSearch: Infinity
            });

        })
    </script>

@endpush
