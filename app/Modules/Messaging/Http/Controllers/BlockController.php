<?php

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Messaging\Actions\MessagingService;
use App\Modules\Messaging\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    /** List the user ids you have blocked. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => UserBlock::where('blocker_id', $request->user()->id)->pluck('blocked_id')]);
    }

    /** Block a user: neither of you can message the other until you unblock. Idempotent. */
    public function store(Request $request, User $user): JsonResponse
    {
        $this->messaging->block($request->user(), $user);

        return response()->json(['blocked' => true], 201);
    }

    /** Unblock a user. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->messaging->unblock($request->user(), $user);

        return response()->json(['blocked' => false]);
    }
}
