<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}
}
