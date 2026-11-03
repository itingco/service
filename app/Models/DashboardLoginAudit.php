<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DashboardLoginAudit extends Model
{
    public $timestamps = false;

    protected $table = 'dashboard_login_audits';

    protected $fillable = [
        'user_id', 'device_id', 'username', 'event', 'ip_address', 'user_agent', 'reason', 'created_at',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('dashboard_security.connection', 'sqlite_targets');
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
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
