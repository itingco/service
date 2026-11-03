<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class StockAgingDataException extends RuntimeException
{
    /** @param list<string> $failedSources */
    public function __construct(
        private readonly array $failedSources,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @return list<string> */
    public function failedSources(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $source): string => strtoupper(trim($source)),
            $this->failedSources,
        )));
    }

    public static function forSource(string $source, string $message, ?Throwable $previous = null): self
    {
        return new self([$source], $message, $previous);
    }

    /** @param list<string> $sources */
    public static function forSources(array $sources, string $message, ?Throwable $previous = null): self
    {
        return new self($sources, $message, $previous);
    }
}
