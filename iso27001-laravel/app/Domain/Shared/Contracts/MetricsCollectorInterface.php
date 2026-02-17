<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

interface MetricsCollectorInterface
{
    /** @param array<string, string> $tags */
    public function increment(string $metric, array $tags = [], int $value = 1): void;

    /** @param array<string, string> $tags */
    public function timing(string $metric, float $durationMs, array $tags = []): void;
}
