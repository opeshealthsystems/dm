<?php

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Http\Resources\NotificationResource;
use App\Modules\Messaging\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * List your notifications (newest first). `unread=1` shows only unread ones.
     * Requires `orders:read`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Notification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest('id')->paginate(min($request->integer('per_page', 20), 100));

        return NotificationResource::collection($items)->additional([
            'unread_count' => Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]);
    }

    /** Mark one of your notifications as read. */
    public function read(Notification $notification): NotificationResource
    {
        $this->authorize('update', $notification);
        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return new NotificationResource($notification);
    }

    /**
     * Mark all your notifications as read.
     *
     * @response array{marked: int}
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['marked' => $count]);
    }
}
