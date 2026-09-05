<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Rendered as 409 with a stable error code so the SPA can explain it.
 */
class LastOwnerException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __('auth.last_owner'),
            'error_code' => 'LAST_OWNER',
        ], 409);
    }
}
