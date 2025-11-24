<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    /**
     * Display privacy policy page
     */
    public function privacyPolicy()
    {
        $user = Auth::check() ? Auth::user() : null;
        return view('pages.privacy-policy', compact('user'));
    }
}

