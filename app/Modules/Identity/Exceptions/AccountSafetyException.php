<?php

namespace App\Modules\Identity\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A business-rule failure in the account-safety flows. Renders as JSON with a stable `code`. */
class AccountSafetyException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 422, public readonly ?int $retryAfter = null, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $body = ['message' => $this->getMessage(), 'code' => $this->errorCode];
        if ($this->field !== null) {
            $body['errors'] = [$this->field => [$this->getMessage()]]; // same shape as validation errors
        }
        $response = response()->json($body, $this->status);
        if ($this->retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }
}
