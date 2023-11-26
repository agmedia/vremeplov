@extends('emails.layouts.base')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset">{!! __('Pozdrav Akademska Knjiga,') !!}</td>
        </tr>

        <tr>
            <td class="ag-mail-tableset">
                <h3>{{ 'Izvještaj za datum ' . now()->subDay()->format('m.d.Y.') }}</h3>

                <p style="font-size:12px"> Imate dodatak u privitku ili gumb kojim možete učitati excel datoteku.</p>
                <br><br>

                Lijep pozdrav,<br>Plava Krava
            </td>
        </tr>

        <tr>
            <td style="padding: 20px; font-family: sans-serif; font-size: 15px; line-height: 20px; color: #555555; text-align: center;">
                <a href="{{ url('akmk_report.xlsx') }}" style="display: block; display: inline-block; width: 200px; min-height: 20px; padding: 10px; background-color: #a50000; border-radius: 3px; color: #ffffff; font-size: 15px; line-height: 25px; text-align: center; text-decoration: none; -webkit-text-size-adjust: none;">
                    Idi na stranicu
                </a>
            </td>
        </tr>

    </table>
@endsection
