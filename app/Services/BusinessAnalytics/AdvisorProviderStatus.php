<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class AdvisorProviderStatus
{
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly string $apiKey,
        private readonly bool $webResearch,
    ) {}

    /** @return array{name:string,model:string,display_model:?string,web_research:bool,configured:bool,state:string,badge:string} */
    public function toArray(): array
    {
        $name = strtolower(trim($this->provider));
        $model = trim($this->model);
        $hasKey = trim($this->apiKey) !== '';

        if ($name === '' || $name === 'disabled') {
            return [
                'name' => 'disabled',
                'model' => $model,
                'display_model' => null,
                'web_research' => $this->webResearch,
                'configured' => false,
                'state' => 'DISABLED',
                'badge' => 'AI OFF · INTERNAL ONLY',
            ];
        }

        if ($name === 'openai' && !$hasKey) {
            return [
                'name' => 'openai',
                'model' => $model,
                'display_model' => $model !== '' ? $model : null,
                'web_research' => $this->webResearch,
                'configured' => false,
                'state' => 'NEEDS_KEY',
                'badge' => 'AI SETUP REQUIRED',
            ];
        }

        if ($name === 'openai') {
            $modelLabel = strtoupper($model !== '' ? $model : 'MODEL NOT SET');
            return [
                'name' => 'openai',
                'model' => $model,
                'display_model' => $model !== '' ? $model : null,
                'web_research' => $this->webResearch,
                'configured' => true,
                'state' => 'READY',
                'badge' => 'AI ON · '.$modelLabel.' · WEB '.($this->webResearch ? 'ON' : 'OFF'),
            ];
        }

        return [
            'name' => $name,
            'model' => $model,
            'display_model' => $model !== '' ? $model : null,
            'web_research' => $this->webResearch,
            'configured' => false,
            'state' => 'UNSUPPORTED',
            'badge' => 'AI PROVIDER UNSUPPORTED',
        ];
    }
}
