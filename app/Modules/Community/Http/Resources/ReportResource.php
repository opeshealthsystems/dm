<?php

namespace App\Modules\Community\Http\Resources;

use App\Modules\Community\Models\CommunityReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunityReport */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $post = $this->post;

        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'note' => $this->note,
            'status' => $this->status,
            'reporter' => $this->whenLoaded('reporter', fn () => ['id' => $this->reporter->id, 'handle' => $this->reporter->handle]),
            'post' => $post ? [
                'id' => $post->id,
                'thread_id' => $post->thread_id,
                'thread_title' => $post->thread?->title,
                'thread_path' => $post->thread?->path(),
                'excerpt' => mb_substr((string) $post->body, 0, 300), // plain text; clients must escape it
                'author_handle' => $post->author?->handle,
                'is_deleted' => $post->trashed(),
            ] : null,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
