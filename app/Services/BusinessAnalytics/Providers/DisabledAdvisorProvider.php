<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics\Providers;

use App\Contracts\BusinessAnalytics\AdvisorSynthesisProvider;
use App\Contracts\BusinessAnalytics\MarketResearchProvider;

final class DisabledAdvisorProvider implements MarketResearchProvider, AdvisorSynthesisProvider
{
    public function research(array $context): array
    {
        return ['status'=>'DISABLED','summary'=>'External market research belum diaktifkan.','segments'=>[],'sources'=>[]];
    }

    public function synthesize(array $context, string $question): array
    {
        return ['status'=>'DISABLED'];
    }

    public function name(): string { return 'disabled'; }
    public function model(): ?string { return null; }
}
