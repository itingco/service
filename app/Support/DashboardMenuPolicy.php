<?php

declare(strict_types=1);

namespace App\Support;

final class DashboardMenuPolicy
{
    /** @param list<string> $grantedMenus */
    public function allows(string $role, array $grantedMenus, string $menuKey): bool
    {
        $role = strtolower(trim($role));
        $menuKey = strtolower(trim($menuKey));

        if ($role === '' || $menuKey === '') {
            return false;
        }

        if ($role === 'admin') {
            return true;
        }

        return in_array($menuKey, $grantedMenus, true);
    }
}
