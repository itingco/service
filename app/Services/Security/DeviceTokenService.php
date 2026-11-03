<?php

declare(strict_types=1);

namespace App\Services\Security;

use InvalidArgumentException;

final class DeviceTokenService
{
    public function __construct(private readonly string $applicationKey)
    {
        if ($applicationKey === '') {
            throw new InvalidArgumentException('APP_KEY wajib diisi untuk keamanan perangkat.');
        }
    }

    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->normalizedKey());
    }

    public function matches(string $token, string $expectedHash): bool
    {
        return $token !== '' && $expectedHash !== '' && hash_equals($expectedHash, $this->hash($token));
    }

    private function normalizedKey(): string
    {
        if (str_starts_with($this->applicationKey, 'base64:')) {
            $decoded = base64_decode(substr($this->applicationKey, 7), true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $this->applicationKey;
    }
}
