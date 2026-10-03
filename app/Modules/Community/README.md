# Community module

Forum for buyer tips, seller help and announcements. API first (`/api/v1/community/*`), then Blade + Alpine pages.

- Models: category (admin managed, translatable via lang keys), thread, post, report, subscription, thread read marker.
- Rules live in `Actions/CommunityService` (cooldown, link limit, edit window via policy); settings in `config/community.php`.
- Markdown-lite: `Support/MarkdownLite` escapes everything first and emits only strong, em, code, br and `a` (http/https, nofollow ugc noopener). The one `{!! !!}` is `resources/views/community/components/safe-html.blade.php`, which takes raw markdown, never HTML.
- Thread page `/community/t/{id}-{slug}` is server-rendered (so no `x-html`); other pages call the API from Alpine.
- Notifications: `Events/ThreadReplied` is handled by `Messaging/Listeners/ThreadReplyNotifier` (registered in `CommunityServiceProvider`).
- Admin: `/admin/community` (reports queue + categories); API under `community/admin/*`, pin/lock under `community/threads/{id}/pin|lock`.
- Tests: `tests/Feature/Community/*`.
