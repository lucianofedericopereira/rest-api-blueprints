<?php

declare(strict_types=1);

namespace App\RateLimiter;

use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimiterFactoryAdapter implements RateLimiterFactoryInterface
{
    public function __construct(private readonly RateLimiterFactory $inner) {}

    public function create(?string $key = null): LimiterInterface
    {
        return $this->inner->create($key);
    }
}
