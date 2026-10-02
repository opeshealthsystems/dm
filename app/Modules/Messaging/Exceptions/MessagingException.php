<?php

namespace App\Modules\Messaging\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Business-rule failure in messaging (blocked, unknown counterpart, ...). Renders as JSON. */
class MessagingException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
