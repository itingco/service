<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DashboardRole extends Model
{
    protected $table = 'dashboard_roles';

    protected $primaryKey = 'role_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'role_key',
        'name',
        'is_system',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('dashboard_security.connection', 'sqlite_targets');
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function menus(): HasMany
    {
        return $this->hasMany(DashboardRoleMenu::class, 'role_key', 'role_key');
    }

    public function isAdministrator(): bool
    {
        return $this->role_key === 'admin';
    }
}
