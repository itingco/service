<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DashboardActivityAudit extends Model
{
    public $timestamps = false;

    protected $table = 'dashboard_activity_audits';

    protected $fillable = [
        'user_id', 'device_id', 'action', 'route_name', 'method', 'path',
        'response_status', 'ip_address', 'metadata', 'created_at',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('dashboard_security.connection', 'sqlite_targets');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(DashboardUser::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(DashboardDevice::class, 'device_id');
    }
}
