<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StockAgingRequest;
use Illuminate\Http\RedirectResponse;

final class StockStrategyReviewController extends Controller
{
    /**
     * Legacy URL kept for bookmarks. The former separate Strategy Review
     * has been merged into the main Stock & Aging page.
     */
    public function index(StockAgingRequest $request): RedirectResponse
    {
        $query = $request->query();
        unset($query['page']);

        return redirect()->route('stock-aging.index', $query);
    }
}
