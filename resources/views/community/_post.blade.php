@php
    $author = $post->author;
    $badge = \App\Modules\Community\Http\Resources\AuthorResource::badgeFor($author);
    $canEdit = $signedIn && auth()->user()->can('update', $post);
    $canDelete = $signedIn && auth()->user()->can('delete', $post);
    $canReport = $signedIn && auth()->user()->can('report', $post);
@endphp
<article id="post-{{ $post->id }}" class="card min-w-0" x-data="communityPost(@js($post->id), @js($post->id === $firstPostId), @js($thread->category->slug))">
    <header class="mb-2 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-medium text-ink">{{ $author->handle ?? '#' . $post->user_id }}</span>
        @if ($badge)
            <span class="badge {{ $badge === 'verified_vendor' ? 'badge-ok' : 'badge-accent' }}">{{ __('community.badge.' . $badge) }}</span>
        @endif
        <a class="text-ink-3 no-underline" href="#post-{{ $post->id }}"><time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->isoFormat('lll') }}</time></a>
        @if ($post->edited_at)
            <span class="text-ink-3">{{ '·' }} {{ __('community.thread.edited', ['date' => $post->edited_at->isoFormat('lll')]) }}</span>
        @endif
    </header>

    <div x-show="!editing">
        <x-community::safe-html :markdown="$post->body" />
    </div>

    @if ($canEdit)
        <form x-show="editing" x-cloak class="grid gap-2" @submit.prevent="save()">
            <label class="sr-only" for="edit-{{ $post->id }}">{{ __('community.thread.edit') }}</label>
            <textarea id="edit-{{ $post->id }}" x-model="draft" rows="6" maxlength="10000" required></textarea>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-sm btn-primary" :disabled="saving">{{ __('community.thread.save') }}</button>
                <button type="button" class="btn btn-sm" @click="editing = false">{{ __('community.thread.cancel') }}</button>
            </div>
        </form>
    @endif

    @if ($canEdit || $canDelete || $canReport)
        <div class="mt-3 flex flex-wrap gap-2" x-show="!editing">
            @if ($canEdit)<button type="button" class="btn btn-sm" @click="startEdit()">{{ __('community.thread.edit') }}</button>@endif
            @if ($canDelete)<button type="button" class="btn btn-sm btn-danger" @click="remove()">{{ __('community.thread.delete') }}</button>@endif
            @if ($canReport)
                <button type="button" class="btn btn-sm" x-show="!reported" @click="reporting = !reporting" :aria-expanded="reporting">{{ __('community.thread.report') }}</button>
            @endif
        </div>
    @endif

    @if ($canReport)
        <form x-show="reporting" x-cloak class="mt-3 grid gap-2 border-t border-line pt-3" @submit.prevent="sendReport()">
            <p class="font-medium">{{ __('community.report.heading') }}</p>
            <div>
                <label for="rr-{{ $post->id }}">{{ __('community.report.reason') }}</label>
                <select id="rr-{{ $post->id }}" x-model="reason">
                    @foreach (\App\Modules\Community\Models\CommunityReport::REASONS as $r)
                        <option value="{{ $r }}">{{ __('community.report.reasons.' . $r) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rn-{{ $post->id }}">{{ __('community.report.note') }}</label>
                <input id="rn-{{ $post->id }}" x-model="note" maxlength="500" autocomplete="off">
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-sm btn-primary" :disabled="sending">{{ __('community.report.submit') }}</button>
                <button type="button" class="btn btn-sm" @click="reporting = false">{{ __('community.thread.cancel') }}</button>
            </div>
        </form>
        <p class="mt-2 text-sm text-ok" x-show="reported" x-cloak role="status">{{ __('community.report.sent') }}</p>
    @endif
    <p class="mt-2 text-sm text-bad" x-show="err" x-cloak role="alert" x-text="err"></p>
</article>
