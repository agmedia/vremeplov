<h3>Podaci o kupcu:</h3>
<table cellspacing="0" cellpadding="0" border="0" width="100%">
    <tr>
        <td style="width: 40%">{{ __('Ime i prezime') }}:</td>
        <td style="width: 60%"><b>{{ $order->payment_fname . ' ' . $order->payment_lname }}</b></td>
    </tr>
    <tr>
        <td>{{ __('Adresa') }}:</td>
        <td><b>{{ $order->payment_address }}</b></td>
    </tr>
    <tr>
        <td>{{ __('Grad') }}:</td>
        <td><b>{{ $order->payment_zip . ' ' . $order->payment_city }}</b></td>
    </tr>
    <tr>
        <td>{{ __('Email adresa') }}:</td>
        <td><b style="color: #2D2224;">{{ $order->payment_email }}</b></td>
    </tr>
    <tr>
        <td>{{ __('Telefon') }}:</td>
        <td><b>{{ ($order->payment_phone) ? $order->payment_phone : '' }}</b></td>
    </tr>
    @if( ! empty($order->company) || ! empty($order->oib))
        <tr><td></td><td></td></tr>
        <tr>
            <td>{{ __('Tvrtka') }}:</td>
            <td><b>{{ ($order->company) ? $order->company : '' }}</b></td>
        </tr>
        <tr>
            <td>{{ __('OIB') }}:</td>
            <td><b>{{ ($order->oib) ? $order->oib : '' }}</b></td>
        </tr>
    @endif
</table>
