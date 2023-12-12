@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Grupe Kategorija</h1>
                <button class="btn btn-hero-success my-2" onclick="event.preventDefault(); openModal();">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Nova Grupa</span>
                </button>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="block">
            <div class="block-header block-header-default">
                <h3 class="block-title">Lista</h3>
            </div>
            <div class="block-content">
                <table class="table table-striped table-borderless table-vcenter">
                    <thead class="thead-light">
                    <tr>
                        <th class="text-center" style="width: 5%;">Br.</th>
                        <th style="width: 30%;">Naziv</th>
                        <th style="width: 20%;">Url</th>
                        <th class="text-center">Poredak</th>
                        <th class="text-center">Status</th>
                        <th class="text-right" style="width: 100px;">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($groups as $group)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}.</td>
                            <td>{{ $group->title }}</td>
                            <td>{{ $group->slug }}</td>
                            <td class="text-center">{{ $group->sort_order }}</td>
                            <td class="text-center">@include('back.layouts.partials.status', ['status' => $group->status])</td>
                            <td class="text-right font-size-sm">
                                <button class="btn btn-sm btn-alt-secondary" onclick="event.preventDefault(); openModal({{ json_encode($group) }});">
                                    <i class="fa fa-fw fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-alt-danger" onclick="event.preventDefault(); deleteGroup({{ json_encode($group) }});">
                                    <i class="fa fa-fw fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="6">Nema grupa. Otvorite novu grupu da biste dodavali kategorije...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="group-modal" tabindex="-1" role="dialog" aria-labelledby="group--modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Grupa kategorije</h3>
                        <div class="block-options">
                            <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10">
                                <div class="form-group">
                                    <label for="group-title">Naziv grupe</label>
                                    <input type="text" class="form-control" id="group-title" name="title">
                                </div>

                                <div class="form-group">
                                    <label for="group-slug">Url</label>
                                    <input type="text" class="form-control" id="group-slug" name="slug">
                                </div>

                                <div class="form-group">
                                    <label for="group-sort-order">Poredak</label>
                                    <input type="text" class="form-control" id="group-sort-order" name="sort_order">
                                </div>

                                <div class="form-group">
                                    <label for="group-status" class="css-control css-control-sm css-control-success css-switch res">
                                        <input type="checkbox" class="css-control-input" id="group-status" name="status">
                                        <span class="css-control-indicator"></span> Status
                                    </label>
                                </div>

                                <input type="hidden" id="group-id" name="id" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); createGroup();">
                            Snimi <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete-group-modal" tabindex="-1" role="dialog" aria-labelledby="group--modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Obriši groupu</h3>
                        <div class="block-options">
                            <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10">
                                <h4>Jeste li sigurni da želite obrisati groupu kategorija?</h4>
                                <input type="hidden" id="delete-group-id" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-danger" onclick="event.preventDefault(); confirmDelete();">
                            Obriši <i class="fa fa-trash-alt ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {

        });

        /**
         *
         * @param item
         * @param type
         */
        function openModal(item = {}) {
            //console.log(item);

            $('#group-modal').modal('show');
            editGroup(item);
        }

        /**
         *
         */
        function createGroup() {
            let item = {
                id: $('#group-id').val(),
                title: $('#group-title').val(),
                slug: $('#group-slug').val(),
                sort_order: $('#group-sort-order').val(),
                status: $('#group-status')[0].checked
            };

            axios.post("{{ route('api.categories.groups.store') }}", {data: item})
            .then(response => {
                //console.log(response.data)
                if (response.data.success) {
                    successToast.fire(response.data.success);
                    setTimeout(location.reload(), 900);
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         */
        function deleteGroup(id) {
            $('#delete-group-modal').modal('show');
            $('#delete-group-id').val(id);
        }

        /**
         *
         */
        function confirmDelete() {
            let item = {
                id: $('#delete-group-id').val()
            };

            axios.post("{{ route('api.categories.groups.destroy') }}", {data: item})
            .then(response => {
                //console.log(response.data)
                if (response.data.success) {
                    successToast.fire(response.data.success);
                    setTimeout(location.reload(), 900);
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         * @param item
         */
        function editGroup(item) {
            $('#group-id').val(item.id);
            $('#group-title').val(item.title);
            $('#group-slug').val(item.slug);
            $('#group-sort-order').val(item.sort_order);

            if (item.status) {
                $('#group-status')[0].checked = !!item.status;
            }
        }
    </script>
@endpush
