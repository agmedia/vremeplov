<?php

namespace App\Http\Controllers\Back\Settings;

use App\Http\Controllers\Controller;
use App\Services\GoogleLoginSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoogleLoginSettingsController extends Controller
{
    public function edit(GoogleLoginSettingsService $settings)
    {
        $values = $settings->get();

        return view('back.settings.google-login.edit', [
            'settings' => [
                'enabled' => $values['enabled'],
                'client_id' => $values['client_id'],
                'has_client_secret' => $values['client_secret'] !== '',
            ],
            'callbackUrl' => route('google.login.callback'),
        ]);
    }

    public function update(Request $request, GoogleLoginSettingsService $settings)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:500'],
        ]);

        $validator->after(function ($validator) use ($request, $settings) {
            if (! $request->boolean('enabled')) {
                return;
            }

            if (! $settings->validClientId($request->input('client_id'))) {
                $validator->errors()->add('client_id', 'Upišite valjani Google OAuth Client ID.');
            }

            if (trim((string) $request->input('client_secret')) === ''
                && $settings->get()['client_secret'] === '') {
                $validator->errors()->add('client_secret', 'Upišite Google OAuth Client Secret.');
            }
        });

        $validated = $validator->validate();
        $validated['enabled'] = $request->boolean('enabled');

        if ($settings->save($validated)) {
            return redirect()
                ->route('google-login.edit')
                ->with('success', 'Postavke Google prijave su spremljene.');
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', 'Postavke Google prijave nije moguće spremiti.');
    }
}
