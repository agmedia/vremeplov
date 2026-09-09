@php
    $analyticsItem = \App\Models\TagManager::getGoogleProductDataLayer($product, false);
    $cardCategory = $product->relationLoaded('categories')
        ? $product->categories->first(fn ($category) => (int) $category->parent_id === 0)
        : null;
    $reviewCount = (int) ($product->reviews_count ?? 0);
    $reviewAverage = round((float) ($product->reviews_avg_stars ?? 0), 1);
    $filledStars = (int) round($reviewAverage);
@endphp
<div class="article pb-1" data-analytics-item='@json($analyticsItem)'>

    <article class="card product-card product-card--refined d-flex align-items-stretch">
        @if ($product->main_price > $product->main_special)
            <span class="badge rounded-pill bg-primary badge-shadow product-card__badge">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($product->price, $product->special())), 0) }}%</span>
        @endif

        <a class="card-img-top product-card__media d-block overflow-hidden" href="{{ url($product->url) }}">
            <img src="{{ $product->thumb }}" loading="lazy" width="350" height="300" alt="{{ $product->card_name }}">
        </a>
        <div class="card-body product-card__body">
            @if ($cardCategory)
                <a class="product-card__category" href="{{ $cardCategory->url() }}">{{ $cardCategory->title }}</a>
            @endif

            @if ($reviewCount > 0)
                <div class="product-card__rating" aria-label="Ocjena {{ number_format($reviewAverage, 1, ',', '.') }} od 5 na temelju {{ $reviewCount }} recenzija">
                    <span aria-hidden="true">
                        @for ($star = 1; $star <= 5; $star++)
                            <i class="fa-{{ $star <= $filledStars ? 'solid' : 'regular' }} fa-star"></i>
                        @endfor
                    </span>
                    <small>({{ $reviewCount }})</small>
                </div>
            @endif

            <h3 class="product-title product-card__title"><a href="{{ url($product->url) }}">{{ $product->card_name }}</a></h3>

            @if ($product->main_price > $product->main_special)
                <div class="product-price product-card__previous-price"><small>NC 30 dana: {{ $product->main_price_text }} @if($product->secondary_price_text) {{ $product->secondary_price_text }} @endif</small></div>
                <div class="product-price product-card__price"><span>{{ $product->main_special_text }} @if($product->secondary_special_text) <small>{{ $product->secondary_special_text }}</small> @endif</span></div>
            @else
                <div class="product-price product-card__price"><span>{{ $product->main_price_text }} @if($product->secondary_price_text) <small>{{ $product->secondary_price_text }}</small>@endif</span></div>
            @endif
        </div>

        <div class="product-floating-btn product-card__action">
            <add-to-cart-btn-simple id="{{ $product->id }}" available="{{ $product->quantity }}"></add-to-cart-btn-simple>
        </div>
    </article>

</div>
