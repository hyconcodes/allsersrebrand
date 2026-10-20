<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticatedSessionResponse as TwoFactorAuthenticatedSessionResponseContract;

class CustomTwoFactorLoginResponse implements TwoFactorAuthenticatedSessionResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 200);
        }

        // Same as CustomLoginResponse: never honor a stale intended URL.
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard');
    }
}
