<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BusinessAnalytics\AdvisorHealthProbe;
use App\Services\BusinessAnalytics\AdvisorProviderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CheckBusinessAdvisor extends Command
{
    protected $signature = 'business-advisor:check {--live : Call the configured OpenAI Responses API} {--web : Force one web_search call during live check}';
    protected $description = 'Check Product Strategy Advisor AI configuration and optional live OpenAI connectivity';

    public function handle(AdvisorHealthProbe $probe): int
    {
        $providerName = (string) config('business_advisor.provider', 'disabled');
        $model = (string) config('business_advisor.model', '');
        $apiKey = (string) config('business_advisor.api_key', '');
        $webResearch = (bool) config('business_advisor.web_research', true);

        $status = (new AdvisorProviderStatus($providerName, $model, $apiKey, $webResearch))->toArray();

        $this->line('Business Advisor status');
        $this->line('  State       : '.$status['state']);
        $this->line('  Provider    : '.strtoupper($status['name']));
        $this->line('  Model       : '.($status['display_model'] ?? '—'));
        $this->line('  API key     : '.(trim($apiKey) !== '' ? 'SET (hidden)' : 'NOT SET'));
        $this->line('  Web research: '.($webResearch ? 'ON' : 'OFF'));

        $preflight = $probe->preflight($providerName, $model, $apiKey);
        if (!$preflight['ready']) {
            $this->warn($preflight['code'].': '.$preflight['message']);
            return self::FAILURE;
        }
        $this->info('CONFIG READY');

        if (!$this->option('live')) {
            $this->comment('Tambahkan --live untuk menguji koneksi OpenAI. Tambahkan --web untuk menguji web search.');
            return self::SUCCESS;
        }

        $payload = [
            'model' => $model,
            'store' => false,
            'input' => 'Reply exactly OK.',
        ];

        if ((bool) $this->option('web')) {
            $payload['tools'] = [['type' => 'web_search']];
            $payload['tool_choice'] = 'required';
            $payload['include'] = ['web_search_call.action.sources'];
            $payload['input'] = 'Use web search to find the official OpenAI API documentation homepage, then reply exactly OK.';
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(max(10, (int) config('business_advisor.timeout_seconds', 45)))
                ->post('https://api.openai.com/v1/responses', $payload);

            $json = $response->json();
            $body = is_array($json) ? $json : ['error' => ['message' => mb_substr($response->body(), 0, 500)]];
            $result = $probe->interpretHttp($response->status(), $body);

            if (!$result['ok']) {
                $this->error($result['code'].': '.$result['message']);
                return self::FAILURE;
            }

            $this->info('LIVE API: OK');
            if ((bool) $this->option('web')) $this->info('WEB SEARCH: OK');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('CONNECTION_FAILED: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
