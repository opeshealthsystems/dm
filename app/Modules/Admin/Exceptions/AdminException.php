<?php

namespace App\Modules\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A business-rule failure in the admin module; renders as JSON. */
class AdminException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
