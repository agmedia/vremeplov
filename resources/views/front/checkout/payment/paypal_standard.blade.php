@if ($data['testmode'])
    <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> Test mode plaćanja je uključen...</div>
@endif
<form action="{{ $data['action'] }}" method="post">
    <input type="hidden" name="cmd" value="_cart" />
    <input type="hidden" name="upload" value="1" />
    <input type="hidden" name="business" value="{{ $data['business'] }}" />

    @foreach ($data['products'] as $key => $product)
        <input type="hidden" name="item_name_{{ $key + 1 }}" value="{{ $product['name'] }}" />
        <input type="hidden" name="item_number_{{ $key + 1 }}" value="{{ $product['model'] }}" />
        <input type="hidden" name="amount_{{ $key + 1 }}" value="{{ $product['price'] }}" />
        <input type="hidden" name="quantity_{{ $key + 1 }}" value="{{ $product['quantity'] }}" />
    @endforeach

    @if ($data['discount_amount_cart'])
        <input type="hidden" name="discount_amount_cart" value="{{ $data['discount_amount_cart'] }}" />
    @endif

    <input type="hidden" name="currency_code" value="{{ $data['currency'] }}" />
    <input type="hidden" name="first_name" value="{{ $data['firstname'] }}" />
    <input type="hidden" name="last_name" value="{{ $data['lastname'] }}" />
    <input type="hidden" name="address1" value="{{ $data['address'] }}" />
    <input type="hidden" name="city" value="{{ $data['city'] }}" />
    <input type="hidden" name="zip" value="{{ $data['postcode'] }}" />
    <input type="hidden" name="country" value="{{ $data['country'] }}" />
    <input type="hidden" name="address_override" value="0" />
    <input type="hidden" name="email" value="{{ $data['email'] }}" />
    <input type="hidden" name="invoice" value="{{ $data['invoice'] }}" />
    <input type="hidden" name="lc" value="{{ $data['lc'] }}" />
    <input type="hidden" name="rm" value="2" />
    <input type="hidden" name="no_note" value="1" />
    <input type="hidden" name="no_shipping" value="1" />
    <input type="hidden" name="charset" value="UTF-8" />
    <input type="hidden" name="return" value="{{ $data['return'] }}" />
    <input type="hidden" name="notify_url" value="{{ $data['notify_url'] }}" />
    <input type="hidden" name="cancel_return" value="{{ $data['cancel_return'] }}" />
    <input type="hidden" name="paymentaction" value="sale" />
    <input type="hidden" name="custom" value="{{ $data['order_id'] }}" />
    <div class="buttons">
        <div class="pull-right">
            <input type="submit" value="Potvrdi Plaćanje" class="btn btn-primary" />
        </div>
    </div>
</form>
