<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DashboardRoleMenu extends Model
{
    protected $table = 'dashboard_role_menus';

    protected $fillable = [
        'role_key',
        'menu_key',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('dashboard_security.connection', 'sqlite_targets');
    }
}
