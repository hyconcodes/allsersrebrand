<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;

class CustomPasswordResetLinkResponse implements SuccessfulPasswordResetLinkRequestResponseContract
{
    /**
     * The response status language key.
     *
     * @var string
     */
    protected $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $message = trans($this->status);

        // The framework's "passwords.sent" text mentions a reset link; this
        // app emails a code instead, and we must report send failures honestly.
        if ($this->status === Password::RESET_LINK_SENT) {
            $message = User::$passwordResetSendFailed
                ? 'We couldn’t send the reset code right now. Please try again shortly.'
                : 'We’ve emailed a password reset code to that address if it exists in our system.';
        }

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
