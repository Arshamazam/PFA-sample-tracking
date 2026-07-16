<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a custody transition violates the state machine or an SOP guard.
 * Renders as HTTP 422 with the standard failure envelope.
 */
class IllegalTransitionException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'to_status' => [$this->getMessage()],
            ],
        ], 422);
    }
}
