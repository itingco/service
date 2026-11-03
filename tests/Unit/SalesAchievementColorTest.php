<?php

namespace Tests\Unit;

use App\Support\SalesAchievementColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesAchievementColorTest extends TestCase
{
    #[DataProvider('achievementCases')]
    public function test_it_returns_the_expected_css_class(
        ?float $achievementRatio,
        string $expectedClass
    ): void {
        self::assertSame(
            $expectedClass,
            SalesAchievementColor::classFor($achievementRatio)
        );
    }

    public static function achievementCases(): array
    {
        return [
            'target unavailable' => [null, 'achievement-na'],
            'below seventy percent' => [0.6999, 'achievement-danger'],
            'exactly seventy percent' => [0.70, 'achievement-warning'],
            'exactly one hundred percent' => [1.00, 'achievement-warning'],
            'above one hundred percent' => [1.0001, 'achievement-success'],
        ];
    }
}
