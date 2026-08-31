@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Načini dostave</h1>
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
                        <th>Naziv</th>
                        <th class="text-center" style="width: 15%;">Poredak</th>
                        <th class="text-center" style="width: 15%;">Cijena</th>
                        <th class="text-center" style="width: 15%;">Status</th>
                        <th style="width: 10%;" class="text-right">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($shippings as $shipping)
                        <tr>
                            <td>{{ $shipping->title }}</td>
                            <td class="text-center">{{ $shipping->sort_order }}</td>
                            <td class="text-center">{{ number_format($shipping->data->price, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @include('back.layouts.partials.status', ['status' => $shipping->status])
                            </td>
                            <td class="text-right font-size-sm">
                                @if($shipping->code !== 'boxnow' || $canManageBoxNow)
                                    <button type="button" class="btn btn-sm btn-alt-secondary" onclick="event.preventDefault(); edit({{ json_encode($shipping) }}, '{{ $shipping->code }}');">
                                        <i class="fa fa-fw fa-pencil-alt"></i>
                                    </button>
                                @else
                                    <span class="text-muted" title="BOX NOW postavke može uređivati samo administrator.">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="4">Nema čestih pitanja...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <!-- Pop Out Block Modal -->
    @foreach($shippings as $shipping)
        @if($shipping->code !== 'boxnow' || $canManageBoxNow)
            @include('back.settings.app.shipping.modals.' . $shipping->code)
        @endif
    @endforeach
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        /**
         *
         * @param item
         * @param type
         */
        function edit(item, type) {
            $('#shipment-modal-' + type).modal('show');
            // Call to individual edit function.
            // As. edit_flat (item) {}
            window["edit_" + type](item);
        }
    </script>

    @stack('shipment-modal-js')
@endpush
