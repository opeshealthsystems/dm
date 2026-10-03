<?php

namespace App\Modules\Escrow\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Business-rule failure in disputes / fees. Rendered as HTTP 403/409/422. */
class EscrowException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
