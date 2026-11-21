<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

trait HandlesBusinessIdentity
{
    private function identityResponse(Closure $action): Response
    {
        try {
            return $action();
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Esta empresa o cuenta ya está vinculada. Revisa la vinculación existente.'], 409);
        } catch (InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }
    }
}
