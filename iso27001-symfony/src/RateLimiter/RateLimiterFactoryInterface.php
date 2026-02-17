<?php

declare(strict_types=1);

namespace App\RateLimiter;

use Symfony\Component\RateLimiter\LimiterInterface;

interface RateLimiterFactoryInterface
{
    public function create(?string $key = null): LimiterInterface;
}
