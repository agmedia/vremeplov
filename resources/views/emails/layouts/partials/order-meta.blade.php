<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 5px;background-color:#2d2224;border-radius:9px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
    <tr>
        <td class="mobile-block" width="34%" valign="top" style="width:34%;padding:17px 10px 17px 20px;">
            <div style="color:#dcc695;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Narudžba</div>
            <div style="color:#ffffff;font-family:Georgia,'Times New Roman',serif;font-size:21px;line-height:27px;">#{{ $order->id }}</div>
        </td>
        <td class="mobile-block" width="33%" valign="top" style="width:33%;padding:17px 10px;">
            <div style="color:#dcc695;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Datum</div>
            <div style="color:#ffffff;font-size:13px;font-weight:bold;line-height:21px;">{{ optional($order->created_at)->format('d.m.Y.') ?: now()->format('d.m.Y.') }}</div>
        </td>
        <td class="mobile-block mobile-last" width="33%" valign="top" style="width:33%;padding:17px 20px 17px 10px;">
            <div style="color:#dcc695;font-size:10px;font-weight:bold;letter-spacing:.9px;line-height:15px;text-transform:uppercase;">Status</div>
            <div style="color:#ffffff;font-size:13px;font-weight:bold;line-height:21px;">{{ $statusLabel ?? 'Zaprimljeno' }}</div>
        </td>
    </tr>
</table>
