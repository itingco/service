<?php

declare(strict_types=1);

namespace App\Data;

final class SalesResearchInsights
{
    /**
     * @param list<array<string,mixed>> $productSignals
     * @param list<array<string,mixed>> $newDemand
     * @param list<array<string,mixed>> $lostDemand
     * @param list<array<string,mixed>> $concentrationRisks
     * @param list<array<string,mixed>> $discountWatch
     * @param list<array<string,mixed>> $gradeDemand
     * @param list<array<string,mixed>> $channelFit
     * @param array<string,int> $signalCounts
     */
    public function __construct(
        public readonly array $productSignals,
        public readonly array $newDemand,
        public readonly array $lostDemand,
        public readonly array $concentrationRisks,
        public readonly array $discountWatch,
        public readonly array $gradeDemand,
        public readonly array $channelFit,
        public readonly array $signalCounts,
    ) {
    }
}
