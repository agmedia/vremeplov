@extends('back.layouts.backend')

@section('content')
    <!-- Hero -->
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h3 font-w400 mt-2 mb-0 mb-sm-2">Nadzorna ploča</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active" aria-current="page">Nadzorna ploča</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <!-- END Hero -->

    <!-- Page Content -->
    <div class="content">
        @include('back.layouts.partials.session')
        <!-- Super-admin view -->
        @if (auth()->user()->can('*'))
            <div class="row">
                <div class="col-md-12">
                    <div class="block block-rounded block-mode-hidden">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Superadmin dashboard</h3>
                            <div class="block-options">
                                <button type="button" class="btn-block-option" data-toggle="block-option" data-action="content_toggle"></button>
                            </div>
                        </div>
                        <div class="block-content">
                            <a href="{{ route('roles.set') }}" class="btn btn-hero-sm btn-rounded btn-hero-secondary mb-3 mr-3">Set Roles</a>
                            <br>
                            {{--<a href="{{ route('import.initial') }}" class="btn btn-hero-sm btn-rounded btn-hero-info mb-3 mr-3">Initial Import</a>--}}
                            <a href="{{ route('mailing.test') }}" class="btn btn-hero-sm btn-rounded btn-hero-info mb-3 mr-3">Mail Test</a>
                            <br>
                            <a href="{{ route('letters.import') }}" class="btn btn-hero-sm btn-rounded btn-hero-warning mb-3 mr-3">First Letters Import</a>
                            <a href="{{ route('set.pdv.products') }}" class="btn btn-hero-sm btn-rounded btn-hero-warning mb-3 mr-3">Set PDV 25 Products</a>
                            <br>
                            <a href="{{ route('set.group') }}" class="btn btn-hero-sm btn-rounded btn-hero-danger mb-3 mr-3">Set New Category Group</a>
                            <a href="{{ route('set.unlimited') }}" class="btn btn-hero-sm btn-rounded btn-hero-danger mb-3 mr-3">Set Unlimited Quantity</a>
                            {{--<a href="{{ route('statuses.cron') }}" class="btn btn-hero-sm btn-rounded btn-hero-success mb-3 mr-3">Statuses</a>--}}
                            {{--<a href="{{ route('slugs.revision') }}" class="btn btn-hero-sm btn-rounded btn-hero-primary mb-3 mr-3">Slugs revision</a>--}}
                            {{--<a href="{{ route('duplicate.revision', ['target' => 'images']) }}" class="btn btn-hero-sm btn-rounded btn-hero-primary mb-3 mr-3">Duplicate Images revision</a>--}}
                            {{--<a href="{{ route('duplicate.revision', ['target' => 'publishers']) }}" class="btn btn-hero-sm btn-rounded btn-hero-primary mb-3 mr-3">Duplicate Publishers revision</a>--}}
                        </div>
                    </div>
                </div>
            </div>
        @endif


        <!-- Overview -->
            <div class="row items-push">
                <div class="col-sm-6 col-xl-3">
                    <div class="block block-rounded text-center d-flex flex-column h-100 mb-0">
                        <div class="block-content block-content-full">
                            <div class="item rounded-circle bg-body mx-auto my-3">
                                <i class="fa fa-wallet fa-lg text-primary"></i>
                            </div>
                            <div class="font-size-h3 text-success font-w600 mb-1">{{ $data['this_month_total'] }}€</div>
                            <div class="text-muted ">Mjesečni promet</div>

                        </div>
                        <div class="block-content block-content-full block-content-sm bg-body-light fs-sm">
                            <a class="fw-medium" href="{{ route('orders') }}">
                                Pregled narudžbi
                                <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="block block-rounded text-center d-flex flex-column h-100 mb-0">
                        <div class="block-content block-content-full flex-grow-1">
                            <div class="item rounded-circle bg-body mx-auto my-3">
                                <i class="fa fa-chart-line fa-lg text-primary"></i>
                            </div>
                            <div class="font-size-h3 text-success font-w600 mb-1">{{ $data['this_month'] }}</div>
                            <div class="text-muted ">Narudžbi ovaj mjesec</div>

                        </div>
                        <div class="block-content block-content-full block-content-sm bg-body-light fs-sm">
                            <a class="fw-medium" href="{{ route('orders') }}">
                                Pregled narudžbi
                                <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="block block-rounded text-center d-flex flex-column h-100 mb-0">
                        <div class="block-content block-content-full flex-grow-1">
                            <div class="item rounded-circle bg-body mx-auto my-3">
                                <i class="fa fa-chart-line fa-lg text-primary"></i>
                            </div>
                            <div class="font-size-h3 text-success font-w600 mb-1">{{ $data['today'] }}</div>
                            <div class="text-muted ">Narudžbi danas</div>

                        </div>
                        <div class="block-content block-content-full block-content-sm bg-body-light fs-sm">
                            <a class="fw-medium" href="{{ route('orders') }}">
                                Pregled narudžbi
                                <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="block block-rounded text-center d-flex flex-column h-100 mb-0">
                        <div class="block-content block-content-full flex-grow-1">
                            <div class="item rounded-circle bg-body mx-auto my-3">
                                <i class="fa fa-users fa-lg text-primary"></i>
                            </div>
                            <div class="font-size-h3 text-success font-w600 mb-1">{{ $data['users'] }}</div>
                            <div class="text-muted ">Registriranih korisnika</div>

                        </div>
                        <div class="block-content block-content-full block-content-sm bg-body-light fs-sm">
                            <a class="fw-medium" href="{{ route('users') }}">
                                Pregled korisnika
                                <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END Overview -->


        <!-- Orders Overview -->
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Prodaja</h3>
                <div class="block-options">
                    <button type="button" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content block-content-full">
{{--                Chart.js is initialized in js/pages/be_pages_ecom_dashboard.min.js which was auto compiled from _js/pages/be_pages_ecom_dashboard.js)--}}
{{--                For more info and examples you can check out http://www.chartjs.org/docs/--}}
                <div style="height: 420px;"><canvas class="js-chartjs-overview"></canvas></div>
            </div>
        </div>


        <!-- Top Products and Latest Orders -->
        <div class="row">

            <div class="col-xl-12">
                <!-- Latest Orders -->
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Zadnje narudžbe</h3>
                        <div class="block-options">
                            <button type="button" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                                <i class="si si-refresh"></i>
                            </button>
                        </div>
                    </div>
                    <div class="block-content">
                        <!-- All Orders Table -->
                        <div class="table-responsive">
                            <table class="table table-borderless table-striped table-vcenter font-size-sm">
                                <thead>
                                <tr>

                                    <th class="text-center" style="width: 36px;">Br.</th>
                                    <th class="text-center">Datum</th>
                                    <th>Status</th>
                                    <th>Plaćanje</th>
                                    <th>Kupac</th>
                                    <th class="text-center">Artikli</th>
                                    <th class="text-right">Vrijednost</th>
                                    <th class="text-right">Detalji</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($orders->sortByDesc('id') as $order)
                                    <tr>
                                        <td class="text-center">
                                            <a class="font-w600" href="{{ route('orders.show', ['order' => $order]) }}">
                                                <strong>{{ $order->id }}</strong>
                                            </a>
                                        </td>
                                        <td class="text-center">{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y') }}</td>
                                        <td class="font-size-base">
                                            <span class="badge badge-pill badge-{{ $order->status->color }}">{{ $order->status->title }}</span>
                                        </td>
                                        <td class="text-lwft">{{ $order->payment_method }}</td>
                                        <td>
                                            <a class="font-w600" href="{{ route('orders.show', ['order' => $order]) }}">{{ $order->shipping_fname }} {{ $order->shipping_lname }}</a>
                                        </td>
                                        <td class="text-center">{{ $order->products->count() }}</td>
                                        <td class="text-right">
                                            <strong>{{ number_format($order->total, 2, ',', '.') }}€ </strong>
                                        </td>
                                        <td class="text-right font-size-base">
                                            <a class="btn btn-sm btn-alt-secondary" href="{{ route('orders.show', ['order' => $order]) }}">
                                                <i class="fa fa-fw fa-eye"></i>
                                            </a>
                                            <a class="btn btn-sm btn-alt-info" href="{{ route('orders.edit', ['order' => $order]) }}">
                                                <i class="fa fa-fw fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center font-size-sm" colspan="8">
                                            <label>Nema narudžbi...</label>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->

                    </div>
                    <div class="block-content block-content-full text-center block-content-sm bg-body-light fs-sm">
                        <a class="fw-medium" href="{{ route('orders') }}">
                            Sve narudžbe
                            <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                        </a>
                    </div>
                </div>
                <!-- END Latest Orders -->
            </div>

        </div>


            <div class="row">

                <div class="col-md-6 d-flex flex-column">
                    <div class="block block-rounded">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Komentari kupaca</h3>

                        </div>
                        <div class="block-content block-content-full d-flex justify-content-between align-items-center flex-grow-1">
                            <div class="me-3">
                                <div class="font-size-h3 text-warning font-w600 mb-1">{{ $data['comments'] }}</div>

                                <p class="text-muted mb-0">
                                    Novih komentara
                                </p>
                            </div>
                            <div class="item rounded-circle bg-body">
                                <i class="fas fa-comments fa-lg text-primary"></i>

                            </div>
                        </div>
                        <div class="block-content block-content-full block-content-sm bg-body-light fs-sm text-center">
                            <a class="fw-medium" href="{{ route('reviews') }}">
                                Pogledaj komentare
                                <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 d-flex flex-column">
                    <div class="block block-rounded">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Rasprodano</h3>

                        </div>
                        <div class="block-content block-content-full d-flex justify-content-between align-items-center flex-grow-1">
                            <div class="me-3">
                                <div class="font-size-h3 text-warning font-w600 mb-1">{{ $data['zeroproducts'] }}</div>
                                <p class="text-muted mb-0">
                                    Rasprodanih artikala
                                </p>
                            </div>
                            <div class="item rounded-circle bg-body">
                                <i class="fas fa-exclamation-triangle fa-lg text-primary"></i>
                            </div>
                        </div>
                        <div class="block-content block-content-full block-content-sm bg-body-light fs-sm text-center">
                            <a class="fw-medium" href="{{ route('products') }}">
                                Pogledaj artikle
                                <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-6">
                    <!-- Top Products -->
                    <div class="block block-rounded">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Najprodavaniji artikli</h3>
                            <div class="block-options">
                                <button type="button" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                                    <i class="si si-refresh"></i>
                                </button>
                            </div>
                        </div>
                        <div class="block-content">
                            <table class="table table-borderless table-striped table-vcenter font-size-sm">
                                <tbody>
                                @foreach ($bestsellers as $product)
                                    <tr>
                                        <td class="text-center" style="width: 5%;">
                                            <a class="font-w600" href="{{ route('products.edit', ['product' => $product->product_id]) }}">{{ $product->product_id }}</a>
                                        </td>
                                        <td>
                                            <a href="{{ route('products.edit', ['product' => $product->product_id]) }}">{{ $product->name }}</a>
                                        </td>
                                        <td class="font-w600 text-right" style="width: 20%;">{{ $product->total }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- END Top Products -->
                </div>
                <div class="col-xl-6">
                    <!-- Top Products -->
                    <div class="block block-rounded">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Zadnje prodani artikli</h3>
                            <div class="block-options">
                                <button type="button" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                                    <i class="si si-refresh"></i>
                                </button>
                            </div>
                        </div>
                        <div class="block-content">
                            <table class="table table-borderless table-striped table-vcenter font-size-sm">
                                <tbody>
                                @foreach ($products->take(9) as $product)
                                    <tr>
                                        <td class="text-center" style="width: 5%;">
                                            <a class="font-w600" href="{{ route('products.edit', ['product' => $product->product_id]) }}">{{ $product->id }}</a>
                                        </td>
                                        <td>
                                            <a href="{{ route('products.edit', ['product' => $product->product_id]) }}">{{ $product->name }}</a>
                                        </td>
                                        <td class="font-w600 text-right" style="width: 20%;">{{ \App\Helpers\Currency::main($product->price, true) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- END Top Products -->
                </div>

            </div>
        <!-- END Top Products and Latest Orders -->
    </div>
    <!-- END Page Content -->
@endsection

@push('js_after')

    <!-- Page JS Plugins -->
    <script src="{{ asset('js/plugins/chart.js/Chart.bundle.min.js') }}"></script>

    <script>
        $(() => {
            let this_year = sort('{{ $this_year }}');
            let last_year = sort('{{ $last_year }}');

            if (this_year.top > 20000) {
                this_year.step = 5000;
            }
            if (this_year.top < 20000 && this_year.top > 4000) {
                this_year.step = 1000;
            }
            if (this_year.top < 4000 && this_year.top > 1000) {
                this_year.step = 500;
            }

            console.log(this_year.names, this_year.values, this_year.step, this_year.top)
            console.log(last_year.names, last_year.values, last_year.step, last_year.top)

            // Set Global Chart.js configuration
            Chart.defaults.global.defaultFontColor              = '#818d96';
            Chart.defaults.scale.gridLines.color                = 'rgba(0,0,0,.04)';
            Chart.defaults.scale.gridLines.zeroLineColor        = 'rgba(0,0,0,.1)';
            Chart.defaults.scale.ticks.beginAtZero              = true;
            Chart.defaults.global.elements.line.borderWidth     = 2;
            Chart.defaults.global.elements.point.radius         = 5;
            Chart.defaults.global.elements.point.hoverRadius    = 7;
            Chart.defaults.global.tooltips.cornerRadius         = 3;
            Chart.defaults.global.legend.labels.boxWidth        = 12;

            // Get Chart Container
            let chartOverviewCon  = jQuery('.js-chartjs-overview');

            // Set Chart Variables
            let chartOverview, chartOverviewOptions, chartOverviewData;

            // Overview Chart Options
            chartOverviewOptions = {
                maintainAspectRatio: false,
                tension: .4,
                scales: {
                    yAxes: [{
                        ticks: {
                            suggestedMax: this_year.top + ( this_year.top * 0.1)
                        }
                    }]
                },
                tooltips: {
                    intersect: false,
                    callbacks: {
                        label: function(tooltipItems, data) {
                            return  tooltipItems.yLabel + '€';
                        }
                    }
                }
            };

            // Overview Chart Data
            chartOverviewData = {
                labels: this_year.names,
                datasets: [
                    {
                        label: 'Ova godina',
                        fill: true,
                        backgroundColor: 'rgba(59,101,190,0.75)',
                        borderColor: 'rgb(24, 50, 109)',
                        pointBackgroundColor: 'rgba(24, 50, 109, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(24, 50, 109, 1)',
                        data: this_year.values
                    },
                    {
                        label: 'Zadnja godina',
                        fill: true,
                        backgroundColor: 'rgba(108, 117, 125, .25)',
                        borderColor: 'rgba(108, 117, 125, .75)',
                        pointBackgroundColor: 'rgba(108, 117, 125, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(108, 117, 125, 1)',
                        data: last_year.values
                    }
                ]
            };

            // Init Overview Chart
            if (chartOverviewCon !== null) {
                chartOverview = new Chart(chartOverviewCon, {
                    type: 'line',
                    data: chartOverviewData,
                    options: chartOverviewOptions
                });
            }
        });


        function sort(data) {
            let data_data = JSON.parse(data.replace(/&quot;/g,'"'));
            let data_names = [];
            let data_values = [];
            let top = 0;
            let step_size = 100;

            for (let i = 0; i < data_data.length; i++) {
                data_names.push(data_data[i].title + '.');
                data_values.push(data_data[i].value);
            }

            for (let i = 0; i < data_values.length; i++) {
                if (data_values[i] > top) {
                    top = data_values[i];
                }
            }

            return {
                values: data_values,
                names: data_names,
                top: top,
                step: step_size
            };
        }
    </script>

@endpush

