<form name="pay" class="w-100" action="{{ $data['action'] }}" method="POST">
    <input type="hidden" name="ShopID" value="{{ $data['shop_id'] }}">
    <input type="hidden" name="ShoppingCartID" value="{{ $data['order_id'] }}">
    <input type="hidden" name="TotalAmount" value="{{ $data['total'] }}">
    <input type="hidden" name="Signature" value="{{ $data['md5'] }}">

    <input type="hidden" name="CustomerFirstname" value="{{ $data['firstname'] }}">
    <input type="hidden" name="CustomerLastName" value="{{ $data['lastname'] }}">
    <input type="hidden" name="CustomerAddress" value="{{ $data['address'] }}">
    <input type="hidden" name="CustomerCity" value="{{ $data['city'] }}">
    <input type="hidden" name="CustomerCountry" value="{{ $data['country'] }}">
    <input type="hidden" name="CustomerZIP" value="{{ $data['postcode'] }}">
    <input type="hidden" name="CustomerPhone" value="{{ $data['phone'] }}">
    <input type="hidden" name="CustomerEmail" value="{{ $data['email'] }}">
    <input type="hidden" name="Lang" value="{{ $data['lang'] }}">
  {{--  <input type="hidden" name="PaymentPlan" value="{{ $data['plan'] }}">
    <input type="hidden" name="CreditCardName" value="{{ $data['cc_name'] }}">
    <input type="hidden" name="valuta" value="{{ $data['currency'] }}">
    <input type="hidden" name="tecaj" value="{{ $data['rate'] }}"> --}}
    <input type="hidden" name="ReturnErrorURL" value="{{ $data['cancel'] }}">
    <input type="hidden" name="ReturnURL" value="{{ $data['return'] }}">
    <input type="hidden" name="CancelURL" value="{{ $data['cancel'] }}">
    <input type="hidden" name="ReturnMethod" value="GET">
    <div class="d-flex mt-3">
        <div class="w-50 pe-3"><a class="btn btn-secondary d-block w-100" href="{{ route('naplata') }}"><i class="ci-arrow-left  me-1"></i><span class="d-none d-sm-inline">Povratak na plaćanje</span><span class="d-inline d-sm-none">Povratak</span></a></div>
        <div class="w-50 ps-2"><button class="btn btn-primary d-block w-100" type="submit"><span class="d-none d-sm-inline">Završite narudžbu</span><span class="d-inline d-sm-none">Završi kupnju</span><i class="ci-arrow-right ms-1"></i></button></div>
    </div>
    <div class="clearfix"></div>
</form>
