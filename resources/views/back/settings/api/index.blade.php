@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">API Postavke</h1>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="row">
            <div class="col-12">
                <div class="block block-rounded">
                    <ul class="nav nav-tabs nav-tabs-block" data-toggle="tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" href="#btabs-static-home">Api IN (Download)</a>
                        </li>
                        <li class="nav-item ml-auto">
                            <a class="nav-link" href="#btabs-static-settings">
                                <i class="si si-settings"></i>
                            </a>
                        </li>
                    </ul>
                    <div class="block-content tab-content">
                        <div class="tab-pane active" id="btabs-static-home" role="tabpanel">
                            <div class="row">
                                <div class="col-md-8 mb-4">
                                    <div id="accordion2" role="tablist" aria-multiselectable="true">
                                        <div class="block block-rounded mb-1">
                                            <div class="block-header block-header-default" role="tab" id="akademska_knjiga_tab">
                                                <a class="font-w600" data-toggle="collapse" data-parent="#accordion2" href="#akademska_knjiga" aria-expanded="true" aria-controls="akademska_knjiga">Akademska Knjiga .mk</a>
                                            </div>
                                            <div id="akademska_knjiga" class="collapse" role="tabpanel" aria-labelledby="akademska_knjiga_tab">
                                                <div class="block-content px-1">
                                                    <div class="row items-push">
                                                        <div class="col-md-12">
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered table-vcenter mb-0">
                                                                    <tbody>
                                                                    <tr>
                                                                        <td style="width: 30%;">
                                                                            <button type="button" class="btn btn-sm btn-alt-warning" onclick="event.preventDefault(); importTarget('akademska-knjiga-mk', 'check-products', '{{ route('api.api.import') }}');">Provjera Novih
                                                                                                                                                                                                                                                               Proizvoda
                                                                            </button>
                                                                        </td>
                                                                        <td>
                                                                            <code>Provjeri ima li novih proizvoda za import...</code>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 30%;">
                                                                            <button type="button" class="btn btn-sm btn-alt-info" onclick="event.preventDefault(); importTarget('akademska-knjiga-mk', 'products', '{{ route('api.api.import') }}');">Import Proizvoda
                                                                            </button>
                                                                        </td>
                                                                        <td>
                                                                            <code>Import novih proizvoda...</code>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td>
                                                                            <button type="button" class="btn btn-sm btn-alt-info" onclick="event.preventDefault(); importTarget('akademska-knjiga-mk', 'update-prices-quantities', '{{ route('api.api.import') }}');">Update
                                                                                                                                                                                                                                                                      Cijena i
                                                                                                                                                                                                                                                                      Količina
                                                                            </button>
                                                                        </td>
                                                                        <td>
                                                                            <code>Update cijena i količina...</code>
                                                                        </td>
                                                                    </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="block block-rounded mb-1">
                                            <div class="block-header block-header-default" role="tab" id="plava_krava_tab">
                                                <a class="font-w600" data-toggle="collapse" data-parent="#accordion2" href="#plava_krava" aria-expanded="true" aria-controls="plava_krava">Plava-Krava.hr</a>
                                            </div>
                                            <div id="plava_krava" class="collapse" role="tabpanel" aria-labelledby="plava_krava_tab">
                                                <div class="block-content px-1">
                                                    <div class="row items-push">
                                                        <div class="col-md-12">
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered table-vcenter mb-0">
                                                                    <tbody>
                                                                    <tr>
                                                                        <td style="width: 30%;">
                                                                            <input type="file" id="excel-file" name="file" accept=".xlsx,.xls" style="display: none;" onchange="uploadFile(event)">
                                                                            <button class="btn btn-sm btn-alt-info" onclick="document.getElementById('excel-file').click()">Upload Excel & Import</button>
                                                                        </td>
                                                                        <td>
                                                                            <code>Upload excel -> Import novih proizvoda iz excela.</code>
                                                                        </td>
                                                                    </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="block block-rounded block-bordered" id="my-block">
                                        <div class="block-header block-header-default">
                                            <h3 class="block-title">Rezultat</h3>
                                        </div>
                                        <div class="block-content">
                                            <p class="font-w300 font-size-sm" id="api-result">Ovdje će se prikazati rezultati ili greške API poziva, zavisno od toga što ste pozvali...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="btabs-static-settings" role="tabpanel">
                            <h4 class="font-w400">Settings Content</h4>
                            <p>...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('modals')

@endpush

@push('js_after')
    <script>
        $(() => {

        });

        function importTarget(target, method) {
            let block = $('#my-block');
            let item  = {target: target, method: method};

            block.addClass('block-mode-loading');

            axios.post('{{ route('api.api.import') }}', {data: item})
            .then(response => {
                showToast(response.data);
                showResult(response.data);

                block.removeClass('block-mode-loading');
            });
        }


        function uploadFile(event) {
            let block = $('#my-block');
            let file = event.target.files[0];

            if (!file) {
                return errorToast.fire('Molimo učitajte Excel datoteku!');
            }

            let fd = new FormData();
            fd.append("file", file);
            fd.append("target", 'plava-krava');
            fd.append("method", 'upload-excel');

            block.addClass('block-mode-loading');

            axios.post('{{ route('api.api.upload') }}', fd)
            .then(response => {
                showToast(response.data);
                showResult(response.data);

                block.removeClass('block-mode-loading');
            });
        }


        function showResult(result) {
            let text = result.success ? result.success : result.error;

            $('#api-result').html(text);
        }


        function showToast(result) {
            if (result.success) {
                successToast.fire();
            } else {
                errorToast.fire(result.message);
            }
        }

    </script>
@endpush
