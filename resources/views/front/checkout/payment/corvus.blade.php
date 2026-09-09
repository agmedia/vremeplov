<form name="pay" class="w-100" action="{{ $data['action'] }}" method="POST">
    <input type="hidden" name="amount" value="{{ $data['total'] }}">
    <input id="cart" name="cart" value="Web shop kupnja {{ $data['order_id'] }}" type="hidden"/>
    <input id="currency" name="currency" value="{{ $data['currency'] }}" type="hidden"/>
    <input id="language" name="language" value="{{ $data['lang'] }}" type="hidden"/>
    <input type="hidden" name="order_number" value="{{ $data['order_id'] }}">
    <input id="require_complete" name="require_complete" value="false" hidden="true"/>
    <input type="hidden" name="store_id" value="{{ $data['merchant'] }}">
    <input type="hidden" name="signature" value="{{ $data['md5'] }}">
    <input type="hidden" name="cardholder_name" value="{{ $data['firstname'] }}">
    <input type="hidden" name="cardholder_surname" value="{{ $data['lastname'] }}">
    <input type="hidden" name="cardholder_phone" value="{{ $data['telephone'] }}">
    <input type="hidden" name="cardholder_email" value="{{ $data['email'] }}">
    <input type="hidden" name="payment_all" value="{{ $data['number_of_installments'] }}">
    <input type="hidden" name="version" value="1.3">

    <div class="d-flex mt-3">
        <div class="w-50 pe-3"><a class="btn btn-secondary d-block w-100" href="{{ route('naplata') }}"><i class="fa-regular fa-arrow-left mt-sm-0 me-1"></i><span class="d-none d-sm-inline">Povratak na plaćanje</span><span class="d-inline d-sm-none">Povratak</span></a></div>
        <div class="w-50 ps-2"><button class="btn btn-primary d-block w-100" type="submit"><span class="d-none d-sm-inline">Završite narudžbu</span><span class="d-inline d-sm-none">Završi kupnju</span><i class="fa-regular fa-arrow-right mt-sm-0 ms-1"></i></button></div>
    </div>
    <div class="clearfix"></div>
</form>
