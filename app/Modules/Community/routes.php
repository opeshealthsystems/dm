<?php

use App\Modules\Community\Http\Controllers\CommunityAdminController as Admin;
use App\Modules\Community\Http\Controllers\CommunityController as C;
use Illuminate\Support\Facades\Route;

/*
| Community API, mounted under /api/v1 by routes/api.php (see the last lines there).
| Reads are public; writes need the `profile` scope; moderation needs `admin`.
*/
Route::prefix('community')->group(function () {
    // Public reads
    Route::middleware('throttle:api')->group(function () {
        Route::get('categories', [C::class, 'categories']);
        Route::get('categories/{category:slug}', [C::class, 'category']);
        Route::get('categories/{category:slug}/threads', [C::class, 'threads']);
        Route::get('threads/{thread}', [C::class, 'thread'])->whereNumber('thread');
        Route::get('threads/{thread}/posts', [C::class, 'posts'])->whereNumber('thread');
        Route::get('posts/{post}', [C::class, 'post'])->whereNumber('post');
    });

    // Signed-in writes
    Route::middleware(['auth:api', 'scopes:profile', 'throttle:api'])->group(function () {
        Route::post('categories/{category:slug}/threads', [C::class, 'storeThread'])->middleware('throttle:community-posts');
        Route::post('threads/{thread}/posts', [C::class, 'storePost'])->whereNumber('thread')->middleware('throttle:community-posts');
        Route::put('posts/{post}', [C::class, 'updatePost'])->whereNumber('post');
        Route::delete('posts/{post}', [C::class, 'destroyPost'])->whereNumber('post');
        Route::post('posts/{post}/report', [C::class, 'report'])->whereNumber('post')->middleware('throttle:community-reports');
        Route::put('threads/{thread}/subscription', [C::class, 'subscribe'])->whereNumber('thread');
        Route::delete('threads/{thread}/subscription', [C::class, 'unsubscribe'])->whereNumber('thread');
        Route::post('threads/{thread}/read', [C::class, 'markRead'])->whereNumber('thread');
    });

    // Moderation (admin scope + admin role)
    Route::middleware(['auth:api', 'scopes:admin', 'throttle:api'])->group(function () {
        Route::post('threads/{thread}/pin', [Admin::class, 'pin'])->whereNumber('thread');
        Route::delete('threads/{thread}/pin', [Admin::class, 'unpin'])->whereNumber('thread');
        Route::post('threads/{thread}/lock', [Admin::class, 'lock'])->whereNumber('thread');
        Route::delete('threads/{thread}/lock', [Admin::class, 'unlock'])->whereNumber('thread');
        Route::delete('threads/{thread}', [Admin::class, 'destroyThread'])->whereNumber('thread');

        Route::prefix('admin')->group(function () {
            Route::get('categories', [Admin::class, 'categories']);
            Route::post('categories', [Admin::class, 'storeCategory']);
            Route::put('categories/{category}', [Admin::class, 'updateCategory'])->whereNumber('category');
            Route::delete('categories/{category}', [Admin::class, 'destroyCategory'])->whereNumber('category');
            Route::get('reports', [Admin::class, 'reports']);
            Route::post('reports/{report}/resolve', [Admin::class, 'resolveReport'])->whereNumber('report');
        });
    });
});
