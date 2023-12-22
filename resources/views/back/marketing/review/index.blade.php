@extends('back.layouts.backend')

@push('css_before')

@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">{{ __('back/review.title') }}</h1>
                <a class="btn btn-hero-success my-2" href="{{ route('reviews.create') }}">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> {{ __('back/review.write_new') }} </span>
                </a>
            </div>
        </div>
    </div>

    <div class="content">
    @include('back.layouts.partials.session')
        <!-- All Products -->
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">{{ __('back/review.all_reviews') }}   ({{ $reviews->total() }})</h3>
            </div>

            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter">
                        <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th>{{ __('back/review.title_review') }}</th>
                            <th>{{ __('back/review.title_review_desc') }} </th>
                            <th>{{ __('back/review.title_user') }}</th>
                            <th class="text-center font-size-sm">{{ __('back/review.title_featured') }}</th>
                            <th class="text-center font-size-sm">{{ __('Status') }}</th>
                            <th class="text-right" style="width: 10%;">{{ __('Uredi') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($reviews as $review)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td class="font-size-sm">{{ number_format($review->stars, 1) }}</td>
                                <td class="font-size-sm">{{ substr(strip_tags($review->message), 0, 50) }}...</td>
                                <td class="font-size-sm">{{ $review->fname }} {{ $review->lname }}</td>
                                <td class="text-center font-size-sm">
                                    @include('back.layouts.partials.status', ['status' => $review->featured, 'simple' => true])
                                </td>
                                <td class="text-center font-size-sm">
                                    @include('back.layouts.partials.status', ['status' => $review->status, 'simple' => true])
                                </td>
                                <td class="text-right font-size-sm">
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ route('reviews.edit', ['review' => $review]) }}">
                                        <i class="fa fa-fw fa-pencil-alt"></i>
                                    </a>
                                    <button class="btn btn-sm btn-alt-danger" onclick="event.preventDefault(); deleteItem({{ $review->id }}, '{{ route('reviews.destroy.api') }}');"><i class="fa fa-fw fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="font-size-sm text-center" colspan="7">
                                    <label for="">{{ __('back/review.no_reviews') }}</label>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $reviews->links() }}
            </div>
        </div>
    </div>

@endsection

@push('js_after')
    <script>
        $(() => {
            $("#checkAll").click(function () {
                $('input:checkbox').not(this).prop('checked', this.checked);
            });
        })
    </script>
@endpush
