<?php

namespace App\Modules\Community\Exceptions;

use RuntimeException;

/** Business-rule failure in the Community module; renders as JSON. `$reason` is a community.errors.* key. */
class CommunityException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 422, array $replace = [])
    {
        parent::__construct(__('community.errors.' . $reason, $replace));
    }

    public function render()
    {
        return response()->json(['message' => $this->getMessage(), 'reason' => $this->reason], $this->status);
    }
}
