<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use Throwable;

final class MarketResearchService
{
    public function __construct(
        private readonly AdvisorProviderFactory $factory,
        private readonly AdvisorContextSanitizer $sanitizer,
        private readonly ProductOpportunityScorer $scorer,
    ) {}

    /** @param array<string,mixed> $facts @return array<string,mixed> */
    public function research(array $facts): array
    {
        $provider=$this->factory->market();
        try{
            $raw=$provider->research($this->sanitizer->sanitize($facts));
            $rawSegments=(array)($raw['segments']??[]);
            if($rawSegments===[])$rawSegments=$this->scorer->heuristicSegments((array)($facts['profile']??[]));
            $segments=$this->scorer->rank((array)($facts['profile']??[]),[
                'online_share'=>$facts['online_share']??null,'online_yoy_growth'=>$facts['online_yoy_growth']??null,'offline_share'=>$facts['offline_share']??null,
            ],$rawSegments);
            return ['status'=>$raw['status']??'SUCCESS','provider'=>$provider->name(),'model'=>$provider->model(),'summary'=>$raw['summary']??'','segments'=>$segments,'sources'=>$raw['sources']??[]];
        }catch(Throwable $e){
            report($e);
            return ['status'=>'FAILED','provider'=>$provider->name(),'model'=>$provider->model(),'summary'=>'External market research gagal; internal analytics tetap tersedia.','segments'=>[],'sources'=>[],'error'=>$e->getMessage()];
        }
    }
}
