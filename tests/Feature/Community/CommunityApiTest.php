<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Models\CommunityPost;
use App\Modules\Community\Models\CommunityReport;
use App\Modules\Community\Models\CommunityThread;
use App\Modules\Messaging\Models\Notification;
use Database\Seeders\CommunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CommunityApiTest extends TestCase
{
    use RefreshDatabase;

    private CommunityCategory $cat;

    protected function setUp(): void
    {
        parent::setUp();
        // Posting limits are exercised in their own tests; keep them out of the way here.
        config(['community.rate_per_minute' => 1000, 'community.rate_per_hour' => 10000]);
        $this->seed(CommunitySeeder::class);
        $this->cat = CommunityCategory::where('slug', 'general')->firstOrFail();
    }

    private function user(string $role = 'buyer', array $attrs = [], bool $aged = true): User
    {
        $user = User::factory()->create(['role' => $role, 'handle' => 'u' . uniqid()] + $attrs);
        if ($aged) {
            $user->forceFill(['created_at' => now()->subDays(3)])->save();
        }

        return $user;
    }

    private function actAs(User $u, ?array $scopes = null): User
    {
        Passport::actingAs($u, $scopes ?? $u->allowedScopes());

        return $u;
    }

    private function thread(?User $author = null, array $attrs = []): CommunityThread
    {
        $author ??= $this->user();
        $this->actAs($author);
        $id = $this->postJson("/api/v1/community/categories/{$this->cat->slug}/threads", $attrs + ['title' => 'Hello world', 'body' => 'First post'])
            ->assertCreated()->json('data.id');

        return CommunityThread::findOrFail($id);
    }

    // --- reads -------------------------------------------------------------

    public function test_seeder_is_idempotent_and_creates_four_translatable_categories(): void
    {
        $this->seed(CommunitySeeder::class);
        $this->assertSame(4, CommunityCategory::count());
        $this->assertTrue(CommunityCategory::where('slug', 'announcements')->value('staff_only'));
        foreach (CommunityCategory::all() as $c) {
            $this->assertNotSame($c->name_key, __($c->name_key), "{$c->slug} name key is not translated");
        }
    }

    public function test_public_can_read_categories_threads_and_posts(): void
    {
        $thread = $this->thread();
        auth()->forgetGuards();

        $cats = $this->getJson('/api/v1/community/categories')->assertOk();
        $general = collect($cats->json('data'))->firstWhere('slug', 'general');
        $this->assertSame(1, $general['threads_count']);
        $this->assertSame(1, $general['posts_count']);

        $this->getJson("/api/v1/community/categories/general/threads")->assertOk()->assertJsonPath('data.0.id', $thread->id);
        $this->getJson("/api/v1/community/threads/{$thread->id}")->assertOk()->assertJsonPath('data.path', "/community/t/{$thread->id}-hello-world");
        $this->getJson("/api/v1/community/threads/{$thread->id}/posts")->assertOk()->assertJsonPath('data.0.body_html', 'First post');
    }

    public function test_post_resource_returns_sanitised_html(): void
    {
        $thread = $this->thread(attrs: ['body' => '<script>x</script> **b**']);
        $this->getJson("/api/v1/community/threads/{$thread->id}/posts")
            ->assertJsonPath('data.0.body_html', '&lt;script&gt;x&lt;/script&gt; <strong>b</strong>')
            ->assertJsonPath('data.0.body', '<script>x</script> **b**');
    }

    public function test_pagination(): void
    {
        $thread = $this->thread();
        $user = $this->user();
        for ($i = 0; $i < 24; $i++) {
            $thread->posts()->create(['user_id' => $user->id, 'body' => "Reply $i"]);
        }
        $this->getJson("/api/v1/community/threads/{$thread->id}/posts?per_page=10&page=3")
            ->assertOk()->assertJsonPath('meta.last_page', 3)->assertJsonCount(5, 'data');
        $this->getJson("/api/v1/community/threads/{$thread->id}/posts?per_page=500")->assertOk()->assertJsonPath('meta.per_page', 50);

        for ($i = 0; $i < 25; $i++) {
            $this->thread($user);
        }
        $this->getJson('/api/v1/community/categories/general/threads')->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
    }

    public function test_unknown_category_and_thread_are_404(): void
    {
        $this->getJson('/api/v1/community/categories/nope')->assertNotFound();
        $this->getJson('/api/v1/community/threads/99999')->assertNotFound();
    }

    // --- authorization matrix --------------------------------------------------

    public function test_writes_require_authentication(): void
    {
        $thread = $this->thread();
        auth()->forgetGuards();
        $post = $thread->posts()->first();

        $this->postJson('/api/v1/community/categories/general/threads', ['title' => 'abc', 'body' => 'abc'])->assertUnauthorized();
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'abc'])->assertUnauthorized();
        $this->putJson("/api/v1/community/posts/{$post->id}", ['body' => 'abc'])->assertUnauthorized();
        $this->deleteJson("/api/v1/community/posts/{$post->id}")->assertUnauthorized();
        $this->postJson("/api/v1/community/posts/{$post->id}/report", ['reason' => 'spam'])->assertUnauthorized();
        $this->putJson("/api/v1/community/threads/{$thread->id}/subscription")->assertUnauthorized();
        $this->postJson("/api/v1/community/threads/{$thread->id}/pin")->assertUnauthorized();
        $this->getJson('/api/v1/community/admin/reports')->assertUnauthorized();
    }

    public function test_writes_need_the_profile_scope(): void
    {
        $this->actAs($this->user(), ['catalog:read']);
        $this->postJson('/api/v1/community/categories/general/threads', ['title' => 'abc', 'body' => 'abc'])->assertForbidden();
    }

    public function test_every_role_can_post_but_suspended_users_cannot(): void
    {
        foreach (['buyer', 'vendor', 'admin'] as $role) {
            $this->actAs($this->user($role));
            $this->postJson('/api/v1/community/categories/general/threads', ['title' => "From $role", 'body' => 'hello there'])->assertCreated();
        }

        $suspended = $this->user('buyer', ['suspended_at' => now()]);
        $this->actAs($suspended);
        $this->postJson('/api/v1/community/categories/general/threads', ['title' => 'abc', 'body' => 'abc'])->assertForbidden();
    }

    public function test_suspended_user_with_a_token_is_blocked_by_the_policy_too(): void
    {
        $thread = $this->thread();
        $suspended = $this->user('buyer', ['suspended_at' => now()]);
        $this->actAs($suspended);
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'hello'])->assertForbidden();
        $this->putJson("/api/v1/community/threads/{$thread->id}/subscription")->assertForbidden();
    }

    public function test_admin_scope_endpoints_are_closed_to_non_admins(): void
    {
        $thread = $this->thread();
        $this->actAs($this->user('buyer'), ['*']);
        $this->postJson("/api/v1/community/threads/{$thread->id}/pin")->assertForbidden();
        $this->postJson("/api/v1/community/threads/{$thread->id}/lock")->assertForbidden();
        $this->deleteJson("/api/v1/community/threads/{$thread->id}")->assertForbidden();
        $this->getJson('/api/v1/community/admin/reports')->assertForbidden();
        $this->postJson('/api/v1/community/admin/categories', ['name' => 'X'])->assertForbidden();

        $this->actAs($this->user('vendor'), ['*']);
        $this->getJson('/api/v1/community/admin/categories')->assertForbidden();
    }

    public function test_announcements_are_staff_only(): void
    {
        $this->actAs($this->user('buyer'));
        $this->postJson('/api/v1/community/categories/announcements/threads', ['title' => 'Nope', 'body' => 'nope nope'])
            ->assertForbidden()->assertJsonPath('message', __('community.errors.staff_only'));

        $this->actAs($this->user('admin'));
        $this->postJson('/api/v1/community/categories/announcements/threads', ['title' => 'News', 'body' => 'hear ye'])->assertCreated();
    }

    public function test_validation(): void
    {
        $this->actAs($this->user());
        $this->postJson('/api/v1/community/categories/general/threads', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'body']);
        $this->postJson('/api/v1/community/categories/general/threads', ['title' => str_repeat('a', 200), 'body' => 'x y'])->assertJsonValidationErrors(['title']);
    }

    // --- rate limits, cooldown, spam guard ----------------------------------------

    public function test_posting_is_rate_limited_per_user(): void
    {
        config(['community.rate_per_minute' => 3]);
        $thread = $this->thread();
        $a = $this->actAs($this->user());
        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => "reply $i"])->assertCreated();
        }
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'one too many'])->assertStatus(429);

        // Another user has their own bucket.
        $this->actAs($this->user());
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'fine'])->assertCreated();
        $this->assertNotNull($a);
    }

    public function test_report_rate_limit(): void
    {
        config(['community.report_per_minute' => 2]);
        $author = $this->user();
        $thread = $this->thread($author);
        $reporter = $this->actAs($this->user());
        $posts = [];
        for ($i = 0; $i < 3; $i++) {
            $posts[] = $thread->posts()->create(['user_id' => $author->id, 'body' => "p$i"]);
        }
        $this->postJson("/api/v1/community/posts/{$posts[0]->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->postJson("/api/v1/community/posts/{$posts[1]->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->postJson("/api/v1/community/posts/{$posts[2]->id}/report", ['reason' => 'spam'])->assertStatus(429);
        $this->assertNotNull($reporter);
    }

    public function test_new_accounts_wait_five_minutes_before_posting(): void
    {
        $fresh = $this->actAs($this->user(aged: false));
        $this->postJson('/api/v1/community/categories/general/threads', ['title' => 'Too soon', 'body' => 'hello'])
            ->assertStatus(429)->assertJsonPath('reason', 'cooldown');
        $this->assertSame(0, CommunityThread::count());

        $this->travel(6)->minutes();
        $this->postJson('/api/v1/community/categories/general/threads', ['title' => 'Now fine', 'body' => 'hello'])->assertCreated();
    }

    public function test_admins_skip_the_cooldown_and_link_limit(): void
    {
        $this->actAs($this->user('admin', aged: false));
        $this->postJson('/api/v1/community/categories/general/threads', [
            'title' => 'Links', 'body' => 'http://a.example http://b.example http://c.example http://d.example',
        ])->assertCreated();
    }

    public function test_accounts_under_24_hours_may_use_at_most_two_links(): void
    {
        $user = $this->user(aged: false);
        $user->forceFill(['created_at' => now()->subHours(2)])->save();
        $this->actAs($user);

        $three = 'see https://a.example and [b](https://b.example) and www.c.example';
        $this->postJson('/api/v1/community/categories/general/threads', ['title' => 'Spammy', 'body' => $three])
            ->assertUnprocessable()->assertJsonPath('reason', 'too_many_links');
        $this->postJson('/api/v1/community/categories/general/threads', ['title' => 'Fine', 'body' => 'see https://a.example and [b](https://b.example)'])
            ->assertCreated();

        $thread = CommunityThread::firstOrFail();
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => $three])->assertUnprocessable();

        // After 24 hours the limit no longer applies.
        $user->forceFill(['created_at' => now()->subHours(25)])->save();
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => $three])->assertCreated();
    }

    // --- editing and deleting ----------------------------------------------------

    public function test_author_can_edit_within_15_minutes_only(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $post = $thread->posts()->first();

        $this->actAs($author);
        $this->putJson("/api/v1/community/posts/{$post->id}", ['body' => 'Edited text'])
            ->assertOk()->assertJsonPath('data.body', 'Edited text');
        $this->assertNotNull($post->fresh()->edited_at);

        $this->travel(16)->minutes();
        $this->putJson("/api/v1/community/posts/{$post->id}", ['body' => 'Too late'])
            ->assertForbidden()->assertJsonPath('message', __('community.errors.edit_window', ['minutes' => 15]));
        $this->assertSame('Edited text', $post->fresh()->body);
    }

    public function test_only_the_author_can_edit_even_admins_cannot(): void
    {
        $thread = $this->thread();
        $post = $thread->posts()->first();

        $this->actAs($this->user());
        $this->putJson("/api/v1/community/posts/{$post->id}", ['body' => 'hijack'])->assertForbidden();
        $this->actAs($this->user('admin'));
        $this->putJson("/api/v1/community/posts/{$post->id}", ['body' => 'hijack'])->assertForbidden();
    }

    public function test_soft_delete_by_author_and_admin_not_by_others(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $reply = $thread->posts()->create(['user_id' => $author->id, 'body' => 'my reply']);
        $other = $thread->posts()->create(['user_id' => $this->user()->id, 'body' => 'their reply']);
        $thread->refresh()->forceFill(['posts_count' => 3])->save();

        $this->actAs($this->user());
        $this->deleteJson("/api/v1/community/posts/{$reply->id}")->assertForbidden();

        $this->actAs($author);
        $this->deleteJson("/api/v1/community/posts/{$reply->id}")->assertNoContent();
        $this->assertSoftDeleted('community_posts', ['id' => $reply->id]);
        $this->assertSame(2, $thread->fresh()->posts_count);

        $this->actAs($this->user('admin'));
        $this->deleteJson("/api/v1/community/posts/{$other->id}")->assertNoContent();
        $this->assertSoftDeleted('community_posts', ['id' => $other->id]);

        // Deleted posts disappear from the list and cannot be fetched.
        $this->getJson("/api/v1/community/threads/{$thread->id}/posts")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/community/posts/{$reply->id}")->assertNotFound();
    }

    public function test_deleting_the_opening_post_removes_the_thread(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $this->actAs($author);
        $this->deleteJson('/api/v1/community/posts/' . $thread->posts()->first()->id)->assertNoContent();
        $this->assertSoftDeleted('community_threads', ['id' => $thread->id]);
        $this->getJson("/api/v1/community/threads/{$thread->id}")->assertNotFound();
    }

    // --- pins and locks ----------------------------------------------------------

    public function test_admin_can_pin_and_pinned_threads_sort_first(): void
    {
        $user = $this->user();
        $old = $this->thread($user);
        $this->travel(1)->hours();
        $new = $this->thread($user);

        $this->getJson('/api/v1/community/categories/general/threads')->assertJsonPath('data.0.id', $new->id);

        $this->actAs($this->user('admin'));
        $this->postJson("/api/v1/community/threads/{$old->id}/pin")->assertOk()->assertJsonPath('data.is_pinned', true);
        $this->getJson('/api/v1/community/categories/general/threads')->assertJsonPath('data.0.id', $old->id);
        $this->deleteJson("/api/v1/community/threads/{$old->id}/pin")->assertOk()->assertJsonPath('data.is_pinned', false);
        $this->getJson('/api/v1/community/categories/general/threads')->assertJsonPath('data.0.id', $new->id);
    }

    public function test_locked_threads_reject_replies_edits_and_deletes_from_non_admins(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $post = $thread->posts()->first();

        $this->actAs($this->user('admin'));
        $this->postJson("/api/v1/community/threads/{$thread->id}/lock")->assertOk()->assertJsonPath('data.is_locked', true);

        $this->actAs($author);
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'nope'])->assertForbidden();
        $this->putJson("/api/v1/community/posts/{$post->id}", ['body' => 'nope'])->assertForbidden();
        $this->deleteJson("/api/v1/community/posts/{$post->id}")->assertForbidden();
        $this->getJson("/api/v1/community/threads/{$thread->id}")->assertJsonPath('data.viewer.can_reply', false);

        // Admins can still reply; unlocking restores everything.
        $this->actAs($this->user('admin'));
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'mod note'])->assertCreated();
        $this->deleteJson("/api/v1/community/threads/{$thread->id}/lock")->assertOk();
        $this->actAs($author);
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'back again'])->assertCreated();
    }

    public function test_admin_can_delete_a_thread(): void
    {
        $thread = $this->thread();
        $this->actAs($this->user('admin'));
        $this->deleteJson("/api/v1/community/threads/{$thread->id}")->assertNoContent();
        $this->assertSoftDeleted('community_threads', ['id' => $thread->id]);
    }

    // --- reports -----------------------------------------------------------------

    public function test_report_flow_and_admin_queue(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $post = $thread->posts()->first();
        $reporter = $this->user();

        $this->actAs($reporter);
        $this->postJson("/api/v1/community/posts/{$post->id}/report", ['reason' => 'bogus'])->assertUnprocessable();
        $this->postJson("/api/v1/community/posts/{$post->id}/report", ['reason' => 'spam', 'note' => 'ads'])->assertCreated();
        $this->postJson("/api/v1/community/posts/{$post->id}/report", ['reason' => 'spam'])->assertConflict();

        $this->actAs($author);
        $this->postJson("/api/v1/community/posts/{$post->id}/report", ['reason' => 'spam'])->assertForbidden(); // own post

        $this->actAs($this->user('admin'));
        $queue = $this->getJson('/api/v1/community/admin/reports')->assertOk();
        $queue->assertJsonCount(1, 'data')->assertJsonPath('data.0.reason', 'spam')->assertJsonPath('data.0.post.id', $post->id)
            ->assertJsonPath('data.0.reporter.id', $reporter->id);
        $reportId = $queue->json('data.0.id');

        $this->postJson("/api/v1/community/admin/reports/{$reportId}/resolve", ['action' => 'nonsense'])->assertUnprocessable();
        $this->postJson("/api/v1/community/admin/reports/{$reportId}/resolve", ['action' => 'dismiss'])
            ->assertOk()->assertJsonPath('data.status', 'dismissed');
        $this->postJson("/api/v1/community/admin/reports/{$reportId}/resolve", ['action' => 'dismiss'])->assertConflict();
        $this->getJson('/api/v1/community/admin/reports')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/community/admin/reports?status=dismissed')->assertJsonCount(1, 'data');
    }

    public function test_removing_a_reported_post_closes_all_its_reports(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $reply = $thread->posts()->create(['user_id' => $author->id, 'body' => 'bad reply']);
        $thread->refresh()->forceFill(['posts_count' => 2])->save();

        foreach ([$this->user(), $this->user()] as $r) {
            CommunityReport::create(['post_id' => $reply->id, 'user_id' => $r->id, 'reason' => 'abuse']);
        }
        $this->actAs($this->user('admin'));
        $id = CommunityReport::first()->id;
        $this->postJson("/api/v1/community/admin/reports/{$id}/resolve", ['action' => 'remove_post'])->assertOk()->assertJsonPath('data.status', 'actioned');

        $this->assertSoftDeleted('community_posts', ['id' => $reply->id]);
        $this->assertSame(0, CommunityReport::where('status', 'open')->count());
        $this->assertSame(1, $thread->fresh()->posts_count);
    }

    // --- badges and product link -----------------------------------------------------

    public function test_verified_sellers_get_a_badge_others_do_not(): void
    {
        $verified = $this->user('vendor', ['is_verified_vendor' => true]);
        $plain = $this->user('vendor');
        $thread = $this->thread($verified);
        $thread->posts()->create(['user_id' => $plain->id, 'body' => 'hello']);
        $thread->posts()->create(['user_id' => $this->user('admin')->id, 'body' => 'hello']);

        $res = $this->getJson("/api/v1/community/threads/{$thread->id}/posts")->assertOk();
        $this->assertSame(['verified_vendor', null, 'staff'], array_column(array_column($res->json('data'), 'author'), 'badge'));
        $this->getJson("/api/v1/community/threads/{$thread->id}")->assertJsonPath('data.author.badge', 'verified_vendor');
    }

    public function test_thread_can_link_a_product_the_author_sells(): void
    {
        $vendor = $this->user('vendor');
        $mine = $vendor->products()->create(['title' => 'My Widget', 'price_cents' => 500, 'stock' => 1, 'status' => 'active']);
        $theirs = $this->user('vendor')->products()->create(['title' => 'Theirs', 'price_cents' => 500, 'stock' => 1, 'status' => 'active']);
        $draft = $vendor->products()->create(['title' => 'Draft', 'price_cents' => 500, 'stock' => 1, 'status' => 'draft']);

        $this->actAs($vendor);
        $url = '/api/v1/community/categories/general/threads';
        $this->postJson($url, ['title' => 'Selling', 'body' => 'look', 'product' => $theirs->slug])->assertUnprocessable()->assertJsonPath('reason', 'product_not_yours');
        $this->postJson($url, ['title' => 'Selling', 'body' => 'look', 'product' => $draft->slug])->assertUnprocessable();
        $this->postJson($url, ['title' => 'Selling', 'body' => 'look', 'product' => 'does-not-exist'])->assertUnprocessable();
        $res = $this->postJson($url, ['title' => 'Selling', 'body' => 'look', 'product' => $mine->slug])->assertCreated();

        $res->assertJsonPath('data.product', ['slug' => $mine->slug, 'title' => 'My Widget']);
        $this->assertArrayNotHasKey('product_id', $res->json('data'));
    }

    // --- subscriptions, notifications, unread -------------------------------------------

    public function test_replying_notifies_subscribers_but_not_the_replier(): void
    {
        $author = $this->user();
        $thread = $this->thread($author); // author auto-subscribed
        $follower = $this->user();
        $this->actAs($follower);
        $this->putJson("/api/v1/community/threads/{$thread->id}/subscription")->assertOk()->assertJsonPath('data.is_subscribed', true);
        $bystander = $this->user();

        $replier = $this->actAs($this->user());
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'great question'])->assertCreated();

        $this->assertSame(1, Notification::where('user_id', $author->id)->where('type', 'community.reply')->count());
        $note = Notification::where('user_id', $follower->id)->where('type', 'community.reply')->firstOrFail();
        $this->assertSame($thread->id, $note->data['thread_id']);
        $this->assertSame($thread->path(), $note->data['path']);
        $this->assertStringContainsString('Hello world', $note->title);
        $this->assertSame(0, Notification::where('user_id', $bystander->id)->count());
        $this->assertSame(0, Notification::where('user_id', $replier->id)->count());

        // Replying subscribes the replier; unsubscribing stops the notifications.
        $this->putJson("/api/v1/community/threads/{$thread->id}/subscription")->assertOk();
        $this->deleteJson("/api/v1/community/threads/{$thread->id}/subscription")->assertOk()->assertJsonPath('data.is_subscribed', false);
        $this->actAs($author);
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'thanks'])->assertCreated();
        $this->assertSame(0, Notification::where('user_id', $replier->id)->count());
    }

    public function test_notifications_endpoint_shows_the_community_notification(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $this->actAs($this->user());
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'hello'])->assertCreated();

        $this->actAs($author);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.type', 'community.reply');
    }

    public function test_unread_markers(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        $reader = $this->user();

        $this->actAs($reader);
        $list = fn () => $this->getJson('/api/v1/community/categories/general/threads')->json('data.0.is_unread');
        $this->assertTrue($list()); // never opened
        $this->postJson("/api/v1/community/threads/{$thread->id}/read")->assertNoContent();
        $this->assertFalse($list());

        $this->actAs($author);
        $this->assertFalse($list()); // the author's own post is read

        $this->actAs($this->user());
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'new reply here'])->assertCreated();

        $this->actAs($reader);
        $this->assertTrue($list()); // new reply since last read
        $this->getJson("/api/v1/community/threads/{$thread->id}")->assertJsonPath('data.is_unread', true);
        $this->postJson("/api/v1/community/threads/{$thread->id}/read")->assertNoContent();
        $this->assertFalse($list());

        auth()->forgetGuards();
        $this->assertFalse($list()); // guests never see unread markers
    }

    // --- admin categories -----------------------------------------------------------------

    public function test_admin_manages_categories(): void
    {
        $this->actAs($this->user('admin'));
        $id = $this->postJson('/api/v1/community/admin/categories', ['name' => 'Meet & greet', 'description' => 'Say hi'])
            ->assertCreated()->assertJsonPath('data.slug', 'meet-greet')->assertJsonPath('data.name', 'Meet & greet')->json('data.id');
        $this->postJson('/api/v1/community/admin/categories', ['name' => 'Again', 'slug' => 'meet-greet'])->assertUnprocessable();
        $this->postJson('/api/v1/community/admin/categories', ['name' => 'Bad', 'slug' => 'Bad Slug!'])->assertUnprocessable();

        $this->putJson("/api/v1/community/admin/categories/{$id}", ['name' => 'Hello', 'staff_only' => true, 'position' => 9])
            ->assertOk()->assertJsonPath('data.name', 'Hello')->assertJsonPath('data.staff_only', true);

        $this->getJson('/api/v1/community/admin/categories')->assertOk()->assertJsonCount(5, 'data');
        $this->assertContains('hello', array_map(fn ($c) => strtolower($c['name']), $this->getJson('/api/v1/community/categories')->json('data')));

        // A built-in category keeps its translation until an admin types a name.
        $general = CommunityCategory::where('slug', 'general')->first();
        $this->putJson("/api/v1/community/admin/categories/{$general->id}", ['position' => 5])->assertOk();
        $this->assertNotNull($general->fresh()->name_key);
        $this->putJson("/api/v1/community/admin/categories/{$general->id}", ['name' => 'Lounge'])->assertOk();
        $this->assertNull($general->fresh()->name_key);

        $this->deleteJson("/api/v1/community/admin/categories/{$id}")->assertNoContent();

        $this->thread();
        $this->actAs($this->user('admin'));
        $this->deleteJson("/api/v1/community/admin/categories/{$general->id}")->assertConflict();
    }

    public function test_posts_count_stays_consistent(): void
    {
        $thread = $this->thread();
        $this->actAs($this->user());
        $this->postJson("/api/v1/community/threads/{$thread->id}/posts", ['body' => 'a reply'])->assertCreated()
            ->assertJsonPath('meta.thread_path', $thread->path())->assertJsonPath('meta.last_page', 1);
        $thread->refresh();
        $this->assertSame(2, $thread->posts_count);
        $this->assertSame(CommunityPost::where('thread_id', $thread->id)->max('id'), $thread->last_post_id);
        $this->getJson("/api/v1/community/threads/{$thread->id}")->assertJsonPath('data.replies_count', 1);
    }
}
