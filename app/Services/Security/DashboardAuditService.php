<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\DashboardActivityAudit;
use App\Models\DashboardDevice;
use App\Models\DashboardLoginAudit;
use App\Models\DashboardUser;
use Illuminate\Http\Request;
use Throwable;

final class DashboardAuditService
{
    public function login(
        Request $request,
        string $event,
        ?DashboardUser $user = null,
        ?DashboardDevice $device = null,
        ?string $username = null,
        ?string $reason = null,
    ): void {
        try {
            DashboardLoginAudit::query()->create([
                'user_id' => $user?->getKey(),
                'device_id' => $device?->getKey(),
                'username' => $username ?? $user?->username,
                'event' => $event,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string,mixed> $metadata */
    public function activity(
        Request $request,
        string $action,
        int $responseStatus,
        ?DashboardUser $user,
        ?DashboardDevice $device,
        array $metadata = [],
    ): void {
        try {
            DashboardActivityAudit::query()->create([
                'user_id' => $user?->getKey(),
                'device_id' => $device?->getKey(),
                'action' => $action,
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 255),
                'response_status' => $responseStatus,
                'ip_address' => $request->ip(),
                'metadata' => $metadata === [] ? null : $metadata,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
