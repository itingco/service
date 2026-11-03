<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class BusinessIntelligenceHubController extends Controller
{
    public function index(): View
    {
        // Lightweight landing page. Existing analysis queries run only after a section is opened.
        return view('business-intelligence.index');
    }
}
