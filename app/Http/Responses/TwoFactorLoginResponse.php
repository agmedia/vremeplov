<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\CheckoutLoginRedirect;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
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
