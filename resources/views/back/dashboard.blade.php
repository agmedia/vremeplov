@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light admin-page-hero dashboard-hero">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <span class="admin-page-kicker"><i class="fa fa-book-open mr-1" aria-hidden="true"></i>Antikvarijat Vremeplov</span>
                    <h1>Nadzorna ploča</h1>
                    <p class="admin-page-subtitle">Pregled prodaje, narudžbi i zadnjih aktivnosti.</p>
                </div>
                <a class="btn btn-primary mt-3 mt-sm-0" href="{{ route('orders') }}">
                    <i class="fa fa-chart-line mr-2" aria-hidden="true"></i>Sve narudžbe
                    <i class="fa fa-arrow-right ml-2" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="content dashboard-content">
        @include('back.layouts.partials.session')

        <div class="dashboard-section-heading">
            <div>
                <span class="admin-section-eyebrow">Brzi pregled</span>
                <h2>Prodaja po razdobljima</h2>
            </div>
            <span class="dashboard-section-note">Promet uključuje statuse definirane za prodajnu statistiku.</span>
        </div>

        <div class="dashboard-kpi-grid">
            @foreach([
                ['key' => 'today', 'label' => 'Danas', 'icon' => 'fa-calendar-day'],
                ['key' => 'this_month', 'label' => 'Ovaj mjesec', 'icon' => 'fa-chart-line'],
                ['key' => 'this_year', 'label' => 'Ova godina', 'icon' => 'fa-calendar-check'],
            ] as $card)
                <a class="block block-rounded block-link-shadow dashboard-kpi-card" href="{{ route('orders') }}">
                    <div class="block-content">
                        <div class="dashboard-kpi-head">
                            <span class="dashboard-kpi-icon"><i class="fa {{ $card['icon'] }}" aria-hidden="true"></i></span>
                            <strong>{{ $card['label'] }}</strong>
                            <i class="fa fa-chevron-right text-muted" aria-hidden="true"></i>
                        </div>
                        <span class="dashboard-kpi-label">Promet</span>
                        <div class="dashboard-kpi-value">{{ \App\Helpers\Currency::main($data[$card['key'] . '_total'], true) ?: '0,00 €' }}</div>
                        <div class="dashboard-kpi-meta">
                            <span><strong>{{ number_format($data[$card['key']], 0, ',', '.') }}</strong> narudžbi</span>
                            <span><strong>{{ number_format($data[$card['key'] . '_items_average'], 2, ',', '.') }}</strong> artikala / nar.</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="block block-rounded dashboard-sales-block">
            <div class="block-header block-header-default dashboard-sales-header">
                <div>
                    <h2 class="block-title mb-1">Statistika prometa</h2>
                    <p class="text-muted font-size-sm mb-0">Iznosi, narudžbe i prodani artikli prema datumu nastanka.</p>
                </div>
                <div class="dashboard-sales-filters">
                    <div>
                        <label class="admin-filter-label" for="chart-year">Godina</label>
                        <select id="chart-year" class="form-control">
                            @foreach($yearsWithOrders as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="admin-filter-label" for="chart-month">Mjesec</label>
                        <select id="chart-month" class="form-control">
                            @foreach([1 => 'Siječanj', 2 => 'Veljača', 3 => 'Ožujak', 4 => 'Travanj', 5 => 'Svibanj', 6 => 'Lipanj', 7 => 'Srpanj', 8 => 'Kolovoz', 9 => 'Rujan', 10 => 'Listopad', 11 => 'Studeni', 12 => 'Prosinac'] as $month => $name)
                                <option value="{{ $month }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="block-content dashboard-sales-content">
                <div id="dashboard-sales-error" class="alert alert-danger d-none" role="alert">Statistiku trenutačno nije moguće učitati. Pokušajte ponovno.</div>
                <ul class="nav nav-tabs dashboard-sales-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#dashboard-month" role="tab"><i class="fa fa-calendar-day mr-2"></i>Mjesečni pregled</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#dashboard-year" role="tab"><i class="fa fa-calendar-alt mr-2"></i>Godišnji pregled</a></li>
                </ul>

                <div class="tab-content">
                    @foreach(['month' => 'Mjesečni', 'year' => 'Godišnji'] as $period => $title)
                        <div class="tab-pane fade {{ $period === 'month' ? 'show active' : '' }}" id="dashboard-{{ $period }}" role="tabpanel">
                            <div class="dashboard-summary-grid">
                                <div class="dashboard-summary-card"><span>{{ $title }} promet</span><strong id="{{ $period }}-total">—</strong><small>Ukupna vrijednost</small></div>
                                <div class="dashboard-summary-card"><span>Narudžbe</span><strong id="{{ $period }}-orders">—</strong><small>U prometnoj statistici</small></div>
                                <div class="dashboard-summary-card"><span>Prodani artikli</span><strong id="{{ $period }}-items">—</strong><small id="{{ $period }}-items-average">— po narudžbi</small></div>
                                <div class="dashboard-summary-card"><span>Prosječna narudžba</span><strong id="{{ $period }}-average">—</strong><small>Po narudžbi</small></div>
                            </div>
                            <div class="dashboard-chart-wrap"><canvas id="{{ $period }}-chart" aria-label="{{ $title }} graf prometa i narudžbi" role="img"></canvas></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="dashboard-operational-grid">
            <a class="dashboard-operational-card" href="{{ route('orders') }}"><i class="fa fa-clock"></i><span><strong>{{ number_format($data['proccess'], 0, ',', '.') }}</strong>Narudžbi u obradi</span></a>
            <a class="dashboard-operational-card" href="{{ route('reviews') }}"><i class="fa fa-comments"></i><span><strong>{{ number_format($data['comments'], 0, ',', '.') }}</strong>Komentara za pregled</span></a>
            <a class="dashboard-operational-card" href="{{ route('products', ['status' => 'kolicina']) }}"><i class="fa fa-box-open"></i><span><strong>{{ number_format($data['zeroproducts'], 0, ',', '.') }}</strong>Rasprodanih artikala</span></a>
        </div>

        <div class="row dashboard-list-row">
            <div class="col-xl-6">
                <div class="block block-rounded dashboard-list-block">
                    <div class="block-header block-header-default"><h2 class="block-title">Zadnje narudžbe</h2><a class="btn btn-sm btn-light" href="{{ route('orders') }}">Sve <i class="fa fa-arrow-right ml-1"></i></a></div>
                    <div class="block-content">
                        <table class="table table-borderless table-vcenter dashboard-list-table dashboard-orders-list-table"><tbody>
                        @forelse($orders as $order)
                            @php($status = $order->status)
                            <tr>
                                <td class="dashboard-list-id"><a href="{{ route('orders.show', ['order' => $order]) }}">#{{ $order->id }}</a></td>
                                <td><a class="dashboard-list-link" href="{{ route('orders.show', ['order' => $order]) }}">{{ trim($order->payment_fname . ' ' . $order->payment_lname) ?: 'Nepoznati kupac' }}</a><small class="d-block text-muted">{{ optional($order->created_at)->format('d.m.Y. H:i') }} · {{ $order->products_count }} artikala</small></td>
                                <td class="text-right"><strong class="d-block">{{ \App\Helpers\Currency::main($order->total, true) }}</strong><span class="badge badge-pill badge-{{ $status->color ?? 'secondary' }} dashboard-order-status" title="{{ $status->title ?? 'Nepoznat status' }}">{{ $status->title ?? 'Nepoznat status' }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="admin-empty-state"><i class="fa fa-receipt"></i>Nema narudžbi za prikaz.</td></tr>
                        @endforelse
                        </tbody></table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="block block-rounded dashboard-list-block">
                    <div class="block-header block-header-default"><h2 class="block-title">Zadnje prodani artikli</h2><a class="btn btn-sm btn-light" href="{{ route('products') }}">Sve <i class="fa fa-arrow-right ml-1"></i></a></div>
                    <div class="block-content">
                        <table class="table table-borderless table-vcenter dashboard-list-table"><tbody>
                        @forelse($products as $product)
                            <tr>
                                <td class="dashboard-list-id"><a href="{{ route('products.edit', ['product' => $product->product_id]) }}">#{{ $product->product_id }}</a></td>
                                <td><a class="dashboard-list-link" href="{{ route('products.edit', ['product' => $product->product_id]) }}">{{ $product->name }}</a><small class="d-block text-muted">{{ optional(optional($product->product)->author)->title ?: 'Autor nije naveden' }} · {{ optional($product->created_at)->format('d.m.Y. H:i') }}</small></td>
                                <td class="text-right"><strong>{{ \App\Helpers\Currency::main($product->price, true) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td class="admin-empty-state"><i class="fa fa-book"></i>Nema prodanih artikala za prikaz.</td></tr>
                        @endforelse
                        </tbody></table>
                    </div>
                </div>
            </div>
        </div>

        @if(auth()->user()->can('*'))
            <div class="block block-rounded block-mode-hidden mt-3">
                <div class="block-header block-header-default"><h3 class="block-title"><i class="fa fa-tools mr-2"></i>Alati održavanja</h3><div class="block-options"><button type="button" class="btn-block-option" data-toggle="block-option" data-action="content_toggle" aria-label="Prikaži alate"></button></div></div>
                <div class="block-content">
                    <a href="{{ route('import.products') }}" class="btn btn-light mb-3 mr-2">Import artikala</a>
                    <a href="{{ route('products.duplicates') }}" class="btn btn-light mb-3 mr-2">Duplikati artikala</a>
                    <a href="{{ route('import.customers') }}" class="btn btn-light mb-3 mr-2">Import kupaca</a>
                    <a href="{{ route('mailing.test') }}" class="btn btn-light mb-3 mr-2">Test e-maila</a>
                    <a href="{{ route('slugs.revision') }}" class="btn btn-light mb-3">Revizija URL-ova</a>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('css_after')
    <style>
        .dashboard-kpi-meta { display: flex; gap: .65rem; justify-content: space-between; padding-top: .38rem; border-top: 1px solid #e4ddd3; line-height: 1.25; }
        .dashboard-kpi-meta > span { min-width: 0; }
        .dashboard-kpi-meta > span:last-child { text-align: right; }
        .dashboard-sales-header { align-items: flex-end; }
        .dashboard-sales-header .text-muted { color: #5f5a56 !important; font-weight: 500; }
        .dashboard-sales-filters { display: flex; gap: .65rem; }
        .dashboard-sales-filters > div { min-width: 10rem; }
        .dashboard-sales-content { padding-top: .7rem; }
        .dashboard-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .55rem; margin: .55rem 0; }
        .dashboard-summary-card { padding: .65rem .75rem; border: 1px solid var(--admin-line); border-radius: var(--admin-radius-sm); background: #fffdf9; }
        .dashboard-summary-card span { display: block; color: #625d59; font-size: .7rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .dashboard-summary-card strong { display: block; margin: .24rem 0 .08rem; color: var(--admin-brown-dark); font-size: 1.35rem; line-height: 1.2; }
        .dashboard-summary-card small { color: #5f5a56; font-weight: 500; }
        .dashboard-operational-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; margin: .75rem 0; }
        .dashboard-operational-card { display: flex; gap: .7rem; align-items: center; padding: .68rem .82rem; border: 1px solid var(--admin-line); border-radius: var(--admin-radius); color: #494441 !important; background: #fff; font-size: .84rem; font-weight: 600; line-height: 1.3; }
        .dashboard-operational-card > i { display: inline-flex; width: 2.35rem; height: 2.35rem; align-items: center; justify-content: center; border-radius: .5rem; color: var(--admin-brown); background: var(--admin-gold-soft); }
        .dashboard-operational-card strong { display: block; color: var(--admin-brown-dark); font-size: 1.15rem; }
        .dashboard-list-row { margin-top: .75rem; }
        .dashboard-list-row > [class*="col-"] { min-width: 0; }
        .dashboard-list-block { height: calc(100% - 1rem); min-width: 0; overflow: hidden; }
        .dashboard-list-table { width: 100%; table-layout: fixed; }
        .dashboard-list-table td { min-width: 0; padding: .62rem .55rem; }
        .dashboard-list-table td:first-child { width: 5.5rem; }
        .dashboard-list-table td:nth-child(2) { overflow: hidden; }
        .dashboard-list-table td:last-child { width: 6.3rem; overflow: hidden; white-space: nowrap; }
        .dashboard-orders-list-table td:last-child { width: 8.7rem; }
        .dashboard-list-link { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dashboard-list-table .text-muted { color: #625d59 !important; font-weight: 500; }
        .dashboard-list-id a { font-weight: 800; font-variant-numeric: tabular-nums; }
        .dashboard-orders-list-table .dashboard-list-id a { font-size: 1.08rem; }
        .dashboard-order-status { display: inline-block; overflow: hidden; max-width: 100%; margin-top: .18rem; text-overflow: ellipsis; vertical-align: middle; white-space: nowrap; }
        @media (max-width: 991.98px) { .dashboard-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dashboard-operational-grid { grid-template-columns: 1fr; } }
        @media (max-width: 575.98px) { .dashboard-sales-header, .dashboard-sales-filters { align-items: stretch; flex-direction: column; width: 100%; } .dashboard-summary-grid { grid-template-columns: 1fr; } .dashboard-kpi-meta { flex-wrap: wrap; } }
    </style>
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/chart.js/Chart.bundle.min.js') }}"></script>
    <script>
        (() => {
            const currency = new Intl.NumberFormat('hr-HR', { style: 'currency', currency: 'EUR' });
            const integer = new Intl.NumberFormat('hr-HR', { maximumFractionDigits: 0 });
            const decimal = new Intl.NumberFormat('hr-HR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            let monthChart;
            let yearChart;

            function updateSummary(prefix, summary) {
                document.getElementById(prefix + '-total').textContent = currency.format(Number(summary.total || 0));
                document.getElementById(prefix + '-orders').textContent = integer.format(Number(summary.orders || 0));
                document.getElementById(prefix + '-items').textContent = integer.format(Number(summary.items || 0));
                document.getElementById(prefix + '-items-average').textContent = decimal.format(Number(summary.average_items || 0)) + ' po narudžbi';
                document.getElementById(prefix + '-average').textContent = currency.format(Number(summary.average_order || 0));
            }

            function renderChart(canvasId, existing, labels, totals, orders) {
                if (existing) existing.destroy();
                return new Chart(document.getElementById(canvasId).getContext('2d'), {
                    type: 'line',
                    data: { labels, datasets: [
                        { label: 'Promet', data: totals, yAxisID: 'sales', borderColor: '#3a2926', backgroundColor: 'rgba(220,168,79,.2)', pointBackgroundColor: '#3a2926', fill: true, lineTension: 0 },
                        { label: 'Narudžbe', data: orders, yAxisID: 'orders', borderColor: '#dca84f', backgroundColor: '#dca84f', pointBackgroundColor: '#dca84f', fill: false, lineTension: 0 }
                    ]},
                    options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltips: { mode: 'index', intersect: false }, scales: { yAxes: [
                        { id: 'sales', position: 'left', ticks: { beginAtZero: true, callback: value => currency.format(value) } },
                        { id: 'orders', position: 'right', ticks: { beginAtZero: true, precision: 0 }, gridLines: { drawOnChartArea: false } }
                    ], xAxes: [{ gridLines: { display: false } }] } }
                });
            }

            function showError(show) { document.getElementById('dashboard-sales-error').classList.toggle('d-none', !show); }
            function loadMonth() {
                const year = Number($('#chart-year').val());
                const month = Number($('#chart-month').val());
                $.get('{{ route('dashboard.chart.month') }}', { year, month }).done(response => {
                    const rows = {}; (response.days || []).forEach(row => rows[Number(row.day)] = row);
                    const count = new Date(year, month, 0).getDate();
                    const labels = Array.from({ length: count }, (_, i) => (i + 1) + '.');
                    updateSummary('month', response.summary || {});
                    monthChart = renderChart('month-chart', monthChart, labels, labels.map((_, i) => Number((rows[i + 1] || {}).total || 0)), labels.map((_, i) => Number((rows[i + 1] || {}).orders || 0)));
                    showError(false);
                }).fail(() => showError(true));
            }
            function loadYear() {
                const labels = ['Sij', 'Velj', 'Ožu', 'Tra', 'Svi', 'Lip', 'Srp', 'Kol', 'Ruj', 'Lis', 'Stu', 'Pro'];
                $.get('{{ route('dashboard.chart.year') }}', { year: Number($('#chart-year').val()) }).done(response => {
                    const rows = {}; (response.months || []).forEach(row => rows[Number(row.month)] = row);
                    updateSummary('year', response.summary || {});
                    yearChart = renderChart('year-chart', yearChart, labels, labels.map((_, i) => Number((rows[i + 1] || {}).total || 0)), labels.map((_, i) => Number((rows[i + 1] || {}).orders || 0)));
                    showError(false);
                }).fail(() => showError(true));
            }

            const now = new Date();
            $('#chart-year').val(String(now.getFullYear()));
            $('#chart-month').val(String(now.getMonth() + 1));
            $('#chart-year').on('change', () => { loadMonth(); loadYear(); });
            $('#chart-month').on('change', loadMonth);
            $('a[href="#dashboard-year"]').on('shown.bs.tab', () => yearChart && yearChart.resize());
            loadMonth(); loadYear();
        })();
    </script>
@endpush
