<?php

namespace App\Modules\Community\Http\Resources;

use App\Modules\Community\Models\CommunityPost;
use App\Modules\Community\Support\MarkdownLite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin CommunityPost */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('api');
        $can = fn (string $ability) => $viewer ? Gate::forUser($viewer)->allows($ability, $this->resource) : false;

        return [
            'id' => $this->id,
            'thread_id' => $this->thread_id,
            'author' => $this->whenLoaded('author', fn () => new AuthorResource($this->author)),
            // Raw markdown (for the edit form) and the sanitised HTML produced by MarkdownLite.
            'body' => $this->body,
            'body_html' => MarkdownLite::render($this->body),
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'viewer' => ['can_edit' => $can('update'), 'can_delete' => $can('delete'), 'can_report' => $can('report')],
        ];
    }
}
