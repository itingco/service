<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class OpenAIRequestBuilder
{
    public function __construct(
        private readonly string $model,
        private readonly bool $webResearch = true,
    ) {}

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    public function research(string $input, array $schema): array
    {
        $payload = $this->base($input, 'product_market_research', $schema);
        if ($this->webResearch) {
            $payload['tools'] = [['type' => 'web_search']];
            $payload['tool_choice'] = 'required';
            $payload['include'] = ['web_search_call.action.sources'];
        }
        return $payload;
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    public function advisor(string $input, array $schema): array
    {
        return $this->base($input, 'sales_strategy_advisor', $schema);
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function base(string $input, string $schemaName, array $schema): array
    {
        return [
            'model' => $this->model,
            'store' => false,
            'input' => $input,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];
    }
}
