<?php

namespace App\Modules\Orders\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A business-rule failure (bad state transition, out of stock...). Rendered as HTTP 409/422. */
class OrderException extends RuntimeException
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
