<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Public content pages. All text lives in lang/<locale>/pages.php; the views only lay it out.
 * The contact form posts to /api/v1/support from the browser.
 */
class ContentController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function how(): View
    {
        return view('pages.how');
    }

    public function faq(): View
    {
        return view('pages.faq');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function sellers(): View
    {
        return view('pages.sellers');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }
}
