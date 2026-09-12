<?php

declare(strict_types=1);

namespace Dirthara\Entity\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Entity
{
    public function __construct(
        public ?string $table = null,
        public ?string $connection = null,
    ) {}
}
