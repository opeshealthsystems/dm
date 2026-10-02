<?php

namespace App\Modules\Reputation\Exceptions;

use RuntimeException;

/** Business-rule failure in the Reputation module; renders as JSON. */
class ReputationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
