<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Models\CommunityThread;
use Database\Seeders\CommunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CommunityPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CommunitySeeder::class);
    }

    private function user(string $role = 'buyer'): User
    {
        $u = User::factory()->create(['role' => $role, 'handle' => 'u' . uniqid()]);
        $u->forceFill(['created_at' => now()->subDays(3)])->save();

        return $u;
    }

    private function thread(User $author, string $body = 'Hello **world**', string $title = 'My first thread'): CommunityThread
    {
        $thread = new CommunityThread(['category_id' => CommunityCategory::where('slug', 'general')->value('id'), 'user_id' => $author->id, 'title' => $title]);
        $thread->posts_count = 0;
        $thread->save();
        $thread->posts()->create(['user_id' => $author->id, 'body' => $body]);
        app(\App\Modules\Community\Actions\CommunityService::class)->syncThreadStats($thread);

        return $thread->refresh();
    }

    public function test_public_pages_render_with_seo_and_loading_states(): void
    {
        $this->get('/community')->assertOk()
            ->assertSee('<title>' . __('community.title') . ' | ' . config('app.name') . '</title>', false)
            ->assertSee('<link rel="canonical" href="' . url('/community') . '"', false)
            ->assertSee('name="description"', false)
            ->assertSee('communityIndex', false)
            ->assertSee(__('community.subtitle'));

        $this->get('/community/general')->assertOk()
            ->assertSee(CommunityCategory::where('slug', 'general')->first()->displayName())
            ->assertSee('<link rel="canonical" href="' . url('/community/general') . '"', false)
            ->assertSee('communityCategory', false);

        $this->get('/community/does-not-exist')->assertNotFound();
        $this->get('/community/Bad_Slug')->assertNotFound();
    }

    public function test_new_thread_page_needs_login_and_is_not_indexed(): void
    {
        $this->get('/community/new')->assertRedirect('/login');
        $this->actingAs($this->user())->get('/community/new?category=general')->assertOk()
            ->assertSee('noindex', false)->assertSee('communityNew', false);
    }

    public function test_thread_page_renders_sanitised_posts_on_the_server(): void
    {
        $author = $this->user();
        $thread = $this->thread($author, "<script>alert(1)</script> <img src=x onerror=alert(2)> **bold** [bad](javascript:alert(3)) [ok](https://example.com)");

        $res = $this->get($thread->path())->assertOk();
        $res->assertSee('<strong>bold</strong>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('rel="nofollow ugc noopener noreferrer"', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee('<title>My first thread | ' . __('community.title') . ' | ' . config('app.name') . '</title>', false)
            ->assertSee('<link rel="canonical" href="' . url($thread->path()) . '"', false);

        // Guests see a login prompt instead of the reply form.
        $res->assertSee(__('community.thread.login_to_reply'))->assertDontSee('id="reply-body"', false);
    }

    public function test_thread_url_redirects_to_the_canonical_slug(): void
    {
        $thread = $this->thread($this->user());
        $this->get("/community/t/{$thread->id}")->assertRedirect($thread->path())->assertStatus(301);
        $this->get("/community/t/{$thread->id}-wrong-slug")->assertRedirect($thread->path());
        $this->get('/community/t/99999-nothing')->assertNotFound();
    }

    public function test_reply_form_edit_and_report_controls_follow_permissions(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);

        $this->actingAs($author)->get($thread->path())->assertOk()
            ->assertSee('id="reply-body"', false)
            ->assertSee('>' . __('community.thread.edit') . '<', false)
            ->assertDontSee('rr-', false); // cannot report own post

        $other = $this->user();
        $this->actingAs($other)->get($thread->path())->assertOk()
            ->assertDontSee('>' . __('community.thread.edit') . '<', false)
            ->assertSee(__('community.report.heading'));

        $this->travel(16)->minutes();
        $this->actingAs($author)->get($thread->path())->assertDontSee('>' . __('community.thread.edit') . '<', false);
    }

    public function test_locked_thread_shows_notice_and_admin_controls(): void
    {
        $thread = $this->thread($this->user());
        $thread->forceFill(['is_locked' => true])->save();

        $this->actingAs($this->user())->get($thread->path())->assertSee(__('community.thread.locked_notice'))->assertDontSee('id="reply-body"', false);
        $this->actingAs($this->user('admin'))->get($thread->path())->assertSee(__('community.thread.delete_thread'))->assertSee('id="reply-body"', false);
    }

    public function test_thread_pages_are_paginated(): void
    {
        $author = $this->user();
        $thread = $this->thread($author);
        for ($i = 0; $i < 25; $i++) {
            $thread->posts()->create(['user_id' => $author->id, 'body' => "Reply number $i"]);
        }
        $this->get($thread->path())->assertOk()->assertSee('rel="next"', false)->assertSee(__('community.page_of', ['page' => 1, 'last' => 2]));
        $this->get($thread->path() . '?page=2')->assertOk()->assertSee('rel="prev"', false)
            ->assertSee('<link rel="canonical" href="' . url($thread->path()) . '?page=2"', false);
    }

    public function test_deleted_threads_are_gone(): void
    {
        $thread = $this->thread($this->user());
        $path = $thread->path();
        $thread->delete();
        $this->get($path)->assertNotFound();
    }

    public function test_store_header_and_footer_link_to_the_community(): void
    {
        $html = $this->get('/community')->getContent();
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'href="' . route('community.index') . '"'));
    }

    public function test_admin_menu_has_community_and_page_works(): void
    {
        $this->assertContains('nav.community', array_column(config('navigation.admin'), 'label'));
        $this->actingAs($this->user('admin'))->get('/admin/community')->assertOk()->assertSee('adminCommunity', false)
            ->assertSee(__('community.admin.title'));
        $this->actingAs($this->user('buyer'))->get('/admin/community')->assertRedirect(route('dashboard'));
    }

    public function test_pages_render_right_to_left_in_arabic(): void
    {
        $this->get('/locale/ar');
        $this->get('/community')->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('community.title', [], 'ar'));
    }

    // --- static scans of the community views -----------------------------------------------

    /** @return list<string> */
    private function viewFiles(): array
    {
        return array_merge(
            array_map(fn ($f) => $f->getPathname(), File::allFiles(resource_path('views/community'))),
            [resource_path('views/admin/community.blade.php')],
        );
    }

    public function test_views_use_design_tokens_logical_classes_and_no_html_binding(): void
    {
        foreach ($this->viewFiles() as $file) {
            $src = file_get_contents($file);
            $name = basename($file);
            $this->assertDoesNotMatchRegularExpression('/\b(ml|mr|pl|pr)-\d|\btext-(left|right)\b|\b(left|right)-\d|\bfloat-(left|right)\b/', $src, "$name uses physical direction classes");
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b(?!\w)/', $src, "$name contains a hex colour");
            $this->assertStringNotContainsString('x-html', $src, "$name binds HTML");
            $this->assertDoesNotMatchRegularExpression('#https?://[a-z0-9.-]+\.[a-z]{2,}#i', str_replace('https://example.com', '', $src), "$name references an external host");
        }
    }

    public function test_unescaped_output_exists_only_in_the_safe_html_component(): void
    {
        $offenders = [];
        foreach ($this->viewFiles() as $file) {
            if (str_contains(file_get_contents($file), '{!!') && ! str_ends_with(str_replace('\\', '/', $file), 'community/components/safe-html.blade.php')) {
                $offenders[] = basename($file);
            }
        }
        $this->assertSame([], $offenders);

        $component = file_get_contents(resource_path('views/community/components/safe-html.blade.php'));
        $this->assertSame(1, substr_count($component, '{!!'));
        $this->assertStringContainsString('MarkdownLite::render(', $component);
    }

    public function test_every_community_key_used_in_views_exists_in_english(): void
    {
        $missing = [];
        $found = 0;
        foreach ($this->viewFiles() as $file) {
            preg_match_all("/(?:__\(|\bt\(|\bcTr\()\s*['\"](community\.[A-Za-z0-9_.]+)['\"]/", file_get_contents($file), $m);
            foreach (array_unique($m[1]) as $key) {
                $found++;
                if (str_ends_with($key, '.') || str_ends_with($key, '_')) {
                    continue; // dynamic suffix, e.g. 'community.report.reasons.' + reason
                }
                if (trans($key, [], 'en') === $key) {
                    $missing[] = basename($file) . ": $key";
                }
            }
        }
        $this->assertGreaterThan(50, $found, 'scanner found suspiciously few keys');
        $this->assertSame([], $missing);

        // Dynamic keys built in JavaScript / PHP loops.
        foreach (\App\Modules\Community\Models\CommunityReport::REASONS as $r) {
            $this->assertNotSame("community.report.reasons.$r", __("community.report.reasons.$r", [], 'en'));
        }
        foreach (['open', 'dismissed', 'actioned'] as $s) {
            $this->assertNotSame("community.admin.status_$s", __("community.admin.status_$s", [], 'en'));
        }
        foreach (['staff', 'verified_vendor'] as $b) {
            $this->assertNotSame("community.badge.$b", __("community.badge.$b", [], 'en'));
        }
    }

    public function test_no_unused_strings_in_the_english_file(): void
    {
        $all = '';
        foreach ($this->viewFiles() as $file) {
            $all .= file_get_contents($file);
        }
        // Keys used by PHP outside the views.
        $all .= file_get_contents(app_path('Modules/Community/Policies/CommunityThreadPolicy.php'))
            . file_get_contents(app_path('Modules/Community/Policies/CommunityPostPolicy.php'))
            . file_get_contents(app_path('Modules/Community/Exceptions/CommunityException.php'))
            . file_get_contents(app_path('Modules/Messaging/Listeners/ThreadReplyNotifier.php'))
            . file_get_contents(app_path('Modules/Community/Http/Controllers/CommunityPageController.php'))
            . file_get_contents(resource_path('views/layouts/store.blade.php'))
            . file_get_contents(base_path('database/seeders/CommunitySeeder.php'));

        $unused = [];
        foreach (\Illuminate\Support\Arr::dot(trans('community', [], 'en')) as $key => $_) {
            $parts = explode('.', $key);
            $group = $parts[0];
            // errors.* are built from a reason code; categories.* from the seeder; the rest must be referenced.
            if (in_array($group, ['errors', 'categories'], true)) {
                continue;
            }
            $dynamic = ($group === 'report' && ($parts[1] ?? '') === 'reasons') || ($group === 'badge')
                || ($group === 'admin' && str_starts_with($parts[1], 'status_'));
            if ($dynamic) {
                continue;
            }
            if (! str_contains($all, "community.$key'")) {
                $unused[] = $key;
            }
        }
        $this->assertSame([], $unused, 'Unused community strings');
    }

    public function test_templates_contain_no_hard_coded_visible_text(): void
    {
        foreach ($this->viewFiles() as $file) {
            $src = file_get_contents($file);
            $src = str_replace(['{!!', '!!}'], ['{{', '}}'], $src); // the sanitised-output tags hold code, not text
            $src = preg_replace(['/^@section\(.title.*$/m', '/<script.*?<\/script>/s', '/<style.*?<\/style>/s', '/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/@php.*?@endphp/s', '/<\/?[a-zA-Z][^\s>]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/s'], ' ', $src);
            $src = preg_replace('/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', ' ', $src);
            $text = trim(preg_replace('/\s+/u', ' ', $src));
            $this->assertSame('', $text, basename($file) . " contains hard-coded text: $text");
        }
    }
}
