<?php

namespace App\Http\Controllers\Back\Settings;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\Shipping\BoxNowSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BoxNowSettingsController extends Controller
{
    public function update(Request $request, BoxNowSettingsService $settings)
    {
        $validator = Validator::make($request->all(), [
            'base_url' => ['required', 'string', 'max:500', 'starts_with:https://'],
            'client_id' => ['required', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:1000'],
            'api_partner_id' => ['nullable', 'string', 'max:191'],
            'widget_partner_id' => ['required', 'integer', 'min:1'],
            'order_prefix' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'warehouse_location_id' => ['required', 'string', 'max:191'],
            'origin_name' => ['required', 'string', 'max:191'],
            'origin_email' => ['required', 'email', 'max:191'],
            'origin_phone' => ['required', 'string', 'max:50'],
            'tracking_url' => ['required', 'string', 'max:500', 'starts_with:https://'],
            'allow_return' => ['required', 'boolean'],
            'cod_enabled' => ['required', 'boolean'],
            'email_label_on_create' => ['required', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $settings) {
            if (trim((string) $request->input('client_secret')) === ''
                && $settings->get()['client_secret'] === '') {
                $validator->errors()->add('client_secret', 'Upišite Box Now Client Secret.');
            }

            if (! str_contains((string) $request->input('tracking_url'), '{parcel}')) {
                $validator->errors()->add('tracking_url', 'Tracking URL mora sadržavati oznaku {parcel}.');
            }

            if (! $settings->isAllowedApiUrl((string) $request->input('base_url'))) {
                $validator->errors()->add(
                    'base_url',
                    'Upišite službeni BOX NOW HTTPS API URL bez dodatnih parametara, s putanjom /api/v1.'
                );
            }
        });

        if ($validator->fails()) {
            return redirect()
                ->route('shippings')
                ->withErrors($validator)
                ->withInput($request->except('client_secret'));
        }

        $validated = $validator->validated();
        $validated['allow_return'] = $request->boolean('allow_return');
        $validated['cod_enabled'] = $request->boolean('cod_enabled');
        $validated['email_label_on_create'] = $request->boolean('email_label_on_create');

        if ($settings->save($validated)) {
            // Ukloni i stari cache ključ koji je prije mogao sadržavati cijeli
            // shipping namespace te osvježi javne liste nakon promjene opcija.
            Helper::resolveCache('set')->forget('cart.public.v2');
            Helper::resolveCache('set')->forget('cart');

            return redirect()
                ->route('shippings')
                ->with('success', 'Box Now API postavke su spremljene.');
        }

        return redirect()
            ->route('shippings')
            ->withInput($request->except('client_secret'))
            ->with('error', 'Box Now API postavke nije moguće spremiti.');
    }
}
