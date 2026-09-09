@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Widgets ({{ $groups->total() }})</h1>
                <a class="btn btn-hero-success my-2" href="{{ route('widget.group.create') }}">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Nova Widget Grupa</span>
                </a>
                <button type="button" class="btn btn-hero-success my-2 ml-3" data-toggle="modal" data-target="#modal-block-popout">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Novi Widget
                </button>
            </div>
        </div>
    </div>

    <div class="row no-gutters flex-md-10-auto">
        <div class="col-md-12 order-md-0 bg-body-dark">
            <!-- Main Content -->
            <div class="content content-full">
                @include('back.layouts.partials.session')

                <div id="accordion" role="tablist" aria-multiselectable="true">
                    @forelse($groups as $group)
                        <div class="block block-rounded mb-1">
                            <div class="block-header block-header-default" role="tab" id="accordion_h{{ $group->id }}">
                                <a class="h3 block-title" data-toggle="collapse" data-parent="#accordion" href="#accordion_q{{ $group->id }}" aria-expanded="@if($loop->first) true @else false @endif" aria-controls="accordion_q{{ $group->id }}">
                                    {{ $group->title }}
                                    @if ($group->status)
                                        <i class="si si-check text-success ml-3"></i>
                                    @else
                                        <i class="si si-ban text-danger"></i>
                                    @endif
                                </a>
                                <div class="block-options">
                                    <div class="btn-group">
                                        <a href="{{ route('widget.group.edit', ['widget' => $group]) }}" class="btn btn-sm btn-secondary js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Uredi">
                                            <i class="fa fa-pencil-alt"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @if ($group->allWidgets->isNotEmpty())
                                <div id="accordion_q{{ $group->id }}" class="collapse @if($loop->first) show @endif" role="tabpanel" aria-labelledby="accordion_h{{ $group->id }}" data-parent="#accordion">
                                    <div class="block-content pb-4">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p class="small">Da biste implementirali widget, umetnite bilo koju od dolje oznaka u opis stranice.</p>
                                                <p class="h4 small font-weight-bold">++{{ $group->slug }}++<br>++{{ $group->id }}++</p>
                                            </div>
                                            <div class="col-md-8">
                                                <h4>Lista Widgeta</h4>
                                                <div class="row">
                                                    @foreach($group->allWidgets as $widget)
                                                        <div class="col-md-4">
                                                            <a class="block block-rounded block-link-pop text-center" href="{{ route('widget.edit', ['widget' => $widget])  }}">
                                                                @if ($widget->image)
                                                                    <div class="block-content block-content-full bg-image" style="background-image: url({{ asset($widget->image) }}); height: 100px;"></div>
                                                                @endif
                                                                <div class="block-content block-content-full bg-black-5">
                                                                    <p class="font-w600 mb-0">{{ $widget->title }}</p>
                                                                    <p class="mb-1">
                                                                        @if ($widget->status)
                                                                            <span class="badge badge-success">Aktivan</span>
                                                                        @else
                                                                            <span class="badge badge-secondary">Neaktivan</span>
                                                                        @endif
                                                                    </p>
                                                                    <p class="font-size-sm font-italic text-muted mb-0">
                                                                        {{ $widget->subtitle }}
                                                                    </p>
                                                                </div>
                                                            </a>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <h3>Widgeti su prazni. Napravite <a href="#" data-toggle="modal" data-target="#modal-block-popout">novi.</a></h3>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Pop Out Block Modal -->
    <div class="modal fade" id="modal-block-popout" tabindex="-1" role="dialog" aria-labelledby="modal-block-popout" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content">
                <form action="{{ route('widget.create') }}" method="get" enctype="multipart/form-data">
                    <div class="block block-themed block-transparent mb-0">
                        <div class="block-header bg-primary-dark">
                            <h3 class="block-title">Odaberi Grupu Widgeta</h3>
                            <div class="block-options">
                                <button type="button" class="btn-block-option" data-dismiss="modal" aria-label="Close">
                                    <i class="fa fa-fw fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="block-content mb-3">
                            <div class="form-group">
                                <label for="subtitle-input">Grupa widgeta</label>
                                <select class="js-select2 form-control" id="group-select" name="group" style="width: 100%;">
                                    <option></option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="block-content block-content-full text-right bg-light">
                            <button type="button" class="btn btn-sm btn-light" data-dismiss="modal">Poništi</button>
                            <button type="submit" class="btn btn-sm btn-primary">Nastavi</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        $('#group-select').select2({
            placeholder: 'Odaberite grupu..'
        });

        /**
         *
         * @param type
         * @param search
         */
        function setURL(type, search) {
            let url = new URL(location.href);
            let params = new URLSearchParams(url.search);
            let keys = [];

            for(var key of params.keys()) {
                if (key === type) {
                    keys.push(key);
                }
            }

            keys.forEach((value) => {
                if (params.has(value)) {
                    params.delete(value);
                }
            })

            if (search.value) {
                params.append(type, search.value);
            }

            url.search = params;
            location.href = url;
        }

        /**
         *
         * @param item
         */
        function shouldDeleteItem(item) {
            console.log(item)

            confirmPopUp.fire({
                title: 'Jeste li sigurni!?',
                text: 'Potvrdi brisanje ' + item.name,
                type: 'warning',
                confirmButtonText: 'Da, obriši!',
            }).then((result) => {
                if (result.value) {
                    deleteItem(item)
                }
            })
        }

        /**
         *
         * @param item
         */
        function deleteItem(item) {
            axios.post("{{ route('widget.destroy') }}", {data: item})
            .then(r => {
                if (r.data) {
                    location.reload()
                }
            })
            .catch(e => {
                errorToast.fire({
                    text: e,
                })
            })
        }
    </script>

@endpush
