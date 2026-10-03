<?php

namespace App\Modules\Community\Events;

use App\Modules\Community\Models\CommunityPost;
use App\Modules\Community\Models\CommunityThread;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a reply is stored. The Messaging module listens and notifies subscribers. */
class ThreadReplied
{
    use Dispatchable;

    public function __construct(public readonly CommunityThread $thread, public readonly CommunityPost $post)
    {
    }
}
