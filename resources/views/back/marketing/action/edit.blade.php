@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}">
@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Akcija edit</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('actions') }}">Akcije</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Nova akcija</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <div class="content content-full ">

        @include('back.layouts.partials.session')

        <form action="{{ isset($action) ? route('actions.update', ['action' => $action]) : route('actions.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($action))
                {{ method_field('PATCH') }}
            @endif
            <div class="row">

                <div class="col-md-7">
                    <div class="block">
                        <div class="block-header block-header-default">
                            <a class="btn btn-light" href="{{ back()->getTargetUrl() }}">
                                <i class="fa fa-arrow-left mr-1"></i> Povratak
                            </a>
                            <div class="block-options">
                                <div class="custom-control custom-switch custom-control-success">
                                    <input type="checkbox" class="custom-control-input" id="status-switch" name="status" @if (isset($action) and $action->status) checked @endif>
                                    <label class="custom-control-label" for="status-switch">Aktiviraj</label>
                                </div>
                            </div>
                        </div>
                        <div class="block-content">
                            <div class="row justify-content-center push">
                                <div class="col-md-12">
                                    <div class="form-group row items-push mb-2">
                                        <div class="col-md-8">
                                            <label for="title-input">Naziv akcije <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naziv akcije" value="{{ isset($action) ? $action->title : old('title') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="group-select">Grupa akcije <span class="text-danger">*</span></label>
                                            <select class="form-control" id="group-select" name="group">
                                                <option></option>
                                                @foreach ($groups as $group)
                                                    <option value="{{ $group->id }}" {{ (isset($action) and $group->id == $action->group) ? 'selected="selected"' : '' }}>{{ $group->title }}</option>
                                                @endforeach
{{--                                                <option value="all" {{ (isset($action) and 'all' == $action->group) ? 'selected="selected"' : '' }}>Svi Artikli</option>--}}
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row items-push mb-2">
                                        <div class="col-md-6">
                                            <label for="type-select">Vrsta popusta <span class="text-danger">*</span></label>
                                            <select class="form-control" id="type-select" name="type">
                                                @foreach ($types as $type)
                                                    <option value="{{ $type->id }}" {{ (isset($action) and $type->id == $action->type) ? 'selected="selected"' : '' }}>{{ $type->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="discount-input">Akcija @include('back.layouts.partials.required-star')</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="discount-input" name="discount" placeholder="Unesite popust" value="{{ isset($action) ? $action->discount : old('discount') }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text" id="discount-append-badge">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row items-push mb-2">
                                        <div class="col-md-12">
                                            <label for="date-start-input">Akcija vrijedi</label>
                                            <div class="input-daterange input-group" data-date-format="mm/dd/yyyy" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                                <input type="text" class="form-control" id="date-start-input" name="date_start"
                                                       value="{{ isset($action) && $action->date_start ? \Illuminate\Support\Carbon::make($action->date_start)->format('d.m.Y') : '' }}"
                                                       placeholder="od" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                                <div class="input-group-prepend input-group-append">
                                                    <span class="input-group-text font-w600">
                                                        <i class="fa fa-fw fa-arrow-right"></i>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control" id="date-end-input" name="date_end"
                                                       value="{{ isset($action) && $action->date_end ? \Illuminate\Support\Carbon::make($action->date_end)->format('d.m.Y') : '' }}"
                                                       placeholder="do" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row items-push mb-0 mt-4">
                                        <div class="col-md-4 pt-2">
                                            <label>Zahtjeva Kupon kod @include('back.layouts.partials.popover', ['title' => 'Ako upišete Kupon Kod', 'content' => 'Smatrat će se da ga zahtjevate prilikom kupnje za ostvarivanje akcije i pripadajučeg popusta...'])</label>
                                        </div>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" name="coupon" placeholder="Upišite kupon kod..." value="{{ isset($action) ? $action->coupon : old('coupon') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row items-push mb-2">
                                        <div class="col-md-4 pt-2"></div>
                                        <div class="col-md-8">
                                            <div class="custom-control custom-switch custom-control-success">
                                                <input type="checkbox" class="custom-control-input" id="coupon-quantity" name="coupon_quantity" @if (isset($action) and $action->quantity) checked @endif>
                                                <label class="custom-control-label" for="coupon-quantity">Koristi kupon samo jednom</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="block-content bg-body-light">
                            <div class="row">
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-hero-success mb-3">
                                        <i class="fas fa-save mr-1"></i> Snimi
                                    </button>
                                </div>
                                @if (isset($action))
                                    <div class="col-md-6 text-right">
                                        <a href="{{ route('actions.destroy', ['action' => $action]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Obriši" onclick="event.preventDefault(); document.getElementById('delete-action-form{{ $action->id }}').submit();">
                                            <i class="fa fa-trash-alt"></i> Obriši
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-5" id="action-list-view">
                    @if (isset($action))
                        @livewire('back.marketing.action-group-list', ['group' => $action->group, 'list' => json_decode($action->links)])
                    @else
                        @livewire('back.marketing.action-group-list', ['group' => 'products'])
                    @endif
                </div>
            </div>
        </form>

        @if (isset($action))
            <form id="delete-action-form{{ $action->id }}" action="{{ route('actions.destroy', ['action' => $action]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('js/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>

    <script>jQuery(function(){Dashmix.helpers(['datepicker']);});</script>

    <script>
        $(() => {
            /**
             *
             */
            $('#group-select').select2({
                placeholder: '-- Molimo odaberite --',
                minimumResultsForSearch: Infinity
            });
            $('#group-select').on('change', function (e) {
                Livewire.emit('groupUpdated', e.currentTarget.value);
            });

            Livewire.on('list_full', () => {
                $('#group-select').attr("disabled", true);
            });
            Livewire.on('list_empty', () => {
                $('#group-select').attr("disabled", false);
            });
            /**
             *
             */
            $('#type-select').select2({
                placeholder: '-- Molimo odaberite --',
                minimumResultsForSearch: Infinity
            });
            $('#type-select').on('change', function (e) {
                if (e.currentTarget.value == 'F') {
                    $('#discount-append-badge').text('EUR');
                } else {
                    $('#discount-append-badge').text('%');
                }
            });

            @if (isset($action) && ! in_array($action->group, ['all', 'total']))
                $('#group-select').attr("disabled", true);

                @if ($action->type == 'F')
                    $('#discount-append-badge').text('EUR');
                @else
                    $('#discount-append-badge').text('%');
                @endif
            @endif

        })
    </script>

@endpush
