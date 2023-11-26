@extends('front.layouts.app')

@section('content')

    <!-- Order Details Modal-->
    @foreach ($orders as $order)
        <div class="modal fade" id="order-details{{ $order->id }}">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Broj narudžbe - {{ $order->id }}</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pb-0">
                        @foreach ($order->products as $product)
                            <div class="d-sm-flex justify-content-between mb-4 pb-3 pb-sm-2 border-bottom">
                                <div class="d-sm-flex text-center text-sm-start">
                                    <a class="d-inline-block flex-shrink-0 mx-auto" href="{{ url($product->real->url) }}" style="width: 10rem;">
                                        <img src="{{ $product->product->image ? asset($product->product->image) : asset('media/avatars/avatar0.jpg') }}" alt="{{ $product->name }}">
                                    </a>
                                    <div class="ps-sm-4 pt-2">
                                        <h3 class="product-title fs-base mb-2"><a href="{{ url($product->real->url) }}">{{ $product->name }}</a></h3>
                                        <div class="fs-lg text-accent pt-2">{{ number_format($product->price, 2, ',', '.') }}</div>
                                    </div>
                                </div>
                                <div class="pt-2 ps-sm-3 mx-auto mx-sm-0 text-center">
                                    <div class="text-muted mb-2">Količina:</div>{{ $product->quantity }}
                                </div>
                                <div class="pt-2 ps-sm-3 mx-auto mx-sm-0 text-center">
                                    <div class="text-muted mb-2">Ukupno</div>{{ number_format($product->total, 2, ',', '.') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <!-- Footer-->
                    <div class="modal-footer flex-wrap justify-content-between bg-secondary fs-md">
                        @foreach ($order->totals as $total)
                            <div class="px-2 py-1"><span class="text-muted">{{ $total->title }}:&nbsp;</span><span>{{ number_format($total->value, 2, ',', '.') }}</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @include('front.customer.layouts.header')

    <div class="container pb-5 mb-2 mb-md-4">
        <div class="row">
        @include('front.customer.layouts.sidebar')

            <!-- Content  -->
            <section class="col-lg-8">
                <!-- Toolbar-->
                <div class="d-none d-lg-flex justify-content-between align-items-center pt-lg-3 pb-4 pb-lg-5 mb-lg-3">
                    <h6 class="fs-base text-dark mb-0">Pogledajte povijest svoji narudžbi:</h6><a class="btn btn-primary btn-sm" href="{{ route('logout') }}"><i class="ci-sign-out me-2"></i>Odjava</a>
                </div>
                <!-- Orders list-->
                <div class="table-responsive fs-md mb-4">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Broj narudžbe #</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th>Ukupno</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="py-3"><a class="nav-link-style fw-medium fs-sm" href="#order-details{{ $order->id }}" data-bs-toggle="modal">{{ $order->id }}</a></td>
                                <td class="py-3">{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y') }}</td>
                                <td class="py-3"><span class="badge bg-info m-0">{{ $order->status->title }}</span></td>
                                <td class="py-3">{{ number_format($order->total, 2, ',', '.') }} kn</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center font-size-sm" colspan="4">
                                    <label>Trenutno nemate narudžbi...</label>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $orders->links() }}

            </section>
        </div>
    </div>

@endsection
