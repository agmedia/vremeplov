<!doctype html>
<html lang="hr" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>@yield('email_title', 'Antikvarijat Vremeplov')</title>
    <!--[if mso]>
    <style>body, table, td, a { font-family: Arial, sans-serif !important; }</style>
    <![endif]-->
    <style>
        html, body {
            width: 100% !important;
            min-width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #f2eee5;
        }

        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }

        table, td {
            mso-table-lspace: 0 !important;
            mso-table-rspace: 0 !important;
            border-collapse: collapse !important;
        }

        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        a {
            color: #76542f;
        }

        .mail-shell {
            width: 100%;
            max-width: 640px;
        }

        .mail-gutter {
            padding-left: 46px !important;
            padding-right: 46px !important;
        }

        .mail-content {
            color: #453b35;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 24px;
        }

        .mail-card {
            width: 100%;
            border: 1px solid #e1d7c8;
            border-radius: 9px;
            background-color: #fbf8f2;
        }

        .mail-title {
            margin: 0 0 15px;
            color: #2d2224;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 31px;
            font-weight: normal;
            line-height: 39px;
        }

        .mail-section-title {
            margin: 0 0 14px;
            color: #2d2224;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 20px;
            font-weight: normal;
            line-height: 26px;
        }

        .mail-eyebrow {
            margin: 0 0 11px;
            color: #a17436;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1.5px;
            line-height: 16px;
            text-transform: uppercase;
        }

        .mail-label {
            color: #89796a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: .9px;
            line-height: 15px;
            text-transform: uppercase;
        }

        .mail-value {
            color: #2d2224;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: bold;
            line-height: 21px;
        }

        .mail-button {
            display: inline-block;
            padding: 14px 25px;
            border: 1px solid #2d2224;
            border-radius: 6px;
            background-color: #2d2224;
            color: #ffffff !important;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: bold;
            line-height: 20px;
            text-align: center;
            text-decoration: none;
        }

        .ag-btn {
            display: inline-block;
            padding: 14px 25px;
            border: 1px solid #2d2224;
            border-radius: 6px;
            background-color: #2d2224;
            color: #ffffff !important;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: bold;
            line-height: 20px;
            text-align: center;
            text-decoration: none;
        }

        @media screen and (max-width: 520px) {
            .mail-shell {
                width: 100% !important;
                border-radius: 0 !important;
            }

            .mail-gutter {
                padding-left: 23px !important;
                padding-right: 23px !important;
            }

            .mail-title {
                font-size: 27px !important;
                line-height: 34px !important;
            }

            .mobile-block {
                display: block !important;
                box-sizing: border-box !important;
                width: 100% !important;
                padding-right: 0 !important;
                padding-bottom: 14px !important;
                text-align: left !important;
            }

            .mobile-last {
                padding-bottom: 0 !important;
            }

            .mail-button,
            .ag-btn {
                display: block !important;
                box-sizing: border-box !important;
                width: 100% !important;
            }
        }
    </style>
    @stack('css')
</head>
<body style="margin:0;padding:0;background-color:#f2eee5;color:#453b35;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
    @yield('preheader', 'Poruka iz Antikvarijata Vremeplov')
</div>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f2eee5" style="background-color:#f2eee5;">
    <tr>
        <td align="center" style="padding:30px 12px 40px;">
            <!--[if mso]><table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0"><tr><td><![endif]-->
            <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" class="mail-shell" style="width:100%;max-width:640px;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 30px rgba(45,34,36,.09);">
                <tr>
                    <td align="center" bgcolor="#2d2224" style="padding:25px 32px 21px;background-color:#2d2224;">
                        <a href="{{ route('index') }}" target="_blank" style="display:inline-block;text-decoration:none;">
                            <img src="{{ config('settings.images_domain') . 'media/img/vremeplov-logo.png' }}" width="205" alt="Antikvarijat Vremeplov" style="display:block;width:205px;max-width:100%;height:auto;">
                        </a>
                        <div style="margin-top:9px;color:#dcc695;font-family:Georgia,'Times New Roman',serif;font-size:11px;letter-spacing:1.5px;line-height:16px;text-transform:uppercase;">
                            Knjige i predmeti s pričom
                        </div>
                    </td>
                </tr>
                <tr>
                    <td height="5" style="height:5px;background-color:#c7a361;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                    <td class="mail-gutter mail-content" style="padding:42px 46px 40px;background-color:#ffffff;color:#453b35;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td class="mail-gutter" align="center" style="padding:25px 46px 27px;background-color:#f8f4ec;border-top:1px solid #e4dacb;color:#756a60;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:19px;">
                        <strong style="color:#2d2224;font-family:Georgia,'Times New Roman',serif;font-size:15px;font-weight:normal;">Antikvarijat Vremeplov</strong><br>
                        Zvonimirova 24, 10000 Zagreb &nbsp;·&nbsp; 091 762 7441<br>
                        <a href="mailto:info@antiqueshop.hr" style="color:#76542f;">info@antiqueshop.hr</a>
                        <span style="padding:0 5px;color:#c7a361;">•</span>
                        <a href="{{ route('index') }}" style="color:#76542f;">Posjetite webshop</a>
                        <p style="margin:12px 0 0;color:#918579;font-size:11px;line-height:17px;">
                            <a href="{{ url('/info/uvjeti-kupnje') }}" style="color:#766455;">Uvjeti kupnje</a>
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            <a href="{{ url('/info/izjava-o-privatnosti') }}" style="color:#766455;">Privatnost</a><br>
                            © {{ now()->year }} Vremeplov razglednica d.o.o. Sva prava pridržana.
                        </p>
                    </td>
                </tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td>
    </tr>
</table>
</body>
</html>
