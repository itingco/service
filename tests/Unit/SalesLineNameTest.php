<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SalesLineName;
use PHPUnit\Framework\TestCase;

final class SalesLineNameTest extends TestCase
{
    public function test_it_normalizes_all_other_line_variants_to_lainnya(): void
    {
        foreach (['LAINNYA', 'lainnya', 'LAIN - LAIN', 'LAIN-LAIN', 'LAIN LAIN', ' lain  -  lain '] as $value) {
            self::assertSame('LAINNYA', SalesLineName::normalize($value));
        }
    }
}
