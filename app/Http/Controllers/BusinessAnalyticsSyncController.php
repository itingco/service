<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BusinessAnalytics\AnalyticsWebSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class BusinessAnalyticsSyncController extends Controller
{
    public function start(Request $request, AnalyticsWebSyncService $service): RedirectResponse
    {
        try { $service->start($this->userId($request)); return back()->with('status','Sync Business Analytics 36 bulan dimulai di background. Halaman boleh ditutup.'); }
        catch(Throwable $e){return back()->withErrors(['sync'=>$e->getMessage()]);}
    }

    public function status(AnalyticsWebSyncService $service): JsonResponse
    {
        return response()->json(['data'=>$service->latestStatus()]);
    }

    private function userId(Request $request): ?int
    {
        $user=$request->user(); if($user && isset($user->id)) return (int)$user->id;
        $session=$request->session()->get('dashboard_user'); if(is_array($session) && isset($session['id'])) return (int)$session['id'];
        return null;
    }
}
