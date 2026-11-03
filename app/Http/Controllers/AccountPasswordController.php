<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Security\ChangeOwnPasswordRequest;
use App\Models\DashboardUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class AccountPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var DashboardUser $user */
        $user = $request->attributes->get('dashboard_user');

        return view('account.password', [
            'user' => $user,
            'passwordMinLength' => max(1, (int) config('dashboard_security.password_min_length', 8)),
        ]);
    }

    public function update(ChangeOwnPasswordRequest $request): RedirectResponse
    {
        /** @var DashboardUser $user */
        $user = $request->attributes->get('dashboard_user');
        $currentPassword = (string) $request->validated('current_password');

        if (!Hash::check($currentPassword, (string) $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama tidak sesuai.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
        ])->save();

        return redirect()->route('account.password.edit')->with(
            'status',
            'Password berhasil diubah. Sesi Anda tetap aktif.',
        );
    }
}
