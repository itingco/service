<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UpdateDashboardDeviceRequest;
use App\Models\DashboardDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SecurityDeviceController extends Controller
{
    public function index(Request $request): View
    {
        return view('security.devices.index', [
            'devices' => DashboardDevice::query()
                ->with('user')
                ->latest('last_seen_at')
                ->latest('created_at')
                ->paginate(30),
            'currentDeviceId' => (int) $request->session()->get(
                (string) config('dashboard_security.session_device_id_key'),
                0,
            ),
        ]);
    }

    public function update(
        UpdateDashboardDeviceRequest $request,
        DashboardDevice $dashboardDevice,
    ): RedirectResponse {
        $dashboardDevice->forceFill([
            'label' => trim((string) $request->validated('label')),
        ])->save();

        return back()->with('status', 'Nama perangkat berhasil diperbarui.');
    }
}
