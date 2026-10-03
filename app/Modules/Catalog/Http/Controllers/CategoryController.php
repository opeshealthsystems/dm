<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /** List all categories (public), flat, ordered by name. Use `parent_id` to build a tree. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => Category::query()->orderBy('name')->get(['id', 'parent_id', 'name', 'slug'])]);
    }
}
