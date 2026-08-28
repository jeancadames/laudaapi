<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse([], 204);
        }

        $user = $request->user();

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('dashboard');
        }

        return redirect()->route('app.gateway');
    }
}
