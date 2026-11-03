<?php

declare(strict_types=1);

namespace App\Services\BusinessAnalytics;

final class AdvisorContextSanitizer
{
    private const SENSITIVE_KEY_PARTS = [
        'customer_name', 'customer_code', 'customer', 'salesperson', 'sales_person',
        'invoice', 'phone', 'email', 'address', 'contact', 'password', 'credential',
        'api_key', 'token', 'internal_note',
    ];

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function sanitize(array $context): array
    {
        return $this->walk($context);
    }

    /** @param array<string|int,mixed> $values @return array<string|int,mixed> */
    private function walk(array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            $keyText = strtolower((string) $key);
            if ($this->isSensitive($keyText)) {
                continue;
            }
            if (is_array($value)) {
                $value = $this->walk($value);
                if ($value === [] && $this->looksLikeSensitiveCollection($keyText)) {
                    continue;
                }
            }
            $clean[$key] = $value;
        }
        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_PARTS as $needle) {
            if ($key === $needle || str_contains($key, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function looksLikeSensitiveCollection(string $key): bool
    {
        return str_contains($key, 'customer') || str_contains($key, 'salesperson') || str_contains($key, 'invoice');
    }
}
