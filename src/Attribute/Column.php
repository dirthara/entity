<?php

declare(strict_types=1);

namespace Dirthara\Entity\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $column = null,
        public ?string $converter = null,
    ) {}
}
