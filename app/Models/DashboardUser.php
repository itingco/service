<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DashboardUser extends Model
{
    protected $table = 'dashboard_users';

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'sales_person_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = ['password'];

    public function getConnectionName(): ?string
    {
        return (string) config('dashboard_security.connection', 'sqlite_targets');
    }

    protected function casts(): array
    {
        return [
            'sales_person_id' => 'integer',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(DashboardDevice::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSales(): bool
    {
        return $this->role === 'sales';
    }
}
