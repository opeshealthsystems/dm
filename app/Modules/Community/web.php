<?php

use App\Modules\Community\Http\Controllers\CommunityPageController;
use Illuminate\Support\Facades\Route;

/*
| Community web pages, required from routes/web.php (so they run in the web middleware group).
| Order matters: the fixed paths (new, t/...) come before the {category} catch-all.
*/
Route::prefix('community')->controller(CommunityPageController::class)->group(function () {
    Route::get('/', 'index')->name('community.index');
    Route::get('new', 'create')->middleware('auth')->name('community.new');
    Route::get('t/{thread}', 'thread')->where('thread', '[0-9]+(-[A-Za-z0-9-]*)?')->name('community.thread');
    Route::get('{category}', 'category')->where('category', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('community.category');
});
