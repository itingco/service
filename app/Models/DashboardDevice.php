<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DashboardDevice extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVOKED = 'revoked';

    protected $table = 'dashboard_devices';

    protected $fillable = [
        'user_id',
        'label',
        'token_hash',
        'status',
        'browser',
        'platform',
        'first_ip',
        'last_ip',
        'user_agent',
        'approved_at',
        'approved_by',
        'last_seen_at',
    ];

    protected $hidden = ['token_hash'];

    public function getConnectionName(): ?string
    {
        return (string) config('dashboard_security.connection', 'sqlite_targets');
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(DashboardUser::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(DashboardUser::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
