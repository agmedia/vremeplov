<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\CheckoutLoginRedirect;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        if ($user instanceof User && $user->isAdministrator()) {
            $request->session()->forget('url.intended');
            CheckoutLoginRedirect::forget($request);

            return redirect()->route('dashboard');
        }

        if ($redirect = CheckoutLoginRedirect::pull($request)) {
            $request->session()->forget('url.intended');

            return redirect()->to($redirect);
        }

        if ($user instanceof User && optional($user->details)->role === 'customer') {
            return redirect()->route('moj-racun');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
