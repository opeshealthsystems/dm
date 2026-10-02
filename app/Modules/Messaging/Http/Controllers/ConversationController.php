<?php

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Messaging\Actions\MessagingService;
use App\Modules\Messaging\Exceptions\MessagingException;
use App\Modules\Messaging\Http\Requests\SendMessageRequest;
use App\Modules\Messaging\Http\Requests\StartConversationRequest;
use App\Modules\Messaging\Http\Resources\ConversationResource;
use App\Modules\Messaging\Http\Resources\MessageResource;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    /**
     * List your conversations.
     *
     * Only conversations you take part in, most recently active first, each with its unread count.
     * Requires `orders:read`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $conversations = Conversation::with('participants')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ConversationResource::collection($conversations);
    }

    /**
     * Total unread messages across all your conversations.
     *
     * @response array{unread_count: int}
     */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->messaging->unreadCount($request->user())]);
    }

    /**
     * Start a conversation (or continue an existing one) and send the first message.
     *
     * Pass `order_id` to talk about one of your orders (the counterpart is the other party of the
     * order), or `recipient_id` for a general conversation. Blocked users cannot be contacted.
     * Requires `orders:write` or `vendor:manage`; rate limited.
     */
    public function store(StartConversationRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('order_id')) {
            $order = Order::findOrFail($request->integer('order_id'));
            if (! in_array($user->id, [$order->buyer_id, $order->vendor_id], true)) {
                abort(403, 'This order is not yours.');
            }
            $conversation = $this->messaging->conversationForOrder($order);
        } else {
            $recipient = User::findOrFail($request->integer('recipient_id'));
            if ($recipient->is($user)) {
                throw new MessagingException('You cannot message yourself.');
            }
            if ($recipient->isSuspended()) {
                throw new MessagingException('This user cannot receive messages.');
            }
            $conversation = $this->messaging->generalConversation($user, $recipient, $request->validated('subject'));
        }

        $message = $this->messaging->send($conversation, $user, $request->validated('body'));

        return response()->json([
            'data' => (new ConversationResource($conversation->load('participants')))->resolve($request),
            'message' => (new MessageResource($message))->resolve($request),
        ], 201);
    }

    /**
     * Read a conversation's messages (oldest first, paginated).
     *
     * Participants only. Opening it marks it as read for you (read receipt).
     */
    public function show(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()->orderBy('id')->paginate(min($request->integer('per_page', 50), 100));
        $this->messaging->markRead($conversation, $request->user());

        return MessageResource::collection($messages);
    }

    /**
     * Post a message into a conversation.
     *
     * Participants only; refused with 403 if either side blocked the other. Rate limited.
     */
    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        $message = $this->messaging->send($conversation, $request->user(), $request->validated('body'));

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    /** Mark the conversation as read up to its latest message. */
    public function read(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);
        $this->messaging->markRead($conversation, $request->user());

        return new ConversationResource($conversation->load('participants'));
    }
}
