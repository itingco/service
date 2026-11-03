<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

use InvalidArgumentException;

final class AdvisorResponseValidator
{
    private const REQUIRED_ARRAYS = [
        'facts', 'inferences', 'market_opportunities', 'recommendations', 'experiments',
        'risks', 'missing_information', 'external_sources',
    ];

    /** @param array<string,mixed> $response @return array<string,mixed> */
    public function validate(array $response): array
    {
        $summary = trim((string) ($response['summary'] ?? ''));
        if ($summary === '') {
            throw new InvalidArgumentException('Advisor response membutuhkan summary.');
        }
        foreach (self::REQUIRED_ARRAYS as $key) {
            if (!array_key_exists($key, $response) || !is_array($response[$key])) {
                throw new InvalidArgumentException("Advisor response membutuhkan array {$key}.");
            }
        }
        foreach ($response['external_sources'] as $source) {
            if (!is_array($source)) {
                throw new InvalidArgumentException('External source harus object/array.');
            }
            if (trim((string) ($source['title'] ?? '')) === '' || trim((string) ($source['url'] ?? '')) === '') {
                throw new InvalidArgumentException('External source membutuhkan title dan url.');
            }
        }
        return $response;
    }
}
