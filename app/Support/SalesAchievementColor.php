<?php

namespace App\Support;

final class SalesAchievementColor
{
    /**
     * Return the CSS class for an achievement ratio.
     *
     * The dashboard stores achievement as a ratio:
     * 0.70 = 70%, 1.00 = 100%, 1.01 = 101%.
     */
    public static function classFor(?float $achievementRatio): string
    {
        if ($achievementRatio === null) {
            return 'achievement-na';
        }

        if ($achievementRatio < 0.70) {
            return 'achievement-danger';
        }

        if ($achievementRatio <= 1.00) {
            return 'achievement-warning';
        }

        return 'achievement-success';
    }
}
