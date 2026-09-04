<div style="margin:25px 0 10px;color:#a17436;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.2px;line-height:16px;text-transform:uppercase;">Artikli i iznosi</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #e1d7c8;border-radius:9px;background-color:#ffffff;">
    @forelse ($order->products as $product)
        @php
            $catalogProduct = $product->relationLoaded('product') ? $product->getRelation('product') : $product->product;
            $productName = $product->name ?: optional($catalogProduct)->name ?: 'Artikl iz narudžbe';
            $rawProductImage = $catalogProduct ? trim((string) $catalogProduct->getRawOriginal('image')) : '';
            $productImage = null;
            if ($rawProductImage !== '') {
                if (preg_match('#^https?://#i', $rawProductImage)) {
                    $productImage = $rawProductImage;
                } else {
                    $imageHost = rtrim((string) (config('settings.images_domain') ?: config('app.url')), '/');
                    $imagePath = preg_replace('/\.jpg$/i', '.webp', ltrim($rawProductImage, '/'));
                    $productImage = $imageHost . '/' . $imagePath;
                }
            }
        @endphp
        <tr>
            <td valign="middle" style="padding:14px 10px 14px 18px;{{ ! $loop->last ? 'border-bottom:1px solid #eee7dd;' : '' }}">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        @if ($productImage)
                            <td width="48" valign="middle" style="width:48px;padding-right:10px;">
                                <img src="{{ $productImage }}" width="40" alt="" style="display:block;width:40px;max-width:40px;height:auto;border-radius:3px;">
                            </td>
                        @endif
                        <td valign="middle">
                            <div style="color:#2d2224;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;line-height:19px;">{{ $productName }}</div>
                            <div style="padding-top:2px;color:#887b70;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:17px;">{{ $product->quantity }} × {{ number_format((float) $product->price, 2, ',', '.') }} €@if ($catalogProduct && $catalogProduct->isbn) &nbsp;·&nbsp; ISBN {{ $catalogProduct->isbn }}@endif</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td width="105" valign="middle" align="right" style="width:105px;padding:14px 18px 14px 8px;{{ ! $loop->last ? 'border-bottom:1px solid #eee7dd;' : '' }}color:#2d2224;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;line-height:20px;white-space:nowrap;">{{ number_format((float) $product->total, 2, ',', '.') }} €</td>
        </tr>
    @empty
        <tr><td colspan="2" style="padding:16px 18px;color:#756a60;font-family:Arial,Helvetica,sans-serif;font-size:13px;">Stavke narudžbe nisu dostupne za prikaz.</td></tr>
    @endforelse
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;font-family:Arial,Helvetica,sans-serif;">
    @foreach ($order->totals as $total)
        @php($isGrandTotal = $total->code === 'total')
        <tr>
            <td align="right" style="padding:{{ $isGrandTotal ? '11px' : '6px' }} 10px {{ $loop->last ? '4px' : '6px' }} 0;{{ $isGrandTotal ? 'border-top:2px solid #c7a361;' : '' }}color:{{ $isGrandTotal ? '#2d2224' : '#756a60' }};font-size:{{ $isGrandTotal ? '14px' : '12px' }};font-weight:{{ $isGrandTotal ? 'bold' : 'normal' }};line-height:19px;">{{ $total->title }}</td>
            <td width="115" align="right" style="width:115px;padding:{{ $isGrandTotal ? '11px' : '6px' }} 0 {{ $loop->last ? '4px' : '6px' }} 8px;{{ $isGrandTotal ? 'border-top:2px solid #c7a361;' : '' }}color:#2d2224;font-size:{{ $isGrandTotal ? '16px' : '12px' }};font-weight:{{ $isGrandTotal ? 'bold' : 'normal' }};line-height:19px;white-space:nowrap;">
                @if ($order->shipping_state !== 'Croatia' && $total->code === 'shipping')
                    Naknadno
                @else
                    {{ number_format((float) $total->value, 2, ',', '.') }} €
                @endif
            </td>
        </tr>
    @endforeach
</table>
<p style="margin:7px 0 0;color:#93877b;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:16px;text-align:right;">Sve cijene uključuju pripadajući PDV.</p>
