<?php

namespace App\Modules\Community\Http\Resources;

use App\Modules\Community\Models\CommunityThread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin CommunityThread */
class ThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('api');
        $attrs = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id, 'slug' => $this->category->slug,
                'name' => $this->category->displayName(), 'name_key' => $this->category->name_key,
            ]),
            'author' => $this->whenLoaded('author', fn () => new AuthorResource($this->author)),
            'product' => $this->whenLoaded('product', fn () => $this->product
                ? ['slug' => $this->product->slug, 'title' => $this->product->title] : null),
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
            'posts_count' => $this->posts_count,
            'replies_count' => max(0, $this->posts_count - 1),
            'last_posted_at' => $this->last_posted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_unread' => $this->when(array_key_exists('is_unread', $attrs), fn () => (bool) $this->is_unread),
            'is_subscribed' => $this->when(array_key_exists('is_subscribed', $attrs), fn () => (bool) $this->is_subscribed),
            'viewer' => [
                'can_reply' => $viewer ? Gate::forUser($viewer)->allows('reply', $this->resource) : false,
                'is_admin' => (bool) $viewer?->isAdmin(),
            ],
        ];
    }
}
