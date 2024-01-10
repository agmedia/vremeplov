<div class="article pb-1" >

    <div class="card product-card d-flex align-items-stretch shadow pb-1">
        @if ($product->main_price > $product->main_special)
            <span class="badge rounded-pill bg-primary mt-1 ms-1 badge-shadow">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($product->price, $product->special())), 0) }}%</span>


        @endif


        <a class="card-img-top d-block overflow-hidden" href="{{ url($product->url) }}">
            <img  src="{{ $product->thumb }}" width="350" height="300" alt="{{ $product->name }}">
        </a>
        <div class="card-body pt-2" style="min-height: 136px;">

            <h3 class="product-title pt-2 fs-sm"><a href="{{ url($product->url) }}">{{ $product->name }}</a></h3>

            @if ($product->main_price > $product->main_special)
                <div class="product-price"><small><span class="text-muted">NC 30 dana: {{ $product->main_price_text }}  @if($product->secondary_price_text) {{ $product->secondary_price_text }} @endif</span></small></div>
                <div class="product-price"><span class="text-primary">{{ $product->main_special_text }}  @if($product->secondary_special_text) <small class="text-muted">{{ $product->secondary_special_text }}</small> @endif</span></div>
            @else
                <div class="product-price"><span class="text-primary">{{ $product->main_price_text }}  @if($product->secondary_price_text) <small class="text-muted">{{ $product->secondary_price_text }} </small>@endif</span></div>
            @endif


        </div>

        <div class="product-floating-btn">
            <add-to-cart-btn-simple id="{{ $product->id }}" available="{{ $product->quantity }}"></add-to-cart-btn-simple>
        </div>
    </div>

</div>

