@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">

    <style>
        .ag-hide {
            display: none;
        }
    </style>
@endpush


@section('content')
    <div class="content" id="pages-app">

        @include('back.layouts.partials.session')

        <form action="{{ isset($widget) ? route('widget.update', ['widget' => $widget]) : route('widget.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <h2 class="content-heading"> <a href="{{ route('widgets') }}" class="mr-2 text-gray font-size-h4"><i class="si si-action-undo"></i></a>
                @if (isset($widget))
                    {{ method_field('PATCH') }}
                    Uredi Product Carousel Widget <small class="text-primary pl-4">{{ $widget->title }}</small>
                @else
                    Napravi Novi Product Carousel Widget
                @endif
                <button type="submit" class="btn btn-primary btn-sm float-right"><i class="fa fa-save mr-5"></i> Snimi</button>
            </h2>

            <div class="block block-rounded block-shadow">
                <div class="block-content">
                    <div class="row items-push">
                        <div class="col-lg-7">
                            <h5 class="text-black mb-0 mt-20">Generalne Informacije</h5>
                            <hr class="mb-30">

                            <div class="form-group row items-push mb-3">
                                <div class="col-md-8">
                                    <label for="title-input">Naziv widgeta @include('back.layouts.partials.required-star')</label>
                                    <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naziv widgeta" value="{{ isset($widget) ? $widget->title : old('title') }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="group-select">Grupa stavki @include('back.layouts.partials.required-star')</label>
                                    <select class="form-control" id="target-select" name="target">
                                        <option></option>
                                        <option value="product" {{ (isset($widget->target) and $widget->target == 'product') ? 'selected="selected"' : '' }}>Artikli</option>
                                        <option value="product_category" {{ (isset($widget->target) and $widget->target == 'product_category') ? 'selected="selected"' : '' }}>Kategorije artikala</option>
                                        <option value="publisher" {{ (isset($widget->target) and $widget->target == 'publisher') ? 'selected="selected"' : '' }}>Izdavači</option>
                                        {{--@foreach ($targets as $target)
                                            <option value="{{ $target->id }}" {{ (isset($widget) and $target->id == $widget->target) ? 'selected="selected"' : '' }}>{{ $target->title }}</option>
                                        @endforeach--}}
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="catalog-group-select">Glavna grupa artikala</label>
                                <select class="form-control" id="catalog-group-select" name="catalog_group">
                                    <option value="">Sve grupe artikala</option>
                                    @foreach (($catalogGroups ?? collect()) as $catalogGroup)
                                        <option value="{{ $catalogGroup }}" {{ old('catalog_group', $widget->data['catalog_group'] ?? '') === $catalogGroup ? 'selected' : '' }}>
                                            {{ \Illuminate\Support\Str::headline($catalogGroup) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Primjer: odaberite Knjige kako se u redu ne bi prikazivale karte, razglednice i ostali artikli.</small>
                            </div>

                            <div class="form-group row mb-30">
                                <label class="col-12" for="subtitle-input">Podnaslov</label>
                                <div class="col-12">
                                    <textarea class="form-control" id="subtitle-input" name="subtitle" rows="4" placeholder="Kratak tekst, ako je potreban..">{{ isset($widget->subtitle) ? $widget->subtitle : '' }}</textarea>
                                </div>
                            </div>


                            <div class="form-group mb-3">
                                   <label for="url-input">URL Link @include('back.layouts.partials.popover', ['title' => 'Link Widgeta', 'content' => 'Može se proizvoljno upisati bilo koji link. Odete na front gdje želite > Copy/Paste i to je to.'])</label>
                                   <input type="text" class="form-control" name="url" id="url-input" value="{{ isset($widget->url) ? $widget->url : '' }}" placeholder="">
                           </div>


                            <div class="form-group mb-5">
                                <label for="css-input">Custom CSS klasa</label>
                                <input type="text" class="form-control" name="css" id="css-input" value="{{ isset($widget->data['css']) ? $widget->data['css'] : '' }}" placeholder="Klasa se odnosi na vanjski okvir widgeta...">
                            </div>

                            <h5 class="text-black mb-0 mt-20">Detalji Widgeta</h5>
                            <hr class="mb-30">

                            <div class="block">
                                <div class="block-content" style="background-color: #f8f9f9; border: 1px solid #e9e9e9; padding: 30px;">
                                    <div class="row">
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group mb-3">
                                                <div class="custom-control custom-switch custom-control-success">
                                                    <input type="checkbox" class="custom-control-input" id="new-switch" name="new" @if (isset($widget->data['new']) and $widget->data['new']) checked @endif>
                                                    <label class="custom-control-label" for="new-switch">Uključi nove stavke</label>
                                                </div>
                                            </div>
                                            <div class="form-group mb-5">
                                                <div class="custom-control custom-switch custom-control-success">
                                                    <input type="checkbox" class="custom-control-input" id="popular-switch" name="popular" @if (isset($widget->data['popular']) and $widget->data['popular']) checked @endif>
                                                    <label class="custom-control-label" for="popular-switch">Uključi popularne stavke</label>
                                                </div>
                                            </div>
                                            <div class="form-group mb-5">
                                                <div class="custom-control custom-switch custom-control-success">
                                                    <input type="checkbox" class="custom-control-input" id="best-selling-switch" name="best_selling" @if (isset($widget->data['best_selling']) and $widget->data['best_selling']) checked @endif>
                                                    <label class="custom-control-label" for="best-selling-switch">Uključi najprodavanije (zadnjih 30 dana)</label>
                                                </div>
                                            </div>
                                            <div class="form-group mb-3">
                                                <div class="custom-control custom-switch custom-control-success">
                                                    <input type="checkbox" class="custom-control-input" id="status-switch" name="status" @if (isset($widget) and $widget->status) checked @endif>
                                                    <label class="custom-control-label" for="status-switch">Status widgeta</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group mb-2">
                                                <div class="custom-control custom-switch custom-control-info">
                                                    <input type="checkbox" class="custom-control-input" id="container-switch" name="container" @if (isset($widget->data['container']) and $widget->data['container']) checked @endif>
                                                    <label class="custom-control-label" for="container-switch">Uključi okvir s sjenom</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="col-lg-5">
                            <h5 class="text-black mb-0 mt-20">Stavke Widgeta</h5>
                            <hr class="mb-30">

                            @if (isset($widget))
                                @livewire('back.marketing.action-group-list', ['group' => $widget->target, 'list' => json_decode($widget->links)])
                            @else
                                @livewire('back.marketing.action-group-list', ['group' => 'product'])
                            @endif

                        </div>
                    </div>

                </div>

                <input type="hidden" name="group_id" value="{{ $selected->id }}">
                <input type="hidden" name="group_template" value="{{ $selected->template }}">

                <div class="block-content block-content-full block-content-sm bg-body-light font-size-sm text-right">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save mr-5"></i> Snimi
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection


@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        $(() => {
            $('#target-select').select2({
                placeholder: '-- Molimo odaberite --',
                minimumResultsForSearch: Infinity
            });
            $('#target-select').on('change', function (e) {
                Livewire.emit('groupUpdated', e.currentTarget.value);
            });

            Livewire.on('list_full', () => {
                $('#target-select').attr("disabled", true);
            });
            Livewire.on('list_empty', () => {
                $('#target-select').attr("disabled", false);
            });

            @if (isset($widget) && ! empty(json_decode($widget->links)))
                $('#target-select').attr("disabled", true);
            @endif
        });
    </script>

@endpush
