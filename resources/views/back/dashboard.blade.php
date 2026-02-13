@extends('back.layouts.backend')

@section('content')
    <!-- Hero -->
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Nadzorna ploča</h1>
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
                            <a href="{{ route('import.products') }}" class="btn btn-hero-sm btn-rounded btn-hero-info mb-3 mr-3">Import Products</a>
                            <a href="{{ route('products.duplicates') }}" class="btn btn-hero-sm btn-rounded btn-hero-warning mb-3 mr-3">Delete Products Duplicates</a>
                            <a href="{{ route('import.customers') }}" class="btn btn-hero-sm btn-rounded btn-hero-info mb-3 mr-3">Import Customers</a>
                            <a href="{{ route('mailing.test') }}" class="btn btn-hero-sm btn-rounded btn-hero-info mb-3 mr-3">Mail Test</a>
                            <a href="{{ route('slugs.revision') }}" class="btn btn-hero-sm btn-rounded btn-hero-primary mb-3 mr-3">Slugs revision</a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

            <!-- Quick Overview -->
        <div class="row row-deck">
                 <div class="col-6 col-lg-3">
                     <a class="block block-rounded block-link-shadow text-center" href="{{ route('orders') }}">
                         <div class="block-content py-5">
                             <div class="font-size-h3 font-w600 text-warning mb-1">{{ $data['proccess'] }}</div>
                             <p class="font-w600 font-size-sm text-muted text-uppercase mb-0">Narudžbi u obradi</p>
                         </div>
                     </a>
                 </div>
                 <div class="col-6 col-lg-3">
                     <a class="block block-rounded block-link-shadow text-center" href="{{ route('orders') }}">
                         <div class="block-content py-5">
                             <div class="font-size-h3 font-w600 text-success mb-1">{{ $data['finished'] }}</div>
                             <p class="font-w600 font-size-sm text-muted text-uppercase mb-0">Dovršenih narudžbi</p>
                         </div>
                     </a>
                 </div>
                 <div class="col-6 col-lg-3">
                     <a class="block block-rounded block-link-shadow text-center" href="{{ route('orders') }}">
                         <div class="block-content py-5">
                             <div class="font-size-h3 text-success font-w600 mb-1">{{ $data['today'] }}</div>
                             <p class="font-w600 font-size-sm text-muted text-uppercase mb-0">Narudžbi danas</p>
                         </div>
                     </a>
                 </div>
                 <div class="col-6 col-lg-3">
                     <a class="block block-rounded block-link-shadow text-center" href="{{ route('orders') }}">
                         <div class="block-content py-5">
                             <div class="font-size-h3 text-success font-w600 mb-1">{{ $data['this_month'] }}</div>
                             <p class="font-w600 font-size-sm text-muted text-uppercase mb-0">Narudžbi ovaj mjesec</p>
                         </div>
                     </a>
                 </div>
             </div>
            <!-- END Quick Overview -->

            <!-- Sales Overview Block with Tabs -->
            <div class="block block-rounded mt-4">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Statistika prometa</h3>
                </div>
                <div class="block-content">
                    <!-- Tabs nav -->
                    <!-- Tabs nav -->
                    <ul class="nav nav-tabs" id="salesTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="sales-tab" data-toggle="tab" href="#tab-sales" role="tab"
                               aria-controls="tab-sales" aria-selected="true">Mjesečni pregled</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="monthly-tab" data-toggle="tab" href="#tab-monthly" role="tab"
                               aria-controls="tab-monthly" aria-selected="false">Godišnji pregled</a>
                        </li>
                    </ul>

                    <!-- Tabs content -->
                    <div class="tab-content mt-3" id="salesTabsContent">
                        <!-- Tab 1: Mjesečni pregled (po danima) -->
                        <div class="tab-pane fade show active" id="tab-sales" role="tabpanel" aria-labelledby="sales-tab">
                            <div class="row mb-4 mt-3">
                                <div class="col-md-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label>Godina</label>
                                            <select id="chart-year" class="form-control">
                                                @foreach($yearsWithOrders as $y)
                                                    <option value="{{ $y }}">{{ $y }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Mjesec</label>
                                            <select id="chart-month" class="form-control">
                                                @php
                                                    $hrMonths = [
                                                        1 => 'Siječanj',
                                                        2 => 'Veljača',
                                                        3 => 'Ožujak',
                                                        4 => 'Travanj',
                                                        5 => 'Svibanj',
                                                        6 => 'Lipanj',
                                                        7 => 'Srpanj',
                                                        8 => 'Kolovoz',
                                                        9 => 'Rujan',
                                                        10 => 'Listopad',
                                                        11 => 'Studeni',
                                                        12 => 'Prosinac',
                                                    ];
                                                @endphp

                                                @foreach($hrMonths as $m => $name)
                                                    <option value="{{ $m }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <!-- KPI boxevi -->
                                <div class="col-md-9">
                                    <div class="row">
                                        <!-- Mjesečni promet -->
                                        <div class="col-12 col-md-3 mb-3">
                                            <div class="block block-rounded text-center h-100">
                                                <div class="block-content py-3">
                                                    <div class="font-size-sm text-muted text-uppercase">Mjesečni promet</div>
                                                    <div id="kpi-month-total" class="font-size-h3 font-w600 mt-1">—</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Broj narudžbi u mjesecu (računa se iz JS serije) -->
                                        <div class="col-12 col-md-3 mb-3">
                                            <div class="block block-rounded text-center h-100">
                                                <div class="block-content py-3">
                                                    <div class="font-size-sm text-muted text-uppercase">Narudžbe mjesec</div>
                                                    <div id="kpi-month-orders" class="font-size-h3 font-w600 mt-1">—</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Narudžbi danas (iz backend varijable) -->
                                        <div class="col-12 col-md-3 mb-3">
                                            <div class="block block-rounded text-center h-100">
                                                <div class="block-content py-3">
                                                    <div class="font-size-sm text-muted text-uppercase">Narudžbe danas</div>
                                                    <div class="font-size-h3 font-w600 mt-1">
                                                        {{ $data['today'] }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Prosj. narudžba -->
                                        <div class="col-12 col-md-3 mb-3">
                                            <div class="block block-rounded text-center h-100">
                                                <div class="block-content py-3">
                                                    <div class="font-size-sm text-muted text-uppercase">Prosj. narudžba</div>
                                                    <div id="kpi-month-aov" class="font-size-h3 font-w600 mt-1">—</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                </div>
                            </div>

                            <div class="chart-container large">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>

                        <!-- Tab 2: Godišnji pregled -->
                        <div class="tab-pane fade" id="tab-monthly" role="tabpanel" aria-labelledby="monthly-tab">
                            <div class="chart-container large">
                                <canvas class="js-chartjs-overview"></canvas>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        <!-- Top Products and Latest Orders -->
        <div class="row mt-4">
            <div class="col-xl-6">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Zadnje prodani artikli</h3>
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
            </div>
            <div class="col-xl-6">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Zadnje narudžbe</h3>
                    </div>
                    <div class="block-content">
                        <table class="table table-borderless table-striped table-vcenter font-size-sm">
                            <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="font-w600 text-center" style="width: 5%;">
                                        <a href="{{ route('orders.edit', ['order' => $order]) }}">{{ $order->id }}</a>
                                    </td>
                                    <td class="d-none d-sm-table-cell">
                                        <a href="{{ route('orders.edit', ['order' => $order]) }}">{{ $order->payment_fname . ' ' . $order->payment_lname }}</a>
                                    </td>
                                    <td class="text-right" style="width: 5%;">
                                        <span class="badge badge-pill badge-{{ $order->status->color }}">{{ $order->status->title }}</span>
                                    </td>
                                    <td class="font-w600 text-right" style="width: 20%;">{{ \App\Helpers\Currency::main($order->total, true) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
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
                            <p class="text-muted mb-0">Novih komentara</p>
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
                            <p class="text-muted mb-0">Rasprodanih artikala</p>
                        </div>
                        <div class="item rounded-circle bg-body">
                            <i class="fas fa-exclamation-triangle fa-lg text-primary"></i>
                        </div>
                    </div>
                    <div class="block-content block-content-full block-content-sm bg-body-light fs-sm text-center">
                        <a class="fw-medium" href="{{ route('products') }}?status=kolicina">
                            Pogledaj artikle
                            <i class="fa fa-arrow-right ms-1 opacity-25"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css_after')
    <style>
        .chart-container { position: relative; width: 100%; }
        .chart-container.small { height: 200px; }
        .chart-container.medium { height: 280px; }
        .chart-container.large { height: 400px; }
    </style>
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/chart.js/Chart.bundle.min.js') }}"></script>

    <script>
        // =====================
        // PROMET I NARUDŽBE (po danima u mjesecu)
        // =====================
        let ctx = document.getElementById('salesChart').getContext('2d');
        let salesChart;

        // Formatiranje valuta i brojeva (hr-HR, EUR)
        const fmtCurrency = new Intl.NumberFormat('hr-HR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 });
        const fmtInt = new Intl.NumberFormat('hr-HR', { maximumFractionDigits: 0 });

        // Helper: složi pune serije za sve dane u mjesecu (prazno => 0)
        function prepareMonthSeries(year, month, raw) {
            const daysInMonth = new Date(year, month, 0).getDate(); // month: 1–12
            const map = {};
            (raw || []).forEach(d => {
                const day = parseInt(d.day, 10);
                map[day] = {
                    total: Number(d.total) || 0,
                    orders: Number(d.orders) || 0
                };
            });

            const labels = [];
            const values = [];
            const counts = [];

            for (let day = 1; day <= daysInMonth; day++) {
                labels.push(day + '.');
                const row = map[day] || { total: 0, orders: 0 };
                values.push(row.total);
                counts.push(row.orders);
            }
            return { labels, values, counts };
        }

        // Izračun KPI-ja i upis u DOM
        function updateMonthStats(total, orders) {
            const aov = orders > 0 ? (total / orders) : 0;
            document.getElementById('kpi-month-total').textContent  = fmtCurrency.format(total);
            document.getElementById('kpi-month-orders').textContent = fmtInt.format(orders);
            document.getElementById('kpi-month-aov').textContent    = fmtCurrency.format(aov);
        }

        function loadMonth(year, month) {
            $.get('{{ route('dashboard.chart.month') }}', { year, month }, function(data) {
                const series = prepareMonthSeries(Number(year), Number(month), data);

                // KPI: sumiraj promet i narudžbe za mjesec
                const monthTotal  = series.values.reduce((s, v) => s + Number(v || 0), 0);
                const monthOrders = series.counts.reduce((s, v) => s + Number(v || 0), 0);
                updateMonthStats(monthTotal, monthOrders);

                renderChart(series);
            });
        }

        function renderChart(series) {
            const labels = series.labels;
            const values = series.values;
            const counts = series.counts;

            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Promet (€)',
                            data: values,
                            borderColor: 'rgba(6, 101, 208, 1)',
                            backgroundColor: 'rgba(6, 101, 208, .3)',
                            fill: true,
                            yAxisID: 'y-axis-1',
                            tension: 0,
                            lineTension: 0
                        },
                        {
                            label: 'Broj narudžbi',
                            data: counts,
                            borderColor: 'rgba(0, 51, 153, 1)',
                            backgroundColor: 'rgba(0, 51, 153, .2)',
                            fill: false,
                            yAxisID: 'y-axis-2',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: 'rgba(0, 51, 153, 1)',
                            pointBorderColor: '#fff',
                            tension: 0,
                            lineTension: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(tooltipItem, data) {
                                const label = data.datasets[tooltipItem.datasetIndex].label || '';
                                const value = tooltipItem.yLabel;
                                if (label.includes('Promet')) {
                                    return label + ': ' + value + '€';
                                } else {
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        yAxes: [
                            {
                                id: 'y-axis-1',
                                position: 'left',
                                ticks: {
                                    beginAtZero: true,
                                    callback: value => value + '€'
                                }
                            },
                            {
                                id: 'y-axis-2',
                                position: 'right',
                                ticks: { beginAtZero: true },
                                gridLines: { drawOnChartArea: false }
                            }
                        ]
                    }
                }
            });
        }

        // Automatski refresh na promjenu selecta
        $('#chart-year, #chart-month').on('change', function() {
            const year  = $('#chart-year').val();
            const month = $('#chart-month').val();
            loadMonth(year, month);
        });

        // Inicijalni prikaz trenutnog mjeseca
        const now = new Date();
        $('#chart-year').val(now.getFullYear());
        $('#chart-month').val(now.getMonth() + 1);
        loadMonth(now.getFullYear(), now.getMonth() + 1);


        // =====================
        // MJESEČNI PREGLED (ova vs prošla godina)
        // =====================
        $(() => {
            let this_year = sort('{!! $this_year !!}');
            let last_year = sort('{!! $last_year !!}');

            let chartOverviewCon  = jQuery('.js-chartjs-overview');

            let chartOverviewOptions = {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    yAxes: [{
                        ticks: { suggestedMax: this_year.top }
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

            let chartOverviewData = {
                labels: this_year.names,
                datasets: [
                    {
                        label: 'Ova godina',
                        fill: false,
                        borderColor: 'rgba(6, 101, 208, 1)',
                        backgroundColor: 'rgba(6, 101, 208, .3)',
                        data: this_year.values,
                        tension: 0, lineTension: 0,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Prošla godina',
                        fill: false,
                        borderColor: 'rgba(0, 51, 153, 1)',
                        backgroundColor: 'rgba(0, 51, 153, .2)',
                        data: last_year.values,
                        tension: 0, lineTension: 0,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            };

            if (chartOverviewCon.length) {
                new Chart(chartOverviewCon, {
                    type: 'line',
                    data: chartOverviewData,
                    options: chartOverviewOptions
                });
            }
        });

        // helper za dekodiranje podataka iz PHP
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
                if (data_values[i] > top) top = data_values[i];
            }

            return { values: data_values, names: data_names, top: top, step: step_size };
        }
    </script>
@endpush
