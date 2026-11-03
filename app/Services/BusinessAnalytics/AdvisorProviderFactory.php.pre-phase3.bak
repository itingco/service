<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use App\Contracts\BusinessAnalytics\AdvisorSynthesisProvider;
use App\Contracts\BusinessAnalytics\MarketResearchProvider;
use App\Services\BusinessAnalytics\Providers\DisabledAdvisorProvider;
use App\Services\BusinessAnalytics\Providers\OpenAIAdvisorProvider;

final class AdvisorProviderFactory
{
    public function market(): MarketResearchProvider { return $this->make(); }
    public function advisor(): AdvisorSynthesisProvider { return $this->make(); }

    private function make(): DisabledAdvisorProvider|OpenAIAdvisorProvider
    {
        $provider=strtolower((string)config('business_advisor.provider','disabled'));
        if($provider!=='openai')return new DisabledAdvisorProvider();
        return new OpenAIAdvisorProvider(
            apiKey:(string)config('business_advisor.api_key',''),
            modelName:(string)config('business_advisor.model','gpt-5.6-terra'),
            timeoutSeconds:(int)config('business_advisor.timeout_seconds',45),
            webResearch:(bool)config('business_advisor.web_research',true),
        );
    }
}
