<?php

namespace App\Modules\Wallet\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Business-rule failure in the wallet (overdraw, bad payout state...). Rendered as HTTP 409/422. */
class WalletException extends RuntimeException
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
