<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class SalesKpiDataException extends RuntimeException
{
    /** @param list<string> $sources */
    public function __construct(
        public readonly array $sources,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function forSource(string $source, string $message, ?Throwable $previous = null): self
    {
        return new self([strtoupper(trim($source))], $message, $previous);
    }

    /** @param list<string> $sources */
    public static function forSources(array $sources, string $message): self
    {
        $sources = array_values(array_unique(array_map(
            static fn (string $source): string => strtoupper(trim($source)),
            $sources,
        )));

        return new self($sources, $message);
    }

    /** @return list<string> */
    public function failedSources(): array
    {
        return $this->sources;
    }
}
